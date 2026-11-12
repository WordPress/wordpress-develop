<?php

/**
 * Tests for the `install_themes_tabs` filter.
 *
 * @group theme
 * @group admin
 *
 * @covers ::apply_filters( 'install_themes_tabs' )
 */
class Tests_Theme_InstallThemesTabs extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID without theme install/upload capabilities.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Set up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Tear down test fixtures after all tests run.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Simulates tabs computation in wp-admin/theme-install.php.
	 *
	 * @return array{tabs: array<string, string>, browse_tabs: array<string, string>, default_tab: string}
	 */
	private function get_theme_install_tabs() {
		$tabs = array(
			'popular'      => _x( 'Popular', 'themes' ),
			'new'          => _x( 'Latest', 'themes' ),
			'block-themes' => _x( 'Block Themes', 'themes' ),
			'favorites'    => _x( 'Favorites', 'themes' ),
		);

		if ( current_user_can( 'upload_themes' ) ) {
			/*
			 * No longer a real tab. Here for filter compatibility.
			 * Gets skipped in the filter links below.
			 */
			$tabs['upload'] = __( 'Upload Theme' );
		}

		/** This filter is documented in wp-admin/theme-install.php */
		$tabs = apply_filters( 'install_themes_tabs', $tabs );

		$browse_tabs = array_diff_key( $tabs, array( 'upload' => true ) );
		$default_tab = ! empty( $browse_tabs ) ? (string) array_key_first( $browse_tabs ) : 'popular';

		return array(
			'tabs'        => $tabs,
			'browse_tabs' => $browse_tabs,
			'default_tab' => $default_tab,
		);
	}

	/**
	 * Tests that default tabs for an administrator include all core browse tabs and upload.
	 *
	 * @ticket 65979
	 */
	public function test_default_tabs_for_user_with_upload_themes_cap() {
		wp_set_current_user( self::$admin_id );

		$result = $this->get_theme_install_tabs();

		$expected_tab_keys = array( 'popular', 'new', 'block-themes', 'favorites', 'upload' );
		$this->assertSame(
			$expected_tab_keys,
			array_keys( $result['tabs'] ),
			'Default tabs array should contain popular, new, block-themes, favorites, and upload keys.'
		);

		$expected_browse_keys = array( 'popular', 'new', 'block-themes', 'favorites' );
		$this->assertSame(
			$expected_browse_keys,
			array_keys( $result['browse_tabs'] ),
			'Browse tabs array should exclude upload and retain core browse tab keys.'
		);

		$this->assertSame(
			'popular',
			$result['default_tab'],
			'Default browse tab should be popular when no filter modifies the tabs array.'
		);
	}

	/**
	 * Tests that default tabs for a user without upload_themes capability omit the upload tab.
	 *
	 * @ticket 65979
	 */
	public function test_default_tabs_for_user_without_upload_themes_cap() {
		wp_set_current_user( self::$subscriber_id );

		$result = $this->get_theme_install_tabs();

		$expected_tab_keys = array( 'popular', 'new', 'block-themes', 'favorites' );
		$this->assertSame(
			$expected_tab_keys,
			array_keys( $result['tabs'] ),
			'Tabs array for user without upload capability should not contain the upload tab.'
		);

		$this->assertSame(
			$expected_tab_keys,
			array_keys( $result['browse_tabs'] ),
			'Browse tabs array should match tabs array when upload is not present.'
		);

		$this->assertSame(
			'popular',
			$result['default_tab'],
			'Default browse tab should remain popular for user without upload capability.'
		);
	}

	/**
	 * Tests adding a custom browse tab via the install_themes_tabs filter.
	 *
	 * @ticket 65979
	 */
	public function test_add_custom_tab_via_filter() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'install_themes_tabs',
			static function ( array $tabs ) {
				$tabs['featured'] = 'Featured';
				return $tabs;
			}
		);

		$result = $this->get_theme_install_tabs();

		$this->assertArrayHasKey(
			'featured',
			$result['tabs'],
			'Custom tab key "featured" should be present in the filtered tabs array.'
		);
		$this->assertArrayHasKey(
			'featured',
			$result['browse_tabs'],
			'Custom tab key "featured" should be present in browse tabs.'
		);
		$this->assertSame(
			'Featured',
			$result['browse_tabs']['featured'],
			'Custom tab title should match the filtered value.'
		);
		$this->assertSame(
			'popular',
			$result['default_tab'],
			'Default tab should remain popular when custom tab is appended.'
		);
	}

	/**
	 * Tests suppressing the upload tab via the install_themes_tabs filter.
	 *
	 * @ticket 65979
	 */
	public function test_suppress_upload_tab_via_filter() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'install_themes_tabs',
			static function ( array $tabs ) {
				unset( $tabs['upload'] );
				return $tabs;
			}
		);

		$result = $this->get_theme_install_tabs();

		$this->assertArrayNotHasKey(
			'upload',
			$result['tabs'],
			'Upload tab should be omitted when unset via install_themes_tabs.'
		);
		$this->assertArrayNotHasKey(
			'upload',
			$result['browse_tabs'],
			'Upload tab should not be present in browse tabs.'
		);
		$this->assertArrayHasKey(
			'popular',
			$result['browse_tabs'],
			'Core browse tabs should remain intact when upload tab is suppressed.'
		);
	}

	/**
	 * Tests changing the default tab when popular is removed or reordered.
	 *
	 * @ticket 65979
	 */
	public function test_reordering_tabs_changes_default_tab() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'install_themes_tabs',
			static function ( array $tabs ) {
				unset( $tabs['popular'] );
				return array( 'community' => 'Community' ) + $tabs;
			}
		);

		$result = $this->get_theme_install_tabs();

		$this->assertSame(
			'community',
			$result['default_tab'],
			'The first registered browse tab should become the default tab.'
		);
		$this->assertArrayNotHasKey(
			'popular',
			$result['browse_tabs'],
			'Popular tab should not be present in browse tabs after removal.'
		);
	}

	/**
	 * Tests that an empty browse tabs array safely falls back to popular.
	 *
	 * @ticket 65979
	 */
	public function test_empty_browse_tabs_falls_back_to_popular() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'install_themes_tabs',
			static function () {
				return array( 'upload' => 'Upload Theme' );
			}
		);

		$result = $this->get_theme_install_tabs();

		$this->assertSame(
			array(),
			$result['browse_tabs'],
			'Browse tabs should be empty when only upload is returned.'
		);
		$this->assertSame(
			'popular',
			$result['default_tab'],
			'Default tab should safely fall back to popular when browse tabs are empty.'
		);
	}
}
