import {
    visitAdminPage,
    __experimentalRest as rest,
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

const postsEndpoint = '/wp/v2/posts';

export async function createPost(post) {
    await rest({
        method: 'POST',
        path: postsEndpoint,
        data: {
            title: post.title,
            status: 'publish',
            categories: post.categories,
        }
    });
}

export async function deleteAllPosts() {
    const posts = await rest({ path: postsEndpoint });
    posts.map(async(post) => {
        await rest({
            method: 'DELETE',
            path: `${ postsEndpoint }/${ post.id }`,
        });
    });
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

    it('correctly sort categories per name', async() => {
        const categoriesNames = ['Category 1', 'Category 2', 'Category 3'];
        for (let i = 0, n = categoriesNames.length; i < n; i++) {
            await createNewCategory(categoriesNames[i]);
        }

        await page.waitForSelector('#the-list');

        // ASC order
        await page.click('#name');
        await page.waitForSelector('#the-list');

        const categoriesTitles = await page.$$('#the-list tr .row-title');

        // Check that the last item is "Uncategorized"
        const lastCategoryTitle = await categoriesTitles[categoriesTitles.length - 1].evaluate((element) => element.textContent);
        expect(lastCategoryTitle).toBe('Uncategorized');

        // Remove the last item because it is the "Uncategorized" category
        categoriesTitles.pop();

        categoriesTitles.map(async(categoryTitle, index) => {
            expect(
                await categoryTitle.evaluate((element) => element.textContent)
            ).toBe(categoriesNames[index]);
        });

        // DESC order
        await page.click('#name');
        await page.waitForSelector('#the-list');

        const categoriesTitles2 = await page.$$('#the-list tr .row-title');

        // Check that the last first is "Uncategorized"
        const firstCategoryTitle = await categoriesTitles2[0].evaluate((element) => element.textContent);
        expect(firstCategoryTitle).toBe('Uncategorized');

        // Remove the first item because it is the "Uncategorized" category
        categoriesTitles2.shift();

        categoriesTitles2.map(async(categoryTitle, index) => {
            expect(
                await categoryTitle.evaluate((element) => element.textContent)
            ).toBe(categoriesNames[categoriesNames.length - index - 1]);
        });
    });

    it('correctly sorts categories per post count', async() => {
        const categoriesNames = ['Category 0', 'Category 1'];

        for (let i = 0, n = categoriesNames.length; i < n; i++) {
            await createNewCategory(categoriesNames[i]);
        }

        // Get the ID of 'Category 1'
        await page.reload();
        let category1Id = await page.$('#the-list tr:nth-child(2)');
        category1Id = await category1Id.evaluate(element => (element.id));
        category1Id = category1Id.split('tag-')[1];

        await deleteAllPosts();

        await createPost({
            title: 'Post 0',
        });
        await createPost({
            title: 'Post 1',
            categories: [category1Id],
        });

        // ASC order
        await page.click('#posts');
        await page.waitForSelector('#the-list');
        const categoriesTitles = await page.$$('#the-list tr .row-title');

        // Remove the second item because it is the "Uncategorized" category
        categoriesTitles.splice(1, 1);

        categoriesTitles.map(async(categoryTitle, index) => {
            expect(
                await categoryTitle.evaluate((element) => element.textContent)
            ).toBe(categoriesNames[index]);
        });

        // DESC order
        await page.click('#posts');
        await page.waitForSelector('#the-list');
        const categoriesTitles2 = await page.$$('#the-list tr .row-title');

        // Remove the first item because it is the "Uncategorized" category
        categoriesTitles2.shift();

        categoriesTitles2.map(async(categoryTitle, index) => {
            expect(
                await categoryTitle.evaluate((element) => element.textContent)
            ).toBe(categoriesNames[categoriesNames.length - index - 1]);
        });
    });
});