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
