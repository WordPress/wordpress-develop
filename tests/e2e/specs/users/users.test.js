import {
    visitAdminPage,
} from "@wordpress/e2e-test-utils";

async function deleteNonCurrentUsers() {
    await visitAdminPage('users.php');
    await page.waitForSelector('#the-list tr');

    const allUsersRows = await page.$$('#the-list tr');
    if (allUsersRows.length > 1) {
        await page.click('#cb-select-all-1');
        await page.select('#bulk-action-selector-top', 'delete');

        await page.click('#doaction');

        await page.waitForSelector('input#submit');
        await page.click('input#submit');

        await page.waitForNavigation();
    }
}

async function createBasicUser(username, useremail) {
    await visitAdminPage('user-new.php');

    // Wait for the username field to appear and focus it
    const newUsernameField = await page.waitForSelector('input#user_login');
    await newUsernameField.focus();

    // Type the user name and user email
    await page.keyboard.type(username);
    await page.keyboard.press('Tab');
    await page.keyboard.type(useremail);

    // Add the user
    await page.click('input#createusersub');
}

async function goToUserProfilePage(username) {
    // Wait for the username to appears before focus on it
    await page.waitForSelector('td.column-username');

    const [newUserLink] = await page.$x(
        `//td[contains( @class, "column-username" )]//a[contains( text(), "${ username }" )]`
    );

    // Focus on the new user link and move to the edit link
    newUserLink.focus();
    await page.keyboard.press('Tab');

    // Click on the edit link
    await page.keyboard.press('Enter');
}

describe('Core Users', () => {
    const e2eTestUser = 'testuser';
    const e2eTestUserEmail = 'testuser@test.com';

    beforeEach(async() => {
        await deleteNonCurrentUsers();
    });

    it('Correctly shows a new added user', async() => {
        createBasicUser(e2eTestUser, e2eTestUserEmail)

        // Wait for two users rows to appear
        await page.waitForSelector('#the-list tr + tr');

        // Check that the new user is added and shows correctly
        const newUserLink = await page.$x(
            `//td[contains( @class, "column-username" )]//a[contains( text(), "testuser" )]`
        );
        expect(newUserLink.length).toBe(1);
    });

    it('Returns the appropriate result when searching for an existing user', async() => {
        createBasicUser(e2eTestUser, e2eTestUserEmail)

        // Wait for the search field to appear and focus it
        const userSearchInput = await page.waitForSelector('#user-search-input');
        userSearchInput.focus();

        // Type the new username in the search input
        await page.keyboard.type(e2eTestUser);

        // Move to the search button and click on it
        await page.keyboard.press('Tab');
        await page.keyboard.press('Enter');
        await page.waitForNavigation();

        // Check that there is only one user row
        const allUsersRows = await page.$$('#the-list tr');
        expect(allUsersRows.length).toBe(1);

        // Check that the remaining user is "testuser"
        const foundUserRow = await page.waitForSelector('#the-list td.column-username a');
        expect(
            await foundUserRow.evaluate((element) => element.textContent)
        ).toContain(e2eTestUser);
    });

    it('Should return No users found. when searching for a user that does not exist', async() => {
        createBasicUser(e2eTestUser, e2eTestUserEmail)

        // Wait for the search field to appear and focus it
        const userSearchInput = await page.waitForSelector('#user-search-input');
        userSearchInput.focus();

        // Type the new username in the search input
        await page.keyboard.type('nonexistinguser');

        // Move to the search button and click on it
        await page.keyboard.press('Tab');
        await page.keyboard.press('Enter');
        await page.waitForNavigation();

        // Check that there is only one user row
        const allUsersRows = await page.$$('#the-list tr.no-items');
        expect(allUsersRows.length).toBe(1);

        // Check that the remaining row contains "No users found."
        const notFoundUserRow = await page.waitForSelector('#the-list tr.no-items');
        expect(
            await notFoundUserRow.evaluate((element) => element.textContent)
        ).toContain('No users found.');
    });

    it('Correctly edit a user first and last names', async() => {
        createBasicUser(e2eTestUser, e2eTestUserEmail)
        await goToUserProfilePage(e2eTestUser);

        // Wait for the user first name input to appears
        await page.waitForSelector('input#first_name');

        // Focus on the user first name input field
        await page.focus('input#first_name');

        // Edit the user first and last names
        await page.keyboard.type('Test');
        await page.keyboard.press('Tab');
        await page.keyboard.type('User');

        // Focus on the submit button and save the changes
        await page.focus('input#submit');
        await page.keyboard.press('Enter');

        // Wait for the success notice message to show
        await page.waitForSelector('#message');

        // Go back to the users list page
        await visitAdminPage('users.php');

        // Check that the new user complete name is "Test User"
        const editedUserFullName = await page.$x(
            `//td[contains( @class, "column-name" )][contains( text(), "Test User" )]`
        );
        expect(editedUserFullName.length).toBe(1);
    });

    it('Correctly changes the role of a user', async() => {
        createBasicUser(e2eTestUser, e2eTestUserEmail)
        await goToUserProfilePage(e2eTestUser);

        // Wait for the role field to appears
        await page.waitForSelector('select#role');

        // Change the user role to author
        await page.select('select#role', 'author');

        // Focus on the submit button and save the changes
        await page.focus('input#submit');
        await page.keyboard.press('Enter');

        // Wait for the success notice message to show
        await page.waitForSelector('#message');

        // Go back to the users list page
        await visitAdminPage('users.php');

        // Check that the new user role name is "author"
        const editedUserRole = await page.$x(
            `//td[contains( @class, "column-role" )][contains( text(), "Author" )]`
        );
        expect(editedUserRole.length).toBe(1);
    });

    it('Should not allows the main admin user to change their role', async() => {
        await goToUserProfilePage("admin");
        await page.waitForNavigation();

        // Check that there is no field to change the admin role
        const changeUserRoleField = await page.$x(
            `//select[contains( @id, "role" )]`
        );
        expect(changeUserRoleField.length).toBe(0);
    });
});