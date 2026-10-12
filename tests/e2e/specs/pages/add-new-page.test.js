/**
 * Node dependencies
 */
const { randomUUID } = require( 'node:crypto' );

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Add new page', () => {
	let pageId;

	test.beforeEach( async ( { admin, page } ) => {
		pageId = undefined;
		try {
			await admin.createNewPost( { postType: 'page' } );
		} finally {
			// Opening the editor creates an auto-draft, even before typing.
			// Auto-drafts do not yet include their ID in the editor URL.
			pageId = Number( new URL( page.url() ).searchParams.get( 'post' ) );
			if ( ! pageId ) {
				const postId = await page.waitForFunction( () =>
					window.wp?.data?.select( 'core/editor' )?.getCurrentPostId()
				);
				pageId = await postId.jsonValue();
			}
			expect(
				pageId,
				'Capture the page ID for cleanup before editing'
			).toBeGreaterThan( 0 );
		}
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( pageId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ pageId }`,
				params: { force: true },
			} );
		}
	} );

	test( 'publishes a new page with its title and content', async ( {
		editor,
		page,
		requestUtils,
	} ) => {
		const title = `Test Page ${ randomUUID() }`;
		const content = 'Test page description';
		const titleInput = editor.canvas.getByRole( 'textbox', {
			name: 'Add title',
			exact: true,
		} );

		await titleInput.fill( title );
		await titleInput.press( 'Enter' );
		await editor.canvas
			.getByRole( 'document', {
				name: 'Empty block; start writing or type forward slash to choose a block',
				exact: true,
			} )
			.fill( content );

		const publishedId = await editor.publishPost();
		expect( publishedId ).toBe( pageId );

		// Read persisted state; creation and publication remain browser actions.
		const savedPage = await requestUtils.rest( {
			path: `/wp/v2/pages/${ pageId }`,
			params: { context: 'edit' },
		} );
		expect( savedPage.id ).toBe( pageId );
		expect( savedPage.status ).toBe( 'publish' );
		expect( savedPage.title.raw ).toBe( title );
		expect( savedPage.content.raw ).toContain( `<p>${ content }</p>` );

		await page.goto( savedPage.link );
		await expect(
			page.getByRole( 'heading', { name: title, exact: true } )
		).toBeVisible();
		await expect(
			page.getByText( content, { exact: true } )
		).toBeVisible();
	} );
} );
