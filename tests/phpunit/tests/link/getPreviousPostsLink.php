<?php

/**
 * Tests the `get_previous_posts_link()` function.
 *
 * @since 6.2.0
 *
 * @group link
 *
 * @covers ::get_previous_posts_link
 * @covers ::previous_posts_link
 */
class Tests_Link_GetPreviousPostsLink extends WP_UnitTestCase {

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
	 * Tests that the 'previous_posts_link_attributes' filter is applied correctly.
	 *
	 * @ticket 55751
	 */
	public function test_get_previous_posts_link_should_apply_previous_posts_link_attributes_filter() {
		$filter = new MockAction();
		add_filter( 'previous_posts_link_attributes', array( &$filter, 'filter' ) );

		get_previous_posts_link();

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that previous_posts_link() echoes get_previous_posts_link().
	 *
	 * @ticket 65819
	 */
	public function test_previous_posts_link_echoes_get_previous_posts_link() {
		global $paged;

		$old_paged = $paged;
		$paged     = 2;

		$expected = get_previous_posts_link( 'Previous Page' );
		$actual   = get_echo( 'previous_posts_link', array( 'Previous Page' ) );

		$paged = $old_paged;

		$this->assertNotEmpty( $actual );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that previous_posts_link() outputs nothing when on the first page.
	 *
	 * @ticket 65819
	 */
	public function test_previous_posts_link_outputs_nothing_on_first_page() {
		global $paged;

		$old_paged = $paged;
		$paged     = 1;

		$actual = get_echo( 'previous_posts_link', array( 'Previous Page' ) );

		$paged = $old_paged;

		$this->assertSame( '', $actual );
	}
}
