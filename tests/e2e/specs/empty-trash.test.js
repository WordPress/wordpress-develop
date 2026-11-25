/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import { adminNotice } from '../utils/admin';

test.describe( 'Empty Trash', () => {
	test( 'Empty Trash', async ( {
		admin,
		muPlugin,
		page,
		requestUtils,
	} ) => {
		// Empty Trash deletes every trashed post of one post type, so this test owns its type.
		// Post type names have at most 20 characters.
		const postType = `e2e_${ randomUUID().replaceAll( '-', '' ).slice( 0, 16 ) }`;
		// Other workers share the site, so only requests that name this type register it.
		await muPlugin.write(
			'empty-trash-post-type.php',
			`<?php
			if ( str_contains( $_SERVER['REQUEST_URI'] ?? '', '${ postType }' ) ) {
				add_action(
					'init',
					static function () {
						register_post_type(
							'${ postType }',
							array(
								'label'        => 'Empty Trash test',
								'show_ui'      => true,
								'show_in_menu' => false,
								'show_in_rest' => true,
								'rewrite'      => false,
							)
						);
					}
				);
			}`
		);

		const post = await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/${ postType }`,
			data: { title: 'Empty Trash test', status: 'publish' },
		} );

		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/${ postType }/${ post.id }`,
			} );

			await admin.visitAdminPage(
				'edit.php',
				new URLSearchParams( {
					post_type: postType,
					post_status: 'trash',
				} ).toString()
			);
			await page
				.getByRole( 'button', { name: 'Empty Trash' } )
				.first()
				.click();

			await expect( adminNotice( page ) ).toContainText(
				'1 post permanently deleted.'
			);
		} finally {
			await requestUtils
				.rest( {
					method: 'DELETE',
					path: `/wp/v2/${ postType }/${ post.id }`,
					params: { force: true },
				} )
				.catch( ( error ) => {
					if ( error.data?.status !== 404 ) {
						throw error;
					}
				} );
		}
	} );
} );
