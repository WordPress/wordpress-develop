<?php
/**
 * @group admin
 */
class Tests_Admin_TrashRedirect extends WP_UnitTestCase {
	protected $admin_user_id;

	public function set_up() {
		parent::set_up();
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Ensure we are in the admin context.
		set_current_screen( 'edit-post' );

		// Define ABSPATH if not defined (though it should be in tests).
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 4 ) . '/src/' );
		}

		add_filter( 'wp_redirect', array( $this, 'catch_redirect' ), 1, 1 );
	}

	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'catch_redirect' ), 1 );
		parent::tear_down();
	}

	public function catch_redirect( $location ) {
		throw new Tests_Admin_TrashRedirect_Exception( $location );
	}

	/**
	 * Test that visiting an empty trash view redirects to all posts view.
	 */
	public function test_empty_trash_view_redirects_to_all_posts() {
		// Mock the request for empty trash.
		$_GET['post_status']     = 'trash';
		$_GET['post_type']       = 'post';
		$_REQUEST['post_status'] = 'trash';
		$_SERVER['REQUEST_URI']  = admin_url( 'edit.php?post_status=trash' );

		try {
			ob_start();
			include ABSPATH . 'wp-admin/edit.php';
			ob_end_clean();
		} catch ( Tests_Admin_TrashRedirect_Exception $e ) {
			ob_end_clean();
			$this->assertStringNotContainsString( 'post_status=trash', $e->get_location() );
			$this->assertStringContainsString( 'edit.php', $e->get_location() );
			return;
		}

		$this->fail( 'Redirect expected when visiting empty trash view.' );
	}

	/**
	 * Test that emptying trash via 'delete_all' redirects to all posts view.
	 */
	public function test_empty_trash_action_redirects_to_all_posts() {
		// Create a post in trash.
		$post_id = self::factory()->post->create( array( 'post_status' => 'trash' ) );

		$_REQUEST['post_status'] = 'trash';
		$_REQUEST['post_type']   = 'post';
		$_REQUEST['action']      = 'delete_all';
		$_REQUEST['_wpnonce']    = wp_create_nonce( 'bulk-posts' );
		$_SERVER['REQUEST_URI']  = admin_url( 'edit.php?post_status=trash' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'edit.php?post_status=trash' );

		try {
			ob_start();
			include ABSPATH . 'wp-admin/edit.php';
			ob_end_clean();
		} catch ( Tests_Admin_TrashRedirect_Exception $e ) {
			ob_end_clean();
			$this->assertStringNotContainsString( 'post_status=trash', $e->get_location() );
			$this->assertStringContainsString( 'edit.php', $e->get_location() );
			// Check if post is actually deleted.
			$this->assertNull( get_post( $post_id ) );
			return;
		}

		$this->fail( 'Redirect expected after emptying trash.' );
	}
}

class Tests_Admin_TrashRedirect_Exception extends Exception {
	protected $location;
	public function __construct( $location ) {
		$this->location = $location;
	}
	public function get_location() {
		return $this->location;
	}
}
