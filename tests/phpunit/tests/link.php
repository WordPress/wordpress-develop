<?php
/**
 * @group link
 */
class Tests_Link extends WP_UnitTestCase {

	public function test_wp_get_shortlink() {
		$post_id  = self::factory()->post->create();
		$post_id2 = self::factory()->post->create();

		// Basic case.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$GLOBALS['post'] = get_post( $post_id );

		// Global post is set.
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( get_permalink( $post_id ), wp_get_shortlink() );

		// Not the global post.
		$this->assertSame( get_permalink( $post_id2 ), wp_get_shortlink( $post_id2, 'post' ) );

		unset( $GLOBALS['post'] );

		// Global post is not set, once again.
		$this->assertSame( '', wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( '', wp_get_shortlink( 0 ) );
		$this->assertSame( '', wp_get_shortlink() );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		// With a permalink structure set, get_permalink() will no longer match.
		$this->assertNotEquals( get_permalink( $post_id ), wp_get_shortlink( $post_id, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		// Global post and permalink structure are set.
		$GLOBALS['post'] = get_post( $post_id );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0, 'post' ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( 0 ) );
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink() );
	}

	public function test_wp_get_shortlink_with_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		// Basic case.
		// Don't test against get_permalink() since it uses ?page_id= for pages.
		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '?p=' . $post_id ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 26871
	 */
	public function test_wp_get_shortlink_with_home_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $post_id );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( '/' ), wp_get_shortlink( $post_id, 'post' ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_post_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$non_pretty_permalink = add_query_arg( 'p', $p, trailingslashit( home_url() ) );

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_permalink_should_not_reveal_post_name_for_cpt_with_post_status_future() {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'wptests_pt', array( 'public' => true ) );

		flush_rewrite_rules();

		$p = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_type'   => 'wptests_pt',
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);

		$non_pretty_permalink = add_query_arg(
			array(
				'post_type' => 'wptests_pt',
				'p'         => $p,
			),
			trailingslashit( home_url() )
		);

		$this->assertSame( $non_pretty_permalink, get_permalink( $p ) );
	}

	/**
	 * @ticket 1914
	 */
	public function test_unattached_attachment_has_a_pretty_permalink() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( home_url( user_trailingslashit( $attachment->post_name ) ), get_permalink( $attachment_id ) );
	}

	/**
	 * @ticket 1914
	 */
	public function test_attachment_attached_to_non_existent_post_type_has_a_pretty_permalink() {
		global $wp_post_types;

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type( 'not_a_post_type', array( 'public' => true ) );

		flush_rewrite_rules();

		$post_id = self::factory()->post->create( array( 'post_type' => 'not_a_post_type' ) );

		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'An Attachment!',
				'post_status'    => 'inherit',
			)
		);

		$attachment = get_post( $attachment_id );

		$this->assertSame( get_permalink( $post_id ) . user_trailingslashit( $attachment->post_name ), get_permalink( $attachment_id ) );

		foreach ( $wp_post_types as $id => $pt ) {
			if ( 'not_a_post_type' === $pt->name ) {
				unset( $wp_post_types[ $id ] );
				break;
			}
		}

		$this->assertSame( home_url( "/?attachment_id={$attachment->ID}" ), get_permalink( $attachment_id ) );
		// Visit permalink.
		$this->go_to( get_permalink( $attachment_id ) );
		$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );
	}

	/**
	 * @ticket 32322
	 */
	public function test_wp_force_plain_post_permalink_statuses_filter_future_posts_default() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_name'   => 'future-post',
			)
		);

		$post = get_post( $post_id );

		// Should return true (force plain permalink) for future posts by default.
		$this->assertTrue( wp_force_plain_post_permalink( $post ) );
	}

	/**
	 * @ticket 32322
	 */
	public function test_wp_force_plain_post_permalink_statuses_filter_allows_customization() {
		// Add filter to allow future posts to use pretty permalinks.
		add_filter(
			'wp_force_plain_post_permalink_statuses',
			function ( $statuses ) {
				return array_diff( $statuses, array( 'future' ) );
			}
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_name'   => 'future-post',
			)
		);

		$post = get_post( $post_id );

		// Should return false (allow pretty permalink) when filter is applied.
		$this->assertFalse( wp_force_plain_post_permalink( $post ) );

		// Clean up.
		remove_all_filters( 'wp_force_plain_post_permalink_statuses' );
	}

	/**
	 * @ticket 32322
	 */
	public function test_wp_force_plain_post_permalink_statuses_filter_selective_by_status() {
		// Add filter to allow only future posts to use pretty permalinks.
		add_filter(
			'wp_force_plain_post_permalink_statuses',
			function ( $statuses ) {
				return array_diff( $statuses, array( 'future' ) );
			}
		);

		$future_post_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_name'   => 'future-post',
			)
		);

		$draft_post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'draft-post',
			)
		);

		$future_post = get_post( $future_post_id );
		$draft_post  = get_post( $draft_post_id );

		// Future post should use pretty permalink.
		$this->assertFalse( wp_force_plain_post_permalink( $future_post ) );

		// Draft post should still use plain permalink.
		$this->assertTrue( wp_force_plain_post_permalink( $draft_post ) );

		// Clean up.
		remove_all_filters( 'wp_force_plain_post_permalink_statuses' );
	}

	/**
	 * @ticket 32322
	 */
	public function test_wp_force_plain_post_permalink_statuses_filter_selective_by_post_type() {
		// Add filter to allow future posts pretty permalinks only for 'post' type.
		add_filter(
			'wp_force_plain_post_permalink_statuses',
			function ( $statuses, $post ) {
				if ( 'future' === $post->post_status && 'post' === $post->post_type ) {
					return array_diff( $statuses, array( 'future' ) );
				}
				return $statuses;
			},
			10,
			2
		);

		// Create future post.
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_name'   => 'future-post',
			)
		);

		// Create future page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_name'   => 'future-page',
			)
		);

		$post = get_post( $post_id );
		$page = get_post( $page_id );

		// Future post should use pretty permalinks.
		$this->assertFalse( wp_force_plain_post_permalink( $post ) );

		// Future page should still use plain permalinks.
		$this->assertTrue( wp_force_plain_post_permalink( $page ) );

		// Clean up.
		remove_all_filters( 'wp_force_plain_post_permalink_statuses' );
	}

	/**
	 * @ticket 32322
	 */
	public function test_wp_force_plain_post_permalink_statuses_filter_published_posts_unaffected() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'published-post',
			)
		);

		$post = get_post( $post_id );

		// Should return false (allow pretty permalink) for published posts.
		$this->assertFalse( wp_force_plain_post_permalink( $post ) );
	}
}
