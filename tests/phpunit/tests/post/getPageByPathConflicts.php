<?php

/**
 * @group post
 * @covers ::get_page_by_path
 */
class Tests_Post_GetPageByPathConflicts extends WP_UnitTestCase {
	/**
	 * @ticket 61996
	 * @dataProvider data_page_path_conflicts
	 */
	public function test_published_page_wins_in_both_creation_orders( $draft_first, $nested, $post_type ) {
		list( $published, $path ) = $this->create_conflicting_pages( $draft_first, $nested );

		$found = get_page_by_path( $path, OBJECT, $post_type );
		$this->assertInstanceOf( 'WP_Post', $found );
		$this->assertSame( $published, $found->ID );

		$queries = get_num_queries();
		$cached  = get_page_by_path( $path, OBJECT, $post_type );
		$this->assertSame( $published, $cached->ID );
		$this->assertSame( $queries, get_num_queries(), 'A cached lookup should not query the database.' );
	}

	/**
	 * @ticket 61996
	 * @dataProvider data_public_page_conflicts
	 */
	public function test_anonymous_permalink_returns_the_published_page( $nested ) {
		list( $published, $path ) = $this->create_conflicting_pages( true, $nested );
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( 0 );

		$this->go_to( home_url( '/' . $path . '/' ) );

		$this->assertFalse( is_404(), 'The draft must not make the published permalink return a 404.' );
		$this->assertSame( $published, get_queried_object_id() );
	}

	/**
	 * @ticket 61996
	 * @dataProvider data_unpublished_statuses
	 */
	public function test_unpublished_page_is_still_found_without_a_published_match( $status ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
				'post_name'   => 'privacy-policy',
			)
		);

		$found = get_page_by_path( 'privacy-policy' );
		$this->assertInstanceOf( 'WP_Post', $found );
		$this->assertSame( $page, $found->ID );
	}

	/**
	 * @ticket 61996
	 */
	public function test_custom_viewable_status_is_preferred() {
		register_post_status( 'wptests_public', array( 'public' => true ) );
		$draft     = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_name'   => 'draft-policy',
			)
		);
		$published = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'wptests_public',
				'post_name'   => 'privacy-policy',
			)
		);
		wp_update_post(
			array(
				'ID'        => $draft,
				'post_name' => 'privacy-policy',
			)
		);

		$this->assertSame( $published, get_page_by_path( 'privacy-policy' )->ID );
	}

	/**
	 * @ticket 61996
	 */
	public function test_lookup_works_without_viewable_statuses() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_filter( 'is_post_status_viewable', '__return_false' );
		try {
			$this->assertSame( $page, get_page_by_path( get_post( $page )->post_name )->ID );
		} finally {
			remove_filter( 'is_post_status_viewable', '__return_false' );
		}
	}

	/**
	 * @ticket 61996
	 * @group query
	 * @dataProvider data_explicit_status_queries
	 */
	public function test_query_respects_explicit_draft_status( $nested, $post_type, $post_status ) {
		if ( 'page' !== $post_type ) {
			register_post_type( $post_type, array( 'hierarchical' => true ) );
		}
		list( , $path, $draft ) = $this->create_conflicting_pages( true, $nested, $post_type );

		$query = new WP_Query(
			array(
				'post_type'   => $post_type,
				'pagename'    => $path,
				'post_status' => $post_status,
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertSame( $draft, $query->get_queried_object_id() );
	}

	/**
	 * @ticket 61996
	 * @group query
	 * @dataProvider data_page_options
	 */
	public function test_explicit_draft_query_uses_the_selected_pages_flags( $option ) {
		list( $published, $path, $draft ) = $this->create_conflicting_pages( true, false );
		if ( $option ) {
			update_option( $option, $published );
			update_option( 'show_on_front', 'page' );
		}

		$query = new WP_Query(
			array(
				'post_type'   => 'page',
				'pagename'    => $path,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array( $draft ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertSame( $draft, $query->get_queried_object_id() );
		$this->assertTrue( $query->is_page() );
		$this->assertFalse( $query->is_privacy_policy() );
		$this->assertFalse( $query->is_privacy_policy );
		$this->assertFalse( $query->is_posts_page );
		$this->assertFalse( $query->is_home() );
		$this->assertTrue( $query->is_singular() );
		$this->assertFalse( $query->is_comment_feed() );
	}

	/**
	 * @ticket 61996
	 */
	public function test_status_filtered_lookups_have_separate_cache_entries() {
		list( $published, $path, $draft ) = $this->create_conflicting_pages( true, false );

		$this->assertSame( $published, get_page_by_path( $path )->ID );
		$this->assertSame( $draft, get_page_by_path( $path, OBJECT, 'page', 'draft' )->ID );
		$this->assertNull( get_page_by_path( $path, OBJECT, 'page', 'pending' ) );
		$this->assertSame( $published, get_page_by_path( $path )->ID );
		$this->assertSame( $draft, get_page_by_path( $path, OBJECT, 'page', 'draft' )->ID );
	}

	/**
	 * @ticket 61996
	 */
	public function test_status_filter_does_not_exclude_ancestors_with_other_statuses() {
		$parent = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'parent',
				'post_status' => 'publish',
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'child',
				'post_status' => 'draft',
				'post_parent' => $parent,
			)
		);

		$found = get_page_by_path( 'parent/child', OBJECT, 'page', 'draft' );
		$this->assertInstanceOf( 'WP_Post', $found );
		$this->assertSame( $child, $found->ID );
	}

	/**
	 * @ticket 61996
	 */
	public function test_status_change_invalidates_filtered_lookup_cache() {
		list( $published, $path ) = $this->create_conflicting_pages( true, false );
		$this->assertSame( $published, get_page_by_path( $path, OBJECT, 'page', 'publish' )->ID );

		wp_update_post(
			array(
				'ID'          => $published,
				'post_status' => 'draft',
			)
		);

		$this->assertNull( get_page_by_path( $path, OBJECT, 'page', 'publish' ) );
	}

	/**
	 * @ticket 61996
	 */
	public function test_any_status_excludes_internal_statuses_unless_explicitly_requested() {
		register_post_status( 'wptests_hidden', array( 'internal' => true ) );
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'hidden',
				'post_status' => 'wptests_hidden',
			)
		);

		$this->assertNull( get_page_by_path( 'hidden', OBJECT, 'page', 'any' ) );
		$this->assertSame( $page, get_page_by_path( 'hidden', OBJECT, 'page', array( 'any', 'wptests_hidden' ) )->ID );
		$this->assertSame( $page, get_page_by_path( 'hidden' )->ID );
	}

	/**
	 * @ticket 61996
	 */
	public function test_attachment_can_match_its_inherited_status() {
		$attachment = self::factory()->post->create(
			array(
				'post_type'   => 'attachment',
				'post_name'   => 'picture',
				'post_status' => 'inherit',
			)
		);

		$this->assertSame( $attachment, get_page_by_path( 'picture', OBJECT, 'attachment', 'publish' )->ID );
		$this->assertSame( $attachment, get_page_by_path( 'picture', OBJECT, 'attachment', 'inherit' )->ID );
		$this->assertNull( get_page_by_path( 'picture', OBJECT, 'attachment', 'draft' ) );
	}

	/**
	 * @ticket 61996
	 */
	public function test_requested_type_still_takes_precedence_over_attachment_fallback() {
		$attachment = self::factory()->post->create(
			array(
				'post_type'   => 'attachment',
				'post_name'   => 'privacy-policy',
				'post_status' => 'inherit',
			)
		);
		$draft      = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'privacy-policy',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( $draft, get_page_by_path( 'privacy-policy' )->ID );
		$this->assertSame( $attachment, get_page_by_path( 'privacy-policy', OBJECT, array( 'attachment' ) )->ID );
	}

	public static function data_explicit_status_queries() {
		$cases = array();
		foreach ( array( false, true ) as $nested ) {
			foreach ( array( 'page', 'wptests_page' ) as $post_type ) {
				foreach ( array( 'draft', array( 'draft', 'pending' ), 'draft,pending' ) as $post_status ) {
					$cases[] = array( $nested, $post_type, $post_status );
				}
			}
		}
		return $cases;
	}

	public static function data_page_options() {
		return array(
			'ordinary page' => array( '' ),
			'privacy page'  => array( 'wp_page_for_privacy_policy' ),
			'posts page'    => array( 'page_for_posts' ),
		);
	}

	public static function data_page_path_conflicts() {
		return array(
			'older draft, top level, string' => array( true, false, 'page' ),
			'newer draft, top level, string' => array( false, false, 'page' ),
			'older draft, nested, string'    => array( true, true, 'page' ),
			'newer draft, nested, string'    => array( false, true, 'page' ),
			'older draft, top level, array'  => array( true, false, array( 'page' ) ),
			'newer draft, top level, array'  => array( false, false, array( 'page' ) ),
			'older draft, nested, array'     => array( true, true, array( 'page' ) ),
			'newer draft, nested, array'     => array( false, true, array( 'page' ) ),
		);
	}

	public static function data_public_page_conflicts() {
		return array(
			'top level' => array( false ),
			'nested'    => array( true ),
		);
	}

	public static function data_unpublished_statuses() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	private function create_conflicting_pages( $draft_first, $nested, $post_type = 'page' ) {
		$statuses = $draft_first ? array( 'draft', 'publish' ) : array( 'publish', 'draft' );
		$pages    = array();
		$parents  = array();
		foreach ( $statuses as $status ) {
			$parent = 0;
			if ( $nested ) {
				$parent             = self::factory()->post->create(
					array(
						'post_type'   => $post_type,
						'post_status' => $status,
						'post_name'   => $status . '-parent',
					)
				);
				$parents[ $status ] = $parent;
			}
			$pages[ $status ] = self::factory()->post->create(
				array(
					'post_type'   => $post_type,
					'post_status' => $status,
					'post_name'   => 'publish' === $status ? 'privacy-policy' : 'draft-policy',
					'post_parent' => $parent,
				)
			);
		}

		// Draft slug updates are allowed to reuse a published page's slug.
		wp_update_post(
			array(
				'ID'        => $pages['draft'],
				'post_name' => 'privacy-policy',
			)
		);
		$this->assertSame( 'privacy-policy', get_post( $pages['draft'] )->post_name );
		$this->assertSame( 'privacy-policy', get_post( $pages['publish'] )->post_name );
		if ( $nested ) {
			wp_update_post(
				array(
					'ID'        => $parents['draft'],
					'post_name' => 'publish-parent',
				)
			);
		}

		return array( $pages['publish'], $nested ? 'publish-parent/privacy-policy' : 'privacy-policy', $pages['draft'] );
	}
}
