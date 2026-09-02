/**
 * Common WordPress admin interaction patterns.
 *
 * Reusable utilities for UI interactions used in state variants.
 * Can be called directly or composed inside page object state setup functions.
 *
 * @example
 * const { filterByStatus, toggleListGridView } = require( './admin-interactions' );
 *
 * const variant = {
 *   name: 'filtered-draft',
 *   setup: async (page) => { await filterByStatus(page, 'draft'); }
 * };
 */

/**
 * Filter posts by status using the view switcher links.
 *
 * @param {Object} page - Playwright page object
 * @param {string} status - Status to filter by (e.g., 'all', 'published', 'draft', 'scheduled', 'pending')
 * @returns {Promise<void>}
 */
async function filterByStatus( page, status ) {
	const statusMap = {
		all: 'All',
		published: 'Published',
		draft: 'Draft',
		scheduled: 'Scheduled',
		pending: 'Pending review',
		trash: 'Trash',
	};

	const label = statusMap[ status ];
	if ( ! label ) {
		throw new Error( `Unknown status filter: ${ status }. Expected one of: ${ Object.keys( statusMap ).join( ', ' ) }` );
	}

	// Match the label with optional count suffix (e.g., "Draft" or "Draft (1)")
	const link = page.getByRole( 'link', { name: new RegExp( `^${ label }( \\(\\d+\\))?$` ) } );
	await link.click();

	// Brief wait for filter to apply
	await page.waitForLoadState( 'networkidle' );
}

/**
 * Toggle between list and grid view modes.
 *
 * @param {Object} page - Playwright page object
 * @param {string} viewMode - View mode ('list' or 'grid')
 * @returns {Promise<void>}
 */
async function toggleListGridView( page, viewMode ) {
	const viewMap = {
		list: 'List View',
		grid: 'Grid View',
	};

	const label = viewMap[ viewMode ];
	if ( ! label ) {
		throw new Error( `Unknown view mode: ${ viewMode }. Expected one of: list, grid` );
	}

	const button = page.getByRole( 'button', { name: label } );
	await button.click();

	// Brief wait for view to change
	await page.waitForLoadState( 'networkidle' );
}

/**
 * Expand a collapsible section or panel.
 * Works for common WordPress patterns like meta boxes or panels with toggle buttons.
 *
 * @param {Object} page - Playwright page object
 * @param {string} sectionName - Name or label of the section to expand
 * @returns {Promise<void>}
 */
async function expandCollapsibleSection( page, sectionName ) {
	const button = page.getByRole( 'button', { name: sectionName } );
	const isExpanded = await button.getAttribute( 'aria-expanded' );

	if ( isExpanded === 'false' ) {
		await button.click();
		// Brief wait for expansion animation
		await page.waitForTimeout( 300 );
	}
}

/**
 * Close a collapsible section or panel.
 *
 * @param {Object} page - Playwright page object
 * @param {string} sectionName - Name or label of the section to collapse
 * @returns {Promise<void>}
 */
async function collapseCollapsibleSection( page, sectionName ) {
	const button = page.getByRole( 'button', { name: sectionName } );
	const isExpanded = await button.getAttribute( 'aria-expanded' );

	if ( isExpanded === 'true' ) {
		await button.click();
		// Brief wait for collapse animation
		await page.waitForTimeout( 300 );
	}
}

/**
 * Open a modal dialog by clicking its trigger button.
 *
 * @param {Object} page - Playwright page object
 * @param {string} triggerLabel - Label or text of the button that opens the modal
 * @returns {Promise<void>}
 */
async function openModal( page, triggerLabel ) {
	const button = page.getByRole( 'button', { name: triggerLabel } );
	await button.click();

	// Wait for modal to appear
	const modal = page.getByRole( 'dialog' ).first();
	await modal.waitFor( { state: 'visible' } );
}

/**
 * Close the current modal dialog (e.g., by clicking close button or Escape).
 *
 * @param {Object} page - Playwright page object
 * @param {string} [method='button'] - How to close: 'button' (click close button) or 'escape' (press Escape key)
 * @returns {Promise<void>}
 */
async function closeModal( page, method = 'button' ) {
	if ( method === 'escape' ) {
		await page.keyboard.press( 'Escape' );
	} else {
		// Try to find close button (common patterns: aria-label="Close", text "Close")
		const closeButton = page
			.getByRole( 'button', { name: /close/i } )
			.first();
		if ( await closeButton.isVisible() ) {
			await closeButton.click();
		} else {
			throw new Error( 'Could not find close button for modal' );
		}
	}

	// Wait for modal to disappear
	const modal = page.getByRole( 'dialog' ).first();
	await modal.waitFor( { state: 'hidden' } );
}

/**
 * Hover over an element to trigger hover state (e.g., reveal action buttons).
 *
 * @param {Object} page - Playwright page object
 * @param {string} selector - CSS selector or Playwright locator of element to hover
 * @returns {Promise<void>}
 */
async function hoverElement( page, selector ) {
	const locator = typeof selector === 'string' ? page.locator( selector ) : selector;
	await locator.hover();

	// Brief wait for hover state to render
	await page.waitForTimeout( 100 );
}

/**
 * Wait for a table to be fully rendered.
 * Useful for pages that load data dynamically.
 *
 * @param {Object} page - Playwright page object
 * @param {string} [tableSelector='table'] - CSS selector for the table
 * @returns {Promise<void>}
 */
async function waitForTableReady( page, tableSelector = 'table' ) {
	const table = page.locator( tableSelector );
	await table.waitFor( { state: 'visible' } );

	// Wait for rows to be present
	const rows = page.locator( `${ tableSelector } tbody tr` );
	await rows.first().waitFor( { state: 'visible' } );

	await page.waitForLoadState( 'networkidle' );
}

/**
 * Search or filter using a search input.
 *
 * @param {Object} page - Playwright page object
 * @param {string} searchText - Text to search for
 * @param {string} [searchSelector='input[type="search"]'] - Selector for search input
 * @returns {Promise<void>}
 */
async function search( page, searchText, searchSelector = 'input[type="search"]' ) {
	const input = page.locator( searchSelector );
	await input.fill( searchText );

	// Wait for search results to update
	await page.waitForLoadState( 'networkidle' );
}

module.exports = {
	filterByStatus,
	toggleListGridView,
	expandCollapsibleSection,
	collapseCollapsibleSection,
	openModal,
	closeModal,
	hoverElement,
	waitForTableReady,
	search,
};
