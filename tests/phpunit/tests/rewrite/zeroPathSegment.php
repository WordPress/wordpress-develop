<?php

/**
 * Tests for requests whose path segment is the string "0".
 *
 * "0" is falsy in PHP, so code that guards on `empty()` or truthiness treats
 * a legitimate "0" path segment as if no path had been requested at all.
 *
 * @group rewrite
 * @group query
 * @ticket 57858
 */
class Tests_Rewrite_ZeroPathSegment extends WP_UnitTestCase {

	/**
	 * Sets a permalink structure and rebuilds the rules with the taxonomy
	 * rewrite tags registered.
	 *
	 * `WP_Rewrite::init()` resets the registered rewrite tags to the built-in
	 * defaults, which do not include `%category%`. The taxonomies have to be
	 * re-registered and the rules flushed again, otherwise `%category%` is
	 * never substituted into the generated rules.
	 *
	 * @param string $structure Permalink structure to set.
	 */
	private function set_category_permalink_structure( $structure ) {
		global $wp_rewrite;

		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}

	/**
	 * A "0" path segment should not silently fall through to the front page.
	 *
	 * @ticket 57858
	 */
	public function test_zero_path_segment_should_404_when_no_matching_category_exists() {
		$this->set_category_permalink_structure( '/%category%/%postname%/' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( home_url( '/0/' ) );

		$this->assertQueryTrue( 'is_404' );
	}

	/**
	 * A deeper path ending in "0" should 404 rather than resolve to an archive.
	 *
	 * @ticket 57858
	 */
	public function test_nested_zero_path_segment_should_404() {
		$this->set_category_permalink_structure( '/%category%/%postname%/' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( home_url( '/wp-content/0/' ) );

		$this->assertTrue( is_404() );
		$this->assertFalse( is_home() );
	}

	/**
	 * A category whose slug is "0" should still resolve to its archive.
	 *
	 * This guards against fixing the 404 by ignoring "0" outright.
	 *
	 * @ticket 57858
	 */
	public function test_category_with_zero_slug_should_resolve_to_its_archive() {
		global $wpdb;

		$this->set_category_permalink_structure( '/%category%/%postname%/' );

		$category_id = self::factory()->category->create( array( 'name' => 'Zero' ) );

		/*
		 * The slug has to be forced directly, as `wp_insert_term()` discards a
		 * slug of "0" and falls back to one derived from the name.
		 */
		$wpdb->update( $wpdb->terms, array( 'slug' => '0' ), array( 'term_id' => $category_id ) );
		clean_term_cache( $category_id, 'category' );

		self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $category_id ),
			)
		);

		$this->go_to( home_url( '/0/' ) );

		$this->assertQueryTrue( 'is_archive', 'is_category' );
		$this->assertSame( $category_id, get_queried_object_id() );
	}

	/**
	 * The front page must keep working, since an empty request is also falsy.
	 *
	 * @ticket 57858
	 */
	public function test_empty_request_still_resolves_to_the_front_page() {
		$this->set_category_permalink_structure( '/%category%/%postname%/' );

		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( home_url( '/' ) );

		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}
}
