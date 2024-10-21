<?php

/**
 * Test roles and capabilities via the WP_User class.
 *
 * @group user
 * @group capabilities
 */
class Tests_User_Capabilities extends WP_UnitTestCase {

	/**
	 * @var WP_User[] $users
	 */
	protected static $users = array(
		'anonymous'     => null,
		'administrator' => null,
		'editor'        => null,
		'author'        => null,
		'contributor'   => null,
		'subscriber'    => null,
	);

	/**
	 * @var WP_User $super_admin
	 */
	protected static $super_admin = null;

	/**
	 * @var int $block_id
	 */
	protected static $block_id;

	/**
	 * Temporary storage for roles for tests using filter callbacks.
	 *
	 * Used in the `test_wp_roles_init_action()` method.
	 *
	 * @var array
	 */
	private $role_test_wp_roles_init;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users       = array(
			'anonymous'     => new WP_User( 0 ),
			'administrator' => $factory->user->create_and_get( array( 'role' => 'administrator' ) ),
			'editor'        => $factory->user->create_and_get( array( 'role' => 'editor' ) ),
			'author'        => $factory->user->create_and_get( array( 'role' => 'author' ) ),
			'contributor'   => $factory->user->create_and_get( array( 'role' => 'contributor' ) ),
			'subscriber'    => $factory->user->create_and_get( array( 'role' => 'subscriber' ) ),
		);
		self::$super_admin = $factory->user->create_and_get( array( 'role' => 'contributor' ) );
		grant_super_admin( self::$super_admin->ID );

		self::$block_id = $factory->post->create(
			array(
				'post_author'  => self::$users['administrator']->ID,
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Test Block',
				'post_content' => '<!-- wp:core/paragraph --><p>Hello world!</p><!-- /wp:core/paragraph -->',
			)
		);
	}

	public function set_up() {
		parent::set_up();
		// Keep track of users we create.
		$this->flush_roles();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		unset( $this->role_test_wp_roles_init );

		parent::tear_down();
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$block_id, true );
	}


	private function flush_roles() {
		// We want to make sure we're testing against the DB, not just in-memory data.
		// This will flush everything and reload it from the DB.
		unset( $GLOBALS['wp_user_roles'] );
		global $wp_roles;
		$wp_roles = new WP_Roles();
	}

	public function meta_yes_you_can( $can, $key, $post_id, $user_id, $cap, $caps ) {
		return true;
	}

	public function meta_no_you_cant( $can, $key, $post_id, $user_id, $cap, $caps ) {
		return false;
	}

	public function meta_filter( $meta_value, $meta_key, $meta_type ) {
		return $meta_value;
	}

	private function _getSingleSitePrimitiveCaps() {
		return array(

			'unfiltered_html'         => array( 'administrator', 'editor' ),

			'activate_plugins'        => array( 'administrator' ),
			'create_users'            => array( 'administrator' ),
			'delete_plugins'          => array( 'administrator' ),
			'delete_themes'           => array( 'administrator' ),
			'delete_users'            => array( 'administrator' ),
			'edit_files'              => array( 'administrator' ),
			'edit_plugins'            => array( 'administrator' ),
			'edit_themes'             => array( 'administrator' ),
			'edit_users'              => array( 'administrator' ),
			'install_plugins'         => array( 'administrator' ),
			'install_themes'          => array( 'administrator' ),
			'update_core'             => array( 'administrator' ),
			'update_plugins'          => array( 'administrator' ),
			'update_themes'           => array( 'administrator' ),
			'edit_theme_options'      => array( 'administrator' ),
			'export'                  => array( 'administrator' ),
			'import'                  => array( 'administrator' ),
			'list_users'              => array( 'administrator' ),
			'manage_options'          => array( 'administrator' ),
			'promote_users'           => array( 'administrator' ),
			'remove_users'            => array( 'administrator' ),
			'switch_themes'           => array( 'administrator' ),
			'edit_dashboard'          => array( 'administrator' ),
			'resume_plugins'          => array( 'administrator' ),
			'resume_themes'           => array( 'administrator' ),
			'view_site_health_checks' => array( 'administrator' ),

			'moderate_comments'       => array( 'administrator', 'editor' ),
			'manage_categories'       => array( 'administrator', 'editor' ),
			'edit_others_posts'       => array( 'administrator', 'editor' ),
			'edit_pages'              => array( 'administrator', 'editor' ),
			'edit_others_pages'       => array( 'administrator', 'editor' ),
			'edit_published_pages'    => array( 'administrator', 'editor' ),
			'publish_pages'           => array( 'administrator', 'editor' ),
			'delete_pages'            => array( 'administrator', 'editor' ),
			'delete_others_pages'     => array( 'administrator', 'editor' ),
			'delete_published_pages'  => array( 'administrator', 'editor' ),
			'delete_others_posts'     => array( 'administrator', 'editor' ),
			'delete_private_posts'    => array( 'administrator', 'editor' ),
			'edit_private_posts'      => array( 'administrator', 'editor' ),
			'read_private_posts'      => array( 'administrator', 'editor' ),
			'delete_private_pages'    => array( 'administrator', 'editor' ),
			'edit_private_pages'      => array( 'administrator', 'editor' ),
			'read_private_pages'      => array( 'administrator', 'editor' ),

			'edit_published_posts'    => array( 'administrator', 'editor', 'author' ),
			'upload_files'            => array( 'administrator', 'editor', 'author' ),
			'publish_posts'           => array( 'administrator', 'editor', 'author' ),
			'delete_published_posts'  => array( 'administrator', 'editor', 'author' ),

			'edit_posts'              => array( 'administrator', 'editor', 'author', 'contributor' ),
			'delete_posts'            => array( 'administrator', 'editor', 'author', 'contributor' ),

			'read'                    => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),

			'level_10'                => array( 'administrator' ),
			'level_9'                 => array( 'administrator' ),
			'level_8'                 => array( 'administrator' ),
			'level_7'                 => array( 'administrator', 'editor' ),
			'level_6'                 => array( 'administrator', 'editor' ),
			'level_5'                 => array( 'administrator', 'editor' ),
			'level_4'                 => array( 'administrator', 'editor' ),
			'level_3'                 => array( 'administrator', 'editor' ),
			'level_2'                 => array( 'administrator', 'editor', 'author' ),
			'level_1'                 => array( 'administrator', 'editor', 'author', 'contributor' ),
			'level_0'                 => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),

			'administrator'           => array( 'administrator' ),
			'editor'                  => array( 'editor' ),
			'author'                  => array( 'author' ),
			'contributor'             => array( 'contributor' ),
			'subscriber'              => array( 'subscriber' ),

		);
	}

	private function _getMultiSitePrimitiveCaps() {
		return array(

			'unfiltered_html'         => array(),

			'activate_plugins'        => array(),
			'create_users'            => array(),
			'delete_plugins'          => array(),
			'delete_themes'           => array(),
			'delete_users'            => array(),
			'edit_files'              => array(),
			'edit_plugins'            => array(),
			'edit_themes'             => array(),
			'edit_users'              => array(),
			'install_plugins'         => array(),
			'install_themes'          => array(),
			'update_core'             => array(),
			'update_plugins'          => array(),
			'update_themes'           => array(),
			'view_site_health_checks' => array(),

			'edit_theme_options'      => array( 'administrator' ),
			'export'                  => array( 'administrator' ),
			'import'                  => array( 'administrator' ),
			'list_users'              => array( 'administrator' ),
			'manage_options'          => array( 'administrator' ),
			'promote_users'           => array( 'administrator' ),
			'remove_users'            => array( 'administrator' ),
			'switch_themes'           => array( 'administrator' ),
			'edit_dashboard'          => array( 'administrator' ),
			'resume_plugins'          => array( 'administrator' ),
			'resume_themes'           => array( 'administrator' ),

			'moderate_comments'       => array( 'administrator', 'editor' ),
			'manage_categories'       => array( 'administrator', 'editor' ),
			'edit_others_posts'       => array( 'administrator', 'editor' ),
			'edit_pages'              => array( 'administrator', 'editor' ),
			'edit_others_pages'       => array( 'administrator', 'editor' ),
			'edit_published_pages'    => array( 'administrator', 'editor' ),
			'publish_pages'           => array( 'administrator', 'editor' ),
			'delete_pages'            => array( 'administrator', 'editor' ),
			'delete_others_pages'     => array( 'administrator', 'editor' ),
			'delete_published_pages'  => array( 'administrator', 'editor' ),
			'delete_others_posts'     => array( 'administrator', 'editor' ),
			'delete_private_posts'    => array( 'administrator', 'editor' ),
			'edit_private_posts'      => array( 'administrator', 'editor' ),
			'read_private_posts'      => array( 'administrator', 'editor' ),
			'delete_private_pages'    => array( 'administrator', 'editor' ),
			'edit_private_pages'      => array( 'administrator', 'editor' ),
			'read_private_pages'      => array( 'administrator', 'editor' ),

			'edit_published_posts'    => array( 'administrator', 'editor', 'author' ),
			'upload_files'            => array( 'administrator', 'editor', 'author' ),
			'publish_posts'           => array( 'administrator', 'editor', 'author' ),
			'delete_published_posts'  => array( 'administrator', 'editor', 'author' ),

			'edit_posts'              => array( 'administrator', 'editor', 'author', 'contributor' ),
			'delete_posts'            => array( 'administrator', 'editor', 'author', 'contributor' ),

			'read'                    => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),

			'level_10'                => array( 'administrator' ),
			'level_9'                 => array( 'administrator' ),
			'level_8'                 => array( 'administrator' ),
			'level_7'                 => array( 'administrator', 'editor' ),
			'level_6'                 => array( 'administrator', 'editor' ),
			'level_5'                 => array( 'administrator', 'editor' ),
			'level_4'                 => array( 'administrator', 'editor' ),
			'level_3'                 => array( 'administrator', 'editor' ),
			'level_2'                 => array( 'administrator', 'editor', 'author' ),
			'level_1'                 => array( 'administrator', 'editor', 'author', 'contributor' ),
			'level_0'                 => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),

			'administrator'           => array( 'administrator' ),
			'editor'                  => array( 'editor' ),
			'author'                  => array( 'author' ),
			'contributor'             => array( 'contributor' ),
			'subscriber'              => array( 'subscriber' ),

		);
	}

	private function _getSingleSiteMetaCaps() {
		return array(
			'create_sites'                => array(),
			'delete_sites'                => array(),
			'manage_network'              => array(),
			'manage_sites'                => array(),
			'manage_network_users'        => array(),
			'manage_network_plugins'      => array(),
			'manage_network_themes'       => array(),
			'manage_network_options'      => array(),
			'delete_site'                 => array(),
			'upgrade_network'             => array(),

			'setup_network'               => array( 'administrator' ),
			'upload_plugins'              => array( 'administrator' ),
			'upload_themes'               => array( 'administrator' ),
			'customize'                   => array( 'administrator' ),
			'add_users'                   => array( 'administrator' ),
			'install_languages'           => array( 'administrator' ),
			'update_languages'            => array( 'administrator' ),
			'deactivate_plugins'          => array( 'administrator' ),
			'update_php'                  => array( 'administrator' ),
			'update_https'                => array( 'administrator' ),
			'export_others_personal_data' => array( 'administrator' ),
			'erase_others_personal_data'  => array( 'administrator' ),
			'manage_privacy_options'      => array( 'administrator' ),

			'edit_categories'             => array( 'administrator', 'editor' ),
			'delete_categories'           => array( 'administrator', 'editor' ),
			'manage_post_tags'            => array( 'administrator', 'editor' ),
			'edit_post_tags'              => array( 'administrator', 'editor' ),
			'delete_post_tags'            => array( 'administrator', 'editor' ),
			'edit_css'                    => array( 'administrator', 'editor' ),

			'assign_categories'           => array( 'administrator', 'editor', 'author', 'contributor' ),
			'assign_post_tags'            => array( 'administrator', 'editor', 'author', 'contributor' ),
		);
	}

	private function _getMultiSiteMetaCaps() {
		return array(
			'create_sites'                => array(),
			'delete_sites'                => array(),
			'manage_network'              => array(),
			'manage_sites'                => array(),
			'manage_network_users'        => array(),
			'manage_network_plugins'      => array(),
			'manage_network_themes'       => array(),
			'manage_network_options'      => array(),
			'setup_network'               => array(),
			'upload_plugins'              => array(),
			'upload_themes'               => array(),
			'edit_css'                    => array(),
			'upgrade_network'             => array(),
			'install_languages'           => array(),
			'update_languages'            => array(),
			'deactivate_plugins'          => array(),
			'update_php'                  => array(),
			'update_https'                => array(),
			'export_others_personal_data' => array( '' ),
			'erase_others_personal_data'  => array( '' ),
			'manage_privacy_options'      => array(),

			'customize'                   => array( 'administrator' ),
			'delete_site'                 => array( 'administrator' ),
			'add_users'                   => array( 'administrator' ),

			'edit_categories'             => array( 'administrator', 'editor' ),
			'delete_categories'           => array( 'administrator', 'editor' ),
			'manage_post_tags'            => array( 'administrator', 'editor' ),
			'edit_post_tags'              => array( 'administrator', 'editor' ),
			'delete_post_tags'            => array( 'administrator', 'editor' ),

			'assign_categories'           => array( 'administrator', 'editor', 'author', 'contributor' ),
			'assign_post_tags'            => array( 'administrator', 'editor', 'author', 'contributor' ),
		);
	}

	public function dataAllCapsAndRoles() {
		$data = array();
		$caps = $this->getAllCapsAndRoles();

		foreach ( self::$users as $role => $null ) {
			foreach ( $caps as $cap => $roles ) {
				$data[] = array(
					$role,
					$cap,
				);
			}
		}

		return $data;
	}

	/**
	 * Data provider for testing a single site install's roles.
	 *
	 * @return array[] {
	 *     Arguments for test.
	 *
	 *     @type string $role The role to test for.
	 * }
	 */
	public function data_single_site_roles_to_check() {
		return array(
			array( 'anonymous' ),
			array( 'administrator' ),
			array( 'editor' ),
			array( 'author' ),
			array( 'contributor' ),
			array( 'subscriber' ),
		);
	}

	protected function getAllCapsAndRoles() {
		return $this->getPrimitiveCapsAndRoles() + $this->getMetaCapsAndRoles();
	}

	protected function getPrimitiveCapsAndRoles() {
		if ( is_multisite() ) {
			return $this->_getMultiSitePrimitiveCaps();
		} else {
			return $this->_getSingleSitePrimitiveCaps();
		}
	}

	protected function getMetaCapsAndRoles() {
		if ( is_multisite() ) {
			return $this->_getMultiSiteMetaCaps();
		} else {
			return $this->_getSingleSiteMetaCaps();
		}
	}

	/**
	 * Test the tests.
	 */
	public function test_single_and_multisite_cap_tests_match() {
		$single_primitive = array_keys( $this->_getSingleSitePrimitiveCaps() );
		$multi_primitive  = array_keys( $this->_getMultiSitePrimitiveCaps() );
		sort( $single_primitive );
		sort( $multi_primitive );
		$this->assertSame( $single_primitive, $multi_primitive );

		$single_meta = array_keys( $this->_getSingleSiteMetaCaps() );
		$multi_meta  = array_keys( $this->_getMultiSiteMetaCaps() );
		sort( $single_meta );
		sort( $multi_meta );
		$this->assertSame( $single_meta, $multi_meta );
	}

	/**
	 * Test the tests.
	 */
	public function test_all_caps_of_users_are_being_tested() {
		$caps = $this->getPrimitiveCapsAndRoles();

		// `manage_links` is a special case.
		$this->assertSame( '0', get_option( 'link_manager_enabled' ) );
		// `unfiltered_upload` is a special case.
		$this->assertFalse( defined( 'ALLOW_UNFILTERED_UPLOADS' ) );

		foreach ( self::$users as $role => $user ) {
			if ( 'anonymous' === $role ) {
				// The anonymous role does not exist.
				$this->assertFalse( $user->exists(), "User with {$role} role should not exist" );
			} else {
				// Make sure the user is valid.
				$this->assertTrue( $user->exists(), "User with {$role} role does not exist" );
			}

			$user_caps = $user->allcaps;

			unset(
				// `manage_links` is a special case.
				$user_caps['manage_links'],
				// `unfiltered_upload` is a special case.
				$user_caps['unfiltered_upload']
			);

			$diff = array_diff( array_keys( $user_caps ), array_keys( $caps ) );

			$this->assertSame( array(), $diff, "User with {$role} role has capabilities that aren't being tested" );

		}
	}

	/**
	 * Test the tests. The administrator role has all primitive capabilities, therefore the
	 * primitive capability tests can be tested by checking that the list of tested
	 * capabilities matches those of the administrator role.
	 *
	 * @group capTestTests
	 */
	public function testPrimitiveCapsTestsAreCorrect() {
		$actual   = $this->getPrimitiveCapsAndRoles();
		$admin    = get_role( 'administrator' );
		$expected = $admin->capabilities;

		unset(
			// Role names as capabilities are a special case:
			$actual['administrator'],
			$actual['editor'],
			$actual['author'],
			$actual['subscriber'],
			$actual['contributor'],
			// The following are granted via `user_has_cap`:
			$actual['resume_plugins'],
			$actual['resume_themes'],
			$actual['view_site_health_checks']
		);

		unset(
			// `manage_links` is a special case in the caps tests.
			$expected['manage_links'],
			// `unfiltered_upload` is a special case in the caps tests.
			$expected['unfiltered_upload']
		);

		$expected = array_keys( $expected );
		$actual   = array_keys( $actual );

		$missing_primitive_cap_checks = array_diff( $expected, $actual );
		$this->assertSame( array(), $missing_primitive_cap_checks, 'These primitive capabilities are not tested' );

		$incorrect_primitive_cap_checks = array_diff( $actual, $expected );
		$this->assertSame( array(), $incorrect_primitive_cap_checks, 'These capabilities are not primitive' );
	}

	/**
	 * Test the tests. All meta capabilities should have a condition in the `map_meta_cap()`
	 * function that handles the capability.
	 *
	 * @group capTestTests
	 */
	public function testMetaCapsTestsAreCorrect() {
		$actual = $this->getMetaCapsAndRoles();
		$file   = file_get_contents( ABSPATH . WPINC . '/capabilities.php' );

		$matched = preg_match( '/^function map_meta_cap\((.*?)^\}/ms', $file, $function );
		$this->assertSame( 1, $matched );
		$this->assertNotEmpty( $function );

		$matched = preg_match_all( '/^[\t]{1,2}case \'([^\']+)/m', $function[0], $cases );
		$this->assertNotEmpty( $matched );
		$this->assertNotEmpty( $cases );

		$expected = array_flip( $cases[1] );

		unset(
			// These primitive capabilities have a 'case' in `map_meta_cap()` but aren't meta capabilities:
			$expected['unfiltered_upload'],
			$expected['unfiltered_html'],
			$expected['edit_files'],
			$expected['edit_plugins'],
			$expected['edit_themes'],
			$expected['update_plugins'],
			$expected['delete_plugins'],
			$expected['install_plugins'],
			$expected['update_themes'],
			$expected['delete_themes'],
			$expected['install_themes'],
			$expected['update_core'],
			$expected['activate_plugins'],
			$expected['edit_users'],
			$expected['delete_users'],
			$expected['create_users'],
			$expected['manage_links'],
			// Singular object meta capabilities (where an object ID is passed) are not tested:
			$expected['activate_plugin'],
			$expected['deactivate_plugin'],
			$expected['resume_plugin'],
			$expected['resume_theme'],
			$expected['remove_user'],
			$expected['promote_user'],
			$expected['edit_user'],
			$expected['delete_post'],
			$expected['delete_page'],
			$expected['edit_post'],
			$expected['edit_page'],
			$expected['read_post'],
			$expected['read_page'],
			$expected['publish_post'],
			$expected['edit_post_meta'],
			$expected['delete_post_meta'],
			$expected['add_post_meta'],
			$expected['edit_comment'],
			$expected['edit_comment_meta'],
			$expected['delete_comment_meta'],
			$expected['add_comment_meta'],
			$expected['edit_term'],
			$expected['delete_term'],
			$expected['assign_term'],
			$expected['edit_term_meta'],
			$expected['delete_term_meta'],
			$expected['add_term_meta'],
			$expected['delete_user'],
			$expected['edit_user_meta'],
			$expected['delete_user_meta'],
			$expected['add_user_meta'],
			$expected['create_app_password'],
			$expected['list_app_passwords'],
			$expected['read_app_password'],
			$expected['edit_app_password'],
			$expected['delete_app_passwords'],
			$expected['delete_app_password'],
			$expected['edit_block_binding']
		);

		$expected = array_keys( $expected );
		$actual   = array_keys( $actual );

		$missing_meta_cap_checks = array_diff( $expected, $actual );
		$this->assertSame( array(), $missing_meta_cap_checks, 'These meta capabilities are not tested' );

		$incorrect_meta_cap_checks = array_diff( $actual, $expected );
		$this->assertSame( array(), $incorrect_meta_cap_checks, 'These capabilities are not meta' );
	}

	/**
	 * Test the default capabilities of all user roles.
	 *
	 * @dataProvider dataAllCapsAndRoles
	 */
	public function test_default_caps_for_all_roles( $role, $cap ) {
		$user         = self::$users[ $role ];
		$roles_by_cap = $this->getAllCapsAndRoles();

		if ( in_array( $role, $roles_by_cap[ $cap ], true ) ) {
			$this->assertTrue( $user->has_cap( $cap ), "User with the {$role} role should have the {$cap} capability" );
			$this->assertTrue( user_can( $user, $cap ), "User with the {$role} role should have the {$cap} capability" );
		} else {
			$this->assertFalse( $user->has_cap( $cap ), "User with the {$role} role should not have the {$cap} capability" );
			$this->assertFalse( user_can( $user, $cap ), "User with the {$role} role should not have the {$cap} capability" );
		}
	}

	/**
	 * Test miscellaneous capabilities of all user roles.
	 *
	 * @dataProvider data_single_site_roles_to_check
	 */
	public function test_other_caps_for_all_roles( $role ) {
		$user   = self::$users[ $role ];
		$old_id = wp_get_current_user()->ID;
		wp_set_current_user( $user->ID );

		// Make sure the role name is correct.
		$expected_roles = array( $role );
		if ( 'anonymous' === $role ) {
			//  Anonymous role does not exist, user roles should be empty.
			$expected_roles = array();
		}
		$this->assertSame( $expected_roles, $user->roles, "User should only have the {$role} role" );

		$this->assertFalse( $user->has_cap( 'start_a_fire' ), "User with the {$role} role should not have a custom capability (test via WP_User->has_cap() method)." );
		$this->assertFalse( user_can( $user, 'start_a_fire' ), "User with the {$role} role should not have a custom capability (test by user object)." );
		$this->assertFalse( user_can( $user->ID, 'start_a_fire' ), "User with the {$role} role should not have a custom capability (test by user ID)." );
		$this->assertFalse( current_user_can( 'start_a_fire' ), "User with the {$role} role should not have a custom capability (test by current user)." );

		$this->assertFalse( $user->has_cap( 'do_not_allow' ), "User with the {$role} role should not have the do_not_allow capability (test via WP_User->has_cap() method)." );
		$this->assertFalse( user_can( $user, 'do_not_allow' ), "User with the {$role} role should not have the do_not_allow capability (test by user object)." );
		$this->assertFalse( user_can( $user->ID, 'do_not_allow' ), "User with the {$role} role should not have the do_not_allow capability (test by user ID)." );
		$this->assertFalse( current_user_can( 'do_not_allow' ), "User with the {$role} role should not have the do_not_allow capability (test by current user)." );

		$this->assertTrue( $user->has_cap( 'exist' ), "User with the {$role} role should have the exist capability (test via WP_User->has_cap() method)." );
		$this->assertTrue( user_can( $user, 'exist' ), "User with the {$role} role should have the exist capability (test by user object)." );
		$this->assertTrue( user_can( $user->ID, 'exist' ), "User with the {$role} role should have the exist capability (test by user ID)." );
		$this->assertTrue( current_user_can( 'exist' ), "User with the {$role} role should have the exist capability (test by current user)." );

		wp_set_current_user( $old_id );
	}

	/**
	 * Test user exists/does not exist as expected.
	 *
	 * @dataProvider data_single_site_roles_to_check
	 */
	public function test_user_exists_in_database( $role ) {
		$user     = self::$users[ $role ];
		$expected = true;

		if ( 'anonymous' === $role ) {
			$expected = false;
		}

		$this->assertSame( $expected, $user->exists() );
	}

	/**
	 * @ticket 41059
	 */
	public function test_do_not_allow_is_denied_for_all_roles() {
		foreach ( self::$users as $role => $user ) {

			// Test adding the cap directly to the user.
			$user->add_cap( 'do_not_allow' );
			$has_cap = $user->has_cap( 'do_not_allow' );
			$user->remove_cap( 'do_not_allow' );
			$this->assertFalse( $has_cap, "User with the {$role} role should not have the do_not_allow capability" );

			// Test adding the cap via a filter.
			add_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ), 10, 4 );
			$has_cap = $user->has_cap( 'do_not_allow' );
			remove_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ), 10, 4 );
			$this->assertFalse( $has_cap, "User with the {$role} role should not have the do_not_allow capability" );

			if ( 'anonymous' === $role ) {
				// The anonymous role does not exist.
				continue;
			}

			// Test adding the cap to the user's role.
			$role_obj = get_role( $role );
			$role_obj->add_cap( 'do_not_allow' );
			$has_cap = $user->has_cap( 'do_not_allow' );
			$role_obj->remove_cap( 'do_not_allow' );
			$this->assertFalse( $has_cap, "User with the {$role} role should not have the do_not_allow capability" );
		}
	}

	/**
	 * @group ms-required
	 * @ticket 41059
	 */
	public function test_do_not_allow_is_denied_for_super_admins() {
		// Test adding the cap directly to the user.
		self::$super_admin->add_cap( 'do_not_allow' );
		$has_cap = self::$super_admin->has_cap( 'do_not_allow' );
		self::$super_admin->remove_cap( 'do_not_allow' );
		$this->assertFalse( $has_cap, 'Super admins should not have the do_not_allow capability' );

		// Test adding the cap via a filter.
		add_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ), 10, 4 );
		$has_cap = self::$super_admin->has_cap( 'do_not_allow' );
		remove_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ), 10, 4 );
		$this->assertFalse( $has_cap, 'Super admins should not have the do_not_allow capability' );
	}

	public function grant_do_not_allow( $allcaps, $caps, $args, $user ) {
		$allcaps['do_not_allow'] = true;
		return $allcaps;
	}

	/**
	 * Special case for the link manager.
	 */
	public function test_link_manager_caps() {
		$caps = array(
			'manage_links' => array( 'administrator', 'editor' ),
		);

		$this->assertSame( '0', get_option( 'link_manager_enabled' ) );

		// No-one should have access to the link manager by default.
		foreach ( self::$users as $role => $user ) {
			foreach ( $caps as $cap => $roles ) {
				$this->assertFalse( $user->has_cap( $cap ), "User with the {$role} role should not have the {$cap} capability" );
				$this->assertFalse( user_can( $user, $cap ), "User with the {$role} role should not have the {$cap} capability" );
			}
		}

		update_option( 'link_manager_enabled', '1' );
		$this->assertSame( '1', get_option( 'link_manager_enabled' ) );

		foreach ( self::$users as $role => $user ) {
			foreach ( $caps as $cap => $roles ) {
				if ( in_array( $role, $roles, true ) ) {
					$this->assertTrue( $user->has_cap( $cap ), "User with the {$role} role should have the {$cap} capability" );
					$this->assertTrue( user_can( $user, $cap ), "User with the {$role} role should have the {$cap} capability" );
				} else {
					$this->assertFalse( $user->has_cap( $cap ), "User with the {$role} role should not have the {$cap} capability" );
					$this->assertFalse( user_can( $user, $cap ), "User with the {$role} role should not have the {$cap} capability" );
				}
			}
		}

		update_option( 'link_manager_enabled', '0' );
		$this->assertSame( '0', get_option( 'link_manager_enabled' ) );
	}

	/**
	 * Special case for unfiltered uploads.
	 */
	public function test_unfiltered_upload_caps() {
		$this->assertFalse( defined( 'ALLOW_UNFILTERED_UPLOADS' ) );

		// No-one should have this cap.
		foreach ( self::$users as $role => $user ) {
			$this->assertFalse( $user->has_cap( 'unfiltered_upload' ), "User with the {$role} role should not have the unfiltered_upload capability" );
			$this->assertFalse( user_can( $user, 'unfiltered_upload' ), "User with the {$role} role should not have the unfiltered_upload capability" );
		}
	}

	/**
	 * @dataProvider data_user_with_role_can_edit_own_post
	 *
	 * @param  string $role              User role name
	 * @param  bool   $can_edit_own_post Can users with this role edit their own posts?
	 */
	public function test_user_can_edit_comment_on_own_post( $role, $can_edit_own_post ) {
		$owner   = self::$users[ $role ];
		$post    = self::factory()->post->create_and_get(
			array(
				'post_author' => $owner->ID,
			)
		);
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $post->ID,
			)
		);

		$owner_can_edit = user_can( $owner->ID, 'edit_comment', $comment->comment_ID );
		$this->assertSame( $can_edit_own_post, $owner_can_edit );
	}

	/**
	 * @dataProvider data_user_with_role_can_edit_others_posts
	 *
	 * @param  string $role                 User role name
	 * @param  bool   $can_edit_others_post Can users with this role edit others' posts?
	 */
	public function test_user_can_edit_comment_on_others_post( $role, $can_edit_others_post ) {
		$user    = self::$users[ $role ];
		$owner   = self::factory()->user->create_and_get(
			array(
				'role' => 'editor',
			)
		);
		$post    = self::factory()->post->create_and_get(
			array(
				'post_author' => $owner->ID,
			)
		);
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => $post->ID,
			)
		);

		$user_can_edit = user_can( $user->ID, 'edit_comment', $comment->comment_ID );
		$this->assertSame( $can_edit_others_post, $user_can_edit );
	}

	public function data_user_with_role_can_edit_own_post() {
		$data = array();
		$caps = $this->getPrimitiveCapsAndRoles();

		foreach ( self::$users as $role => $null ) {
			$data[] = array(
				$role,
				in_array( $role, $caps['edit_published_posts'], true ),
			);
		}

		return $data;
	}

	public function data_user_with_role_can_edit_others_posts() {
		$data = array();
		$caps = $this->getPrimitiveCapsAndRoles();

		foreach ( self::$users as $role => $null ) {
			$data[] = array(
				$role,
				in_array( $role, $caps['edit_others_posts'], true ),
			);
		}

		return $data;
	}

	/**
	 * @group ms-required
	 */
	public function test_super_admin_caps() {
		$caps = $this->getAllCapsAndRoles();
		$user = self::$super_admin;

		$this->assertTrue( is_super_admin( $user->ID ) );

		foreach ( $caps as $cap => $roles ) {
			$this->assertTrue( $user->has_cap( $cap ), "Super Admins should have the {$cap} capability" );
			$this->assertTrue( user_can( $user, $cap ), "Super Admins should have the {$cap} capability" );
		}

		$this->assertTrue( $user->has_cap( 'start_a_fire' ), 'Super admins should have all custom capabilities' );
		$this->assertTrue( user_can( $user, 'start_a_fire' ), 'Super admins should have all custom capabilities' );

		$this->assertFalse( $user->has_cap( 'do_not_allow' ), 'Super Admins should not have the do_not_allow capability' );
		$this->assertFalse( user_can( $user, 'do_not_allow' ), 'Super Admins should not have the do_not_allow capability' );

		$this->assertFalse( defined( 'ALLOW_UNFILTERED_UPLOADS' ) );
		$this->assertFalse( $user->has_cap( 'unfiltered_upload' ), 'Super Admins should not have the unfiltered_upload capability' );
		$this->assertFalse( user_can( $user, 'unfiltered_upload' ), 'Super Admins should not have the unfiltered_upload capability' );
	}

	/**
	 * A role that doesn't exist.
	 */
	public function test_bogus_role() {
		$user = self::factory()->user->create_and_get( array( 'role' => 'invalid_role' ) );

		// Make sure the user is valid.
		$this->assertTrue( $user->exists(), 'User does not exist' );

		// Make sure the role name is correct.
		$this->assertSame( array(), $user->roles, 'User should not have any roles' );

		$caps = $this->getAllCapsAndRoles();

		foreach ( $caps as $cap => $roles ) {
			$this->assertFalse( $user->has_cap( $cap ), "User with an invalid role should not have the {$cap} capability" );
			$this->assertFalse( user_can( $user, $cap ), "User with an invalid role should not have the {$cap} capability" );
		}
	}

	/**
	 * A user with multiple roles.
	 */
	public function test_user_subscriber_contributor() {
		$user = self::$users['subscriber'];

		// Make sure the user is valid.
		$this->assertTrue( $user->exists(), 'User does not exist' );

		$user->add_role( 'contributor' );

		// User should have two roles now.
		$this->assertSame( array( 'subscriber', 'contributor' ), $user->roles );

		$caps = $this->getAllCapsAndRoles();

		foreach ( $caps as $cap => $roles ) {
			if ( array_intersect( $user->roles, $roles ) ) {
				$this->assertTrue( $user->has_cap( $cap ), "User should have the {$cap} capability" );
				$this->assertTrue( user_can( $user, $cap ), "User should have the {$cap} capability" );
			} else {
				$this->assertFalse( $user->has_cap( $cap ), "User should not have the {$cap} capability" );
				$this->assertFalse( user_can( $user, $cap ), "User should not have the {$cap} capability" );
			}
		}

		$user->remove_role( 'contributor' );
		// User should have one role now.
		$this->assertSame( array( 'subscriber' ), $user->roles );
	}

	/**
	 * Newly added empty role.
	 */
	public function test_add_empty_role() {
		global $wp_roles;

		$role_name = 'janitor';
		add_role( $role_name, 'Janitor', array() );

		$this->flush_roles();
		$this->assertTrue( $wp_roles->is_role( $role_name ) );

		$user = self::factory()->user->create_and_get( array( 'role' => $role_name ) );

		// Make sure the user is valid.
		$this->assertTrue( $user->exists(), 'User does not exist' );

		// Make sure the role name is correct.
		$this->assertSame( array( $role_name ), $user->roles );

		$caps = $this->getAllCapsAndRoles();

		foreach ( $caps as $cap => $roles ) {
			$this->assertFalse( $user->has_cap( $cap ), "User should not have the {$cap} capability" );
			$this->assertFalse( user_can( $user, $cap ), "User should not have the {$cap} capability" );
		}

		// Clean up.
		remove_role( $role_name );
		$this->flush_roles();
		$this->assertFalse( $wp_roles->is_role( $role_name ) );
	}

	/**
	 * Newly added role.
	 */
	public function test_add_role() {
		global $wp_roles;

		$role_name     = 'janitor';
		$expected_caps = array(
			'edit_posts' => true,
			'edit_pages' => true,
			'level_0'    => true,
			'level_1'    => true,
			'level_2'    => true,
		);
		add_role( $role_name, 'Janitor', $expected_caps );
		$this->flush_roles();
		$this->assertTrue( $wp_roles->is_role( $role_name ) );

		$user = self::factory()->user->create_and_get( array( 'role' => $role_name ) );

		// Make sure the user is valid.
		$this->assertTrue( $user->exists(), 'User does not exist' );

		// Make sure the role name is correct.
		$this->assertSame( array( $role_name ), $user->roles );

		$caps = $this->getPrimitiveCapsAndRoles();

		foreach ( $caps as $cap => $roles ) {
			// The user should have all the above caps.
			if ( isset( $expected_caps[ $cap ] ) ) {
				$this->assertTrue( $user->has_cap( $cap ), "User should have the {$cap} capability" );
				$this->assertTrue( user_can( $user, $cap ), "User should have the {$cap} capability" );
			} else {
				$this->assertFalse( $user->has_cap( $cap ), "User should not have the {$cap} capability" );
				$this->assertFalse( user_can( $user, $cap ), "User should not have the {$cap} capability" );
			}
		}

		// Clean up.
		remove_role( $role_name );
		$this->flush_roles();
		$this->assertFalse( $wp_roles->is_role( $role_name ) );
	}

	/**
	 * Test add_role with implied capabilities grant successfully grants capabilities.
	 */
	public function test_add_role_with_single_level_capabilities() {
		$role_name = 'janitor';
		add_role(
			$role_name,
			'Janitor',
			array(
				'level_1',
			)
		);
		$this->flush_roles();

		// Assign a user to that role.
		$id   = self::factory()->user->create( array( 'role' => $role_name ) );
		$user = new WP_User( $id );

		$this->assertTrue( $user->has_cap( 'level_1' ) );
	}

	/**
	 * Change the capabilities associated with a role and make sure the change
	 * is reflected in has_cap().
	 */
	public function test_role_add_cap() {
		global $wp_roles;
		$role_name = 'janitor';
		add_role( $role_name, 'Janitor', array( 'level_1' => true ) );
		$this->flush_roles();
		$this->assertTrue( $wp_roles->is_role( $role_name ) );

		// Assign a user to that role.
		$id = self::factory()->user->create( array( 'role' => $role_name ) );

		// Now add a cap to the role.
		$wp_roles->add_cap( $role_name, 'sweep_floor' );
		$this->flush_roles();

		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );
		$this->assertSame( array( $role_name ), $user->roles );

		// The user should have all the above caps.
		$this->assertTrue( $user->has_cap( $role_name ) );
		$this->assertTrue( $user->has_cap( 'level_1' ) );
		$this->assertTrue( $user->has_cap( 'sweep_floor' ) );

		// Shouldn't have any other caps.
		$caps = $this->getAllCapsAndRoles();
		foreach ( $caps as $cap => $roles ) {
			if ( 'level_1' !== $cap ) {
				$this->assertFalse( $user->has_cap( $cap ), "User should not have the {$cap} capability" );
			}
		}

		// Clean up.
		remove_role( $role_name );
		$this->flush_roles();
		$this->assertFalse( $wp_roles->is_role( $role_name ) );
	}

	/**
	 * Change the capabilities associated with a role and make sure the change
	 * is reflected in has_cap().
	 */
	public function test_role_remove_cap() {
		global $wp_roles;
		$role_name = 'janitor';
		add_role(
			$role_name,
			'Janitor',
			array(
				'level_1'          => true,
				'sweep_floor'      => true,
				'polish_doorknobs' => true,
			)
		);
		$this->flush_roles();
		$this->assertTrue( $wp_roles->is_role( $role_name ) );

		// Assign a user to that role.
		$id = self::factory()->user->create( array( 'role' => $role_name ) );

		// Now remove a cap from the role.
		$wp_roles->remove_cap( $role_name, 'polish_doorknobs' );
		$this->flush_roles();

		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );
		$this->assertSame( array( $role_name ), $user->roles );

		// The user should have all the above caps.
		$this->assertTrue( $user->has_cap( $role_name ) );
		$this->assertTrue( $user->has_cap( 'level_1' ) );
		$this->assertTrue( $user->has_cap( 'sweep_floor' ) );

		// Shouldn't have the removed cap.
		$this->assertFalse( $user->has_cap( 'polish_doorknobs' ) );

		// Clean up.
		remove_role( $role_name );
		$this->flush_roles();
		$this->assertFalse( $wp_roles->is_role( $role_name ) );
	}

	/**
	 * Add an extra capability to a user.
	 */
	public function test_user_add_cap() {
		// There are two contributors.
		$id_1 = self::$users['contributor']->ID;
		$id_2 = self::factory()->user->create( array( 'role' => 'contributor' ) );

		// User 1 has an extra capability.
		$user_1 = new WP_User( $id_1 );
		$this->assertTrue( $user_1->exists(), "Problem getting user $id_1" );
		$user_1->add_cap( 'publish_posts' );

		// Re-fetch both users from the DB.
		$user_1 = new WP_User( $id_1 );
		$this->assertTrue( $user_1->exists(), "Problem getting user $id_1" );
		$user_2 = new WP_User( $id_2 );
		$this->assertTrue( $user_2->exists(), "Problem getting user $id_2" );

		// Make sure they're both still contributors.
		$this->assertSame( array( 'contributor' ), $user_1->roles );
		$this->assertSame( array( 'contributor' ), $user_2->roles );

		// Check the extra cap on both users.
		$this->assertTrue( $user_1->has_cap( 'publish_posts' ) );
		$this->assertFalse( $user_2->has_cap( 'publish_posts' ) );

		// Make sure the other caps didn't get messed up.
		$caps = $this->getAllCapsAndRoles();
		foreach ( $caps as $cap => $roles ) {
			if ( in_array( 'contributor', $roles, true ) || 'publish_posts' === $cap ) {
				$this->assertTrue( $user_1->has_cap( $cap ), "User should have the {$cap} capability" );
			} else {
				$this->assertFalse( $user_1->has_cap( $cap ), "User should not have the {$cap} capability" );
			}
		}
	}

	/**
	 * Add an extra capability to a user then remove it.
	 */
	public function test_user_remove_cap() {
		// There are two contributors.
		$id_1 = self::$users['contributor']->ID;
		$id_2 = self::factory()->user->create( array( 'role' => 'contributor' ) );

		// User 1 has an extra capability.
		$user_1 = new WP_User( $id_1 );
		$this->assertTrue( $user_1->exists(), "Problem getting user $id_1" );
		$user_1->add_cap( 'publish_posts' );

		// Now remove the extra cap.
		$user_1->remove_cap( 'publish_posts' );

		// Re-fetch both users from the DB.
		$user_1 = new WP_User( $id_1 );
		$this->assertTrue( $user_1->exists(), "Problem getting user $id_1" );
		$user_2 = new WP_User( $id_2 );
		$this->assertTrue( $user_2->exists(), "Problem getting user $id_2" );

		// Make sure they're both still contributors.
		$this->assertSame( array( 'contributor' ), $user_1->roles );
		$this->assertSame( array( 'contributor' ), $user_2->roles );

		// Check the removed cap on both users.
		$this->assertFalse( $user_1->has_cap( 'publish_posts' ) );
		$this->assertFalse( $user_2->has_cap( 'publish_posts' ) );
	}

	/**
	 * Make sure the user_level is correctly set and changed with the user's role.
	 */
	public function test_user_level_update() {
		// User starts as an author.
		$id   = self::$users['author']->ID;
		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );

		// Author = user level 2.
		$this->assertEquals( 2, $user->user_level );

		// They get promoted to editor - level should get bumped to 7.
		$user->set_role( 'editor' );
		$this->assertSame( 7, $user->user_level );

		// Demoted to contributor - level is reduced to 1.
		$user->set_role( 'contributor' );
		$this->assertSame( 1, $user->user_level );

		// If they have two roles, user_level should be the max of the two.
		$user->add_role( 'editor' );
		$this->assertSame( array( 'contributor', 'editor' ), $user->roles );
		$this->assertSame( 7, $user->user_level );
	}

	public function test_user_remove_all_caps() {
		// User starts as an author.
		$id   = self::$users['author']->ID;
		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );

		// Add some extra capabilities.
		$user->add_cap( 'make_coffee' );
		$user->add_cap( 'drink_coffee' );

		// Re-fetch.
		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );

		$this->assertTrue( $user->has_cap( 'make_coffee' ) );
		$this->assertTrue( $user->has_cap( 'drink_coffee' ) );

		// All caps are removed.
		$user->remove_all_caps();

		// Re-fetch.
		$user = new WP_User( $id );
		$this->assertTrue( $user->exists(), "Problem getting user $id" );

		// All capabilities for the user should be gone.
		foreach ( $this->getAllCapsAndRoles() as $cap => $roles ) {
			$this->assertFalse( $user->has_cap( $cap ), "User should not have the {$cap} capability" );
		}

		// The extra capabilities should be gone.
		$this->assertFalse( $user->has_cap( 'make_coffee' ) );
		$this->assertFalse( $user->has_cap( 'drink_coffee' ) );

		// User level should be empty.
		$this->assertEmpty( $user->user_level );
	}

	/**
	 * Simple tests for some common meta capabilities.
	 */
	public function test_post_meta_caps() {
		// Get our author.
		$author = self::$users['author'];

		// Make a post.
		$post = self::factory()->post->create(
			array(
				'post_author' => $author->ID,
				'post_type'   => 'post',
			)
		);

		// The author of the post.
		$this->assertTrue( $author->exists(), "Problem getting user $author->ID" );

		// Add some other users.
		$admin       = self::$users['administrator'];
		$author_2    = new WP_User( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$editor      = self::$users['editor'];
		$contributor = self::$users['contributor'];

		// Administrators, editors and the post owner can edit it.
		$this->assertTrue( $admin->has_cap( 'edit_post', $post ) );
		$this->assertTrue( $author->has_cap( 'edit_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'edit_post', $post ) );
		// Other authors and contributors can't.
		$this->assertFalse( $author_2->has_cap( 'edit_post', $post ) );
		$this->assertFalse( $contributor->has_cap( 'edit_post', $post ) );

		// Administrators, editors and the post owner can delete it.
		$this->assertTrue( $admin->has_cap( 'delete_post', $post ) );
		$this->assertTrue( $author->has_cap( 'delete_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'delete_post', $post ) );
		// Other authors and contributors can't.
		$this->assertFalse( $author_2->has_cap( 'delete_post', $post ) );
		$this->assertFalse( $contributor->has_cap( 'delete_post', $post ) );

		// Administrators, editors, and authors can publish it.
		$this->assertTrue( $admin->has_cap( 'publish_post', $post ) );
		$this->assertTrue( $author->has_cap( 'publish_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'publish_post', $post ) );
		$this->assertTrue( $author_2->has_cap( 'publish_post', $post ) );
		// Contributors can't.
		$this->assertFalse( $contributor->has_cap( 'publish_post', $post ) );

		register_post_type( 'something', array( 'capabilities' => array( 'edit_posts' => 'draw_somethings' ) ) );
		$something = get_post_type_object( 'something' );
		$this->assertSame( 'draw_somethings', $something->cap->edit_posts );
		$this->assertSame( 'draw_somethings', $something->cap->create_posts );

		register_post_type(
			'something',
			array(
				'capabilities' =>
				array(
					'edit_posts'   => 'draw_somethings',
					'create_posts' => 'create_somethings',
				),
			)
		);
		$something = get_post_type_object( 'something' );
		$this->assertSame( 'draw_somethings', $something->cap->edit_posts );
		$this->assertSame( 'create_somethings', $something->cap->create_posts );
		_unregister_post_type( 'something' );

		// Test meta authorization callbacks.
		if ( function_exists( 'register_meta' ) ) {
			$this->assertTrue( $admin->has_cap( 'edit_post_meta', $post ) );
			$this->assertTrue( $admin->has_cap( 'add_post_meta', $post ) );
			$this->assertTrue( $admin->has_cap( 'delete_post_meta', $post ) );

			$this->assertFalse( $admin->has_cap( 'edit_post_meta', $post, '_protected' ) );
			$this->assertFalse( $admin->has_cap( 'add_post_meta', $post, '_protected' ) );
			$this->assertFalse( $admin->has_cap( 'delete_post_meta', $post, '_protected' ) );

			register_meta( 'post', '_protected', array( $this, 'meta_filter' ), array( $this, 'meta_yes_you_can' ) );
			$this->assertTrue( $admin->has_cap( 'edit_post_meta', $post, '_protected' ) );
			$this->assertTrue( $admin->has_cap( 'add_post_meta', $post, '_protected' ) );
			$this->assertTrue( $admin->has_cap( 'delete_post_meta', $post, '_protected' ) );

			$this->assertTrue( $admin->has_cap( 'edit_post_meta', $post, 'not_protected' ) );
			$this->assertTrue( $admin->has_cap( 'add_post_meta', $post, 'not_protected' ) );
			$this->assertTrue( $admin->has_cap( 'delete_post_meta', $post, 'not_protected' ) );

			register_meta( 'post', 'not_protected', array( $this, 'meta_filter' ), array( $this, 'meta_no_you_cant' ) );
			$this->assertFalse( $admin->has_cap( 'edit_post_meta', $post, 'not_protected' ) );
			$this->assertFalse( $admin->has_cap( 'add_post_meta', $post, 'not_protected' ) );
			$this->assertFalse( $admin->has_cap( 'delete_post_meta', $post, 'not_protected' ) );
		}
	}

	/**
	 * @ticket 27020
	 * @dataProvider data_authorless_post
	 */
	public function test_authorless_post( $status ) {
		// Make a post without an author.
		$post = self::factory()->post->create(
			array(
				'post_author' => 0,
				'post_type'   => 'post',
				'post_status' => $status,
			)
		);

		// Add an editor and contributor.
		$editor      = self::$users['editor'];
		$contributor = self::$users['contributor'];

		// Editor can publish, edit, view, and trash.
		$this->assertTrue( $editor->has_cap( 'publish_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'edit_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'delete_post', $post ) );
		$this->assertTrue( $editor->has_cap( 'read_post', $post ) );

		// A contributor cannot (except read a published post).
		$this->assertFalse( $contributor->has_cap( 'publish_post', $post ) );
		$this->assertFalse( $contributor->has_cap( 'edit_post', $post ) );
		$this->assertFalse( $contributor->has_cap( 'delete_post', $post ) );
		$this->assertSame( 'publish' === $status, $contributor->has_cap( 'read_post', $post ) );
	}

	public function data_authorless_post() {
		return array( array( 'draft' ), array( 'private' ), array( 'publish' ) );
	}

	/**
	 * @ticket 16714
	 */
	public function test_create_posts_caps() {
		$admin       = self::$users['administrator'];
		$author      = self::$users['author'];
		$editor      = self::$users['editor'];
		$contributor = self::$users['contributor'];
		$subscriber  = self::$users['subscriber'];

		// 'create_posts' isn't a real cap.
		$this->assertFalse( $admin->has_cap( 'create_posts' ) );
		$this->assertFalse( $author->has_cap( 'create_posts' ) );
		$this->assertFalse( $editor->has_cap( 'create_posts' ) );
		$this->assertFalse( $contributor->has_cap( 'create_posts' ) );
		$this->assertFalse( $subscriber->has_cap( 'create_posts' ) );

		register_post_type( 'foobar' );
		$cap = get_post_type_object( 'foobar' )->cap;

		$this->assertSame( 'edit_posts', $cap->create_posts );

		$this->assertTrue( $admin->has_cap( $cap->create_posts ) );
		$this->assertTrue( $author->has_cap( $cap->create_posts ) );
		$this->assertTrue( $editor->has_cap( $cap->create_posts ) );
		$this->assertTrue( $contributor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $subscriber->has_cap( $cap->create_posts ) );

		_unregister_post_type( 'foobar' );

		// Primitive capability 'edit_foobars' is not assigned to any users.
		register_post_type( 'foobar', array( 'capability_type' => array( 'foobar', 'foobars' ) ) );
		$cap = get_post_type_object( 'foobar' )->cap;

		$this->assertSame( 'edit_foobars', $cap->create_posts );

		$this->assertFalse( $admin->has_cap( $cap->create_posts ) );
		$this->assertFalse( $author->has_cap( $cap->create_posts ) );
		$this->assertFalse( $editor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $contributor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $subscriber->has_cap( $cap->create_posts ) );

		// Add 'edit_foobars' primitive cap to a user.
		$admin->add_cap( 'edit_foobars', true );
		$admin = new WP_User( $admin->ID );
		$this->assertTrue( $admin->has_cap( $cap->create_posts ) );
		$this->assertFalse( $author->has_cap( $cap->create_posts ) );
		$this->assertFalse( $editor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $contributor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $subscriber->has_cap( $cap->create_posts ) );

		$admin->remove_cap( 'edit_foobars' );

		_unregister_post_type( 'foobar' );

		$cap = get_post_type_object( 'attachment' )->cap;
		$this->assertSame( 'upload_files', $cap->create_posts );
		$this->assertSame( 'edit_posts', $cap->edit_posts );

		$this->assertTrue( $author->has_cap( $cap->create_posts ) );
		$this->assertTrue( $author->has_cap( $cap->edit_posts ) );
		$this->assertTrue( $contributor->has_cap( $cap->edit_posts ) );
		$this->assertFalse( $contributor->has_cap( $cap->create_posts ) );
		$this->assertFalse( $subscriber->has_cap( $cap->create_posts ) );
	}

	/**
	 * Simple tests for some common meta capabilities.
	 */
	public function test_page_meta_caps() {
		// Get our author.
		$author = self::$users['author'];

		// Make a page.
		$page = self::factory()->post->create(
			array(
				'post_author' => $author->ID,
				'post_type'   => 'page',
			)
		);

		// The author of the page.
		$this->assertTrue( $author->exists(), 'Problem getting user ' . $author->ID );

		// Add some other users.
		$admin       = self::$users['administrator'];
		$author_2    = new WP_User( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$editor      = self::$users['editor'];
		$contributor = self::$users['contributor'];

		// Administrators, editors and the post owner can edit it.
		$this->assertTrue( $admin->has_cap( 'edit_page', $page ) );
		$this->assertTrue( $editor->has_cap( 'edit_page', $page ) );
		// Other authors and contributors can't.
		$this->assertFalse( $author->has_cap( 'edit_page', $page ) );
		$this->assertFalse( $author_2->has_cap( 'edit_page', $page ) );
		$this->assertFalse( $contributor->has_cap( 'edit_page', $page ) );

		// Administrators, editors and the post owner can delete it.
		$this->assertTrue( $admin->has_cap( 'delete_page', $page ) );
		$this->assertTrue( $editor->has_cap( 'delete_page', $page ) );
		// Other authors and contributors can't.
		$this->assertFalse( $author->has_cap( 'delete_page', $page ) );
		$this->assertFalse( $author_2->has_cap( 'delete_page', $page ) );
		$this->assertFalse( $contributor->has_cap( 'delete_page', $page ) );
	}

	/**
	 * @dataProvider dataTaxonomies
	 *
	 * @ticket 35614
	 */
	public function test_taxonomy_capabilities_are_correct( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'post' );
		}

		$tax  = get_taxonomy( $taxonomy );
		$user = self::$users['administrator'];

		// Primitive capabilities for all taxonomies should match this:
		$expected = array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		);

		foreach ( $expected as $meta_cap => $primitive_cap ) {
			$caps = map_meta_cap( $tax->cap->$meta_cap, $user->ID );
			$this->assertSame(
				array(
					$primitive_cap,
				),
				$caps,
				"Meta cap: {$meta_cap}"
			);
		}
	}

	/**
	 * @dataProvider dataTaxonomies
	 *
	 * @ticket 35614
	 */
	public function test_default_taxonomy_term_cannot_be_deleted( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'post' );
		}

		$tax  = get_taxonomy( $taxonomy );
		$user = self::$users['administrator'];
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => $taxonomy,
			)
		);

		update_option( "default_{$taxonomy}", $term->term_id );

		$this->assertTrue( user_can( $user->ID, $tax->cap->delete_terms ) );
		$this->assertFalse( user_can( $user->ID, 'delete_term', $term->term_id ) );
	}

	/**
	 * @dataProvider dataTaxonomies
	 *
	 * @ticket 35614
	 */
	public function test_taxonomy_caps_map_correctly_to_their_meta_cap( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, 'post' );
		}

		$tax  = get_taxonomy( $taxonomy );
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => $taxonomy,
			)
		);

		foreach ( self::$users as $role => $user ) {
			$this->assertSame(
				user_can( $user->ID, 'edit_term', $term->term_id ),
				user_can( $user->ID, $tax->cap->edit_terms ),
				"Role: {$role}"
			);
			$this->assertSame(
				user_can( $user->ID, 'delete_term', $term->term_id ),
				user_can( $user->ID, $tax->cap->delete_terms ),
				"Role: {$role}"
			);
			$this->assertSame(
				user_can( $user->ID, 'assign_term', $term->term_id ),
				user_can( $user->ID, $tax->cap->assign_terms ),
				"Role: {$role}"
			);
		}
	}

	public function dataTaxonomies() {
		return array(
			array(
				'post_tag',
			),
			array(
				'category',
			),
			array(
				'standard_custom_taxo',
			),
		);
	}

	/**
	 * @ticket 35614
	 */
	public function test_taxonomy_capabilities_with_custom_caps_are_correct() {
		$expected = array(
			'manage_terms' => 'one',
			'edit_terms'   => 'two',
			'delete_terms' => 'three',
			'assign_terms' => 'four',
		);
		$taxonomy = 'custom_cap_taxo';
		register_taxonomy(
			$taxonomy,
			'post',
			array(
				'capabilities' => $expected,
			)
		);

		$tax  = get_taxonomy( $taxonomy );
		$user = self::$users['administrator'];

		foreach ( $expected as $meta_cap => $primitive_cap ) {
			$caps = map_meta_cap( $tax->cap->$meta_cap, $user->ID );
			$this->assertSame(
				array(
					$primitive_cap,
				),
				$caps,
				"Meta cap: {$meta_cap}"
			);
		}
	}

	/**
	 * @ticket 40891
	 */
	public function test_taxonomy_meta_capabilities_with_non_existent_terms() {
		$caps = array(
			'add_term_meta',
			'delete_term_meta',
			'edit_term_meta',
		);

		$taxonomy = 'wptests_tax';
		register_taxonomy( $taxonomy, 'post' );

		$editor = self::$users['editor'];

		$this->setExpectedIncorrectUsage( 'map_meta_cap' );
		foreach ( $caps as $cap ) {
			// `null` represents a non-existent term ID.
			$this->assertFalse( user_can( $editor->ID, $cap, null ) );
		}
	}

	/**
	 * @ticket 21786
	 */
	public function test_negative_caps() {
		$author = self::$users['author'];

		$author->add_cap( 'foo', false );
		$this->assertArrayHasKey( 'foo', $author->caps );
		$this->assertFalse( user_can( $author->ID, 'foo' ) );

		$author->remove_cap( 'foo' );
		$this->assertArrayNotHasKey( 'foo', $author->caps );
		$this->assertFalse( user_can( $author->ID, 'foo' ) );
	}

	/**
	 * @ticket 18932
	 */
	public function test_set_role_same_role() {
		$user = self::$users['administrator'];
		$caps = $user->caps;
		$this->assertNotEmpty( $user->caps );

		$user->set_role( 'administrator' );
		$this->assertNotEmpty( $user->caps );
		$this->assertSame( $caps, $user->caps );
	}

	/**
	 * @ticket 54164
	 */
	public function test_set_role_fires_remove_user_role_and_add_user_role_hooks() {
		$user = self::$users['administrator'];

		$remove_user_role = new MockAction();
		$add_user_role    = new MockAction();
		add_action( 'remove_user_role', array( $remove_user_role, 'action' ) );
		add_action( 'add_user_role', array( $add_user_role, 'action' ) );

		$user->set_role( 'editor' );
		$user->set_role( 'administrator' );
		$this->assertSame( 2, $remove_user_role->get_call_count() );
		$this->assertSame( 2, $add_user_role->get_call_count() );
	}

	/**
	 * @group can_for_site
	 */
	public function test_current_user_can_for_site() {
		global $wpdb;

		$user    = self::$users['administrator'];
		$old_uid = get_current_user_id();
		wp_set_current_user( $user->ID );

		$this->assertTrue( current_user_can_for_site( get_current_blog_id(), 'edit_posts' ) );
		$this->assertFalse( current_user_can_for_site( get_current_blog_id(), 'foo_the_bar' ) );

		if ( ! is_multisite() ) {
			$this->assertTrue( current_user_can_for_site( 12345, 'edit_posts' ) );
			$this->assertFalse( current_user_can_for_site( 12345, 'foo_the_bar' ) );
			return;
		}

		$suppress = $wpdb->suppress_errors();
		$this->assertFalse( current_user_can_for_site( 12345, 'edit_posts' ) );
		$wpdb->suppress_errors( $suppress );

		$blog_id = self::factory()->blog->create( array( 'user_id' => $user->ID ) );

		$this->assertNotWPError( $blog_id );
		$this->assertTrue( current_user_can_for_site( $blog_id, 'edit_posts' ) );
		$this->assertFalse( current_user_can_for_site( $blog_id, 'foo_the_bar' ) );

		$another_blog_id = self::factory()->blog->create( array( 'user_id' => self::$users['author']->ID ) );

		$this->assertNotWPError( $another_blog_id );

		// Verify the user doesn't have a capability
		$this->assertFalse( current_user_can_for_site( $another_blog_id, 'edit_posts' ) );

		// Add the current user to the site
		add_user_to_blog( $another_blog_id, $user->ID, 'author' );

		// Verify they now have the capability
		$this->assertTrue( current_user_can_for_site( $another_blog_id, 'edit_posts' ) );

		wp_set_current_user( $old_uid );
	}

	/**
	 * @group can_for_site
	 */
	public function test_user_can_for_site() {
		$user = self::$users['editor'];

		$this->assertTrue( user_can_for_site( $user->ID, get_current_blog_id(), 'edit_posts' ) );
		$this->assertFalse( user_can_for_site( $user->ID, get_current_blog_id(), 'foo_the_bar' ) );

		if ( ! is_multisite() ) {
			$this->assertTrue( user_can_for_site( $user->ID, 12345, 'edit_posts' ) );
			$this->assertFalse( user_can_for_site( $user->ID, 12345, 'foo_the_bar' ) );
			return;
		}

		$blog_id = self::factory()->blog->create( array( 'user_id' => $user->ID ) );

		$this->assertNotWPError( $blog_id );
		$this->assertTrue( user_can_for_site( $user->ID, $blog_id, 'edit_posts' ) );
		$this->assertFalse( user_can_for_site( $user->ID, $blog_id, 'foo_the_bar' ) );

		$author = self::$users['author'];

		// Verify another user doesn't have a capability
		$this->assertFalse( is_user_member_of_blog( $author->ID, $blog_id ) );
		$this->assertFalse( user_can_for_site( $author->ID, $blog_id, 'edit_posts' ) );

		// Add the author to the site
		add_user_to_blog( $blog_id, $author->ID, 'author' );

		// Verify they now have the capability
		$this->assertTrue( is_user_member_of_blog( $author->ID, $blog_id ) );
		$this->assertTrue( user_can_for_site( $author->ID, $blog_id, 'edit_posts' ) );

		// Verify the user doesn't have a capability for a non-existent site
		$this->assertFalse( user_can_for_site( $user->ID, -1, 'edit_posts' ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_borked_current_user_can_for_site() {
		$orig_blog_id = get_current_blog_id();
		$blog_id      = self::factory()->blog->create();

		$this->nullify_current_user();

		add_action( 'switch_blog', array( $this, 'nullify_current_user_and_keep_nullifying_user' ) );

		current_user_can_for_site( $blog_id, 'edit_posts' );

		$this->assertSame( $orig_blog_id, get_current_blog_id() );
	}

	public function nullify_current_user() {
		// Prevents fatal errors in ::tear_down()'s and other uses of restore_current_blog().
		$function_stack = wp_debug_backtrace_summary( null, 0, false );
		if ( in_array( 'restore_current_blog', $function_stack, true ) ) {
			return;
		}
		$GLOBALS['current_user'] = null;
	}

	public function nullify_current_user_and_keep_nullifying_user() {
		add_action( 'set_current_user', array( $this, 'nullify_current_user' ) );
	}

	/**
	 * @ticket 28374
	 */
	public function test_current_user_edit_caps() {
		$user = self::$users['contributor'];
		wp_set_current_user( $user->ID );

		$user->add_cap( 'publish_posts' );
		$this->assertTrue( $user->has_cap( 'publish_posts' ) );

		$user->add_cap( 'publish_pages' );
		$this->assertTrue( $user->has_cap( 'publish_pages' ) );

		$user->remove_cap( 'publish_pages' );
		$this->assertFalse( $user->has_cap( 'publish_pages' ) );

		$user->remove_cap( 'publish_posts' );
		$this->assertFalse( $user->has_cap( 'publish_posts' ) );
	}

	public function test_subscriber_cant_edit_posts() {
		$user = self::$users['subscriber'];
		wp_set_current_user( $user->ID );

		$post = self::factory()->post->create( array( 'post_author' => 1 ) );

		$this->assertFalse( current_user_can( 'edit_post', $post ) );
		$this->assertFalse( current_user_can( 'edit_post', $post + 1 ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_multisite_administrator_can_not_edit_users() {
		$user       = self::$users['administrator'];
		$other_user = self::$users['subscriber'];

		wp_set_current_user( $user->ID );

		$this->assertFalse( current_user_can( 'edit_user', $other_user->ID ) );
	}

	public function test_user_can_edit_self() {
		foreach ( self::$users as $role => $user ) {
			wp_set_current_user( $user->ID );
			$this->assertTrue( current_user_can( 'edit_user', $user->ID ), "User with role {$role} should have the capability to edit their own profile" );
		}
	}

	public function test_only_admins_and_super_admins_can_remove_users() {
		if ( is_multisite() ) {
			$this->assertTrue( user_can( self::$super_admin->ID, 'remove_user', self::$users['subscriber']->ID ) );
		}

		$this->assertTrue( user_can( self::$users['administrator']->ID, 'remove_user', self::$users['subscriber']->ID ) );

		$this->assertFalse( user_can( self::$users['editor']->ID, 'remove_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['author']->ID, 'remove_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['contributor']->ID, 'remove_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['subscriber']->ID, 'remove_user', self::$users['subscriber']->ID ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_only_super_admins_can_delete_users_on_multisite() {
		$this->assertTrue( user_can( self::$super_admin->ID, 'delete_user', self::$users['subscriber']->ID ) );

		$this->assertFalse( user_can( self::$users['administrator']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['editor']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['author']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['contributor']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['subscriber']->ID, 'delete_user', self::$users['subscriber']->ID ) );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_only_admins_can_delete_users_on_single_site() {
		$this->assertTrue( user_can( self::$users['administrator']->ID, 'delete_user', self::$users['subscriber']->ID ) );

		$this->assertFalse( user_can( self::$users['editor']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['author']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['contributor']->ID, 'delete_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['subscriber']->ID, 'delete_user', self::$users['subscriber']->ID ) );
	}

	public function test_only_admins_and_super_admins_can_promote_users() {
		if ( is_multisite() ) {
			$this->assertTrue( user_can( self::$super_admin->ID, 'promote_user', self::$users['subscriber']->ID ) );
		}

		$this->assertTrue( user_can( self::$users['administrator']->ID, 'promote_user', self::$users['subscriber']->ID ) );

		$this->assertFalse( user_can( self::$users['editor']->ID, 'promote_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['author']->ID, 'promote_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['contributor']->ID, 'promote_user', self::$users['subscriber']->ID ) );
		$this->assertFalse( user_can( self::$users['subscriber']->ID, 'promote_user', self::$users['subscriber']->ID ) );
	}

	/**
	 * @ticket 33694
	 */
	public function test_contributor_cannot_edit_scheduled_post() {

		// Add a contributor.
		$contributor = self::$users['contributor'];

		// Give them a scheduled post.
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => $contributor->ID,
				'post_status' => 'future',
			)
		);

		// Ensure contributor can't edit or trash the post.
		$this->assertFalse( user_can( $contributor->ID, 'edit_post', $post->ID ) );
		$this->assertFalse( user_can( $contributor->ID, 'delete_post', $post->ID ) );

		// Test the tests.
		$this->assertTrue( defined( 'EMPTY_TRASH_DAYS' ) );
		$this->assertNotEmpty( EMPTY_TRASH_DAYS );

		// Trash it.
		$trashed = wp_trash_post( $post->ID );
		$this->assertNotEmpty( $trashed );

		// Ensure contributor can't edit, un-trash, or delete the post.
		$this->assertFalse( user_can( $contributor->ID, 'edit_post', $post->ID ) );
		$this->assertFalse( user_can( $contributor->ID, 'delete_post', $post->ID ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_multisite_administrator_with_manage_network_users_can_edit_users() {
		$user = self::$users['administrator'];
		$user->add_cap( 'manage_network_users' );
		$other_user = self::$users['subscriber'];

		wp_set_current_user( $user->ID );

		$can_edit_user = current_user_can( 'edit_user', $other_user->ID );

		$user->remove_cap( 'manage_network_users' );

		$this->assertTrue( $can_edit_user );
	}

	/**
	 * @group ms-required
	 */
	public function test_multisite_administrator_with_manage_network_users_can_not_edit_super_admin() {
		$user = self::$users['administrator'];
		$user->add_cap( 'manage_network_users' );

		wp_set_current_user( $user->ID );

		$can_edit_user = current_user_can( 'edit_user', self::$super_admin->ID );

		$user->remove_cap( 'manage_network_users' );

		$this->assertFalse( $can_edit_user );
	}

	/**
	 * @ticket 16956
	 * @expectedIncorrectUsage map_meta_cap
	 */
	public function test_require_edit_others_posts_if_post_type_doesnt_exist() {
		register_post_type( 'existed' );
		$post_id = self::factory()->post->create( array( 'post_type' => 'existed' ) );
		_unregister_post_type( 'existed' );

		$subscriber_id = self::$users['subscriber']->ID;
		$editor_id     = self::$users['editor']->ID;

		foreach ( array( 'delete_post', 'edit_post', 'read_post', 'publish_post' ) as $cap ) {
			wp_set_current_user( $subscriber_id );
			$this->assertSame( array( 'edit_others_posts' ), map_meta_cap( $cap, $subscriber_id, $post_id ) );
			$this->assertFalse( current_user_can( $cap, $post_id ) );

			wp_set_current_user( $editor_id );
			$this->assertSame( array( 'edit_others_posts' ), map_meta_cap( $cap, $editor_id, $post_id ) );
			$this->assertTrue( current_user_can( $cap, $post_id ) );
		}
	}

	/**
	 * @ticket 48653
	 * @expectedIncorrectUsage map_meta_cap
	 */
	public function test_require_edit_others_posts_if_post_status_doesnt_exist() {
		register_post_status( 'existed' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'existed' ) );
		_unregister_post_status( 'existed' );

		$subscriber_id = self::$users['subscriber']->ID;
		$editor_id     = self::$users['editor']->ID;

		foreach ( array( 'read_post', 'read_page' ) as $cap ) {
			wp_set_current_user( $subscriber_id );
			$this->assertSame( array( 'edit_others_posts' ), map_meta_cap( $cap, $subscriber_id, $post_id ) );
			$this->assertFalse( current_user_can( $cap, $post_id ) );

			wp_set_current_user( $editor_id );
			$this->assertSame( array( 'edit_others_posts' ), map_meta_cap( $cap, $editor_id, $post_id ) );
			$this->assertTrue( current_user_can( $cap, $post_id ) );
		}
	}

	/**
	 * @ticket 17253
	 */
	public function test_cpt_with_page_capability_type() {
		register_post_type(
			'page_capability',
			array(
				'capability_type' => 'page',
			)
		);

		$cpt = get_post_type_object( 'page_capability' );

		$admin       = self::$users['administrator'];
		$editor      = self::$users['editor'];
		$author      = self::$users['author'];
		$contributor = self::$users['contributor'];

		$this->assertSame( 'edit_pages', $cpt->cap->edit_posts );
		$this->assertTrue( user_can( $admin->ID, $cpt->cap->edit_posts ) );
		$this->assertTrue( user_can( $editor->ID, $cpt->cap->edit_posts ) );
		$this->assertFalse( user_can( $author->ID, $cpt->cap->edit_posts ) );
		$this->assertFalse( user_can( $contributor->ID, $cpt->cap->edit_posts ) );

		$admin_post = self::factory()->post->create_and_get(
			array(
				'post_author' => $admin->ID,
				'post_type'   => 'page_capability',
			)
		);

		$this->assertTrue( user_can( $admin->ID, 'edit_post', $admin_post->ID ) );
		$this->assertTrue( user_can( $editor->ID, 'edit_post', $admin_post->ID ) );
		$this->assertFalse( user_can( $author->ID, 'edit_post', $admin_post->ID ) );
		$this->assertFalse( user_can( $contributor->ID, 'edit_post', $admin_post->ID ) );

		$author_post = self::factory()->post->create_and_get(
			array(
				'post_author' => $author->ID,
				'post_type'   => 'page_capability',
			)
		);

		$this->assertTrue( user_can( $admin->ID, 'edit_post', $author_post->ID ) );
		$this->assertTrue( user_can( $editor->ID, 'edit_post', $author_post->ID ) );
		$this->assertFalse( user_can( $author->ID, 'edit_post', $author_post->ID ) );
		$this->assertFalse( user_can( $contributor->ID, 'edit_post', $author_post->ID ) );

		_unregister_post_type( 'page_capability' );
	}

	public function test_non_logged_in_users_have_no_capabilities() {
		$this->assertFalse( is_user_logged_in() );

		$caps = $this->getAllCapsAndRoles();

		foreach ( $caps as $cap => $roles ) {
			$this->assertFalse( current_user_can( $cap ), "Non-logged-in user should not have the {$cap} capability" );
		}

		// Special cases for link manager and unfiltered uploads.
		$this->assertFalse( current_user_can( 'manage_links' ), 'Non-logged-in user should not have the manage_links capability' );
		$this->assertFalse( current_user_can( 'unfiltered_upload' ), 'Non-logged-in user should not have the unfiltered_upload capability' );

		$this->assertFalse( current_user_can( 'start_a_fire' ), 'Non-logged-in user should not have a custom capability' );
		$this->assertFalse( current_user_can( 'do_not_allow' ), 'Non-logged-in user should not have the do_not_allow capability' );
	}

	/**
	 * @ticket 35488
	 */
	public function test_wp_logout_should_clear_current_user() {
		$user_id = self::$users['author']->ID;
		wp_set_current_user( $user_id );

		wp_logout();

		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * @ticket 23016
	 */
	public function test_wp_roles_init_action() {
		$this->role_test_wp_roles_init = array(
			'role' => 'test_wp_roles_init',
			'info' => array(
				'name'         => 'Test WP Roles Init',
				'capabilities' => array( 'testing_magic' => true ),
			),
		);
		add_action( 'wp_roles_init', array( $this, '_hook_wp_roles_init' ), 10, 1 );

		$wp_roles = new WP_Roles();

		remove_action( 'wp_roles_init', array( $this, '_hook_wp_roles_init' ) );

		$expected = new WP_Role( $this->role_test_wp_roles_init['role'], $this->role_test_wp_roles_init['info']['capabilities'] );

		$role = $wp_roles->get_role( $this->role_test_wp_roles_init['role'] );

		$this->assertEquals( $expected, $role );
		$this->assertContains( $this->role_test_wp_roles_init['info']['name'], $wp_roles->role_names );
	}

	public function _hook_wp_roles_init( $wp_roles ) {
		$wp_roles->add_role( $this->role_test_wp_roles_init['role'], $this->role_test_wp_roles_init['info']['name'], $this->role_test_wp_roles_init['info']['capabilities'] );
	}

	/**
	 * @ticket 23016
	 * @expectedDeprecated WP_Roles::reinit
	 */
	public function test_wp_roles_reinit_deprecated() {
		$wp_roles = new WP_Roles();
		$wp_roles->reinit();
	}

	/**
	 * @ticket 38412
	 */
	public function test_no_one_can_edit_user_meta_for_non_existent_term() {
		wp_set_current_user( self::$super_admin->ID );
		$this->assertFalse( current_user_can( 'edit_user_meta', 999999 ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_can_edit_user_meta() {
		wp_set_current_user( self::$users['administrator']->ID );
		if ( is_multisite() ) {
			grant_super_admin( self::$users['administrator']->ID );
		}
		$this->assertTrue( current_user_can( 'edit_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_cannot_edit_user_meta() {
		wp_set_current_user( self::$users['editor']->ID );
		$this->assertFalse( current_user_can( 'edit_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_no_one_can_delete_user_meta_for_non_existent_term() {
		wp_set_current_user( self::$super_admin->ID );
		$this->assertFalse( current_user_can( 'delete_user_meta', 999999, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_can_delete_user_meta() {
		wp_set_current_user( self::$users['administrator']->ID );
		if ( is_multisite() ) {
			grant_super_admin( self::$users['administrator']->ID );
		}
		$this->assertTrue( current_user_can( 'delete_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_cannot_delete_user_meta() {
		wp_set_current_user( self::$users['editor']->ID );
		$this->assertFalse( current_user_can( 'delete_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_no_one_can_add_user_meta_for_non_existent_term() {
		wp_set_current_user( self::$super_admin->ID );
		$this->assertFalse( current_user_can( 'add_user_meta', 999999, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_can_add_user_meta() {
		wp_set_current_user( self::$users['administrator']->ID );
		if ( is_multisite() ) {
			grant_super_admin( self::$users['administrator']->ID );
		}
		$this->assertTrue( current_user_can( 'add_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 38412
	 */
	public function test_user_cannot_add_user_meta() {
		wp_set_current_user( self::$users['editor']->ID );
		$this->assertFalse( current_user_can( 'add_user_meta', self::$users['subscriber']->ID, 'foo' ) );
	}

	/**
	 * @ticket 39063
	 * @group ms-required
	 */
	public function test_only_super_admins_can_remove_themselves_on_multisite() {
		$this->assertTrue( user_can( self::$super_admin->ID, 'remove_user', self::$super_admin->ID ) );

		$this->assertFalse( user_can( self::$users['administrator']->ID, 'remove_user', self::$users['administrator']->ID ) );
		$this->assertFalse( user_can( self::$users['editor']->ID, 'remove_user', self::$users['editor']->ID ) );
		$this->assertFalse( user_can( self::$users['author']->ID, 'remove_user', self::$users['author']->ID ) );
		$this->assertFalse( user_can( self::$users['contributor']->ID, 'remove_user', self::$users['contributor']->ID ) );
		$this->assertFalse( user_can( self::$users['subscriber']->ID, 'remove_user', self::$users['subscriber']->ID ) );
	}

	/**
	 * @ticket 36961
	 * @group ms-required
	 */
	public function test_init_user_caps_for_different_site() {
		global $wpdb;

		$site_id = self::factory()->blog->create( array( 'user_id' => self::$users['administrator']->ID ) );

		switch_to_blog( $site_id );

		$role_name = 'uploader';
		add_role(
			$role_name,
			'Uploader',
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
		add_user_to_blog( $site_id, self::$users['subscriber']->ID, $role_name );

		restore_current_blog();

		$user = new WP_User( self::$users['subscriber']->ID, '', $site_id );
		$this->assertTrue( $user->has_cap( 'upload_files' ) );
	}

	/**
	 * @ticket 36961
	 * @group ms-required
	 */
	public function test_init_user_caps_for_different_site_by_user_switch() {
		global $wpdb;

		$user = new WP_User( self::$users['subscriber']->ID );

		$site_id = self::factory()->blog->create( array( 'user_id' => self::$users['administrator']->ID ) );

		switch_to_blog( $site_id );

		$role_name = 'uploader';
		add_role(
			$role_name,
			'Uploader',
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
		add_user_to_blog( $site_id, self::$users['subscriber']->ID, $role_name );

		restore_current_blog();

		$user->for_site( $site_id );
		$this->assertTrue( $user->has_cap( 'upload_files' ) );
	}

	/**
	 * @ticket 36961
	 */
	public function test_get_caps_data() {
		global $wpdb;

		$custom_caps = array(
			'do_foo' => true,
			'do_bar' => false,
		);

		// Test `WP_User::get_caps_data()` by manually setting capabilities metadata.
		update_user_meta( self::$users['subscriber']->ID, $wpdb->get_blog_prefix( get_current_blog_id() ) . 'capabilities', $custom_caps );

		$user = new WP_User( self::$users['subscriber']->ID );
		$this->assertSame( $custom_caps, $user->caps );
	}

	/**
	 * @ticket 36961
	 */
	public function test_user_get_site_id_default() {
		$user = new WP_User( self::$users['subscriber']->ID );
		$this->assertSame( get_current_blog_id(), $user->get_site_id() );
	}

	/**
	 * @ticket 36961
	 */
	public function test_user_get_site_id() {
		global $wpdb;

		// Suppressing errors here allows to get around creating an actual site,
		// which is unnecessary for this test.
		$suppress = $wpdb->suppress_errors();
		$user     = new WP_User( self::$users['subscriber']->ID, '', 333 );
		$wpdb->suppress_errors( $suppress );

		$this->assertSame( 333, $user->get_site_id() );
	}

	/**
	 * @ticket 38645
	 * @group ms-required
	 */
	public function test_init_roles_for_different_site() {
		global $wpdb;

		$site_id = self::factory()->blog->create();

		switch_to_blog( $site_id );

		$role_name = 'uploader';
		add_role(
			$role_name,
			'Uploader',
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);

		restore_current_blog();

		$wp_roles = wp_roles();
		$wp_roles->for_site( $site_id );

		$this->assertArrayHasKey( $role_name, $wp_roles->role_objects );
	}

	/**
	 * @ticket 38645
	 */
	public function test_get_roles_data() {
		global $wpdb;

		$custom_roles = array(
			'test_role' => array(
				'name'         => 'Test Role',
				'capabilities' => array(
					'do_foo' => true,
					'do_bar' => false,
				),
			),
		);

		// Test `WP_Roles::get_roles_data()` by manually setting the roles option.
		update_option( $wpdb->get_blog_prefix( get_current_blog_id() ) . 'user_roles', $custom_roles );

		$roles = new WP_Roles();
		$this->assertSame( $custom_roles, $roles->roles );
	}

	/**
	 * @ticket 38645
	 */
	public function test_roles_get_site_id_default() {
		$roles = new WP_Roles();
		$this->assertSame( get_current_blog_id(), $roles->get_site_id() );
	}

	/**
	 * @ticket 38645
	 */
	public function test_roles_get_site_id() {
		global $wpdb;

		// Suppressing errors here allows to get around creating an actual site,
		// which is unnecessary for this test.
		$suppress = $wpdb->suppress_errors();
		$roles    = new WP_Roles( 333 );
		$wpdb->suppress_errors( $suppress );

		$this->assertSame( 333, $roles->get_site_id() );
	}

	/**
	 * @dataProvider data_block_caps
	 */
	public function test_block_caps( $role, $cap, $use_post, $expected ) {
		if ( $use_post ) {
			$this->assertSame( $expected, self::$users[ $role ]->has_cap( $cap, self::$block_id ) );
		} else {
			$this->assertSame( $expected, self::$users[ $role ]->has_cap( $cap ) );
		}
	}

	public function data_block_caps() {
		$post_caps = array(
			'edit_block',
			'read_block',
			'delete_block',
		);

		$all_caps = array(
			'edit_block',
			'read_block',
			'delete_block',
			'edit_blocks',
			'edit_others_blocks',
			'publish_blocks',
			'read_private_blocks',
			'delete_blocks',
			'delete_private_blocks',
			'delete_published_blocks',
			'delete_others_blocks',
			'edit_private_blocks',
			'edit_published_blocks',
		);

		$roles = array(
			'administrator' => $all_caps,
			'editor'        => $all_caps,
			'author'        => array(
				'read_block',
				'edit_blocks',
				'publish_blocks',
				'delete_blocks',
				'delete_published_blocks',
				'edit_published_blocks',
			),
			'contributor'   => array(
				'read_block',
				'edit_blocks',
				'delete_blocks',
			),
			'subscriber'    => array(),
		);

		$data = array();

		foreach ( $roles as $role => $caps ) {
			foreach ( $caps as $cap ) {
				$use_post = in_array( $cap, $post_caps, true );
				$data[]   = array( $role, $cap, $use_post, true );
			}

			foreach ( $all_caps as $cap ) {
				if ( ! in_array( $cap, $caps, true ) ) {
					$use_post = in_array( $cap, $post_caps, true );
					$data[]   = array( $role, $cap, $use_post, false );
				}
			}
		}

		return $data;
	}

	/**
	 * Test `edit_block_binding` meta capability is properly mapped.
	 *
	 * @ticket 61945
	 */
	public function test_edit_block_binding_caps_are_mapped_correctly() {
		$author = self::$users['administrator'];
		$post   = self::factory()->post->create_and_get(
			array(
				'post_author' => $author->ID,
				'post_type'   => 'post',
			)
		);

		foreach ( self::$users as $role => $user ) {
			// It should map to `edit_{post_type}` if editing a post.
			$this->assertSame(
				user_can( $user->ID, 'edit_post', $post->ID ),
				user_can(
					$user->ID,
					'edit_block_binding',
					new WP_Block_Editor_Context(
						array(
							'post' => $post,
							'name' => 'core/edit-post',
						)
					)
				),
				"Role: {$role} in post editing"
			);
			// It should map to `edit_theme_options` if editing a template.
			$this->assertSame(
				user_can( $user->ID, 'edit_theme_options' ),
				user_can(
					$user->ID,
					'edit_block_binding',
					new WP_Block_Editor_Context(
						array(
							'post' => null,
							'name' => 'core/edit-site',
						)
					)
				),
				"Role: {$role} in template editing"
			);
		}
	}
}
