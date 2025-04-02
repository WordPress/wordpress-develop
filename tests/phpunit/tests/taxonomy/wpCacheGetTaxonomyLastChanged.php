<?php

/**
 * @group taxonomy
 */
class Tests_WP_Cache_Get_Taxonomy_Last_Changed extends WP_UnitTestCase {

	public function test_wp_cache_get_taxonomy_last_changed_empty() {
		$last_changed = wp_cache_get_last_changed( 'terms' );
		$this->assertSame( $last_changed, wp_cache_get_taxonomy_last_changed( '' ) );
	}

	public function test_wp_cache_get_taxonomy_last_changed() {
		$last_changed = wp_cache_get_taxonomy_last_changed( 'post_tag' );
		$this->assertSame( $last_changed, wp_cache_get_taxonomy_last_changed( 'post_tag' ) );
	}
}
