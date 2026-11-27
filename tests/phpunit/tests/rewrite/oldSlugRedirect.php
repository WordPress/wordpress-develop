<?php

/**
 * @group rewrite
 * @ticket 33920
 * @covers wp_old_slug_redirect
 */
class Tests_Rewrite_OldSlugRedirect extends WP_UnitTestCase {
	protected $old_slug_redirect_url;
	protected $redirect_status;
	protected $redirect_location;
	protected $redirect_post_id;

	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Foo Bar',
				'post_name'  => 'foo-bar',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'old_slug_redirect_url', array( $this, 'filter_old_slug_redirect_url' ), 10, 1 );

		$this->set_permalink_structure( '/%postname%/' );

		add_rewrite_endpoint( 'custom-endpoint', EP_PERMALINK );
		add_rewrite_endpoint( 'second-endpoint', EP_PERMALINK, 'custom' );

		flush_rewrite_rules();
	}

	public function tear_down() {
		$this->old_slug_redirect_url = null;
		$this->redirect_status       = null;
		$this->redirect_location     = null;
		$this->redirect_post_id      = null;

		parent::tear_down();
	}

	public function test_old_slug_redirect() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
	}

	/**
	 * @ticket 36723
	 */
	public function test_old_slug_redirect_cache() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );

		wp_old_slug_redirect();
		$num_queries = get_num_queries();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );

		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 36723
	 */
	public function test_old_slug_redirect_cache_invalidation() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );

		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'foo-bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$num_queries = get_num_queries();
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
		$this->assertSame( $num_queries + 1, get_num_queries() );
	}

	public function test_old_slug_redirect_attachment() {
		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			self::$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_name'      => 'my-attachment',
			)
		);

		$old_permalink = get_attachment_link( $attachment_id );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertNull( $this->old_slug_redirect_url );
		$this->assertQueryTrue( 'is_attachment', 'is_singular', 'is_single' );

		$old_permalink = get_attachment_link( $attachment_id );

		wp_update_post(
			array(
				'ID'        => $attachment_id,
				'post_name' => 'the-attachment',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'the-attachment' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
	}

	public function test_old_slug_redirect_paged() {
		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => 'Test<!--nextpage-->Test',
			)
		);

		$old_permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_slug_redirect_url );
	}

	/**
	 * @ticket 35031
	 */
	public function test_old_slug_doesnt_redirect_when_reused() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'bar-baz',
			)
		);

		$new_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Foo Bar',
				'post_name'  => 'foo-bar',
			)
		);

		$permalink = user_trailingslashit( get_permalink( $new_post_id ) );

		$this->assertSame( $old_permalink, $permalink );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertNull( $this->old_slug_redirect_url );
	}

	public function filter_old_slug_redirect_url( $url ) {
		$this->old_slug_redirect_url = $url;
		return false;
	}

	/**
	 * Test that the old_slug_redirect_status filter works correctly.
	 *
	 * @ticket 52737
	 */
	public function test_old_slug_redirect_status_filter() {
		// Use the same pattern as the working test
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		wp_update_post(
			array(
				'ID'        => self::$post_id,
				'post_name' => 'status-filter-test',
			)
		);

		// Remove the default URL filter temporarily to test the redirect status
		remove_filter( 'old_slug_redirect_url', array( $this, 'filter_old_slug_redirect_url' ) );

		// Test default 301 status.
		add_filter( 'wp_redirect', array( $this, 'capture_redirect_status' ), 10, 2 );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();

		$this->assertSame( 301, $this->redirect_status );
		$this->assertSame( self::$post_id, $this->redirect_post_id );

		// Reset state for next test
		$this->redirect_status  = null;
		$this->redirect_post_id = null;

		// Test custom 302 status.
		add_filter( 'old_slug_redirect_status', array( $this, 'filter_redirect_status_to_302' ), 10, 2 );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();

		$this->assertSame( 302, $this->redirect_status );
		$this->assertSame( self::$post_id, $this->redirect_post_id );

		// Test that returning 0 prevents redirect.
		remove_filter( 'old_slug_redirect_status', array( $this, 'filter_redirect_status_to_302' ) );
		add_filter( 'old_slug_redirect_status', array( $this, 'filter_redirect_status_to_zero' ), 10, 2 );

		$this->redirect_status  = null;
		$this->redirect_post_id = null;

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();

		$this->assertNull( $this->redirect_status );
		$this->assertNull( $this->redirect_post_id );

		// Clean up.
		remove_filter( 'wp_redirect', array( $this, 'capture_redirect_status' ) );
		remove_filter( 'old_slug_redirect_status', array( $this, 'filter_redirect_status_to_zero' ) );

		// Restore the URL filter
		add_filter( 'old_slug_redirect_url', array( $this, 'filter_old_slug_redirect_url' ), 10, 1 );
	}

	public function capture_redirect_status( $location, $status ) {
		$this->redirect_status   = $status;
		$this->redirect_location = $location;
		// Prevent actual redirect in tests.
		return false;
	}

	public function filter_redirect_status_to_302( $status, $post_id ) {
		$this->redirect_post_id = $post_id;
		return 302;
	}

	public function filter_redirect_status_to_zero( $status, $post_id ) {
		$this->redirect_post_id = $post_id;
		return 0;
	}
}
