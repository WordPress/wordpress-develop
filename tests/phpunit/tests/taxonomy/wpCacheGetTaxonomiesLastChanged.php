<?php

/**
 * @group taxonomy
 */
class Tests_WP_Cache_Get_Taxonomies_Last_Changed extends WP_UnitTestCase {

	public function test_wp_cache_get_taxonomy_last_changed_empty() {
		$last_changed = wp_cache_get_last_changed( 'terms' );
		$this->assertSame( $last_changed, wp_cache_get_taxonomies_last_changed( array() ) );
	}

	public function test_wp_cache_get_taxonomy_last_changed_empty_string() {
		$last_changed = (string) floatval( wp_cache_get_last_changed( 'terms' ) );
		$this->assertSame( $last_changed, wp_cache_get_taxonomies_last_changed( array( '' ) ) );
	}

	public function test_wp_cache_get_taxonomy_last_changed() {
		$last_changed = (string) floatval( wp_cache_get_taxonomy_last_changed( 'post_tag' ) );
		$this->assertSame( $last_changed, wp_cache_get_taxonomies_last_changed( array( 'post_tag' ) ) );
	}

	public function test_wp_cache_get_taxonomy_last_changed_duplicate() {
		$last_changed = (string) floatval( wp_cache_get_taxonomy_last_changed( 'post_tag' ) );
		$this->assertSame( $last_changed, wp_cache_get_taxonomies_last_changed( array( 'post_tag', 'post_tag', 'post_tag' ) ) );
	}
}
