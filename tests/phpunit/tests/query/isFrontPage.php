<?php
/**
 * Tests for the is_front_page() conditional.
 *
 * @group query
 *
 * @ticket 37653
 */
class Tests_Query_IsFrontPage extends WP_UnitTestCase {

	/**
	 * ID of the page used as the static front page.
	 *
	 * @var int
	 */
	private static $front_page_id;

	/**
	 * ID of a page that is not the front page.
	 *
	 * @var int
	 */
	private static $other_page_id;

	/**
	 * Creates the pages used by these tests.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$front_page_id = $factory->post->create( array( 'post_type' => 'page' ) );
		self::$other_page_id = $factory->post->create( array( 'post_type' => 'page' ) );
	}

	/**
	 * Sets up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	/**
	 * Restores the Reading settings touched by these tests.
	 */
	public function tear_down() {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );

		parent::tear_down();
	}

	/**
	 * Returns a WP_Query instance whose query has never been parsed.
	 *
	 * `new WP_Query()` with no arguments skips `query()`, so `parse_query()` never runs and
	 * none of the `is_*` flags are populated. This is the state the Customizer is in when it
	 * evaluates an `active_callback` for a section or control.
	 *
	 * @return WP_Query Unparsed query object.
	 */
	private function get_unparsed_query() {
		$query = new WP_Query();

		// Guard the premise: an unparsed query has no flags set.
		$this->assertFalse( $query->is_home(), 'Expected a WP_Query() with no arguments to be unparsed.' );

		return $query;
	}

	/**
	 * Returns a WP_Query instance built by parsing the given query vars.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Query Parsed query object.
	 */
	private function get_parsed_query( array $args ) {
		$query = new WP_Query();
		$query->query( $args );

		return $query;
	}

	/**
	 * Sets the Reading settings to use a static front page.
	 *
	 * @param int|string $page_on_front ID of the page to use as the front page. Pass an empty
	 *                                  value to simulate "no page selected".
	 */
	private function set_static_front_page( $page_on_front ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_on_front );
	}

	/**
	 * An unparsed query is the front page when a static front page is selected.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_true_for_unparsed_query_with_static_front_page() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_unparsed_query();

		$this->assertTrue( $query->is_front_page() );
	}

	/**
	 * An unparsed query is not the front page when the front page shows the blog posts.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_false_for_unparsed_query_with_posts_on_front() {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );

		$query = $this->get_unparsed_query();

		$this->assertFalse( $query->is_front_page() );
	}

	/**
	 * An unparsed query is the front page when 'show_on_front' is 'page' but no page is set.
	 *
	 * This is the "and no page is selected" case from the ticket summary, which is the
	 * intermediate state the Customizer can put the site in.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_true_for_unparsed_query_with_empty_page_on_front() {
		$this->set_static_front_page( 0 );

		$query = $this->get_unparsed_query();

		$this->assertTrue( $query->is_front_page() );
	}

	/**
	 * A parsed query for the static front page is still the front page.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_true_for_parsed_query_of_the_static_front_page() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_parsed_query( array( 'page_id' => self::$front_page_id ) );

		$this->assertTrue( $query->is_front_page() );
		$this->assertTrue( $query->is_page() );
		$this->assertFalse( $query->is_home() );
	}

	/**
	 * A parsed query for a different page is not the front page.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_false_for_parsed_query_of_a_different_page() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_parsed_query( array( 'page_id' => self::$other_page_id ) );

		$this->assertFalse( $query->is_front_page() );
		$this->assertTrue( $query->is_page() );
	}

	/**
	 * A parsed query with an unresolvable queried object is not the front page.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_false_for_parsed_query_with_missing_queried_object() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_parsed_query( array( 'page_id' => self::$front_page_id + 1000 ) );

		$this->assertFalse( $query->is_front_page() );
	}

	/**
	 * A parsed non-front-page query is not the front page when the front page shows posts.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_false_for_parsed_non_front_page_query() {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );

		$query = $this->get_parsed_query( array( 'page_id' => self::$other_page_id ) );

		$this->assertFalse( $query->is_front_page() );
		$this->assertFalse( $query->is_home() );
	}

	/**
	 * A parsed query with an empty query string resolves the static front page.
	 *
	 * This preserves the existing `parse_query()` behaviour for a legitimately parsed query
	 * whose internal query string is empty.
	 *
	 * @ticket 37653
	 */
	public function test_is_front_page_should_be_true_for_parsed_empty_query_with_static_front_page() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_parsed_query( array() );

		$this->assertTrue( $query->is_front_page() );
		$this->assertTrue( $query->is_page() );
		$this->assertFalse( $query->is_home() );
	}

	/**
	 * A parsed request for the site root reports the expected conditionals.
	 *
	 * @dataProvider data_parsed_root_requests
	 *
	 * @param string $show_on_front Value of the 'show_on_front' option.
	 * @param bool   $is_front_page  Expected result of is_front_page().
	 * @param bool   $is_home        Expected result of is_home().
	 *
	 * @ticket 37653
	 */
	public function test_parsed_root_request( $show_on_front, $is_front_page, $is_home ) {
		update_option( 'show_on_front', $show_on_front );
		update_option( 'page_on_front', 'page' === $show_on_front ? self::$front_page_id : 0 );

		$this->go_to( '/' );

		$this->assertSame( $is_front_page, is_front_page() );
		$this->assertSame( $is_home, is_home() );
	}

	/**
	 * Data provider for test_parsed_root_request().
	 *
	 * @return array[] Test parameters.
	 */
	public function data_parsed_root_requests() {
		return array(
			'posts on front'    => array(
				'show_on_front' => 'posts',
				'is_front_page' => true,
				'is_home'       => true,
			),
			'static front page' => array(
				'show_on_front' => 'page',
				'is_front_page' => true,
				'is_home'       => false,
			),
		);
	}

	/**
	 * An unparsed query does not report page conditionals it cannot back up.
	 *
	 * The fix to is_front_page() deliberately leaves is_page() alone, because an unparsed
	 * query has no queried object to match against.
	 *
	 * @ticket 37653
	 */
	public function test_is_page_should_be_false_for_unparsed_query_with_static_front_page() {
		$this->set_static_front_page( self::$front_page_id );

		$query = $this->get_unparsed_query();

		$this->assertFalse( $query->is_page() );
		$this->assertFalse( $query->is_page( self::$front_page_id ) );
		$this->assertFalse( $query->is_home() );
	}
}
