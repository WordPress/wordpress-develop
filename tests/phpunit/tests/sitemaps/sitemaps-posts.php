<?php

/**
 * @group sitemaps
 */
class Test_WP_Sitemaps_Posts extends WP_UnitTestCase {
	/**
	 * Test ability to filter object subtypes.
	 */
	public function test_filter_sitemaps_post_types() {
		$posts_provider = new WP_Sitemaps_Posts();

		// Return an empty array to show that the list of subtypes is filterable.
		add_filter( 'wp_sitemaps_post_types', '__return_empty_array' );
		$subtypes = $posts_provider->get_object_subtypes();

		$this->assertEquals( array(), $subtypes, 'Could not filter posts subtypes.' );
	}
}
