<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_PostStatus extends WP_Canonical_UnitTestCase {

	/**
	 * User IDs.
	 *
	 * @var array
	 */
	public static $users;

	/**
	 * Post Objects.
	 *
	 * @var array
	 */
	public static $posts;

	public static function wpSetupBeforeClass( $factory ) {
		self::setup_custom_types();
		self::$users = array(
			'anon'           => 0,
			'subscriber'     => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'author'         => $factory->user->create( array( 'role' => 'author' ) ),
			'content_author' => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor'    => $factory->user->create( array( 'role' => 'contributor' ) ),
			'editor'         => $factory->user->create( array( 'role' => 'editor' ) ),
			'administrator'  => $factory->user->create( array( 'role' => 'administrator' ) ),
		);

		$post_statuses = array( 'publish', 'future', 'draft', 'pending', 'private', 'auto-draft', 'trac-5272-status' );
		foreach ( $post_statuses as $post_status ) {
			self::$posts[ $post_status ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'post',
					'post_title'   => "$post_status post",
					'post_name'    => "{$post_status}-post",
					'post_status'  => $post_status,
					'post_content' => "Prevent canonical redirect exposing post slugs.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
					'post_date'    => 'future' === $post_status ? strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 year' ) ) : current_time( 'mysql' ),
				)
			);

			// Add fake attachment to the post (file upload not needed).
			self::$posts[ "{$post_status}-attachment" ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'attachment',
					'post_title'   => "$post_status inherited attachment",
					'post_name'    => "{$post_status}-inherited-attachment",
					'post_status'  => 'inherit',
					'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
					'post_parent'  => self::$posts[ $post_status ]->ID,
					'post_date'    => 'future' === $post_status ? strftime( '%Y-%m-%d %H:%M:%S', strtotime( '+1 year' ) ) : current_time( 'mysql' ),
				)
			);

			// Set up a page with same.
			self::$posts[ "page-$post_status" ] = $factory->post->create_and_get(
				array(
					'post_type'    => 'page',
					'post_title'   => "$post_status page",
					'post_name'    => "{$post_status}-page",
					'post_status'  => $post_status,
					'post_content' => "Prevent canonical redirect exposing page slugs.\n\n<!--nextpage-->Page 2",
					'post_author'  => self::$users['content_author'],
				)
			);
		}

		self::$posts['trac-5272-cpt'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'trac-5272-cpt',
				'post_title'   => 'trac-5272-cpt post',
				'post_name'    => 'trac-5272-cpt-post',
				'post_status'  => 'private',
				'post_content' => 'Prevent canonical redirect exposing trac-5272-cpt titles.',
				'post_author'  => self::$users['content_author'],
			)
		);

		// Add fake attachment to the cpt (file upload not needed).
		self::$posts['trac-5272-cpt-attachment'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'attachment',
				'post_title'   => 'trac-5272-cpt post inherited attachment',
				'post_name'    => 'trac-5272-cpt-post-inherited-attachment',
				'post_status'  => 'inherit',
				'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
				'post_parent'  => self::$posts['trac-5272-cpt']->ID,
			)
		);

		// Post for trashing.
		self::$posts['trash'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_title'   => 'trash post',
				'post_name'    => 'trash-post',
				'post_status'  => 'publish',
				'post_content' => "Prevent canonical redirect exposing post slugs.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
			)
		);

		self::$posts['trash-attachment'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'attachment',
				'post_title'   => 'trash post inherited attachment',
				'post_name'    => 'trash-post-inherited-attachment',
				'post_status'  => 'inherit',
				'post_content' => "Prevent canonical redirect exposing post via attachments.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
				'post_parent'  => self::$posts['trash']->ID,
			)
		);

		// Page for trashing.
		self::$posts['page-trash'] = $factory->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_title'   => 'trash page',
				'post_name'    => 'trash-page',
				'post_status'  => 'publish',
				'post_content' => "Prevent canonical redirect exposing page slugs.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
			)
		);
		wp_trash_post( self::$posts['trash']->ID );
		wp_trash_post( self::$posts['page-trash']->ID );
	}

	function setUp() {
		parent::setUp();
		self::setup_custom_types();
	}

	/**
	 * Set up a custom post type and private status.
	 *
	 * This needs to be called both in the class setup and
	 * test setup.
	 */
	public static function setup_custom_types() {
		// Register custom post type.
		register_post_type(
			'trac-5272-cpt',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug' => 'trac-5272-cpt',
				),
			)
		);

		// Register custom private post status.
		register_post_status(
			'trac-5272-status',
			array(
				'private' => true,
			)
		);
	}

	/**
	 * Test canonical redirect does not reveal private post slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_private_post_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['post']->ID );

		$ugly_id_request   = '/?p=' . self::$posts['post']->ID;
		$ugly_name_request = '/?name=' . self::$posts['post']->post_name;
		$pretty_request    = '/private-post-slug/';
		$pretty_expected   = '/private-post-slug/';

		if ( $can_redirect ) {
			$ugly_id_expected   = $pretty_expected;
			$ugly_name_expected = $pretty_expected;
		} else {
			$ugly_id_expected   = $ugly_id_request;
			$ugly_name_expected = $ugly_name_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $ugly_name_request, $ugly_name_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal inherited attachment slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_private_attachment_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['attachment']->ID );

		$ugly_id_request   = '/?attachment_id=' . self::$posts['attachment']->ID;
		$ugly_type_request = '/?post_type=attachment&p=' . self::$posts['attachment']->ID;
		$pretty_request    = '/private-post-slug/attachment-post-slug/';
		$pretty_expected   = '/private-post-slug/attachment-post-slug/';

		if ( $can_redirect ) {
			$ugly_id_expected   = $pretty_expected;
			$ugly_type_expected = $pretty_expected;
		} else {
			$ugly_id_expected   = $ugly_id_request;
			$ugly_type_expected = $ugly_type_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $ugly_type_request, $ugly_type_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal paged private post slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_private_post_paged_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['post']->ID );

		$ugly_id_request   = '/?page=2&p=' . self::$posts['post']->ID;
		$ugly_name_request = '/?page=2&name=' . self::$posts['post']->post_name;
		$pretty_request    = '/private-post-slug/2/';
		$pretty_expected   = '/private-post-slug/2/';

		if ( $can_redirect ) {
			$ugly_id_expected   = $pretty_expected;
			$ugly_name_expected = $pretty_expected;
		} else {
			$ugly_id_expected   = $ugly_id_request;
			$ugly_name_expected = $ugly_name_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $ugly_name_request, $ugly_name_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal private post slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_private_post_feed_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['post']->ID );

		$ugly_id_request = '/?feed=rss2&p=' . self::$posts['post']->ID;
		$pretty_request  = '/private-post-slug/feed/';
		$pretty_expected = '/private-post-slug/feed/';

		if ( $can_redirect ) {
			$ugly_id_expected = $pretty_expected;
		} else {
			$ugly_id_expected = $ugly_id_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal private page slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_private_page_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['page']->ID );

		$ugly_id_request = '/?page_id=' . self::$posts['page']->ID;
		$pretty_request  = '/private-page-slug/';
		$pretty_expected = '/private-page-slug/';

		if ( $can_redirect ) {
			$ugly_id_expected = $pretty_expected;
		} else {
			$ugly_id_expected = $ugly_id_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal private CPS slugs using the p query variable.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_cpt_with_private_custom_status_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['cpt']->ID );

		$ugly_id_request = '/?p=' . self::$posts['cpt']->ID;
		$pretty_request  = '/trac-5272-cpt/private-trac-5272-cpt-slug/';
		$pretty_expected = '/trac-5272-cpt/private-trac-5272-cpt-slug/';

		if ( $can_redirect ) {
			$ugly_id_expected = $pretty_expected;
		} else {
			$ugly_id_expected = $ugly_id_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Test canonical redirect does not reveal private post slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_post_with_private_custom_status_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$posts['cps']->ID );

		$ugly_id_request   = '/?p=' . self::$posts['cps']->ID;
		$ugly_name_request = '/?name=' . self::$posts['cps']->post_name;
		$pretty_request    = '/private-post-status-slug/';
		$pretty_expected   = '/private-post-status-slug/';

		if ( $can_redirect ) {
			$ugly_id_expected   = $pretty_expected;
			$ugly_name_expected = $pretty_expected;
		} else {
			$ugly_id_expected   = $ugly_id_request;
			$ugly_name_expected = $ugly_name_request;
		}

		$this->assertCanonical( $ugly_id_request, $ugly_id_expected );
		$this->assertCanonical( $ugly_name_request, $ugly_name_expected );
		$this->assertCanonical( $pretty_request, $pretty_expected );
	}

	/**
	 * Data provider for testing users and expected outcomes of canonical redirects.
	 *
	 * return array[] {
	 *        $user         string The user to test against.
	 *        $can_redirect bool   Whether the user should be redirected to the post.
	 * }
	 */
	public function data_trac_5272_redirect() {
		return array(
			array(
				'anon',
				false,
			),
			array(
				'subscriber',
				false,
			),
			array(
				'author',
				false,
			),
			array(
				'content_author',
				true,
			),
			array(
				'contributor',
				false,
			),
			array(
				'editor',
				true,
			),
			array(
				'administrator',
				true,
			),
		);
	}
}
