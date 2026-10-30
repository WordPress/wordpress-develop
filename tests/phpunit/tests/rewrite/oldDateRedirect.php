<?php

/**
 * @group rewrite
 * @covers wp_old_slug_redirect
 */
class Tests_Rewrite_OldDateRedirect extends WP_UnitTestCase {
	protected $old_date_redirect_url;

	public static $post_id;

	public static $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Foo Bar',
				'post_name'  => 'foo-bar',
			)
		);

		self::$attachment_id = $factory->attachment->create_object(
			array(
				'file'           => DIR_TESTDATA . '/images/canola.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_name'      => 'my-attachment',
				'post_parent'    => self::$post_id,
			)
		);
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'old_slug_redirect_url', array( $this, 'filter_old_date_redirect_url' ), 10, 1 );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		add_rewrite_endpoint( 'custom-endpoint', EP_PERMALINK );
		add_rewrite_endpoint( 'second-endpoint', EP_PERMALINK, 'custom' );

		flush_rewrite_rules();
	}

	public function tear_down() {
		$this->old_date_redirect_url = null;

		parent::tear_down();
	}

	public function test_old_date_redirect() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	public function test_old_date_slug_redirect() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	/**
	 * @ticket 36723
	 */
	public function test_old_date_slug_redirect_cache() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );

		wp_old_slug_redirect();
		$num_queries = get_num_queries();
		$this->assertSame( $permalink, $this->old_date_redirect_url );

		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 36723
	 */
	public function test_old_date_redirect_cache_invalidation() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );

		$time = '2014-02-01 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'foo-bar-baz',
			)
		);

		$permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$num_queries = get_num_queries();
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
		$this->assertGreaterThan( $num_queries, get_num_queries() );
	}

	public function test_old_date_redirect_attachment() {
		$old_permalink = get_attachment_link( self::$attachment_id );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			)
		);

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertNull( $this->old_date_redirect_url );
		$this->assertQueryTrue( 'is_attachment', 'is_singular', 'is_single' );

		$old_permalink = get_attachment_link( self::$attachment_id );

		wp_update_post(
			array(
				'ID'        => self::$attachment_id,
				'post_name' => 'the-attachment',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'the-attachment' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	public function test_old_date_slug_redirect_attachment() {
		$old_permalink = get_attachment_link( self::$attachment_id );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
			)
		);

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertNull( $this->old_date_redirect_url );
		$this->assertQueryTrue( 'is_attachment', 'is_singular', 'is_single' );

		$old_permalink = get_attachment_link( self::$attachment_id );

		wp_update_post(
			array(
				'ID'        => self::$attachment_id,
				'post_name' => 'the-attachment',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'the-attachment' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	public function test_old_date_redirect_paged() {
		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => 'Test<!--nextpage-->Test',
			)
		);

		$old_permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	public function test_old_date_slug_redirect_paged() {
		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => 'Test<!--nextpage-->Test',
			)
		);

		$old_permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		$time = '2004-01-04 12:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
			)
		);

		$permalink = user_trailingslashit( trailingslashit( get_permalink( self::$post_id ) ) . 'page/2' );

		$this->go_to( $old_permalink );
		wp_old_slug_redirect();
		$this->assertSame( $permalink, $this->old_date_redirect_url );
	}

	public function test_old_date_slug_doesnt_redirect_when_reused() {
		$old_permalink = user_trailingslashit( get_permalink( self::$post_id ) );

		$time = '2004-01-04 12:00:00';
		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
				'post_name'     => 'bar-baz',
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
		$this->assertNull( $this->old_date_redirect_url );
	}

	/**
	 * @ticket 23074
	 */
	public function test_old_date_doesnt_redirect_when_date_changed_back_on_private_post() {
		$original_time = '2010-05-06 12:00:00';
		$post_id       = self::factory()->post->create(
			array(
				'post_title'    => 'Foo',
				'post_name'     => 'foo',
				'post_date'     => $original_time,
				'post_date_gmt' => get_gmt_from_date( $original_time ),
			)
		);

		$time = '2004-01-03 00:00:00';
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			)
		);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => $original_time,
				'post_date_gmt' => get_gmt_from_date( $original_time ),
			)
		);

		$this->assertNotContains( '2010-05-06', get_post_meta( $post_id, '_wp_old_date' ), 'The current date should not be stored as an old date.' );

		$this->go_to( home_url( '/2010/05/06/foo/' ) );
		$this->assertTrue( is_404(), 'Private post should 404 for logged-out users.' );

		wp_old_slug_redirect();
		$this->assertNull( $this->old_date_redirect_url, 'Old date redirect should not point back to the requested URL.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->go_to( home_url( '/2010/05/06/foo/' ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertSame( $post_id, get_queried_object_id() );

		wp_old_slug_redirect();
		$this->assertNull( $this->old_date_redirect_url, 'Republished post should not trigger an old date redirect.' );
		$this->assertNull( redirect_canonical( home_url( '/2010/05/06/foo/' ), false ), 'Republished post should not trigger a canonical redirect.' );

		$plain_url = home_url( '/?p=' . $post_id );
		$this->go_to( $plain_url );
		$this->assertSame( home_url( '/2010/05/06/foo/' ), redirect_canonical( $plain_url, false ), 'Plain permalink of the republished post should canonicalize to the pretty URL.' );
	}

	/**
	 * @ticket 23074
	 */
	public function test_old_date_doesnt_redirect_when_old_date_matches_current_date() {
		$time    = '2010-05-06 12:00:00';
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Foo',
				'post_name'     => 'foo',
				'post_date'     => $time,
				'post_date_gmt' => get_gmt_from_date( $time ),
			)
		);

		// Simulate legacy data where the current date is also stored as an old date.
		add_post_meta( $post_id, '_wp_old_date', '2010-05-06' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->go_to( home_url( '/2010/05/06/foo/' ) );
		$this->assertTrue( is_404(), 'Private post should 404 for logged-out users.' );

		wp_old_slug_redirect();
		$this->assertNull( $this->old_date_redirect_url, 'Old date redirect should not point back to the requested URL.' );
	}

	public function filter_old_date_redirect_url( $url ) {
		$this->old_date_redirect_url = $url;
		return false;
	}
}
