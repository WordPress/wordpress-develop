<?php

/**
 * @group admin
 */
class Tests_Admin_IncludesPost extends WP_UnitTestCase {
	protected static $contributor_id;
	protected static $author_ids;
	protected static $editor_id;
	protected static $admin_id;
	protected static $post_id;

	protected static $user_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids   = $factory->user->create_many( 2, array( 'role' => 'author' ) );
		self::$author_ids = self::$user_ids;

		self::$contributor_id = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$user_ids[]     = self::$contributor_id;
		self::$editor_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$user_ids[]     = self::$editor_id;
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids[]     = self::$admin_id;

		self::$post_id = $factory->post->create();
	}

	function test__wp_translate_postdata_cap_checks_contributor() {
		wp_set_current_user( self::$contributor_id );

		// Create new draft post.
		$_post_data                = array();
		$_post_data['post_author'] = self::$contributor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'draft', $_results['post_status'] );

		// Submit post for approval.
		$_post_data                = array();
		$_post_data['post_author'] = self::$contributor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['publish']     = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'pending', $_results['post_status'] );

		// Create new draft post for another user.
		$_post_data                = array();
		$_post_data['post_author'] = self::$editor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertInstanceOf( 'WP_Error', $_results );
		$this->assertSame( 'edit_others_posts', $_results->get_error_code() );
		$this->assertSame( 'Sorry, you are not allowed to create posts as this user.', $_results->get_error_message() );

		// Edit draft post for another user.
		$_post_data                = array();
		$_post_data['post_ID']     = self::factory()->post->create( array( 'post_author' => self::$editor_id ) );
		$_post_data['post_author'] = self::$editor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['post_status'] = 'draft';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( true, $_post_data );
		$this->assertInstanceOf( 'WP_Error', $_results );
		$this->assertSame( 'edit_others_posts', $_results->get_error_code() );
		$this->assertSame( 'Sorry, you are not allowed to edit posts as this user.', $_results->get_error_message() );
	}

	function test__wp_translate_postdata_cap_checks_editor() {
		wp_set_current_user( self::$editor_id );

		// Create new draft post.
		$_post_data                = array();
		$_post_data['post_author'] = self::$editor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'draft', $_results['post_status'] );

		// Publish post.
		$_post_data                = array();
		$_post_data['post_author'] = self::$editor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['publish']     = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'publish', $_results['post_status'] );

		// Create new draft post for another user.
		$_post_data                = array();
		$_post_data['post_author'] = self::$contributor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( false, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'draft', $_results['post_status'] );

		// Edit draft post for another user.
		$_post_data                = array();
		$_post_data['post_ID']     = self::factory()->post->create( array( 'post_author' => self::$contributor_id ) );
		$_post_data['post_author'] = self::$contributor_id;
		$_post_data['post_type']   = 'post';
		$_post_data['post_status'] = 'draft';
		$_post_data['saveasdraft'] = true;

		$_results = _wp_translate_postdata( true, $_post_data );
		$this->assertNotWPError( $_results );
		$this->assertSame( $_post_data['post_author'], $_results['post_author'] );
		$this->assertSame( 'draft', $_results['post_status'] );
	}

	/**
	 * edit_post() should convert an existing auto-draft to a draft.
	 *
	 * @ticket 25272
	 */
	function test_edit_post_auto_draft() {
		wp_set_current_user( self::$editor_id );
		$post = self::factory()->post->create_and_get( array( 'post_status' => 'auto-draft' ) );
		$this->assertSame( 'auto-draft', $post->post_status );
		$post_data = array(
			'post_title' => 'Post title',
			'content'    => 'Post content',
			'post_type'  => 'post',
			'post_ID'    => $post->ID,
		);
		edit_post( $post_data );
		$this->assertSame( 'draft', get_post( $post->ID )->post_status );
	}

	/**
	 * @ticket 30615
	 */
	public function test_edit_post_should_parse_tax_input_by_name_rather_than_slug_for_nonhierarchical_taxonomies() {
		wp_set_current_user( self::$editor_id );

		register_taxonomy( 'wptests_tax', array( 'post' ) );
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
				'slug'     => 'bar',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'bar',
				'slug'     => 'foo',
			)
		);

		$post_data = array(
			'post_ID'   => self::$post_id,
			'tax_input' => array(
				'wptests_tax' => 'foo,baz',
			),
		);

		edit_post( $post_data );

		$found = wp_get_post_terms( self::$post_id, 'wptests_tax' );

		// Should contain the term with the name 'foo', not the slug.
		$this->assertContains( $t1, wp_list_pluck( $found, 'term_id' ) );

		// The 'baz' tag should have been created.
		$this->assertContains( 'baz', wp_list_pluck( $found, 'name' ) );
	}

	/**
	 * @ticket 30615
	 */
	public function test_edit_post_should_not_create_terms_for_an_empty_tag_input_field() {
		wp_set_current_user( self::$editor_id );

		register_taxonomy( 'wptests_tax', array( 'post' ) );
		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foo',
				'slug'     => 'bar',
			)
		);

		$post_data = array(
			'post_ID'   => self::$post_id,
			'tax_input' => array(
				'wptests_tax' => ' ',
			),
		);

		edit_post( $post_data );

		$found = wp_get_post_terms( self::$post_id, 'wptests_tax' );

		$this->assertEmpty( $found );
	}

	/**
	 * @ticket 27792
	 */
	public function test_bulk_edit_posts_stomping() {
		wp_set_current_user( self::$admin_id );

		$post1 = self::factory()->post->create(
			array(
				'post_author'    => self::$author_ids[0],
				'comment_status' => 'open',
				'ping_status'    => 'open',
				'post_status'    => 'publish',
			)
		);

		$post2 = self::factory()->post->create(
			array(
				'post_author'    => self::$author_ids[1],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_status'    => 'draft',
			)
		);

		$request = array(
			'post_type'      => 'post',
			'post_author'    => -1,
			'ping_status'    => -1,
			'comment_status' => -1,
			'_status'        => -1,
			'post'           => array( $post1, $post2 ),
		);

		bulk_edit_posts( $request );

		$post = get_post( $post2 );

		// Check that the first post's values don't stomp the second post.
		$this->assertSame( 'draft', $post->post_status );
		$this->assertEquals( self::$author_ids[1], $post->post_author );
		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
	}

	/**
	 * The bulk_edit_posts() function should preserve the post format
	 * when it's unchanged.
	 *
	 * @ticket 44914
	 */
	public function test_bulk_edit_posts_should_preserve_post_format_when_unchanged() {
		wp_set_current_user( self::$admin_id );

		$post_ids = self::factory()->post->create_many( 3 );

		set_post_format( $post_ids[0], 'image' );
		set_post_format( $post_ids[1], 'aside' );

		$request = array(
			'post_format' => -1, // Don't change the post format.
			'_status'     => -1,
			'post'        => $post_ids,
		);

		bulk_edit_posts( $request );

		$terms1 = get_the_terms( $post_ids[0], 'post_format' );
		$terms2 = get_the_terms( $post_ids[1], 'post_format' );
		$terms3 = get_the_terms( $post_ids[2], 'post_format' );

		$this->assertSame( 'post-format-image', $terms1[0]->slug );
		$this->assertSame( 'post-format-aside', $terms2[0]->slug );
		$this->assertFalse( $terms3 );

		$this->assertSame( 'image', get_post_format( $post_ids[0] ) );
		$this->assertSame( 'aside', get_post_format( $post_ids[1] ) );
		$this->assertFalse( get_post_format( $post_ids[2] ) );
	}

	/**
	 * @ticket 41396
	 */
	public function test_bulk_edit_posts_should_set_post_format_before_wp_update_post_runs() {
		wp_set_current_user( self::$admin_id );

		$request = array(
			'post_format' => 'aside',
			'_status'     => -1,
			'post'        => array( self::$post_id ),
		);

		add_action( 'save_post', array( $this, 'check_post_format' ) );

		bulk_edit_posts( $request );

		remove_action( 'save_post', array( $this, 'check_post_format' ) );
	}

	public function check_post_format( $post_id ) {
		if ( self::$post_id === $post_id ) {
			$this->assertSame( 'aside', get_post_format( $post_id ) );
		}
	}

	/**
	 * @ticket 38293
	 */
	public function test_user_cant_delete_protected_meta() {
		$protected_meta_key = '_test_meta_data_that_is_protected';

		// Add some protected meta data.
		$post_id = self::$post_id;
		$meta_id = add_post_meta( $post_id, $protected_meta_key, 'protected' );

		// User editing the post should not effect outcome.
		$expected = get_post_meta( $post_id, $protected_meta_key );

		// Attempt to edit the post.
		wp_set_current_user( self::$admin_id );

		$post_data = array(
			'post_ID' => $post_id,
			'meta'    => array(
				$meta_id => array(
					'key'   => 'unprotected_meta_key',
					'value' => 'protected',
				),
			),
		);
		edit_post( $post_data );

		$actual = get_post_meta( $post_id, $protected_meta_key );
		$this->assertSame( $expected, $actual );

		// Tidy up.
		delete_metadata_by_mid( 'post', $meta_id );
	}

	/**
	 * @ticket 30910
	 */
	public function test_get_sample_permalink_should_return_pretty_permalink_for_posts_with_post_status_future() {
		$permalink_structure = '%postname%';
		$this->set_permalink_structure( "/$permalink_structure/" );

		$future_date = gmdate( 'Y-m-d H:i:s', time() + 100 );
		$p           = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_name'   => 'foo',
				'post_date'   => $future_date,
			)
		);

		$found    = get_sample_permalink( $p );
		$expected = trailingslashit( home_url( $permalink_structure ) );

		$this->assertSame( $expected, $found[0] );
	}

	/**
	 * @ticket 30910
	 * @ticket 18306
	 */
	public function test_get_sample_permalink_html_should_use_default_permalink_for_view_post_link_when_pretty_permalinks_are_disabled() {
		wp_set_current_user( self::$admin_id );

		$future_date = gmdate( 'Y-m-d H:i:s', time() + 100 );
		$p           = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_name'   => 'foo',
				'post_date'   => $future_date,
			)
		);

		$found = get_sample_permalink_html( $p );
		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/?p=' . $p . '"', $found );
		$this->assertStringContainsString( '>' . get_option( 'home' ) . '/?p=' . $p . '<', $found );
	}

	/**
	 * @ticket 30910
	 * @ticket 18306
	 */
	public function test_get_sample_permalink_html_should_use_pretty_permalink_for_view_post_link_when_pretty_permalinks_are_enabled() {
		$this->set_permalink_structure( '/%postname%/' );

		wp_set_current_user( self::$admin_id );

		$future_date = gmdate( 'Y-m-d H:i:s', time() + 100 );
		$p           = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_name'   => 'foo-صورة',
				'post_date'   => $future_date,
			)
		);

		$found = get_sample_permalink_html( $p );
		$post  = get_post( $p );
		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found );
		$this->assertStringContainsString( '>' . urldecode( $post->post_name ) . '<', $found );
	}

	/**
	 * @ticket 35980
	 */
	public function test_get_sample_permalink_html_should_use_pretty_permalink_for_view_attachment_link_when_pretty_permalinks_are_enabled() {
		$this->set_permalink_structure( '/%postname%/' );

		wp_set_current_user( self::$admin_id );

		$p = self::factory()->attachment->create_object(
			'صورة.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'صورة',
				'post_status'    => 'inherit',
			)
		);

		$found = get_sample_permalink_html( $p );
		$post  = get_post( $p );
		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found );
		$this->assertStringContainsString( '>' . urldecode( get_permalink( $post ) ) . '<', $found );
	}

	/**
	 * @ticket 32954
	 * @ticket 18306
	 */
	public function test_get_sample_permalink_html_should_use_correct_permalink_for_view_post_link_when_changing_slug() {
		$this->set_permalink_structure( '/%postname%/' );

		wp_set_current_user( self::$admin_id );

		// Published posts should use published permalink.
		$p = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'foo-صورة',
			)
		);

		$found   = get_sample_permalink_html( $p, null, 'new_slug-صورة' );
		$post    = get_post( $p );
		$message = 'Published post';
		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found, $message );
		$this->assertStringContainsString( '>new_slug-صورة<', $found, $message );

		// Scheduled posts should use published permalink.
		$future_date = gmdate( 'Y-m-d H:i:s', time() + 100 );
		$p           = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_name'   => 'bar-صورة',
				'post_date'   => $future_date,
			)
		);

		$found   = get_sample_permalink_html( $p, null, 'new_slug-صورة' );
		$post    = get_post( $p );
		$message = 'Scheduled post';
		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found, $message );
		$this->assertStringContainsString( '>new_slug-صورة<', $found, $message );

		// Draft posts should use preview link.
		$p = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'baz-صورة',
			)
		);

		$found   = get_sample_permalink_html( $p, null, 'new_slug-صورة' );
		$post    = get_post( $p );
		$message = 'Draft post';

		$preview_link = get_permalink( $post->ID );
		$preview_link = add_query_arg( 'preview', 'true', $preview_link );

		$this->assertStringContainsString( 'href="' . esc_url( $preview_link ) . '"', $found, $message );
		$this->assertStringContainsString( '>new_slug-صورة<', $found, $message );
	}

	/**
	 * @ticket 30910
	 * @ticket 18306
	 */
	public function test_get_sample_permalink_html_should_use_preview_links_for_draft_and_pending_posts_with_no_post_name() {
		$this->set_permalink_structure( '/%postname%/' );

		wp_set_current_user( self::$admin_id );

		$future_date = gmdate( 'Y-m-d H:i:s', time() + 100 );
		$p           = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_name'   => '',
				'post_date'   => $future_date,
			)
		);

		$found = get_sample_permalink_html( $p );
		$post  = get_post( $p );
		$this->assertStringContainsString( 'href="' . esc_url( get_preview_post_link( $post ) ), $found );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_avoid_slugs_that_would_create_clashes_with_year_archives() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '2015',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '2015-2', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_allow_yearlike_slugs_if_permastruct_does_not_cause_an_archive_conflict() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '2015',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '2015', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_avoid_slugs_that_would_create_clashes_with_month_archives() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '11',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '11-2', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_ignore_potential_month_conflicts_for_invalid_monthnum() {
		$this->set_permalink_structure( '/%year%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '13',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '13', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_avoid_slugs_that_would_create_clashes_with_day_archives() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '30',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '30-2', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_iterate_slug_suffix_when_a_date_conflict_is_found() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		self::factory()->post->create(
			array(
				'post_name' => '30-2',
			)
		);

		$p = self::factory()->post->create(
			array(
				'post_name' => '30',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '30-3', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_ignore_potential_day_conflicts_for_invalid_day() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '32',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '32', $found[1] );
	}

	/**
	 * @ticket 5305
	 */
	public function test_get_sample_permalink_should_allow_daylike_slugs_if_permastruct_does_not_cause_an_archive_conflict() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$p = self::factory()->post->create(
			array(
				'post_name' => '30',
			)
		);

		$found = get_sample_permalink( $p );
		$this->assertSame( '30', $found[1] );
	}

	/**
	 * @ticket 35368
	 */
	public function test_get_sample_permalink_should_respect_hierarchy_of_draft_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent Page',
			)
		);

		$child = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child Page',
				'post_parent' => $parent,
				'post_status' => 'draft',
			)
		);

		$actual = get_sample_permalink( $child );
		$this->assertSame( home_url() . '/parent-page/%pagename%/', $actual[0] );
		$this->assertSame( 'child-page', $actual[1] );
	}

	public function test_post_exists_should_match_title() {
		$p = self::factory()->post->create(
			array(
				'post_title' => 'Foo Bar',
			)
		);

		$this->assertSame( $p, post_exists( 'Foo Bar' ) );
	}

	public function test_post_exists_should_not_match_nonexistent_title() {
		$p = self::factory()->post->create(
			array(
				'post_title' => 'Foo Bar',
			)
		);

		$this->assertSame( 0, post_exists( 'Foo Bar Baz' ) );
	}

	public function test_post_exists_should_match_nonempty_content() {
		$title   = 'Foo Bar';
		$content = 'Foo Bar Baz';
		$p       = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_content' => $content,
			)
		);

		$this->assertSame( $p, post_exists( $title, $content ) );
	}

	/**
	 * @ticket 35246
	 */
	public function test_post_exists_should_match_content_with_no_title() {
		$title   = '';
		$content = 'Foo Bar Baz';
		$p       = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_content' => $content,
			)
		);

		$this->assertSame( $p, post_exists( $title, $content ) );
	}

	public function test_post_exists_should_not_match_when_nonempty_content_doesnt_match() {
		$title   = 'Foo Bar';
		$content = 'Foo Bar Baz';
		$p       = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_content' => $content . ' Quz',
			)
		);

		$this->assertSame( 0, post_exists( $title, $content ) );
	}

	public function test_post_exists_should_match_nonempty_date() {
		$title = 'Foo Bar';
		$date  = '2014-05-08 12:00:00';
		$p     = self::factory()->post->create(
			array(
				'post_title' => $title,
				'post_date'  => $date,
			)
		);

		$this->assertSame( $p, post_exists( $title, '', $date ) );
	}

	public function test_post_exists_should_not_match_when_nonempty_date_doesnt_match() {
		$title = 'Foo Bar';
		$date  = '2014-05-08 12:00:00';
		$p     = self::factory()->post->create(
			array(
				'post_title' => $title,
				'post_date'  => '2015-10-10 00:00:00',
			)
		);

		$this->assertSame( 0, post_exists( $title, '', $date ) );
	}

	public function test_post_exists_should_match_nonempty_title_content_and_date() {
		$title   = 'Foo Bar';
		$content = 'Foo Bar Baz';
		$date    = '2014-05-08 12:00:00';
		$p       = self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_date'    => $date,
			)
		);

		$this->assertSame( $p, post_exists( $title, $content, $date ) );
	}

	function test_use_block_editor_for_post() {
		$this->assertFalse( use_block_editor_for_post( -1 ) );
		$bogus_post_id = $this->factory()->post->create(
			array(
				'post_type' => 'bogus',
			)
		);
		$this->assertFalse( use_block_editor_for_post( $bogus_post_id ) );

		register_post_type(
			'restless',
			array(
				'show_in_rest' => false,
			)
		);
		$restless_post_id = $this->factory()->post->create(
			array(
				'post_type' => 'restless',
			)
		);
		$this->assertFalse( use_block_editor_for_post( $restless_post_id ) );

		$generic_post_id = $this->factory()->post->create();

		add_filter( 'use_block_editor_for_post', '__return_false' );
		$this->assertFalse( use_block_editor_for_post( $generic_post_id ) );
		remove_filter( 'use_block_editor_for_post', '__return_false' );

		add_filter( 'use_block_editor_for_post', '__return_true' );
		$this->assertTrue( use_block_editor_for_post( $restless_post_id ) );
		remove_filter( 'use_block_editor_for_post', '__return_true' );
	}

	function test_get_block_editor_server_block_settings() {
		$name     = 'core/test';
		$settings = array(
			'icon'            => 'text',
			'category'        => 'common',
			'render_callback' => 'foo',
		);

		register_block_type( $name, $settings );

		$blocks = get_block_editor_server_block_settings();

		unregister_block_type( $name );

		$this->assertArrayHasKey( $name, $blocks );
		$this->assertSame(
			array(
				'apiVersion'  => 1,
				'title'       => '',
				'description' => '',
				'icon'        => 'text',
				'usesContext' => array(),
				'category'    => 'common',
				'styles'      => array(),
				'keywords'    => array(),
				'variations'  => array(),
			),
			$blocks[ $name ]
		);
	}

	/**
	 * @ticket 43559
	 */
	public function test_post_add_meta_empty_is_allowed() {
		$p = self::factory()->post->create();

		$_POST = array(
			'metakeyinput' => 'testkey',
			'metavalue'    => '',
		);

		wp_set_current_user( self::$admin_id );

		$this->assertNotFalse( add_meta( $p ) );
		$this->assertSame( '', get_post_meta( $p, 'testkey', true ) );
	}

	/**
	 * Test the post type support in post_exists().
	 *
	 * @ticket 37406
	 */
	public function test_post_exists_should_support_post_type() {
		$title     = 'Foo Bar';
		$post_type = 'page';
		$post_id   = self::factory()->post->create(
			array(
				'post_title' => $title,
				'post_type'  => $post_type,
			)
		);
		$this->assertSame( $post_id, post_exists( $title, null, null, $post_type ) );
	}

	/**
	 * Test that post_exists() doesn't find an existing page as a post.
	 *
	 * @ticket 37406
	 */
	public function test_post_exists_should_not_match_a_page_for_post() {
		$title     = 'Foo Bar';
		$post_type = 'page';
		$post_id   = self::factory()->post->create(
			array(
				'post_title' => $title,
				'post_type'  => $post_type,
			)
		);
		$this->assertSame( 0, post_exists( $title, null, null, 'post' ) );
	}

	/**
	 * Test the status support in post_exists()
	 *
	 * @ticket 34012
	 */
	public function test_post_exists_should_support_post_status() {
		$title       = 'Foo Bar';
		$post_type   = 'post';
		$post_status = 'publish';
		$post_id     = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => $post_status,
			)
		);
		$this->assertSame( $post_id, post_exists( $title, null, null, null, $post_status ) );
	}


	/**
	 * Test the type and status query in post_exists()
	 *
	 * @ticket 34012
	 */
	public function test_post_exists_should_support_post_type_status_combined() {
		$title       = 'Foo Bar';
		$post_type   = 'post';
		$post_status = 'publish';
		$post_id     = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => $post_status,
			)
		);
		$this->assertSame( $post_id, post_exists( $title, null, null, $post_type, $post_status ) );
	}

	/**
	 * Test that post_exists() doesn't find an existing draft post when looking for publish
	 *
	 * @ticket 34012
	 */
	public function test_post_exists_should_only_match_correct_post_status() {
		$title       = 'Foo Bar';
		$post_type   = 'post';
		$post_status = 'draft';
		$post_id     = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => $post_status,
			)
		);
		$this->assertSame( 0, post_exists( $title, null, null, null, 'publish' ) );
	}

	/**
	 * Test the status support in post_exists()
	 *
	 * @ticket 34012
	 */
	public function test_post_exists_should_not_match_invalid_post_type_and_status_combined() {
		$title       = 'Foo Bar';
		$post_type   = 'post';
		$post_status = 'publish';
		$post_id     = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => $post_status,
			)
		);

		$this->assertSame( 0, post_exists( $title, null, null, $post_type, 'draft' ) );
		$this->assertSame( 0, post_exists( $title, null, null, 'wp_tests', $post_status ) );
	}
}
