<?php

/**
 * Tests for the `wp_nav_menus_registered` filter.
 *
 * @group menu
 * @covers ::get_registered_nav_menus
 */
class Tests_Menu_RegisteredNavMenus extends WP_UnitTestCase {

	/**
	 * Original set of registered menu locations (location => description).
	 *
	 * @var string[]
	 */
	private $original_registered_nav_menus = array();

	/**
	 * Whether the theme supported menus before the test ran (relevant
	 * when no menus were registered).
	 *
	 * @var bool
	 */
	private $original_menus_theme_support = false;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		global $_wp_registered_nav_menus;
		$this->original_registered_nav_menus = $_wp_registered_nav_menus ?? array();
		$this->original_menus_theme_support  = current_theme_supports( 'menus' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'wp_nav_menus_registered' );

		// Unregister any locations added during the test run.
		foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
			if ( ! isset( $this->original_registered_nav_menus[ $location ] ) ) {
				unregister_nav_menu( $location );
			}
		}

		// Restore the original set of registered locations (and their descriptions).
		if ( ! empty( $this->original_registered_nav_menus ) ) {
			register_nav_menus( $this->original_registered_nav_menus );
		} elseif ( $this->original_menus_theme_support ) {
			add_theme_support( 'menus' );
		} else {
			_remove_theme_support( 'menus' );
		}

		parent::tear_down();
	}

	/**
	 * Tests that `get_registered_nav_menus()` applies the `wp_nav_menus_registered` filter.
	 *
	 * @ticket 31391
	 */
	public function test_filter_is_applied() {
		register_nav_menus(
			array(
				'primary' => 'Primary',
				'footer'  => 'Footer',
			)
		);

		add_filter(
			'wp_nav_menus_registered',
			static function ( $locations ) {
				unset( $locations['footer'] );
				return $locations;
			}
		);

		$locations = get_registered_nav_menus();

		$this->assertArrayHasKey( 'primary', $locations, 'Primary location should be present.' );
		$this->assertArrayNotHasKey( 'footer', $locations, 'Footer location should have been removed by the filter.' );
	}

	/**
	 * Tests that the `wp_nav_menus_registered` filter can reorder menu locations.
	 *
	 * @ticket 31391
	 */
	public function test_filter_can_reorder_locations() {
		register_nav_menus(
			array(
				'primary'   => 'Primary',
				'secondary' => 'Secondary',
				'footer'    => 'Footer',
			)
		);

		add_filter(
			'wp_nav_menus_registered',
			static function ( $locations ) {
				$footer = array( 'footer' => $locations['footer'] );
				unset( $locations['footer'] );
				return array_merge( $footer, $locations );
			}
		);

		$locations = get_registered_nav_menus();
		$keys      = array_keys( $locations );

		$this->assertSame( 'footer', $keys[0], 'Footer location should be first after reordering.' );
	}
}
