<?php
/**
 * Tests for using a custom post type archive as the home page.
 *
 * @group query
 * @ticket 19958
 *
 * @covers WP_Query::parse_query
 * @covers WP_Query::is_front_page
 * @covers ::get_home_page_post_types
 */
class Tests_Query_HomePageCpt extends WP_UnitTestCase {

	/**
	 * Post type that opts in to the home page list.
	 *
	 * @var string
	 */
	const ALLOWED = 'wptests_allowed';

	/**
	 * Post type that does not opt in to the home page list.
	 *
	 * @var string
	 */
	const NOT_ALLOWED = 'wptests_denied';

	public function set_up() {
		parent::set_up();

		register_post_type(
			self::ALLOWED,
			array(
				'public'                 => true,
				'show_in_home_page_list' => true,
			)
		);

		register_post_type(
			self::NOT_ALLOWED,
			array(
				'public'                 => true,
				'show_in_home_page_list' => false,
			)
		);
	}

	/**
	 * Creates one post of each type used by these tests.
	 */
	private function create_posts() {
		self::factory()->post->create();
		self::factory()->post->create( array( 'post_type' => self::ALLOWED ) );
		self::factory()->post->create( array( 'post_type' => self::NOT_ALLOWED ) );
	}

	/**
	 * Returns the unique post types of the posts in a query.
	 *
	 * @param WP_Query $query Query to inspect.
	 * @return string[] Post types found in the query results.
	 */
	private function get_result_post_types( WP_Query $query ) {
		return array_values( array_unique( wp_list_pluck( $query->posts, 'post_type' ) ) );
	}

	public function test_post_is_in_home_page_list_by_default() {
		$post_types = get_home_page_post_types();

		$this->assertContains( 'post', $post_types, 'The post post type should be in the home page list.' );
		$this->assertNotContains( 'page', $post_types, 'The page post type should not be in the home page list.' );
		$this->assertContains( self::ALLOWED, $post_types, 'The allowed post type should be in the home page list.' );
		$this->assertNotContains( self::NOT_ALLOWED, $post_types, 'The denied post type should not be in the home page list.' );
	}

	public function test_filter_cannot_add_pages_or_unregistered_post_types() {
		add_filter(
			'post_types_allowed_on_home_page',
			static function ( $post_types ) {
				return array_merge( $post_types, array( 'page', 'wptests_missing' ) );
			}
		);

		$post_types = get_home_page_post_types();

		$this->assertNotContains( 'page', $post_types, 'The page post type should be removed.' );
		$this->assertNotContains( 'wptests_missing', $post_types, 'An unregistered post type should be removed.' );
	}

	/**
	 * @covers ::sanitize_option
	 */
	public function test_show_archive_on_front_can_be_saved_as_allowed_post_type() {
		update_option( 'show_archive_on_front', self::ALLOWED );

		$this->assertSame( self::ALLOWED, get_option( 'show_archive_on_front' ) );
	}

	/**
	 * @dataProvider data_not_allowed_values
	 *
	 * @covers ::sanitize_option
	 *
	 * @param mixed $value Value that is not an allowed post type.
	 */
	public function test_show_archive_on_front_falls_back_to_post( $value ) {
		update_option( 'show_archive_on_front', self::ALLOWED );
		update_option( 'show_archive_on_front', $value );

		$this->assertSame( 'post', get_option( 'show_archive_on_front' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_not_allowed_values() {
		return array(
			'a denied post type'   => array( self::NOT_ALLOWED ),
			'the page post type'   => array( 'page' ),
			'an unregistered type' => array( 'wptests_missing' ),
			'no submitted value'   => array( null ),
		);
	}

	public function test_default_home_shows_posts() {
		$this->create_posts();

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_home(), 'The home page should be the blog home.' );
		$this->assertTrue( is_front_page(), 'The home page should be the front page.' );
		$this->assertSame( '', get_query_var( 'post_type' ), 'The post_type query var should not be set.' );
		$this->assertSame( array( 'post' ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_home_with_allowed_post_type_shows_that_post_type() {
		$this->create_posts();
		update_option( 'show_archive_on_front', self::ALLOWED );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_home(), 'The home page should be the blog home.' );
		$this->assertTrue( is_front_page(), 'The home page should be the front page.' );
		$this->assertSame( self::ALLOWED, get_query_var( 'post_type' ) );
		$this->assertSame( array( self::ALLOWED ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_second_home_page_shows_allowed_post_type() {
		$this->create_posts();
		$this->create_posts();
		update_option( 'posts_per_page', 1 );
		update_option( 'show_archive_on_front', self::ALLOWED );

		$this->go_to( home_url( '/?paged=2' ) );

		$this->assertTrue( is_home(), 'The second page should be the blog home.' );
		$this->assertSame( array( self::ALLOWED ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_post_types_allowed_on_home_page_filter_can_add_post_type() {
		$this->create_posts();

		add_filter(
			'post_types_allowed_on_home_page',
			static function ( $post_types ) {
				$post_types[] = self::NOT_ALLOWED;
				return $post_types;
			}
		);

		update_option( 'show_archive_on_front', self::NOT_ALLOWED );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_front_page(), 'The home page should be the front page.' );
		$this->assertSame( self::NOT_ALLOWED, get_query_var( 'post_type' ) );
		$this->assertSame( array( self::NOT_ALLOWED ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_post_types_allowed_on_home_page_filter_can_remove_post_type() {
		$this->create_posts();
		update_option( 'show_archive_on_front', self::ALLOWED );

		add_filter(
			'post_types_allowed_on_home_page',
			static function ( $post_types ) {
				return array_diff( $post_types, array( self::ALLOWED ) );
			}
		);

		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', get_query_var( 'post_type' ), 'The post_type query var should not be set.' );
		$this->assertSame( array( 'post' ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_explicit_post_type_query_var_is_not_overridden() {
		$this->create_posts();
		update_option( 'show_archive_on_front', self::ALLOWED );

		$this->go_to( home_url( '/?post_type=post' ) );

		$this->assertSame( 'post', get_query_var( 'post_type' ) );
		$this->assertSame( array( 'post' ), $this->get_result_post_types( $GLOBALS['wp_query'] ) );
	}

	public function test_secondary_query_without_post_type_still_returns_posts() {
		$this->create_posts();
		update_option( 'show_archive_on_front', self::ALLOWED );

		// Same shape as the query used by the Recent Posts widget.
		$query = new WP_Query( array( 'posts_per_page' => 5 ) );

		$this->assertSame( array( 'post' ), $this->get_result_post_types( $query ) );
	}

	public function test_static_front_page_is_not_affected() {
		$this->create_posts();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_archive_on_front', self::ALLOWED );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_front_page(), 'The static page should be the front page.' );
		$this->assertTrue( is_page( $page_id ), 'The static page should be queried.' );
		$this->assertFalse( is_home(), 'The static front page should not be the blog home.' );
	}
}
