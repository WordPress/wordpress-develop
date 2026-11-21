/**
 * Locators for WP admin screens.
 *
 * These elements have no unique accessible name, or share it with a twin control
 * at the bottom of the table, so they are found by ID.
 */

/**
 * @param {import('@playwright/test').Page} page The page to search in.
 * @return {import('@playwright/test').Locator} The `#message` admin notice.
 */
export const adminNotice = ( page ) => page.locator( '#message' );

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} scope Page, or a locator to search in.
 * @param {number}                                                                     id    Post ID.
 * @return {import('@playwright/test').Locator} The post's list table row.
 */
export const postRow = ( scope, id ) => scope.locator( `#post-${ id }` );

/**
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} scope Page, or a locator to search in.
 * @param {number}                                                                     id    Comment ID.
 * @return {import('@playwright/test').Locator} The comment's list table row.
 */
export const commentRow = ( scope, id ) => scope.locator( `#comment-${ id }` );

/**
 * @param {import('@playwright/test').Page} page The page to search in.
 * @return {import('@playwright/test').Locator} The top bulk action dropdown.
 */
export const bulkActionSelect = ( page ) =>
	page.locator( '#bulk-action-selector-top' );

/**
 * @param {import('@playwright/test').Page} page The page to search in.
 * @return {import('@playwright/test').Locator} The top bulk action "Apply" button.
 */
export const bulkActionApply = ( page ) => page.locator( '#doaction' );
