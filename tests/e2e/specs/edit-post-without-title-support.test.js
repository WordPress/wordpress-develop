/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

const POST_TYPES_MU_PLUGIN = `<?php
add_action(
	'init',
	function () {
		register_post_type(
			'no_title_cpt',
			array(
				'public'       => true,
				'label'        => 'No Title CPT',
				'supports'     => array( 'editor' ),
				'show_in_rest' => false,
			)
		);
		register_post_type(
			'with_title_cpt',
			array(
				'public'       => true,
				'label'        => 'With Title CPT',
				'supports'     => array( 'title', 'editor' ),
				'show_in_rest' => false,
			)
		);
	}
);`;

test.describe( 'Edit post without title support', () => {
	test.beforeEach( async ( { muPlugin } ) => {
		await muPlugin.write(
			'post-types-title-support.php',
			POST_TYPES_MU_PLUGIN
		);
	} );

	test( 'shows the permalink for a published post of a type that does not support titles', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'post-new.php', 'post_type=no_title_cpt' );
		await expect( page.locator( '#title' ) ).toHaveCount( 0 );

		await page.locator( '#publish' ).click();
		await expect( page.locator( '#message.updated' ) ).toBeVisible();

		const slugBox = page.locator( '#edit-slug-box' );
		await expect( slugBox ).toBeVisible();
		await expect( slugBox ).toContainText( 'Permalink:' );
		await expect(
			slugBox.locator( '#sample-permalink a' )
		).toHaveAttribute( 'href', /no_title_cpt/ );
	} );

	test( 'still shows the title field and permalink for post types that support titles', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'post-new.php',
			'post_type=with_title_cpt'
		);
		await expect( page.locator( '#title' ) ).toBeVisible();

		await page.locator( '#title' ).fill( 'Post with a title' );
		await page.locator( '#publish' ).click();
		await expect( page.locator( '#message.updated' ) ).toBeVisible();

		const slugBox = page.locator( '#edit-slug-box' );
		await expect( slugBox ).toBeVisible();
		await expect(
			slugBox.locator( '#sample-permalink a' )
		).toHaveAttribute( 'href', /post-with-a-title/ );
	} );
} );
