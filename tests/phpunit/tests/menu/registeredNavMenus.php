<?php

/**
 * Tests for the `wp_nav_menus_registered` filter.
 *
 * @group menu
 * @covers ::get_registered_nav_menus
 */
class Tests_Menu_RegisteredNavMenus extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'wp_nav_menus_registered' );
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
