<?php

/**
 * Tests for the /.well-known/change-password redirect.
 *
 * @group canonical
 * @group rewrite
 * @group query
 * @ticket 51173
 */
class Tests_Canonical_ChangePassword extends WP_UnitTestCase {

	/**
	 * Whether REQUEST_URI was set before the test.
	 *
	 * @var bool
	 */
	private $request_uri_was_set;

	/**
	 * Original REQUEST_URI value.
	 *
	 * @var string
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();
		$this->request_uri_was_set  = isset( $_SERVER['REQUEST_URI'] );
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		if ( $this->request_uri_was_set ) {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}
		parent::tear_down();
	}

	/**
	 * Returns the redirect URL triggered by wp_redirect_admin_locations()
	 * for the given REQUEST_URI, or null if no redirect fires.
	 *
	 * @param string $request_uri
	 * @return string|null
	 */
	private function get_redirect_for( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		global $wp_query;
		$original_is_404  = $wp_query->is_404;
		$wp_query->is_404 = true;

		$captured = null;

		$capture = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			throw new RuntimeException( 'Redirect intercepted.' );
		};

		add_filter( 'wp_redirect', $capture );
		try {
			wp_redirect_admin_locations();
		} catch ( RuntimeException $e ) {
			// Redirect was intercepted; $captured holds the URL.
		} finally {
			remove_filter( 'wp_redirect', $capture );
			$wp_query->is_404 = $original_is_404;
		}

		return $captured;
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_redirects_to_profile() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		$this->assertNotNull( $redirect, 'A redirect should fire for the well-known change-password URL.' );
		$this->assertStringContainsString( 'profile.php', $redirect, 'Should redirect to the profile page.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_with_trailing_slash_redirects_to_profile() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password/' );
		$this->assertNotNull( $redirect, 'A redirect should fire for the trailing-slash variant.' );
		$this->assertStringContainsString( 'profile.php', $redirect, 'Trailing slash variant should also redirect to the profile page.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_no_redirect_without_pretty_permalinks() {
		$this->set_permalink_structure( '' );

		$redirect = $this->get_redirect_for( '/.well-known/change-password' );

		$this->assertNull( $redirect, 'Should not redirect when pretty permalinks are disabled.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_wp_change_password_url_filter() {
		$custom_url = 'https://example.com/my-account/change-password/';
		$filter     = static fn() => $custom_url;

		add_filter( 'wp_change_password_url', $filter );
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		remove_filter( 'wp_change_password_url', $filter );

		$this->assertSame( $custom_url, $redirect );
	}
}
