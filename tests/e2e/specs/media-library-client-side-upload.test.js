/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	TEST_IMAGE_PATH,
	TEST_AUDIO_PATH,
	TEST_SVG_PATH,
	SIMULATED_ERROR,
	UNDECODABLE_HEIC,
	failMediaCreate,
	stubClassicUpload,
	withoutHeicDecoder,
	countUploadRequests,
	tile,
} from '../utils/media-pipeline';

// The plupload HTML5 runtime creates this hidden file input over the
// "Add New" browse button; setting files on it triggers FilesAdded.
const FILE_INPUT_SELECTOR = '.moxie-shim-html5 input[type="file"]';

/**
 * Overrides the plupload defaults the grid's uploader is built from.
 *
 * wp_plupload_default_settings() prints `var _wpPluploadSettings` inline
 * before wp-plupload.js runs, so an accessor installed ahead of it sees the
 * assignment and can adjust the defaults before any uploader reads them.
 *
 * @param {import('@playwright/test').Page} page
 * @param {Record<string, unknown>}         defaults Defaults to override.
 */
async function overridePluploadDefaults( page, defaults ) {
	await page.addInitScript( ( overrides ) => {
		let settings;
		Object.defineProperty( window, '_wpPluploadSettings', {
			configurable: true,
			get() {
				return settings;
			},
			set( value ) {
				settings = value;
				if ( value && value.defaults ) {
					Object.assign( value.defaults, overrides );
				}
			},
		} );
	}, defaults );
}

/**
 * Stops the upload-media provider from ever rendering, so the store never
 * receives the pipeline's settings.
 *
 * @param {import('@playwright/test').Page} page
 */
async function suppressProviderRender( page ) {
	await page.addInitScript( () => {
		let element;
		window.wp = window.wp || {};
		Object.defineProperty( window.wp, 'element', {
			configurable: true,
			get() {
				return element;
			},
			set( value ) {
				// The package's exports are getters, so copy before patching.
				element = Object.assign( {}, value, {
					createRoot( container ) {
						const root = value.createRoot( container );
						const render = root.render.bind( root );
						root.render = ( node ) => {
							const provider =
								window.wp.uploadMedia &&
								window.wp.uploadMedia.MediaUploadProvider;
							if ( ! node || node.type !== provider ) {
								render( node );
							}
						};
						return root;
					},
				} );
			},
		} );
	} );
}

test.describe( 'Media Library grid client-side uploads', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'sends the Document-Isolation-Policy header on the grid', async ( {
		page,
		admin,
	} ) => {
		const responsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( '/wp-admin/upload.php' ) &&
				resp.request().resourceType() === 'document' &&
				resp.status() === 200
		);

		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const headers = ( await responsePromise ).headers();
		expect( headers[ 'document-isolation-policy' ] ).toBe(
			'isolate-and-credentialless'
		);
	} );

	test( 'does not send the Document-Isolation-Policy header in list mode', async ( {
		page,
		admin,
	} ) => {
		const responsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( '/wp-admin/upload.php' ) &&
				resp.request().resourceType() === 'document' &&
				resp.status() === 200
		);

		await admin.visitAdminPage( 'upload.php', 'mode=list' );

		const headers = ( await responsePromise ).headers();
		expect( headers[ 'document-isolation-policy' ] ).toBeUndefined();

		// List mode uploads via media-new.php, so the grid integration
		// script has no business on this screen either.
		await expect(
			page.locator( 'script[src*="media-frame-upload"]' )
		).toHaveCount( 0 );

		// Visiting with ?mode= persists the user's preference; restore it.
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );
	} );

	test( 'uploads an image through the client-side pipeline', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		// In Chromium builds without Document-Isolation-Policy support,
		// isolation is legitimately unavailable and the pipeline falls back
		// to classic uploads. Only assert where isolation is real.
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// The REST route may be a pretty permalink (/wp/v2/media) or the
		// plain form (index.php?rest_route=%2Fwp%2Fv2%2Fmedia), so match on
		// the decoded URL.
		let mediaCreateCount = 0;
		let sideloadCount = 0;
		let finalizeCount = 0;
		const asyncUploads = [];
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			const url = request.url();
			if ( url.includes( '/async-upload.php' ) ) {
				asyncUploads.push( url );
				return;
			}
			const decoded = decodeURIComponent( url );
			if ( /\/wp\/v2\/media\/\d+\/sideload/.test( decoded ) ) {
				sideloadCount++;
			} else if ( /\/wp\/v2\/media\/\d+\/finalize/.test( decoded ) ) {
				finalizeCount++;
			} else if ( /\/wp\/v2\/media(?:[?&]|$)/.test( decoded ) ) {
				mediaCreateCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// The finalized attachment resolves to a normal (non-uploading) tile.
		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		// The original upload and every sideload go through the REST API,
		// and the upload is finalized exactly once.
		expect( mediaCreateCount ).toBeGreaterThanOrEqual( 1 );
		expect( sideloadCount ).toBeGreaterThanOrEqual( 1 );
		expect( finalizeCount ).toBe( 1 );

		// Nothing goes through the classic async-upload.php endpoint.
		expect( asyncUploads ).toEqual( [] );

		// The finalized attachment carries the browser-generated sub-sizes
		// in its metadata (the 640x480 source is larger than thumbnail and
		// medium), and the sideloaded thumbnail file really exists.
		const [ attachment ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1 },
		} );
		const sizes = attachment.media_details.sizes || {};
		expect( Object.keys( sizes ) ).toEqual(
			expect.arrayContaining( [ 'thumbnail', 'medium' ] )
		);

		const thumbnailResponse = await page.request.get(
			sizes.thumbnail.source_url
		);
		expect( thumbnailResponse.status() ).toBe( 200 );
	} );

	test( 'warns before leaving while a pipeline upload is in flight', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// Hold sideload requests so the upload stays in flight at a
		// deterministic point.
		const heldRoutes = [];
		let holding = true;
		await page.route(
			( url ) => decodeURIComponent( url.href ).includes( '/sideload' ),
			async ( route ) => {
				if ( holding ) {
					heldRoutes.push( route );
					return;
				}
				await route.continue();
			}
		);
		const sideloadRequested = page.waitForRequest(
			( request ) =>
				decodeURIComponent( request.url() ).includes( '/sideload' ),
			{ timeout: 60_000 }
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );
		await sideloadRequested;

		// A synthetic cancelable event exercises the guard's listener
		// without triggering the real (untestable) browser prompt.
		const preventedWhileUploading = await page.evaluate( () => {
			const event = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );
			return event.defaultPrevented;
		} );
		expect( preventedWhileUploading ).toBe( true );

		// Release the held requests, let the upload finish, and verify the
		// guard disengages once nothing is in flight anymore.
		holding = false;
		for ( const route of heldRoutes ) {
			await route.continue();
		}
		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		await expect
			.poll( () =>
				page.evaluate( () => {
					const event = new Event( 'beforeunload', {
						cancelable: true,
					} );
					window.dispatchEvent( event );
					return event.defaultPrevented;
				} )
			)
			.toBe( false );
	} );

	test( 'shows an error for a disallowed file type', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( {
			name: 'disallowed.xyz',
			mimeType: 'application/octet-stream',
			buffer: Buffer.from( 'not an allowed file type' ),
		} );

		// The Manage frame renders rejected uploads in the error sidebar.
		const errorNotice = page
			.locator( '.upload-error' )
			.filter( { hasText: 'disallowed.xyz' } );
		await expect( errorNotice ).toBeVisible( { timeout: 30_000 } );

		// The sidebar names the file in its own span and keeps the reason
		// in the message span; the reason has to be readable text, not a
		// stringified object.
		await expect(
			errorNotice.getByText( 'disallowed.xyz', { exact: true } )
		).toBeVisible();
		const message = errorNotice.locator( '.upload-error-message' );
		await expect( message ).toHaveText( /\S/ );
		await expect( message ).not.toContainText( '[object Object]' );
	} );
	test( 'falls back to the classic uploader when the page is not isolated', async ( {
		page,
		admin,
	} ) => {
		// Blocking the Document-Isolation-Policy header reproduces every
		// browser that ignores it: the page is not isolated, the script
		// must no-op, and classic plupload must still upload the file.
		await page.route(
			( url ) => url.pathname.endsWith( '/wp-admin/upload.php' ),
			async ( route ) => {
				const response = await route.fetch();
				const headers = { ...response.headers() };
				delete headers[ 'document-isolation-policy' ];
				await route.fulfill( { response, headers } );
			}
		);

		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		expect( isolated ).toBe( false );

		let asyncUploadCount = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				asyncUploadCount++;
			} else if (
				/\/wp\/v2\/media/.test( decodeURIComponent( request.url() ) )
			) {
				restUploadCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		// Classic plupload handled the upload end to end.
		expect( asyncUploadCount ).toBeGreaterThanOrEqual( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'uploads several files at once, including duplicates', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		let finalizeCount = 0;
		page.on( 'request', ( request ) => {
			if (
				request.method() === 'POST' &&
				/\/wp\/v2\/media\/\d+\/finalize/.test(
					decodeURIComponent( request.url() )
				)
			) {
				finalizeCount++;
			}
		} );

		// The same file twice exercises the shared-identity path: both
		// tiles must track progress and resolve independently.
		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( [ TEST_IMAGE_PATH, TEST_IMAGE_PATH ] );

		await expect( tile( page, 'uploading…' ) ).toHaveCount( 0, {
			timeout: 90_000,
		} );
		await expect( tile( page, 'test-image' ) ).toHaveCount( 2 );
		expect( finalizeCount ).toBe( 2 );
	} );

	test( 'reports pipeline progress on the tile', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// Hold sideloads so the tile is observed mid-pipeline, after the
		// original has been uploaded but before thumbnails have landed.
		const heldRoutes = [];
		let holding = true;
		await page.route(
			( url ) => decodeURIComponent( url.href ).includes( '/sideload' ),
			async ( route ) => {
				if ( holding ) {
					heldRoutes.push( route );
					return;
				}
				await route.continue();
			}
		);
		const sideloadRequested = page.waitForRequest(
			( request ) =>
				decodeURIComponent( request.url() ).includes( '/sideload' ),
			{ timeout: 60_000 }
		);

		// Record the grid uploader's progress callback, which wp-plupload.js
		// calls on every UploadProgress.
		await page.evaluate( () => {
			window.__uploaderProgress = [];
			const uploader = window.wp.media.frame.uploader.uploader;
			const original = uploader.progress;
			uploader.progress = function ( model ) {
				window.__uploaderProgress.push( model.get( 'percent' ) );
				return original.apply( this, arguments );
			};
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );
		await sideloadRequested;

		await expect
			.poll( () =>
				page.evaluate( () => Math.max( 0, ...window.__uploaderProgress ) )
			)
			.toBeGreaterThan( 0 );

		// The placeholder tile's progress bar has advanced past zero but
		// is not reported complete while work remains.
		const bar = tile( page, 'uploading…' ).locator(
			'.media-progress-bar div'
		);
		await expect( bar ).toHaveAttribute( 'style', /width:\s*[1-9]/ );
		await expect( bar ).not.toHaveAttribute( 'style', /width:\s*100%/ );

		holding = false;
		for ( const route of heldRoutes ) {
			await route.continue();
		}
		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );
	} );

	test( 'surfaces a pipeline error with its message and file name', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		await failMediaCreate( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// The error lands in the grid's error sidebar exactly like a classic
		// upload error: the file name and the server's message.
		const error = page
			.locator( '.upload-error' )
			.filter( { hasText: 'test-image.jpg' } );
		await expect( error ).toBeVisible( { timeout: 60_000 } );
		await expect(
			error.getByText( 'test-image.jpg', { exact: true } )
		).toBeVisible();
		await expect(
			error.getByText( SIMULATED_ERROR.message, { exact: true } )
		).toBeVisible();

		// The placeholder tile is removed rather than left spinning.
		await expect( tile( page, 'uploading…' ) ).toHaveCount( 0 );
	} );
	test( 'leaves audio files to the classic uploader', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		let asyncUploadCount = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				asyncUploadCount++;
			} else if (
				/\/wp\/v2\/media/.test( decodeURIComponent( request.url() ) )
			) {
				restUploadCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_AUDIO_PATH );

		await expect( tile( page, 'test-audio' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		expect( asyncUploadCount ).toBeGreaterThanOrEqual( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'leaves files that are not processable images to the classic uploader', async ( {
		page,
		admin,
	} ) => {
		// A plugin can allow an extra type on its own uploader, as page
		// builders do for SVG, without adding it to the allowed mime types
		// the page was rendered with.
		await overridePluploadDefaults( page, {
			filters: { mime_types: [ { extensions: 'svg' } ] },
		} );
		await stubClassicUpload( page );
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const counts = countUploadRequests( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_SVG_PATH );

		// The uploader's own filter decides, so the file reaches the server
		// instead of being rejected in the browser.
		await expect
			.poll( () => counts.asyncUpload, { timeout: 60_000 } )
			.toBeGreaterThanOrEqual( 1 );
		expect( counts.rest ).toBe( 0 );
		await expect(
			page.locator( '.upload-error-message', {
				hasText: 'not allowed to upload this file type',
			} )
		).toHaveCount( 0 );
	} );

	test( 'leaves images of a type the page was not rendered with to the classic uploader', async ( {
		page,
		admin,
	} ) => {
		// A plugin can allow an image type only for its own requests, so
		// the allowed mime types the page was rendered with leave it out
		// while the uploader's own filter lets it through.
		await page.addInitScript( () => {
			let settings;
			Object.defineProperty( window, '_wpMediaUploadPipelineSettings', {
				configurable: true,
				get() {
					return settings;
				},
				set( value ) {
					settings = value;
					const allowed = value && value.allowedMimeTypes;
					for ( const extension in allowed || {} ) {
						if ( 'image/jpeg' === allowed[ extension ] ) {
							delete allowed[ extension ];
						}
					}
				},
			} );
		} );
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const counts = countUploadRequests( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// The server decides, as it would without the pipeline.
		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );
		expect( counts.asyncUpload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.rest ).toBe( 0 );
		await expect( page.locator( '.upload-error' ) ).toHaveCount( 0 );
	} );

	test( 'splits a batch of an image and audio between the pipeline and the classic uploader', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		let asyncUploadCount = 0;
		let restCreateCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			const decoded = decodeURIComponent( request.url() );
			if ( request.url().includes( '/async-upload.php' ) ) {
				asyncUploadCount++;
			} else if ( /\/wp\/v2\/media(?:[?&]|$)/.test( decoded ) ) {
				restCreateCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( [ TEST_IMAGE_PATH, TEST_AUDIO_PATH ] );

		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 90_000,
		} );
		await expect( tile( page, 'test-audio' ) ).toBeVisible();

		// The image went through the pipeline, the audio file through plupload.
		expect( restCreateCount ).toBe( 1 );
		expect( asyncUploadCount ).toBe( 1 );
	} );

	test( 'leaves JPEG images to the classic uploader while plupload resizes them', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// What the "Scale images" setting or a `resize` passed through
		// plupload_init turns on.
		await page.evaluate( () => {
			window.wp.media.frame.uploader.uploader.uploader.setOption(
				'resize',
				{ enabled: true, width: 1024, height: 1024, quality: 100 }
			);
		} );

		let asyncUploadCount = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				asyncUploadCount++;
			} else if (
				/\/wp\/v2\/media/.test( decodeURIComponent( request.url() ) )
			) {
				restUploadCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		expect( asyncUploadCount ).toBe( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'leaves files to the classic uploader until the pipeline settings have landed', async ( {
		page,
		admin,
	} ) => {
		// The store is configured by rendering a provider, which delivers
		// its settings asynchronously. Never rendering it reproduces a file
		// added inside that window: the pipeline must not claim the file,
		// because the store would hand it to a no-op uploader.
		await suppressProviderRender( page );

		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const ready = await page.evaluate( () =>
			window.wp.mediaUploadPipeline.isReady()
		);
		expect( ready ).toBe( false );

		const counts = countUploadRequests( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect( tile( page, 'test-image' ) ).toBeVisible( {
			timeout: 60_000,
		} );

		expect( counts.asyncUpload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.rest ).toBe( 0 );
	} );

	test( 'hands a HEIC the browser cannot decode to the server when it can convert it', async ( {
		page,
		admin,
	} ) => {
		// No heic_upload_error means the server's image editor handles HEIC.
		await overridePluploadDefaults( page, { heic_upload_error: false } );

		await withoutHeicDecoder( page );
		await stubClassicUpload( page );
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const counts = countUploadRequests( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( UNDECODABLE_HEIC );

		// The pipeline's decode failure is not shown; the file is re-queued
		// on the classic uploader, which posts it to async-upload.php.
		await expect
			.poll( () => counts.asyncUpload, { timeout: 60_000 } )
			.toBeGreaterThanOrEqual( 1 );
		expect( counts.rest ).toBe( 0 );
		await expect( tile( page, 'uploading…' ) ).toHaveCount( 0, {
			timeout: 60_000,
		} );
		await expect(
			page.locator( '.upload-error-message', { hasText: 'HEIC' } )
		).toHaveCount( 0 );
	} );

	test( 'reports the HEIC error when neither the browser nor the server can convert it', async ( {
		page,
		admin,
	} ) => {
		await overridePluploadDefaults( page, { heic_upload_error: true } );

		await withoutHeicDecoder( page );
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const counts = countUploadRequests( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( UNDECODABLE_HEIC );

		// Nothing can convert the file, so the pipeline's message (which
		// explains the platform limitation) is what the user sees.
		const error = page
			.locator( '.upload-error' )
			.filter( { hasText: UNDECODABLE_HEIC.name } );
		await expect( error ).toBeVisible( { timeout: 60_000 } );
		await expect(
			error.getByText( UNDECODABLE_HEIC.name, { exact: true } )
		).toBeVisible();
		await expect(
			error.locator( '.upload-error-message' )
		).toContainText( 'HEIC' );
		expect( counts.asyncUpload ).toBe( 0 );
		expect( counts.rest ).toBe( 0 );
		await expect( tile( page, 'uploading…' ) ).toHaveCount( 0 );
	} );
	test( 'deletes the attachment when finalizing the upload fails', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// The original and its sub-sizes reach the server; only finalize fails.
		await page.route(
			( url ) =>
				/\/wp\/v2\/media\/\d+\/finalize/.test(
					decodeURIComponent( url.href )
				),
			( route ) =>
				route.fulfill( {
					status: 500,
					contentType: 'application/json',
					body: JSON.stringify( SIMULATED_ERROR ),
				} )
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect(
			page
				.locator( '.upload-error' )
				.filter( { hasText: 'test-image.jpg' } )
		).toBeVisible( { timeout: 60_000 } );

		// Nothing is left behind for a retry to duplicate.
		await expect
			.poll(
				async () =>
					(
						await requestUtils.rest( {
							path: '/wp/v2/media',
							params: { per_page: 100 },
						} )
					).length,
				{ timeout: 30_000 }
			)
			.toBe( 0 );
	} );
} );
