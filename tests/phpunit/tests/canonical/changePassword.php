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
	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Returns the redirect URL triggered by wp_redirect_admin_locations()
	 * for the given REQUEST_URI, or null if no redirect fires.
	 *
	 * @param string $request_uri Requested URL.
	 * @return string|null Redirect destination, null if no redirect.
	 */
	private function get_redirect_for( $request_uri ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		global $wp_query;
		$wp_query->is_404 = true;

		$captured = null;

		$capture = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			return false;
		};

		// Runs late to ensure filters added by other tests fire first.
		add_filter( 'wp_redirect', $capture, 20 );
		wp_redirect_admin_locations();
		return $captured;
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_redirects_to_profile() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		$this->assertNotNull( $redirect, 'A redirect should fire for the well-known change-password URL.' );
		$this->assertSame( admin_url( 'profile.php' ), $redirect, 'Should redirect to the profile page.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_with_trailing_slash_redirects_to_profile() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password/' );
		$this->assertNotNull( $redirect, 'A redirect should fire for the trailing-slash variant.' );
		$this->assertSame( admin_url( 'profile.php' ), $redirect, 'Trailing slash variant should also redirect to the profile page.' );
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
		$url_filter = static fn() => $custom_url;

		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) {
				$hosts[] = 'example.com';
				return $hosts;
			}
		);

		add_filter( 'wp_change_password_url', $url_filter );
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		$this->assertSame( $custom_url, $redirect );
	}
}
