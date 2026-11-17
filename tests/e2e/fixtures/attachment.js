/**
 * External dependencies
 */
import { createReadStream } from 'node:fs';

/**
 * Uploads attachments, records the ones the browser uploads, then deletes
 * them in teardown.
 *
 * @param {Object}                                                      fixtures              The fixtures this one depends on.
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} fixtures.requestUtils The request utils fixture.
 * @param {Function}                                                    use                   Hands the fixture to the test.
 * @return {Promise<void>}
 */
export const attachments = async ( { requestUtils }, use ) => {
	const ids = new Set();

	await use( {
		async upload( absolutePath ) {
			// Not requestUtils.uploadMedia(): it throws away the response on an
			// error, but the server can save the attachment and then fail while
			// it makes the sub-sizes. Core sends the id in a header before that.
			if ( ! requestUtils.storageState?.nonce ) {
				await requestUtils.setupRest();
			}
			const { rootURL, nonce } = requestUtils.storageState;
			const response = await requestUtils.request.post(
				`${ rootURL }wp/v2/media`,
				{
					headers: { 'X-WP-Nonce': nonce },
					multipart: { file: createReadStream( absolutePath ) },
					failOnStatusCode: false,
				}
			);
			const id = Number(
				response.headers()[ 'x-wp-upload-attachment-id' ]
			);
			if ( id > 0 ) {
				ids.add( id );
			}
			if ( ! response.ok() ) {
				throw new Error(
					`Upload failed with HTTP ${ response.status() }: ${ await response.text() }`
				);
			}
			return response.json();
		},
		track( id ) {
			ids.add( id );
		},
	} );

	const failures = [];
	for ( const id of ids ) {
		try {
			await requestUtils.deleteMedia( id );
		} catch ( error ) {
			// A test can delete its own attachment through the UI.
			if ( error.data?.status !== 404 ) {
				failures.push( `${ id }: ${ error.message }` );
			}
		}
	}

	if ( failures.length ) {
		throw new Error(
			`attachments could not delete:\n${ failures.join( '\n' ) }`
		);
	}
};
