/**
 * Collaboration E2E test fixtures.
 *
 * Extends the base Playwright test with a `collaborationUtils` fixture
 * that provisions three users and enables real-time collaboration.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * WordPress dependencies
 */
import { test as base } from '@wordpress/e2e-test-utils-playwright';
export { expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import CollaborationUtils, { SECOND_USER, THIRD_USER, SYNC_TIMEOUT } from './collaboration-utils';
export { SYNC_TIMEOUT };

export const test = base.extend( {
	collaborationUtils: async (
		{ admin, editor, requestUtils, page },
		use
	) => {
		const utils = new CollaborationUtils( {
			admin,
			editor,
			requestUtils,
			page,
		} );
		await utils.setCollaboration( true );
		await requestUtils.createUser( SECOND_USER );
		await requestUtils.createUser( THIRD_USER );
		await use( utils );
		await utils.teardown();
	},
} );
