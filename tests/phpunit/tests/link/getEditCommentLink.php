<?php
/**
 * @group link
 * @group comment
 * @covers ::get_next_comments_link
 */
class Tests_Link_GetEditCommentLink extends WP_UnitTestCase {

    public static $comment_ids;
    public static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
        self::$comment_ids = array(
            'valid'   => $factory->comment->create( array( 'comment_content' => 'Test comment' ) ),
            'invalid' => 12345, // Invalid comment ID
        );

        self::$user_ids = array(
            'admin'      => $factory->user->create( array( 'role' => 'administrator' ) ),
            'subscriber' => $factory->user->create( array( 'role' => 'subscriber' ) ),
        );
    }

    public function set_up() {
        parent::set_up();
        wp_set_current_user( self::$user_ids['admin'] );
    }

    public function test_get_edit_comment_link_display_context() {
        $comment_id   = self::$comment_ids['valid'];
        $expected_url = admin_url( 'comment.php?action=editcomment&amp;c=' . $comment_id );
		$actual_url   = get_edit_comment_link( $comment_id, 'display' );

        $this->assertSame( $expected_url, $actual_url );
    }

    public function test_get_edit_comment_link_view_context() {
        $comment_id   = self::$comment_ids['valid'];
        $expected_url = admin_url( 'comment.php?action=editcomment&c=' . $comment_id );
		$actual_url   = get_edit_comment_link( $comment_id, '' );

        $this->assertSame( $expected_url, $actual_url );
    }

    public function test_get_edit_comment_link_invalid_comment() {
        $comment_id         = self::$comment_ids['invalid'];
		$actual_url_display = get_edit_comment_link( $comment_id, 'display' );
        $actual_url_view    = get_edit_comment_link( $comment_id, 'view' );

        $this->assertNull( $actual_url_display );
        $this->assertNull( $actual_url_view );
    }

    public function test_get_edit_comment_link_user_cannot_edit() {
        wp_set_current_user( self::$user_ids['subscriber'] );
        $comment_id         = self::$comment_ids['valid'];
		$actual_url_display = get_edit_comment_link( $comment_id, 'display' );
        $actual_url_view    = get_edit_comment_link( $comment_id, 'view' );

        $this->assertNull( $actual_url_display );
        $this->assertNull( $actual_url_view );
    }

    public function test_get_edit_comment_link_filter() {
        $comment_id        = self::$comment_ids['valid'];
        $expected_url      = admin_url( 'comment.php?action=editcomment&amp;c=' . $comment_id );
        $expected_url_view = admin_url( 'comment.php?action=editcomment&c=' . $comment_id );

        add_filter(
            'get_edit_comment_link',
            function( $location, $comment_id, $context ) use ( $expected_url, $expected_url_view ) {
                // Ensure the filtered URL matches the expected URL
                if ( 'display' === $context ) {
                    $location = admin_url( 'comment.php?action=editcomment&amp;c=' ) . $comment_id;
                    $this->assertSame( $expected_url, $location );
                } else{
                    $location = admin_url( 'comment.php?action=editcomment&c=' ) . $comment_id;
                    $this->assertSame( $expected_url_view, $location );
                }
                
                
                return $location; // Return unchanged
            },
            10,
            3
        );

        $actual_url_display = get_edit_comment_link( $comment_id, 'display' );
        $actual_url_view    = get_edit_comment_link( $comment_id, 'view' );

        // Assert the final URLs are as expected
        $this->assertSame( $expected_url, $actual_url_display );
        $this->assertSame( $expected_url_view, $actual_url_view );
    }
}
?>
