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
	public function test_posts_use_closed_default_comment_status_option() {
		update_option( 'default_comment_status', 'closed' );

		$this->assertSame( 'closed', get_default_comment_status( 'post' ) );
		$this->assertSame( 'closed', get_default_comment_status() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_posts_use_closed_default_ping_status_option() {
		update_option( 'default_ping_status', 'closed' );

		$this->assertSame( 'closed', get_default_comment_status( 'post', 'pingback' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_supported_custom_post_type_uses_opposed_comment_and_ping_options() {
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
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_status_post_type_and_comment_type() {
		$filter = new MockAction();
		add_filter( 'get_default_comment_status', array( $filter, 'filter_append' ), 10, 3 );

		update_option( 'default_comment_status', 'open' );

		$result = get_default_comment_status( 'post', 'comment' );

		$this->assertSame( 'open_append', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'open', 'post', 'comment' ), $filter->get_args()[0] );
	}
}
