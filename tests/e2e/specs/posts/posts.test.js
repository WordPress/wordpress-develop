import {
    pressKeyTimes,
    visitAdminPage,
    createNewPost,
    publishPost,
    trashAllPosts,
} from '@wordpress/e2e-test-utils';
import { addQueryArgs } from "@wordpress/url";

async function deleteAllTrashedPosts() {
    const query = addQueryArgs('', {
        post_status: 'trash',
        post_type: 'post',
    });
    await visitAdminPage('edit.php', query);

    const trashPostsRows = await page.$$('#the-list tr.type-post');

    if (trashPostsRows.length > 0) {
        await page.click('[id^=cb-select-all-1]');
        await page.select('#bulk-action-selector-top', 'delete');
        await page.focus('#doaction');
        await page.keyboard.press('Enter');
    }

    // Make sure that all trashed posts have been deleted permanently.
    await page.waitForSelector('#the-list tr:only-child');
}

describe('Manage posts', () => {
    beforeEach(async() => {
        await trashAllPosts();
        await deleteAllTrashedPosts();
    });

    it('Correctly trash a single post', async() => {
        const title = 'Post to be trashed';
        await createNewPost({ title });
        await publishPost();

        await visitAdminPage('edit.php');

        const newPostLink = await page.$('a.row-title');
        newPostLink.focus();
        await pressKeyTimes('Tab', 3);
        await page.keyboard.press('Enter');

        const trashedMessage = await page.waitForSelector('div#message');
        expect(
            await trashedMessage.evaluate((element) => element.innerText)
        ).toContain('1 post moved to the Trash. Undo');

        const query = addQueryArgs('', {
            post_status: 'trash',
            post_type: 'post',
        });
        await visitAdminPage('edit.php', query);

        const trashedPost = await page.$('#the-list td.title');
        expect(
            await trashedPost.evaluate((element) => element.innerText)
        ).toContain(title);
    });
});