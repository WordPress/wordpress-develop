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
		use,
		testInfo
	) => {
		const utils = new CollaborationUtils( {
			admin,
			editor,
			requestUtils,
			page,
		} );

		/*
		 * Skip collaboration tests when the JS runtime is not available.
		 *
		 * The collaboration client-side code lives in Gutenberg and may not
		 * be bundled in every CI environment. Enable the setting, navigate
		 * to the editor, and check whether the runtime loaded.
		 */
		await utils.setCollaboration( true );
		await admin.visitAdminPage( 'post-new.php' );
		await page.waitForFunction( () => window?.wp?.data && window?.wp?.blocks, {
			timeout: 15000,
		} );
		const hasRuntime = await page.evaluate(
			() => !! window._wpCollaborationEnabled
		);
		if ( ! hasRuntime ) {
			testInfo.skip( true, 'Collaboration JS runtime is not available.' );
			return;
		}

		await requestUtils.createUser( SECOND_USER ).catch( ( error ) => {
			if ( error?.code !== 'existing_user_login' ) {
				throw error;
			}
		} );
		await requestUtils.createUser( THIRD_USER ).catch( ( error ) => {
			if ( error?.code !== 'existing_user_login' ) {
				throw error;
			}
		} );
		await use( utils );
		await utils.teardown();
	},
} );
