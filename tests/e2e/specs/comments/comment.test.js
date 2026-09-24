const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// Utility functions
function generateRandomString() {
    return Math.random().toString(36).substring(2, 10);
}

async function verifyCommentPresence(page, commentText) {
    const commentLocator = page.locator(
        `tr[id^='comment-'] .has-row-actions.column-primary p:has-text("${commentText}")`
    );
    await expect(commentLocator).toBeVisible({
        message: `Expected to find a comment with text: ${commentText}`,
    });
}

test.describe('Manage Comments', () => {
    let postTitle;

    test.afterAll(async ({ requestUtils }) => {
        await requestUtils.deleteAllPosts();
        await requestUtils.deleteAllComments();
    });

    test('views the added comment from backend', async ({ page, admin, requestUtils }) => {
        postTitle = 'Test post for comment view';
        const commentString = `test comment ${generateRandomString()}`;

        // Create and publish a post
        await requestUtils.createPost({ title: postTitle, status: 'publish' });

        // Add a comment
        await admin.visitAdminPage('edit.php');
        await page.locator(`a[aria-label^='“${postTitle}” (Edit)']`).first().hover();
        await page.locator(`a[aria-label='View “${postTitle}”']`).first().click();
        await page.fill('#comment', commentString);
        await page.click('role=button[name=/Post Comment/i]');

        // Verify comment presence in admin panel
        await admin.visitAdminPage(`edit-comments.php?s=${commentString}`);
        await expect(page.locator('#the-extra-comment-list .no-items')).toBeHidden();
        await verifyCommentPresence(page, commentString);
    });

    test('quick edits the comment', async ({ page, admin, requestUtils }) => {
        postTitle = 'Test post for quick edit';
        const post = await requestUtils.createPost({ title: postTitle, status: 'publish' });
        const commentString = `test comment ${generateRandomString()}`;

        // Add a comment
        await requestUtils.createComment({ content: commentString, post: post.id });

        // Quick edit the comment
        await admin.visitAdminPage(`edit-comments.php?s=${commentString}`);
        await page.locator('.has-row-actions.column-primary').first().hover();
        await page.locator('.quickedit button[aria-label*="Quick edit this comment inline"]').click();

        const updatedCommentString = `test edited comment ${generateRandomString()}`;
        await page.fill('#replycontent', updatedCommentString);
        await page.click('role=button[name="Update Comment"i]');

        // Verify the edited comment
        await verifyCommentPresence(page, updatedCommentString);
    });

    test('deletes the comment', async ({ page, admin, requestUtils }) => {
        postTitle = 'Test post for delete comment';
        const post = await requestUtils.createPost({ title: postTitle, status: 'publish' });
        const commentString = `test comment ${generateRandomString()}`;

        // Add a comment
        await requestUtils.createComment({ content: commentString, post: post.id });

        // Delete the comment
        await admin.visitAdminPage('edit-comments.php');
        const commentLocator = page
            .locator(`tr[id^='comment-'] p:has-text("${commentString}")`)
            .first();
        await page.locator('.has-row-actions.column-primary').first().hover();
        const trashButton = page.locator('a[aria-label="Move this comment to the Trash"]').first();
        await trashButton.click();

        // Verify the deletion success message
        await expect(page.locator('.trash-undo-inside:visible')).toContainText(
            /Comment by\s+admin\s+moved to the Trash\.\s+Undo/i,
            {
                message: 'Expected success message to confirm comment deletion.',
            }
        );

        // Verify the comment is no longer present
        await expect(commentLocator).toBeHidden({
            message: `Expected the comment "${commentString}" to be deleted.`,
        });
    });
});
