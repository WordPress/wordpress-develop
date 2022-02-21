import { loginUser, visitAdminPage,activateTheme } from '@wordpress/e2e-test-utils';

describe( 'Should create a new menu and remove existing menu', () => {

    beforeEach( async () => {
        await loginUser();
	await activateTheme("twentytwentyone");
    } );


	it( 'Add a new menu', async () => {
		
        await visitAdminPage("nav-menus.php");
        
        //check if it's a first menu
        await page.waitForSelector(".first-menu-message")

        await page.type('#menu-name','New Menu');
        
        // select menu as a primary menu
        await page.click("#locations-primary");

        await page.click("#save_menu_footer");

        await page.waitForSelector(".add-edit-menu-action");

	} );


    it( 'Remove existing menu', async () => {
		
	await visitAdminPage("nav-menus.php");

        const createmenu = await page.$x("//a[normalize-space()='create a new menu']");
        await createmenu[0].click();

        await page.waitForSelector("#menu-name", {timeout: 60000})

        await page.type('#menu-name','Test Menu');
        await page.click("#locations-primary");
        await page.click("#save_menu_footer");
        
        await page.waitForSelector("#nav-menu-footer", {timeout: 60000})
     
        await page.waitForSelector(".delete-action",{timeout: 60000});
        const deletemenu = await page.$x("//a[normalize-space()='Delete Menu']");
		    await deletemenu[0].click();
        await page.click(".submitdelete.deletion.menu-delete");
        page.on('dialog', async (dialog) => {
            console.log(dialog.message());
            await dialog.dismiss();
            await browser.close();
          });
    
        await page.waitForSelector(".add-edit-menu-action", {timeout: 60000});

	} );
  
} );
