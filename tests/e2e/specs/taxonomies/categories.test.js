import {
    visitAdminPage,
} from '@wordpress/e2e-test-utils';
import { addQueryArgs } from "@wordpress/url";

async function createNewCategory(name) {
    const categoriesPageQuery = addQueryArgs('', {
        taxonomy: 'category',
    });
    await visitAdminPage('edit-tags.php', categoriesPageQuery);

    await page.waitForSelector('#tag-name');
    await page.type('#tag-name', name);
    await page.click('#submit');

    await page.waitForSelector('#the-list tr + tr');
}

async function deleteAllCategories() {
    const categoriesPageQuery = addQueryArgs('', {
        taxonomy: 'category',
    });
    await visitAdminPage('edit-tags.php', categoriesPageQuery);
    await page.waitForSelector('#the-list');

    const remainingCategoryRow = await page.$$('#the-list tr');
    if (remainingCategoryRow.length > 1) {
        await page.click('#cb-select-all-1');
        await page.select('#bulk-action-selector-top', 'delete');
        await page.focus('#doaction');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#message p')
    }
}

describe('Manage categories', () => {
    const categoriesPageQuery = addQueryArgs('', {
        taxonomy: 'category',
    });

    beforeEach(async() => {
        await deleteAllCategories();
    });

    it('shows the default Uncategorized category', async() => {
        await visitAdminPage('edit-tags.php', categoriesPageQuery);

        const uncategorizedTitle = await page.waitForSelector('#tag-1 .row-title');
        expect(
            await uncategorizedTitle.evaluate((element) => element.textContent)
        ).toBe('Uncategorized');
    });

    it('correctly creates a new category', async() => {
        await createNewCategory('Test Category');
        const newCategoryTitle = await page.$('#the-list tr:first-child .row-title');
        expect(
            await newCategoryTitle.evaluate((element) => element.textContent)
        ).toBe('Test Category');
    });

    it('should not allow to add the same category name twice', async() => {
        await createNewCategory('Test Category');
        await createNewCategory('Test Category');

        const errorMessage = await page.waitForSelector('#ajax-response p');
        expect(
            await errorMessage.evaluate((element) => element.textContent)
        ).toContain('A term with the name provided already exists with this parent.');
    });

    it('correctly deletes all categories', async() => {
        await createNewCategory('Test Category');
        await deleteAllCategories();

        const successMessage = await page.waitForSelector('#message p');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('Categories deleted.');

        const remainingCategoryRow = await page.$$('#the-list tr');
        expect(remainingCategoryRow.length).toBe(1);
    });

    it('correctly quick edits a category', async() => {
        await createNewCategory('Test Category');

        await page.focus('#the-list tr:first-child .row-title');
        await page.click('#the-list tr:first-child .editinline');
        await page.waitForSelector('input.ptitle[name=name]');
        await page.type('input.ptitle[name=name]', ' Edited');
        await page.keyboard.press('Enter');

        await page.waitForResponse(response => response.status() === 200);
        await page.waitForTimeout(500);

        const editedCategoryTitle = await page.waitForSelector('#the-list tr:first-child .row-title');
        expect(
            await editedCategoryTitle.evaluate((element) => element.textContent)
        ).toBe('Test Category Edited');
    });

    it('correctly edits a category', async() => {
        await createNewCategory('Test Category');

        await page.focus('#the-list tr:first-child .row-title');
        await page.click('span.edit a');

        await page.waitForSelector('#name');
        await page.focus('#name');
        await page.type('#name', ' Edited ');
        await page.click('input.button-primary');

        const successMessage = await page.waitForSelector('#message p');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('Category updated.');

        await visitAdminPage('edit-tags.php', categoriesPageQuery);

        const editedCategoryTitle = await page.waitForSelector('#the-list tr:first-child .row-title');
        expect(
            await editedCategoryTitle.evaluate((element) => element.textContent)
        ).toBe('Edited Test Category');
    });

    it('should correctly searchs an existing category', async() => {
        await createNewCategory('Test Category');

        await page.focus('#tag-search-input');
        await page.type('#tag-search-input', 'Test Category');
        await page.keyboard.press('Enter');

        await page.waitForSelector('span.subtitle');
        await page.waitForSelector('#the-list');

        const remainingCategoryRow = await page.$$('#the-list tr');
        expect(remainingCategoryRow.length).toBe(1);

        const remainCategoryTitle = await page.waitForSelector('#the-list tr:first-child .row-title');
        expect(
            await remainCategoryTitle.evaluate((element) => element.textContent)
        ).toContain('Test Category');
    });

    it('should not find a non existing category', async() => {
        await page.focus('#tag-search-input');
        await page.type('#tag-search-input', 'Test Category');
        await page.keyboard.press('Enter');

        await page.waitForSelector('#the-list');

        const noItemRow = await page.$$('#the-list tr.no-items');
        expect(noItemRow.length).toBe(1);

        const noItemContent = await page.waitForSelector('#the-list tr td');
        expect(
            await noItemContent.evaluate((element) => element.textContent)
        ).toContain('No categories found.');
    });
});