<?php
/**
 * Tests for get_default_comment_status().
 *
 * @group comment
 *
 * @covers ::get_default_comment_status
 */
class Tests_Comment_GetDefaultCommentStatus extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_pages_are_closed_for_comments() {
		update_option( 'default_comment_status', 'open' );

		$this->assertSame( 'closed', get_default_comment_status( 'page' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_pages_are_closed_for_pingbacks() {
		update_option( 'default_ping_status', 'open' );

		$this->assertSame( 'closed', get_default_comment_status( 'page', 'pingback' ) );
		$this->assertSame( 'closed', get_default_comment_status( 'page', 'trackback' ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_posts_use_default_comment_status_option
	 *
	 * @param string $option_value Option value for default_comment_status.
	 * @param string $expected     Expected status.
	 */
	public function test_posts_use_default_comment_status_option( $option_value, $expected ) {
		update_option( 'default_comment_status', $option_value );

		$this->assertSame( $expected, get_default_comment_status( 'post' ) );
		$this->assertSame( $expected, get_default_comment_status() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_posts_use_default_comment_status_option() {
		return array(
			'open'   => array( 'open', 'open' ),
			'closed' => array( 'closed', 'closed' ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_posts_use_default_ping_status_option
	 *
	 * @param string $comment_type Ping comment type.
	 * @param string $option_value Option value for default_ping_status.
	 * @param string $expected     Expected status.
	 */
	public function test_posts_use_default_ping_status_option( $comment_type, $option_value, $expected ) {
		update_option( 'default_ping_status', $option_value );

		$this->assertSame( $expected, get_default_comment_status( 'post', $comment_type ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_posts_use_default_ping_status_option() {
		return array(
			'pingback open'    => array( 'pingback', 'open', 'open' ),
			'pingback closed'  => array( 'pingback', 'closed', 'closed' ),
			'trackback open'   => array( 'trackback', 'open', 'open' ),
			'trackback closed' => array( 'trackback', 'closed', 'closed' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_unsupported_post_type_is_closed() {
		register_post_type(
			'no_comments_cpt',
			array(
				'public'   => true,
				'supports' => array( 'title' ),
			)
		);

		update_option( 'default_comment_status', 'open' );
		update_option( 'default_ping_status', 'open' );

		$this->assertSame( 'closed', get_default_comment_status( 'no_comments_cpt' ) );
		$this->assertSame( 'closed', get_default_comment_status( 'no_comments_cpt', 'pingback' ) );

		unregister_post_type( 'no_comments_cpt' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_supported_custom_post_type_uses_options() {
		register_post_type(
			'with_comments_cpt',
			array(
				'public'   => true,
				'supports' => array( 'comments', 'trackbacks' ),
			)
		);

		update_option( 'default_comment_status', 'open' );
		update_option( 'default_ping_status', 'closed' );

		$this->assertSame( 'open', get_default_comment_status( 'with_comments_cpt' ) );
		$this->assertSame( 'closed', get_default_comment_status( 'with_comments_cpt', 'trackback' ) );

		unregister_post_type( 'with_comments_cpt' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_status_post_type_and_comment_type() {
		$filter = new MockAction();
		add_filter( 'get_default_comment_status', array( $filter, 'filter' ), 10, 3 );

		update_option( 'default_comment_status', 'open' );

		$result = get_default_comment_status( 'post', 'comment' );

		$this->assertSame( 'open', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'open', 'post', 'comment' ), $filter->get_args()[0] );
	}
}
