<?php

/**
 * Tests post navigation link attribute filters.
 *
 * @since 6.2.0
 *
 * @group link
 *
 * @covers ::next_posts_link_attributes
 * @covers ::previous_posts_link_attributes
 */
class Tests_Link_PostNavigationLinkAttributeFilters extends WP_UnitTestCase {

	/**
	 * Creates posts before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wp_query, $paged;

		$factory->post->create_many( 3 );
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
	 * Tests that the 'next_posts_link_attributes' filter is applied correctly.
	 *
	 * @ticket 55751
	 */
	public function test_next_posts_link_attribute() {
		$expected = "data-attribute='next'";
		add_filter(
			'next_posts_link_attributes',
			function() use ( $expected ) {
				return $expected;
			}
		);

		$this->assertStringContainsString( $expected, get_next_posts_link() );
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

		$this->assertStringContainsString( $expected, get_previous_posts_link() );
	}

}
