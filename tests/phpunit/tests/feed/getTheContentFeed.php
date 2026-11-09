<?php
/**
 * Tests for get_the_content_feed().
 *
 * @group feed
 *
 * @covers ::get_the_content_feed
 */
class Tests_Feed_GetTheContentFeed extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_content' => 'Hello feed content',
			)
		);
	}

	/**
	 * Set the global post for content lookups.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['post'] = get_post( self::$post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_filtered_content_for_default_feed_type() {
		$filter = new MockAction();
		add_filter( 'the_content_feed', array( $filter, 'filter' ), 10, 2 );

		$this->assertSame( "<p>Hello feed content</p>\n", get_the_content_feed() );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( 'rss2', $filter->get_args()[0][1] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_escapes_cdata_end_marker() {
		add_filter(
			'the_content',
			static function () {
				return 'Before ]]> After';
			},
			999
		);

		$filter = new MockAction();
		add_filter( 'the_content_feed', array( $filter, 'filter' ), 10, 2 );

		$result = get_the_content_feed( 'rss2' );

		$this->assertSame( 'Before ]]&gt; After', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( 'Before ]]&gt; After', 'rss2' ),
			$filter->get_args()[0]
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_content_and_feed_type() {
		$filter = new MockAction();
		add_filter( 'the_content_feed', array( $filter, 'filter_append' ), 10, 2 );

		$result = get_the_content_feed( 'atom' );

		$this->assertSame( "<p>Hello feed content</p>\n_append", $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array( "<p>Hello feed content</p>\n", 'atom' ),
			$filter->get_args()[0]
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_null_feed_type_uses_default_feed() {
		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$filter = new MockAction();
		add_filter( 'the_content_feed', array( $filter, 'filter' ), 10, 2 );

		get_the_content_feed( null );

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( 'atom', $filter->get_args()[0][1] );
	}
}
