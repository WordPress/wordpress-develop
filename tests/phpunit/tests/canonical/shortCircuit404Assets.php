<?php

/**
 * Tests for wp_short_circuit_404_assets().
 *
 * @covers ::wp_short_circuit_404_assets
 *
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_ShortCircuit404Assets extends WP_UnitTestCase {

	/**
	 * Original request URI before tests run.
	 *
	 * @var string|null
	 */
	private ?string $original_request_uri = null;

	/**
	 * Sets up permalinks and disables process exit during tests.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$this->set_permalink_structure( '/%postname%/' );
		add_filter( 'wp_exit_on_short_circuit_404_assets', '__return_false' );
	}

	/**
	 * Cleans up filters and global state after each test.
	 */
	public function tear_down() {
		remove_filter( 'wp_exit_on_short_circuit_404_assets', '__return_false' );

		if ( null !== $this->original_request_uri ) {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		} else {
			$_SERVER['REQUEST_URI'] = '';
		}

		parent::tear_down();
	}

	/**
	 * Helper to configure $_SERVER['REQUEST_URI'] and $wp_query->is_404.
	 *
	 * @param string $request_uri The request URI to simulate.
	 * @param bool   $is_404      Whether the query is marked as 404.
	 */
	private function set_request( string $request_uri, bool $is_404 = true ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;

		global $wp_query;
		$wp_query->is_404 = $is_404;
	}

	/**
	 * Tests that wp_short_circuit_404_assets returns false when is_404() is false.
	 *
	 * @ticket 66354
	 */
	public function test_returns_false_when_not_404() {
		$this->set_request( '/wp-admin/installHook.js.map', false );

		$this->assertFalse( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that sourcemap (.map) requests are short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_sourcemap_file() {
		$this->set_request( '/wp-content/themes/twentytwentyfive/assets/js/nav.js.map' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that sourcemaps are short-circuited case-insensitively.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_uppercase_sourcemap_file() {
		$this->set_request( '/assets/bundle.JS.MAP' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that sourcemap requests with query strings are short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_sourcemap_with_query_string() {
		$this->set_request( '/wp-admin/installHook.js.map?ver=4.2.1' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that missing files under the admin URL are short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_missing_file_under_admin_url() {
		$admin_path = parse_url( admin_url(), PHP_URL_PATH );
		$this->set_request( $admin_path . 'installHook.js.map' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that non-sourcemap missing assets under the admin URL are short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_missing_asset_under_admin_url() {
		$admin_path = parse_url( admin_url(), PHP_URL_PATH );
		$this->set_request( $admin_path . 'custom-missing-script.js' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that missing files under the includes URL are short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_short_circuits_missing_file_under_includes_url() {
		$includes_path = parse_url( includes_url(), PHP_URL_PATH );
		$this->set_request( $includes_path . 'js/missing-library.js' );

		$this->assertTrue( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that ordinary frontend 404 requests are not short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_does_not_short_circuit_standard_frontend_404() {
		$this->set_request( '/non-existent-page/' );

		$this->assertFalse( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that frontend pages with 'wp-admin' in their slug are not falsely short-circuited.
	 *
	 * @ticket 66354
	 */
	public function test_does_not_short_circuit_url_with_admin_slug_prefix() {
		$this->set_request( '/wp-admin-conference-2026/' );

		$this->assertFalse( wp_short_circuit_404_assets() );
	}

	/**
	 * Tests that the wp_short_circuit_404_assets filter can override false to true.
	 *
	 * @ticket 66354
	 */
	public function test_filter_can_override_to_true() {
		$this->set_request( '/custom-assets/icon.png' );

		$filter = static function ( $should_short_circuit, $request_path ) {
			return str_ends_with( $request_path, '.png' );
		};

		add_filter( 'wp_short_circuit_404_assets', $filter, 10, 2 );
		$result = wp_short_circuit_404_assets();
		remove_filter( 'wp_short_circuit_404_assets', $filter );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that the wp_short_circuit_404_assets filter can override true to false.
	 *
	 * @ticket 66354
	 */
	public function test_filter_can_override_to_false() {
		$this->set_request( '/app.js.map' );

		$filter = '__return_false';

		add_filter( 'wp_short_circuit_404_assets', $filter );
		$result = wp_short_circuit_404_assets();
		remove_filter( 'wp_short_circuit_404_assets', $filter );

		$this->assertFalse( $result );
	}
}
