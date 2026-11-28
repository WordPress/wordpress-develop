<?php

/**
 * Tests for the maybe_redirect_404() function.
 *
 * The NOBLOGREDIRECT constant cannot be undefined once set, so every test that
 * defines it runs in a separate process.
 *
 * @group ms-required
 * @group multisite
 *
 * @covers ::maybe_redirect_404
 */
class Tests_Multisite_MaybeRedirect404 extends WP_UnitTestCase {

	/**
	 * Prefix used to tell a captured redirect apart from any other exception.
	 */
	const REDIRECT_PREFIX = 'maybe_redirect_404 redirected to: ';

	/**
	 * @ticket 21573
	 */
	public function test_maybe_redirect_404_is_hooked_to_template_redirect() {
		$this->assertSame( 10, has_action( 'template_redirect', 'maybe_redirect_404' ) );
	}

	/**
	 * @ticket 21573
	 */
	public function test_should_not_redirect_when_noblogredirect_is_not_defined() {
		if ( defined( 'NOBLOGREDIRECT' ) ) {
			$this->markTestSkipped( 'NOBLOGREDIRECT is already defined in this process.' );
		}

		$this->go_to_404();

		$this->assertNull( $this->get_redirect_location() );
	}

	/**
	 * A missing page on the main site of a subdirectory install may be a request
	 * for a site that does not exist, so it is sent to NOBLOGREDIRECT.
	 *
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_redirect_404_on_main_site_of_subdirectory_install() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'This test requires a subdirectory install.' );
		}

		define( 'NOBLOGREDIRECT', 'https://example.com/no-blog/' );

		$this->go_to_404();

		$this->assertSame( 'https://example.com/no-blog/', $this->get_redirect_location() );
	}

	/**
	 * On a subdomain install a missing site never reaches the main site's 404,
	 * so a 404 on the main site is a real missing page and must not redirect.
	 *
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_redirect_404_on_main_site_of_subdomain_install() {
		if ( ! is_subdomain_install() ) {
			$this->markTestSkipped( 'This test requires a subdomain install.' );
		}

		define( 'NOBLOGREDIRECT', 'https://example.com/no-blog/' );

		$this->go_to_404();

		$this->assertNull( $this->get_redirect_location() );
	}

	/**
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_replace_siteurl_placeholder_with_network_home_url() {
		if ( is_subdomain_install() ) {
			$this->markTestSkipped( 'This test requires a subdirectory install.' );
		}

		define( 'NOBLOGREDIRECT', '%siteurl%' );

		$this->go_to_404();

		$this->assertSame( network_home_url(), $this->get_redirect_location() );
	}

	/**
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_redirect_when_request_is_not_404() {
		define( 'NOBLOGREDIRECT', 'https://example.com/no-blog/' );

		$this->go_to( home_url( '/' ) );
		$this->assertFalse( is_404(), 'The request should not be a 404.' );

		$this->assertNull( $this->get_redirect_location() );
	}

	/**
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_redirect_404_on_non_main_site() {
		define( 'NOBLOGREDIRECT', 'https://example.com/no-blog/' );

		/*
		 * The isolated process runs from standard input, so SCRIPT_FILENAME is empty.
		 * Creating a site calls wp_guess_url(), which passes dirname( SCRIPT_FILENAME )
		 * to strpos() and triggers an "Empty needle" warning on PHP < 8.0.
		 */
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'index.php';

		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );

		$this->go_to_404();
		$redirect = $this->get_redirect_location();

		restore_current_blog();

		$this->assertNull( $redirect );
	}

	/**
	 * @ticket 21573
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_redirect_when_blog_redirect_404_filter_returns_empty() {
		define( 'NOBLOGREDIRECT', 'https://example.com/no-blog/' );

		add_filter( 'blog_redirect_404', '__return_empty_string' );

		$this->go_to_404();

		$this->assertNull( $this->get_redirect_location() );
	}

	/**
	 * Requests a post that does not exist and confirms the query is a 404.
	 */
	private function go_to_404() {
		$this->go_to( home_url( '?p=' . PHP_INT_MAX ) );
		$this->assertTrue( is_404(), 'The request should be a 404.' );
	}

	/**
	 * Runs maybe_redirect_404() and returns the URL it redirects to.
	 *
	 * The function calls exit after redirecting, so the redirect is captured
	 * by throwing from the wp_redirect filter before any header is sent.
	 *
	 * @return string|null Redirect URL, or null if no redirect happened.
	 */
	private function get_redirect_location() {
		$capture = static function ( $location ) {
			throw new RuntimeException( self::REDIRECT_PREFIX . $location );
		};

		add_filter( 'wp_redirect', $capture );

		try {
			maybe_redirect_404();
		} catch ( RuntimeException $e ) {
			if ( ! str_starts_with( $e->getMessage(), self::REDIRECT_PREFIX ) ) {
				throw $e;
			}

			return substr( $e->getMessage(), strlen( self::REDIRECT_PREFIX ) );
		} finally {
			remove_filter( 'wp_redirect', $capture );
		}

		return null;
	}
}
