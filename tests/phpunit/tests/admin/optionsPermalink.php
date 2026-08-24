<?php

/**
 * Tests for wp-admin/options-permalink.php
 *
 * @group admin
 * @group rewrite
 */
class Tests_Admin_OptionsPermalink extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
	}

	public function tear_down() {
		global $wp_rewrite;

		$wp_rewrite->set_category_base( '' );
		$wp_rewrite->set_tag_base( '' );
		$wp_rewrite->flush_rules();

		parent::tear_down();
	}

	/**
	 * Data provider for base sanitization tests.
	 */
	public function data_base_sanitization() {
		return array(
			array( 'Foo Bar', '/foo-bar' ),
			array( 'Foo & Bar!', '/foo-bar' ),
			array( 'Foo Bar/Baz Qux', '/foo-bar/baz-qux' ),
			array( '', '' ),
			array( '/Foo Bar', '/foo-bar' ),
			array( 'Multiple/Slashes', '/multiple/slashes' ),
		);
	}

	/**
	 * Test category and tag base sanitization.
	 *
	 * @ticket 16839
	 * @dataProvider data_base_sanitization
	 */
	public function test_base_sanitization( $input, $expected ) {
		$base   = ltrim( $input, '/' );
		$result = empty( $base ) ? '' : '/' . implode( '/', array_map( 'sanitize_title_with_dashes', preg_split( '|/+|', $base ) ) );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for link generation with custom bases.
	 */
	public function data_category_base_links() {
		return array(
			'latin with space'      => array( 'Foo Bar', 'news' ),
			'hierarchical segments' => array( 'Foo Bar/Baz Qux', 'updates' ),
		);
	}

	/**
	 * Ensure custom category bases are slugified and produce working archive links.
	 *
	 * @ticket 16839
	 * @dataProvider data_category_base_links
	 *
	 * @param string $raw_base      User-entered base.
	 * @param string $category_slug Category slug.
	 */
	public function test_category_base_generates_pretty_links( $raw_base, $category_slug ) {
		global $wp_rewrite;

		$sanitized_base = $this->sanitize_base_from_settings( $raw_base );

		$this->apply_category_base_and_flush( $sanitized_base );

		$cat_id = self::factory()->category->create( array( 'slug' => $category_slug ) );

		$category_link = get_category_link( $cat_id );

		$this->assertStringContainsString( $sanitized_base . '/' . $category_slug . '/', $category_link );

		$this->go_to( $category_link );
		$this->assertQueryTrue( 'is_category', 'is_archive' );
		$this->assertSame( $cat_id, get_queried_object_id() );
	}

	/**
	 * Mirrors the sanitization logic used in wp-admin/options-permalink.php.
	 *
	 * @param string $raw_base Raw user input.
	 * @return string Sanitized base with leading slash or empty string.
	 */
	private function sanitize_base_from_settings( $raw_base ) {
		$base = ltrim( $raw_base, '/' );

		return empty( $base ) ? '' : '/' . implode( '/', array_map( 'sanitize_title_with_dashes', preg_split( '|/+|', $base ) ) );
	}

	/**
	 * Applies the category base option and refreshes taxonomy rewrites.
	 *
	 * @param string $sanitized_base Base to set (leading slash or empty).
	 */
	private function apply_category_base_and_flush( $sanitized_base ) {
		global $wp_rewrite;

		$wp_rewrite->set_category_base( $sanitized_base );

		// Re-register taxonomies and ensure permalink structure stays active before flushing.
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}
}
