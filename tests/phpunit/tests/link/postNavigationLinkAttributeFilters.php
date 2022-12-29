<?php

/**
 * Test post navigation link attribute filters.
 * @ticket 55751
 * @since 6.2.0
 *
 * @covers ::next_posts_link_attributes
 * @covers ::previous_posts_link_attributes
 */
class Tests_Link_PostNavigationLinkAttributeFilters extends WP_UnitTestCase {

	/**
	 * Create posts for the tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
		global $wp_query, $paged;
		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => $paged,
			)
		);
	}

	/**
	 * Test that the 'next_posts_link_attributes' filter is applied correctly.
	 */
	public function test_next_posts_link_attribute() {
		$expected = "data-attribute='next'";
		add_filter(
			'next_posts_link_attributes',
			function() use ( $expected ) {
				return $expected;
			}
		);

		$next_posts_link = get_next_posts_link();
		$this->assertStringContainsString( $expected, $next_posts_link );
	}

	/**
	 * Test that the 'previous_posts_link_attributes' filter is applied correctly.
	 */
	public function test_previous_posts_link_attributes() {
		$expected = "data-attribute='previous'";
		add_filter(
			'previous_posts_link_attributes',
			function() use ( $expected ) {
				return $expected;
			}
		);

		$previous_posts_link = get_previous_posts_link();
		$this->assertStringContainsString( $expected, $previous_posts_link );
	}

}
