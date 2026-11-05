<?php

/**
 * Tests for WP_Rewrite::set_tag_base().
 *
 * @group rewrite
 *
 * @covers WP_Rewrite::set_tag_base
 */
class Tests_Rewrite_SetTagBase extends WP_UnitTestCase {

	/**
	 * Tests that the option is updated when the base changes.
	 *
	 * @ticket 66496
	 */
	public function test_should_update_the_option_when_the_base_changes() {
		update_option( 'tag_base', 'labels' );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->set_tag_base( 'keywords' );

		$this->assertSame( 'keywords', get_option( 'tag_base' ) );
	}

	/**
	 * Tests that the object is initialized again when the base changes.
	 *
	 * @ticket 66496
	 */
	public function test_should_initialize_the_object_again_when_the_base_changes() {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'tag_base', 'labels' );

		$wp_rewrite                      = new WP_Rewrite();
		$wp_rewrite->permalink_structure = '/a-structure-that-is-not-the-option/';

		$wp_rewrite->set_tag_base( 'keywords' );

		$this->assertSame( '/%postname%/', $wp_rewrite->permalink_structure, 'The structure should be read from the option again.' );
	}

	/**
	 * Tests that nothing happens when the base is the same.
	 *
	 * @ticket 66496
	 */
	public function test_should_do_nothing_when_the_base_does_not_change() {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'tag_base', 'labels' );

		$wp_rewrite                      = new WP_Rewrite();
		$wp_rewrite->permalink_structure = '/a-structure-that-is-not-the-option/';

		$wp_rewrite->set_tag_base( 'labels' );

		$this->assertSame( 'labels', get_option( 'tag_base' ) );
		$this->assertSame( '/a-structure-that-is-not-the-option/', $wp_rewrite->permalink_structure, 'The object should not be initialized again.' );
	}

	/**
	 * Tests that the option does not change the category base.
	 *
	 * @ticket 66496
	 */
	public function test_should_not_change_the_category_base() {
		update_option( 'category_base', 'topics' );

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->set_tag_base( 'keywords' );

		$this->assertSame( 'topics', get_option( 'category_base' ) );
	}

	/**
	 * Tests that the base is used in the permalinks of the tags.
	 *
	 * @ticket 66496
	 */
	public function test_should_change_the_permalinks_of_tags() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		$tag_id = self::factory()->tag->create( array( 'slug' => 'wordpress' ) );

		$wp_rewrite->set_tag_base( 'keywords' );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();

		$this->assertStringContainsString( '/keywords/wordpress/', get_tag_link( $tag_id ) );
	}
}
