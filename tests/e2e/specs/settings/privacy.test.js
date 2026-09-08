/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const path = require( 'path' );
const fs = require( 'fs' );

test.describe( 'Privacy Settings', () => {
	const postTitle = 'Privacy Policy Test';
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.createPage( {
			title: postTitle,
			status: 'publish',
		} );
	} );
	test.beforeEach( async ( { admin } ) => {
		// Navigate to Privacy Settings page before each test
		await admin.visitAdminPage( 'options-privacy.php' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPages();
	} );

	test( 'creates the privacy page', async ( { page, editor, admin } ) => {
		// Assert the title of the Privacy Settings page
		await expect(
			page.locator( "div[class='privacy-settings-title-section'] h1" )
		).toHaveText( 'Privacy' );
		await page.locator( '#create-page' ).click();
		await page.waitForLoadState( 'domcontentloaded' );
		await editor.setPreferences( 'core/edit-post', {
			welcomeGuide: false,
			fullscreenMode: false,
		} );

		// Verify the specific text inside the 'Editor content' region
		const guideText =
			'Need help putting together your new Privacy Policy page? Check out our guide for';
		await expect(
			page
				.getByRole( 'region', { name: 'Editor content' } )
				.getByText( guideText )
		).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await editor.publishPost();
		await admin.visitAdminPage( 'options-privacy.php' );

		const privacyPolicyDropdown = page.locator(
			'select#page_for_privacy_policy'
		);

		// Get the selected option's text
		const selectedValue = await privacyPolicyDropdown
			.locator( 'option:checked' )
			.textContent();

		// Verify the selected option is "Privacy Policy"
		await expect( selectedValue.trim() ).toBe( 'Privacy Policy' );
	} );

	test( 'changes the Privacy Policy Page', async ( { page } ) => {
		// Locate the dropdown and set the value by visible text
		await page
			.locator( 'select#page_for_privacy_policy' )
			.selectOption( { label: postTitle } );

		// Click the "Use This Page" button to set the privacy page
		await page.click( 'role=button[name="Use This Page"i]' );

		// Verify success message
		await expect(
			page.locator( '#setting-error-page_for_privacy_policy' )
		).toHaveText(
			/Privacy Policy page setting updated successfully. Remember to update your menus!/
		);
	} );

	test( 'validates the Privacy Policy Guide Title', async ( { page } ) => {
		// Navigate to the Privacy Policy Guide tab
		await page.click( "a[class='privacy-settings-tab']" );

		// Verify the header text in the Privacy Policy Guide section
		await expect(
			page.locator( 'text=Privacy Policy Guide' ).first()
		).toHaveText( 'Privacy Policy Guide' );
	} );

	test( 'copies WordPress text to clipboard', async ( { page } ) => {
		// Navigate to the Privacy Policy Guide tab
		await page.click( "a[class='privacy-settings-tab']" );
		await page.click(
			'.privacy-settings-accordion-trigger:has-text("WordPress")'
		);

		// Click the copy button after ensuring it's visible
		const copyButton = page.locator(
			'div#privacy-settings-accordion-block-wordpress button.privacy-text-copy'
		);
		await copyButton.scrollIntoViewIfNeeded();
		await copyButton.click();

		// Manually trigger a clipboard read event after user interaction
		await page.waitForTimeout( 1000 ); // Small delay to ensure copy action completes
		// Get clipboard content
		const clipboardText = await page.evaluate( async () => {
			return ( await navigator.clipboard.readText() ).trim();
		} );
		console.log( 'clipboard: ' + clipboardText );
		// Read expected text from a file
		const expectedTextPath = path.resolve(
			__dirname,
			'../../assets/expected_text.txt'
		);
		const expectedText = fs
			.readFileSync( expectedTextPath, 'utf-8' )
			.trim();
		// Verify clipboard content matches the expected file content
		expect( clipboardText.trim() ).toBe( expectedText );
	} );
} );
