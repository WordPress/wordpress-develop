/**
 * WordPress dependencies
 */
import { test as base } from '@wordpress/e2e-test-utils-playwright';
export { expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import CollaborationUtils, { SECOND_USER, THIRD_USER } from './collaboration-utils';

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
