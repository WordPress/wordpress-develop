<?php

/**
 * Tests the `get_next_posts_link()` function.
 *
 * @since 6.2.0
 *
 * @group link
 *
 * @covers ::get_next_posts_link
 * @covers ::next_posts_link
 */
class Tests_Link_GetNextPostsLink extends WP_UnitTestCase {

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
	public function test_get_next_posts_link_should_apply_next_posts_link_attributes_filter() {
		$filter = new MockAction();
		add_filter( 'next_posts_link_attributes', array( &$filter, 'filter' ) );

		get_next_posts_link();

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that next_posts_link() echoes get_next_posts_link().
	 *
	 * @ticket 65819
	 */
	public function test_next_posts_link_echoes_get_next_posts_link() {
		global $paged;

		$old_paged = $paged;
		$paged     = 1;

		$expected = get_next_posts_link( 'Next Page', 3 );
		$actual   = get_echo( 'next_posts_link', array( 'Next Page', 3 ) );

		$paged = $old_paged;

		$this->assertNotEmpty( $actual );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that next_posts_link() outputs nothing when on the last page.
	 *
	 * @ticket 65819
	 */
	public function test_next_posts_link_outputs_nothing_on_last_page() {
		global $paged;

		$old_paged = $paged;
		$paged     = 3;

		$actual = get_echo( 'next_posts_link', array( 'Next Page', 3 ) );

		$paged = $old_paged;

		$this->assertSame( '', $actual );
	}
}
