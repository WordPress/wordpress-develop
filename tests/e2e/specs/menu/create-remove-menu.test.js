import { activateTheme, loginUser, visitAdminPage} from '@wordpress/e2e-test-utils';

describe( 'Should create a new Menu', () => {

    beforeEach( async () => {
        await loginUser();
        await activateTheme("twentytwentyone");
    } );


	it( 'Add a new menu', async () => {
		
		await visitAdminPage("nav-menus.php");
        
        // check if it's a first menu
        await page.waitForSelector(".first-menu-message");

        await page.type('#menu-name','New Menu');
        
        // select menu as a primary menu
        await page.click("#locations-primary");

        await page.click("#save_menu_footer");

        await page.waitForSelector(".add-edit-menu-action");

	} );


    it( 'Remove existing menu', async () => {
		
		await visitAdminPage("nav-menus.php");

        await page.click(".add-edit-menu-action a");

        await page.waitForSelector("#menu-name")

        await page.type('#menu-name','Test Menu');
        await page.click("#locations-primary");
        await page.click("#save_menu_footer");
        
        await page.waitForSelector("#nav-menu-footer")
        await page.waitForSelector(".delete-action");
        await page.click(".submitdelete.deletion.menu-delete")
        page.on('dialog', async (dialog) => {
            await dialog.accept();
          });

        
        await page.waitForSelector(".add-edit-menu-action");

	} );


} );


