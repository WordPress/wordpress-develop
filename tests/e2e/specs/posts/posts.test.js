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

        // Make sure that all trashed posts have been deleted permanently.
        await page.waitForSelector('#the-list tr:only-child');
    } else {
        return;
    }
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

    it('Correctly restore a post from trash', async() => {
        const title = 'Post to be restored';
        await createNewPost({ title });
        await publishPost();
        await trashAllPosts();

        const query = addQueryArgs('', {
            post_status: 'trash',
            post_type: 'post',
        });
        await visitAdminPage('edit.php', query);

        // Hover on the trashed post and click on the restore link
        const trashedPost = await page.$('#the-list td.title');
        await trashedPost.hover();
        const restoredLink = await page.$('span.untrash a');
        await restoredLink.click();

        const restoredPostMessage = await page.waitForSelector('div#message');
        expect(
            await restoredPostMessage.evaluate((element) => element.innerText)
        ).toContain('1 post restored from the Trash. Edit Post');

        await visitAdminPage('edit.php');

        const restoredPostLink = await page.$('a.row-title');
        expect(
            await restoredPostLink.evaluate((element) => element.innerText)
        ).toContain(title);
    });
});