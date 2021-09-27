import {
    visitAdminPage,
} from '@wordpress/e2e-test-utils';

async function createNewTag(name) {
    await visitAdminPage('edit-tags.php');

    await page.waitForSelector('#tag-name');
    await page.type('#tag-name', name);
    await page.click('#submit');

    await page.waitForResponse(response => response.status() === 200);
}

async function deleteAllTags() {
    await visitAdminPage('edit-tags.php');
    await page.waitForSelector('#the-list');

    const noItemRow = await page.$$('#the-list tr.no-items');
    if (noItemRow.length == 0) {
        await page.click('#cb-select-all-1');
        await page.select('#bulk-action-selector-top', 'delete');
        await page.focus('#doaction');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#message p')
    }
}

describe('Manage tags', () => {
    beforeEach(async() => {
        await deleteAllTags();
    });

    it('correctly creates a new tag', async() => {
        await createNewTag('Test tag');
        const newTagTitle = await page.$('#the-list tr .row-title');
        expect(
            await newTagTitle.evaluate((element) => element.textContent)
        ).toBe('Test tag');
    });

    it('should not allow to add the same tag name twice', async() => {
        await createNewTag('Test tag');
        await createNewTag('Test tag');

        const errorMessage = await page.waitForSelector('#ajax-response p');
        expect(
            await errorMessage.evaluate((element) => element.textContent)
        ).toContain('A term with the name provided already exists in this taxonomy.');
    });

    it('correctly deletes all tags', async() => {
        await createNewTag('Test tag');
        await deleteAllTags();

        const successMessage = await page.waitForSelector('#message p');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('Tags deleted.');

        const remainingTagRow = await page.$$('#the-list tr');
        expect(remainingTagRow.length).toBe(1);
    });

    it('correctly quick edits a tag', async() => {
        await createNewTag('Test tag');

        await page.focus('#the-list tr .row-title');
        await page.click('#the-list tr .editinline');
        await page.waitForSelector('input.ptitle[name=name]');
        await page.type('input.ptitle[name=name]', ' Edited');
        await page.keyboard.press('Enter');

        await page.waitForResponse(response => response.status() === 200);

        const editedTagTitle = await page.waitForSelector('#the-list tr .row-title');
        expect(
            await editedTagTitle.evaluate((element) => element.textContent)
        ).toBe('Test tag Edited');
    });

    it('correctly edits a tag', async() => {
        await createNewTag('Test tag');

        await page.focus('#the-list tr .row-title');
        await page.click('span.edit a');

        await page.waitForSelector('#name');
        await page.focus('#name');
        await page.type('#name', ' Edited ');
        await page.click('input.button-primary');

        const successMessage = await page.waitForSelector('#message p');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('Tag updated.');

        await visitAdminPage('edit-tags.php');

        const editedTagTitle = await page.waitForSelector('#the-list tr .row-title');
        expect(
            await editedTagTitle.evaluate((element) => element.textContent)
        ).toBe('Edited Test tag');
    });

    it('should correctly searches an existing tag', async() => {
        await createNewTag('Test tag');

        await page.focus('#tag-search-input');
        await page.type('#tag-search-input', 'Test tag');
        await page.keyboard.press('Enter');

        await page.waitForSelector('span.subtitle');
        await page.waitForSelector('#the-list');

        const remainingTagRow = await page.$$('#the-list tr');
        expect(remainingTagRow.length).toBe(1);

        const remainingTagTitle = await page.waitForSelector('#the-list tr .row-title');
        expect(
            await remainingTagTitle.evaluate((element) => element.textContent)
        ).toContain('Test tag');
    });

    it('should not find a non existing tag', async() => {
        await page.focus('#tag-search-input');
        await page.type('#tag-search-input', 'Test tag');
        await page.keyboard.press('Enter');

        await page.waitForSelector('span.subtitle');

        const noItemRow = await page.$$('#the-list tr.no-items');
        expect(noItemRow.length).toBe(1);

        const noItemContent = await page.waitForSelector('#the-list tr td');
        expect(
            await noItemContent.evaluate((element) => element.textContent)
        ).toContain('No tags found.');
    });
});