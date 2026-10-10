import { activateTheme, loginUser, visitAdminPage} from '@wordpress/e2e-test-utils';

describe( 'Should create and delete a menu', () => {

    async function createMenu() {

        await visitAdminPage("nav-menus.php");

        await page.type('#menu-name','New Menu');

        // select menu as a primary menu
        await page.click("#locations-primary");

        await page.click("#save_menu_footer");

        await page.waitForSelector(".add-edit-menu-action");

    }


    beforeEach( async () => {
        await loginUser();
        await activateTheme("twentytwentyone");
    } );



    it( 'Remove existing menu', async () => {

        await createMenu();

	await visitAdminPage("nav-menus.php");

        await page.click(".add-edit-menu-action a");

        await page.waitForSelector("#menu-name")

        await page.type('#menu-name','Test Menu');
        await page.click("#locations-primary");
        await page.click("#save_menu_footer");

        await page.waitForSelector("#nav-menu-footer");
       
        await page.click(".submitdelete.deletion.menu-delete");
        await page.click(".submitdelete.deletion.menu-delete");
        
        page.on('dialog', async (dialog) => {
            await dialog.accept();
          });

        await page.waitForSelector(".add-edit-menu-action");

	} );


} );
