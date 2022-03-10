import {
  visitAdminPage,
  createNewPost,
  trashAllPosts,
  publishPost,
} from "@wordpress/e2e-test-utils";

const POST_TITLE = "Test Title";

describe("Empty Trash", () => {
  async function createPost(title) {
    // Create a Post
    await createNewPost({ title });
    await publishPost();
  }

  afterEach(async () => {
    await trashAllPosts();
  });

  it("Empty Trash", async () => {
    await createPost(POST_TITLE);

    await visitAdminPage("/edit.php");

    // Move post to trash
    await page.hover(`[aria-label^="“${POST_TITLE}”"]`);
    await page.click(`[aria-label='Move “${POST_TITLE}” to the Trash']`);

    // Empty trash
    const trashTab = await page.waitForXPath('//h2[text()="Filter posts list"]/following-sibling::ul//a[contains(text(), "Trash")]');
    await Promise.all([
      trashTab.click(),
      page.waitForNavigation(),
    ]);
    const deleteAllButton = await page.waitForSelector('input[value="Empty Trash"]');
    await Promise.all([
      deleteAllButton.click(),
      page.waitForNavigation(),
    ]);

    const messageElement = await page.waitForSelector("#message");
    const message = await messageElement.evaluate((node) => node.innerText);
    // Until we have `deleteAllPosts`, the number of posts being deleted could be dynamic.
    expect(message).toMatch(/\d+ posts? permanently deleted\./);
  });

  it("Restore trash post", async () => {
    await createPost(POST_TITLE);

    await visitAdminPage("/edit.php");

    // Move one post to trash.
    await page.hover(`[aria-label^="“${POST_TITLE}”"]`);
    await page.click(`[aria-label='Move “${POST_TITLE}” to the Trash']`);

    // Remove post from trash.
    const trashTab = await page.waitForXPath('//h2[text()="Filter posts list"]/following-sibling::ul//a[contains(text(), "Trash")]');
    await Promise.all([
      trashTab.click(),
      page.waitForNavigation(),
    ]);
    const [postTitle] = await page.$x(`//*[text()="${POST_TITLE}"]`);
    await postTitle.hover();
    await page.click(`[aria-label="Restore “${POST_TITLE}” from the Trash"]`);

    // Expect for success message for trashed post.
    const messageElement = await page.waitForSelector("#message");
    const message = await messageElement.evaluate((element) => element.innerText);
    expect(message).toContain("1 post restored from the Trash.");
  });
});
