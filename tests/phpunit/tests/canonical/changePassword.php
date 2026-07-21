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
	 * Original REQUEST_URI value.
	 *
	 * @var string
	 */
	private $original_request_uri;

	public function set_up() {
		parent::set_up();
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
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
		$wp_query->is_404 = true;

		$captured = null;

		$capture = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			// Return empty string so wp_redirect() sends no Location header
			// and doesn't exit; execution returns to our caller.
			return '';
		};

		add_filter( 'wp_redirect', $capture );
		wp_redirect_admin_locations();
		remove_filter( 'wp_redirect', $capture );

		$wp_query->is_404 = false;

		return $captured;
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_redirects_to_profile() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		$this->assertStringContainsString( 'profile.php', $redirect, 'Should redirect to the profile page.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_with_trailing_slash_does_not_redirect() {
		$redirect = $this->get_redirect_for( '/.well-known/change-password/' );
		$this->assertNull( $redirect, 'Trailing slash variant should not redirect (untrailingslashit handles exact match only).' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_well_known_change_password_no_redirect_without_pretty_permalinks() {
		$this->set_permalink_structure( '' );

		$_SERVER['REQUEST_URI'] = '/.well-known/change-password';

		global $wp_query;
		$wp_query->is_404 = true;

		$captured = null;
		$capture  = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			return '';
		};

		add_filter( 'wp_redirect', $capture );
		wp_redirect_admin_locations();
		remove_filter( 'wp_redirect', $capture );

		$wp_query->is_404 = false;

		$this->assertNull( $captured, 'Should not redirect when pretty permalinks are disabled.' );
	}

	/**
	 * @covers ::wp_redirect_admin_locations
	 */
	public function test_wp_change_password_url_filter() {
		$custom_url = 'https://example.com/my-account/change-password/';

		add_filter( 'wp_change_password_url', static fn() => $custom_url );
		$redirect = $this->get_redirect_for( '/.well-known/change-password' );
		remove_all_filters( 'wp_change_password_url' );

		$this->assertSame( $custom_url, $redirect );
	}
}
