<?php

/**
 * Tests for wp_prepare_themes_for_js().
 *
 * @group admin
 * @group themes
 *
 * @covers ::wp_prepare_themes_for_js
 */
class Tests_Admin_Includes_Theme_wpPrepareThemesForJs extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a subscriber.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Original value of `$_SERVER['REQUEST_URI']`.
	 *
	 * @var string|null
	 */
	private $orig_request_uri;

	/**
	 * Original theme directories.
	 *
	 * @var string[]
	 */
	private $orig_theme_directories;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$this->orig_request_uri       = $_SERVER['REQUEST_URI'] ?? null;
		$this->orig_theme_directories = $GLOBALS['wp_theme_directories'];

		$_SERVER['REQUEST_URI'] = '/wp-admin/themes.php';

		$GLOBALS['wp_theme_directories'] = array( DIR_TESTDATA . '/themedir1' );
		wp_clean_themes_cache();

		$this->set_current_theme( 'theme1', 'theme1' );

		wp_set_current_user( self::$admin_id );
	}

	public function tear_down() {
		if ( null === $this->orig_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->orig_request_uri;
		}

		$GLOBALS['wp_theme_directories'] = $this->orig_theme_directories;
		wp_clean_themes_cache();

		parent::tear_down();
	}

	/**
	 * Makes a fixture theme the active theme.
	 *
	 * @param string $stylesheet Stylesheet of the theme.
	 * @param string $template   Template of the theme.
	 */
	private function set_current_theme( $stylesheet, $template ) {
		add_filter(
			'stylesheet',
			static function () use ( $stylesheet ) {
				return $stylesheet;
			}
		);
		add_filter(
			'template',
			static function () use ( $template ) {
				return $template;
			}
		);
	}

	/**
	 * Gets fixture themes by stylesheet.
	 *
	 * @param string ...$stylesheets Theme stylesheets.
	 * @return WP_Theme[] Themes, keyed by stylesheet.
	 */
	private function get_themes( ...$stylesheets ) {
		$themes = array();

		foreach ( $stylesheets as $stylesheet ) {
			$themes[ $stylesheet ] = wp_get_theme( $stylesheet, DIR_TESTDATA . '/themedir1' );
		}

		return $themes;
	}

	/**
	 * Gets the prepared data of each theme, keyed by ID.
	 *
	 * @param WP_Theme[]|null $themes Themes to prepare.
	 * @return array[] Prepared theme data, keyed by theme ID.
	 */
	private function prepare( $themes ) {
		return array_column( wp_prepare_themes_for_js( $themes ), null, 'id' );
	}

	/**
	 * Asserts that a URL equals the expected URL plus a valid nonce for the action.
	 *
	 * @param string $expected Expected URL, without the nonce.
	 * @param string $action   Nonce action.
	 * @param string $url      Actual URL.
	 */
	private function assert_nonce_url( $expected, $action, $url ) {
		$this->assertMatchesRegularExpression( '/&amp;_wpnonce=([a-f0-9]+)$/', $url, 'The URL should end with a nonce.' );

		preg_match( '/&amp;_wpnonce=([a-f0-9]+)$/', $url, $matches );

		$this->assertSame( $expected, substr( $url, 0, -strlen( $matches[0] ) ), 'The URL did not match.' );
		$this->assertSame( 1, wp_verify_nonce( $matches[1], $action ), 'The nonce should be valid for the action.' );
	}

	/**
	 * Tests that a non-empty value from the pre_prepare_themes_for_js filter is returned early.
	 *
	 * @ticket 65819
	 */
	public function test_pre_prepare_themes_for_js_filter_short_circuits() {
		$themes = $this->get_themes( 'theme1' );

		$filter = new MockAction();
		add_filter( 'pre_prepare_themes_for_js', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'pre_prepare_themes_for_js',
			static function () {
				return array( 'custom' => array( 'id' => 'custom' ) );
			},
			20
		);

		$prepare_filter = new MockAction();
		add_filter( 'wp_prepare_themes_for_js', array( $prepare_filter, 'filter' ) );

		$this->assertSame( array( 'custom' => array( 'id' => 'custom' ) ), wp_prepare_themes_for_js( $themes ), 'The filtered value should be returned as is.' );
		$this->assertSame( array( array( array(), $themes, 'theme1' ) ), $filter->get_args(), 'The filter should receive an empty array, the themes and the active theme.' );
		$this->assertSame( 0, $prepare_filter->get_call_count(), 'The themes should not be prepared.' );
	}

	/**
	 * Tests the data prepared for a classic theme that is not active.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_prepares_classic_theme_data() {
		$prepared = $this->prepare( $this->get_themes( 'page-templates' ) );

		$this->assertSame( array( 'page-templates' ), array_keys( $prepared ), 'Only the given theme should be prepared, without a placeholder for the active theme.' );

		$theme = $prepared['page-templates'];

		$this->assertSame( 'Page Template Theme', $theme['name'], 'The name did not match.' );
		$this->assertFalse( $theme['parent'], 'The theme should have no parent.' );
		$this->assertFalse( $theme['active'], 'The theme should not be active.' );
		$this->assertFalse( $theme['blockTheme'], 'The theme should not be a block theme.' );
		$this->assertFalse( $theme['hasUpdate'], 'The theme should have no update.' );
		$this->assertFalse( $theme['hasPackage'], 'The theme should have no update package.' );
		$this->assertSame(
			array(
				'enabled'   => false,
				'supported' => false,
				'forced'    => null,
			),
			$theme['autoupdate'],
			'The auto-update state did not match.'
		);

		$this->assert_nonce_url(
			'http://' . WP_TESTS_DOMAIN . '/wp-admin/themes.php?action=activate&amp;stylesheet=page-templates',
			'switch-theme_page-templates',
			$theme['actions']['activate']
		);
		$this->assert_nonce_url(
			'http://' . WP_TESTS_DOMAIN . '/wp-admin/themes.php?action=delete&amp;stylesheet=page-templates',
			'delete-theme_page-templates',
			$theme['actions']['delete']
		);
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/wp-admin/customize.php?theme=page-templates&#038;return=%2Fwp-admin%2Fthemes.php',
			$theme['actions']['customize'],
			'The customize action should open the Customizer for the theme and return to the current screen.'
		);
		$this->assertNull( $theme['actions']['autoupdate'], 'There should be no auto-update action while auto-updates are disabled.' );
	}

	/**
	 * Tests that the active theme is listed first and the other themes are sorted by name.
	 *
	 * @ticket 65819
	 */
	public function test_lists_active_theme_first_then_sorts_by_name() {
		$this->set_current_theme( 'theme1', 'theme1' );

		$prepared = wp_prepare_themes_for_js( $this->get_themes( 'page-templates', 'block-theme', 'theme1' ) );

		$this->assertSame( array( 'theme1', 'block-theme', 'page-templates' ), wp_list_pluck( $prepared, 'id' ), 'The active theme should be first, then the themes by name.' );
		$this->assertSame( array( true, false, false ), wp_list_pluck( $prepared, 'active' ), 'Only the active theme should be marked as active.' );
	}

	/**
	 * Tests the parent of a child theme, and that the parent of the active child theme cannot be deleted.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_child_theme_parent_and_delete_action() {
		$this->set_current_theme( 'page-templates-child', 'page-templates' );

		$prepared = $this->prepare( $this->get_themes( 'page-templates', 'page-templates-child' ) );

		$this->assertSame( 'Page Template Theme', $prepared['page-templates-child']['parent'], 'The child theme should name its parent.' );
		$this->assertFalse( $prepared['page-templates']['parent'], 'The parent theme should have no parent.' );
		$this->assertArrayNotHasKey( 'delete', $prepared['page-templates']['actions'], 'The parent of the active child theme should not be deletable.' );
		$this->assertArrayHasKey( 'delete', $prepared['page-templates-child']['actions'], 'The active child theme should keep its delete action.' );
	}

	/**
	 * Tests that the parent of an inactive child theme can still be deleted.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_parent_of_inactive_child_theme_can_be_deleted() {
		$prepared = $this->prepare( $this->get_themes( 'page-templates', 'page-templates-child' ) );

		$this->assertNotNull( $prepared['page-templates']['actions']['delete'] );
	}

	/**
	 * Tests the customize action of block themes, which opens the Site Editor.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_block_theme_customize_action
	 *
	 * @param bool   $is_active Whether the block theme is active.
	 * @param string $expected  Expected customize action, without the scheme and domain.
	 */
	public function test_block_theme_customize_action( $is_active, $expected ) {
		if ( $is_active ) {
			$this->set_current_theme( 'block-theme', 'block-theme' );
		}

		$theme = $this->prepare( $this->get_themes( 'block-theme' ) )['block-theme'];

		$this->assertTrue( $theme['blockTheme'], 'The theme should be a block theme.' );
		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . $expected, $theme['actions']['customize'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_block_theme_customize_action() {
		return array(
			'inactive block theme is previewed' => array( false, '/wp-admin/site-editor.php?wp_theme_preview=block-theme&#038;return=%2Fwp-admin%2Fthemes.php' ),
			'active block theme'                => array( true, '/wp-admin/site-editor.php?return=%2Fwp-admin%2Fthemes.php' ),
		);
	}

	/**
	 * Tests that users without the capabilities get no theme actions.
	 *
	 * @ticket 65819
	 */
	public function test_actions_for_user_without_capabilities() {
		wp_set_current_user( self::$subscriber_id );
		add_filter( 'themes_auto_update_enabled', '__return_true' );

		$no_actions = array(
			'activate'   => null,
			'customize'  => null,
			'delete'     => null,
			'autoupdate' => null,
		);
		$prepared   = $this->prepare( $this->get_themes( 'page-templates', 'block-theme' ) );

		$this->assertSame( $no_actions, $prepared['page-templates']['actions'], 'A classic theme should have no actions.' );
		$this->assertSame( $no_actions, $prepared['block-theme']['actions'], 'A block theme should have no actions.' );
	}

	/**
	 * Tests the update data taken from the update_themes transient.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_update_data_from_transient() {
		set_site_transient(
			'update_themes',
			(object) array(
				'response'  => array(
					'page-templates' => array(
						'theme'        => 'page-templates',
						'new_version'  => '2.0',
						'package'      => 'https://downloads.wordpress.org/theme/page-templates.2.0.zip',
						'url'          => 'https://wordpress.org/themes/page-templates/',
						'requires'     => '99.0',
						'requires_php' => '5.6',
					),
					'theme1'         => array(
						'theme'       => 'theme1',
						'new_version' => '2.0',
						'url'         => 'https://wordpress.org/themes/theme1/',
						'package'     => '',
					),
				),
				'no_update' => array(
					'block-theme' => array(
						'theme'       => 'block-theme',
						'new_version' => '1.0',
					),
				),
			)
		);

		$prepared = $this->prepare( $this->get_themes( 'page-templates', 'theme1', 'block-theme', 'page-templates-child' ) );

		$this->assertTrue( $prepared['page-templates']['hasUpdate'], 'A theme in the response should have an update.' );
		$this->assertTrue( $prepared['page-templates']['hasPackage'], 'An update with a package should be installable.' );
		$this->assertSame(
			array(
				'compatibleWP'  => false,
				'compatiblePHP' => true,
			),
			$prepared['page-templates']['updateResponse'],
			'The update compatibility should use the requirements of the update.'
		);
		$this->assertTrue( $prepared['theme1']['hasUpdate'], 'A theme in the response should have an update.' );
		$this->assertFalse( $prepared['theme1']['hasPackage'], 'An update without a package should not be installable.' );
		$this->assertFalse( $prepared['block-theme']['hasUpdate'], 'A theme without an update should have no update.' );

		$this->assertSame(
			array( true, true, true, false ),
			array(
				$prepared['page-templates']['autoupdate']['supported'],
				$prepared['theme1']['autoupdate']['supported'],
				$prepared['block-theme']['autoupdate']['supported'],
				$prepared['page-templates-child']['autoupdate']['supported'],
			),
			'Auto-updates should be supported for themes known to the update API.'
		);
	}

	/**
	 * Tests the auto-update state and action.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_auto_update_states
	 *
	 * @param bool   $enabled_by_user Whether the user enabled auto-updates for the theme.
	 * @param mixed  $forced          Value returned by the auto_update_theme filter, or null to leave it unfiltered.
	 * @param array  $expected        Expected auto-update state.
	 * @param string $action          Expected auto-update action.
	 */
	public function test_auto_update_state( $enabled_by_user, $forced, $expected, $action ) {
		add_filter( 'themes_auto_update_enabled', '__return_true' );

		if ( $enabled_by_user ) {
			update_site_option( 'auto_update_themes', array( 'page-templates' ) );
		}

		if ( null !== $forced ) {
			add_filter(
				'auto_update_theme',
				static function () use ( $forced ) {
					return $forced;
				}
			);
		}

		$theme = $this->prepare( $this->get_themes( 'page-templates' ) )['page-templates'];

		$this->assertSame( $expected, $theme['autoupdate'], 'The auto-update state did not match.' );
		$this->assert_nonce_url(
			'http://' . WP_TESTS_DOMAIN . '/wp-admin/themes.php?action=' . $action . '&amp;stylesheet=page-templates',
			'updates',
			$theme['actions']['autoupdate']
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_auto_update_states() {
		return array(
			'disabled'               => array(
				false,
				null,
				array(
					'enabled'   => false,
					'supported' => false,
					'forced'    => null,
				),
				'enable-auto-update',
			),
			'enabled by the user'    => array(
				true,
				null,
				array(
					'enabled'   => true,
					'supported' => false,
					'forced'    => null,
				),
				'disable-auto-update',
			),
			'forced on by a filter'  => array(
				false,
				true,
				array(
					'enabled'   => true,
					'supported' => false,
					'forced'    => true,
				),
				'enable-auto-update',
			),
			'forced off by a filter' => array(
				true,
				false,
				array(
					'enabled'   => true,
					'supported' => false,
					'forced'    => false,
				),
				'disable-auto-update',
			),
		);
	}

	/**
	 * Tests that the prepared themes are filterable and that empty entries are removed.
	 *
	 * @ticket 65819
	 */
	public function test_wp_prepare_themes_for_js_filter() {
		$filter = new MockAction();
		add_filter( 'wp_prepare_themes_for_js', array( $filter, 'filter' ) );
		add_filter(
			'wp_prepare_themes_for_js',
			static function ( $prepared_themes ) {
				$prepared_themes['block-theme'] = array();
				$prepared_themes['custom']      = array( 'id' => 'custom' );

				return $prepared_themes;
			},
			20
		);

		$prepared = wp_prepare_themes_for_js( $this->get_themes( 'block-theme', 'page-templates' ) );

		$this->assertSame( array( 'theme1', 'block-theme', 'page-templates' ), array_keys( $filter->get_args()[0][0] ), 'The filter should receive the themes keyed by ID, starting with the active theme placeholder.' );
		$this->assertSame( array( 2, 3 ), array_keys( $prepared ), 'Empty entries should be removed without reindexing.' );
		$this->assertSame( array( 'page-templates', 'custom' ), array_values( wp_list_pluck( $prepared, 'id' ) ), 'The filtered themes should be returned.' );
	}

	/**
	 * Tests that the allowed themes and the active theme are prepared when no themes are given.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_prepares_allowed_themes_by_default() {
		$prepared = wp_prepare_themes_for_js();
		$ids      = wp_list_pluck( $prepared, 'id' );

		$this->assertSame( 'theme1', $ids[0], 'The active theme should be first.' );
		$this->assertContains( 'page-templates', $ids, 'The other installed themes should be listed.' );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'Each theme should be listed once.' );
	}

	/**
	 * Tests that only the allowed themes are prepared by default on multisite, with the active theme added first.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_prepares_allowed_themes_and_active_theme_on_multisite() {
		add_filter(
			'network_allowed_themes',
			static function () {
				return array( 'page-templates' => true );
			}
		);
		add_filter( 'site_allowed_themes', '__return_empty_array' );

		$this->assertSame( array( 'theme1', 'page-templates' ), wp_list_pluck( wp_prepare_themes_for_js(), 'id' ) );
	}
}
