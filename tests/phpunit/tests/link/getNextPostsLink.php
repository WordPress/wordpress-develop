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
	 * Whether the pagination global was set before the test.
	 *
	 * @var bool
	 */
	private $had_paged;

	/**
	 * The original pagination global.
	 *
	 * @var mixed
	 */
	private $original_paged;

	/**
	 * Creates posts before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}

	/**
	 * Saves the incoming pagination global before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->had_paged      = array_key_exists( 'paged', $GLOBALS );
		$this->original_paged = $this->had_paged ? $GLOBALS['paged'] : null;

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
	 * Restores the incoming pagination global after each test.
	 */
	public function tear_down() {
		if ( $this->had_paged ) {
			$GLOBALS['paged'] = $this->original_paged;
		} else {
			unset( $GLOBALS['paged'] );
		}

		parent::tear_down();
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
		$paged     = 2;

		$expected = get_next_posts_link( 'Next Page', 3 );
		$actual   = get_echo( 'next_posts_link', array( 'Next Page', 3 ) );

		$paged = $old_paged;

		$this->assertNotEmpty( $actual );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that next_posts_link() outputs nothing when on the last allowed page.
	 *
	 * @ticket 65819
	 */
	public function test_next_posts_link_outputs_nothing_on_last_page() {
		global $paged;

		$old_paged = $paged;
		$paged     = 2;

		$actual = get_echo( 'next_posts_link', array( 'Next Page', 2 ) );

		$paged = $old_paged;

		$this->assertSame( '', $actual );
	}
}
