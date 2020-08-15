<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_Post_Public extends WP_Canonical_UnitTestCase {

	/**
	 * User IDs.
	 *
	 * @var array
	 */
	public static $users;

	/**
	 * Private post.
	 *
	 * @var WP_Post
	 */
	public static $post;

	/**
	 * Private page.
	 *
	 * @var WP_Post
	 */
	public static $page;

	/**
	 * Private CPT.
	 *
	 * @var WP_Post
	 */
	public static $trac_5272_cpt;

	/**
	 * Private post status.
	 *
	 * @var WP_Post
	 */
	public static $trac_5272_status;

	public static function wpSetupBeforeClass( $factory ) {
		parent::wpSetupBeforeClass( $factory );
		self::$users = array(
			'anon'           => 0,
			'subscriber'     => $factory->user->create( array( 'role' => 'subscriber' ) ),
			'author'         => $factory->user->create( array( 'role' => 'author' ) ),
			'content_author' => $factory->user->create( array( 'role' => 'author' ) ),
			'contributor'    => $factory->user->create( array( 'role' => 'contributor' ) ),
			'editor'         => $factory->user->create( array( 'role' => 'editor' ) ),
			'administrator'  => $factory->user->create( array( 'role' => 'administrator' ) ),
		);

		self::$post = $factory->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Author private post',
				'post_name'    => 'private-post-slug',
				'post_status'  => 'private',
				'post_content' => "Prevent canonical redirect exposing post titles.\n\n<!--nextpage-->Page 2",
				'post_author'  => self::$users['content_author'],
			)
		);

		self::$page = $factory->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Author private page',
				'post_name'    => 'private-page-slug',
				'post_status'  => 'private',
				'post_content' => 'Prevent canonical redirect exposing page titles.',
				'post_author'  => self::$users['content_author'],
			)
		);

		self::setup_custom_types();

		self::$trac_5272_cpt = $factory->post->create_and_get(
			array(
				'post_type'    => 'trac_5272_cpt',
				'post_title'   => 'Author private trac_5272_cpt',
				'post_name'    => 'private-trac-5272-cpt-slug',
				'post_status'  => 'private',
				'post_content' => 'Prevent canonical redirect exposing trac_5272_cpt titles.',
				'post_author'  => self::$users['content_author'],
			)
		);

		self::$trac_5272_status = $factory->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Author private post status',
				'post_name'    => 'private-post-status-slug',
				'post_status'  => 'trac_5272_status',
				'post_content' => 'Prevent canonical redirect exposing post titles.',
				'post_author'  => self::$users['content_author'],
			)
		);
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
			'trac_5272_cpt',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug' => 'trac-5272-cpt',
				),
			)
		);

		// Register custom private post status.
		register_post_status(
			'trac_5272_status',
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
	public function test_canonical_post_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$post->ID );

		$ugly_id_request   = '/?p=' . self::$post->ID;
		$ugly_name_request = '/?name=' . self::$post->post_name;
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
	 * Test canonical redirect does not reveal paged private post slugs.
	 *
	 * @ticket 5272
	 * @dataProvider data_trac_5272_redirect
	 */
	public function test_canonical_post_paged_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$post->ID );

		$ugly_id_request   = '/?page=2&p=' . self::$post->ID;
		$ugly_name_request = '/?page=2&name=' . self::$post->post_name;
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
	public function test_canonical_post_feed_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$post->ID );

		$ugly_id_request = '/?feed=rss2&p=' . self::$post->ID;
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
	public function test_canonical_page_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$page->ID );

		$ugly_id_request = '/?page_id=' . self::$page->ID;
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
	public function test_canonical_trac_5272_cpt_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$trac_5272_cpt->ID );

		$ugly_id_request = '/?p=' . self::$trac_5272_cpt->ID;
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
	public function test_canonical_trac_5272_status_redirect( $user_role, $can_redirect ) {
		wp_set_current_user( self::$users[ $user_role ] );
		$this->set_permalink_structure( '/%postname%/' );
		clean_post_cache( self::$trac_5272_status->ID );

		$ugly_id_request   = '/?p=' . self::$trac_5272_status->ID;
		$ugly_name_request = '/?name=' . self::$trac_5272_status->post_name;
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
