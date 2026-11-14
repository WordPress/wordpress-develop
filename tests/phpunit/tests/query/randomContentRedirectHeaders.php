<?php

/**
 * Tests for the HTTP headers sent on random content requests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group query
 * @group rewrite
 * @group xdebug
 *
 * @covers ::wp_random_content_redirect
 */
class Tests_Query_RandomContentRedirectHeaders extends WP_UnitTestCase {

	/**
	 * Message of the exception thrown in place of the `exit` following a redirect.
	 *
	 * @var string
	 */
	const REDIRECT_EXCEPTION_MESSAGE = 'Random content redirect.';

	/**
	 * Sets up pretty permalinks and stops redirects from ending the test run.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		self::factory()->post->create( array( 'post_status' => 'publish' ) );
		add_filter(
			'wp_redirect',
			static function (): void {
				throw new WP_Tests_Exception( self::REDIRECT_EXCEPTION_MESSAGE );
			}
		);

		/*
		 * The random redirect is sent on `template_redirect`, see go_to(). Canonical
		 * redirects run on the same action and exit after redirecting, which would
		 * end the test run.
		 */
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Requests a URL and fires the `template_redirect` action the redirect is sent on.
	 *
	 * @param string $url The URL for the request.
	 */
	public function go_to( $url ) {
		try {
			parent::go_to( $url );
			do_action( 'template_redirect' );
		} catch ( WP_Tests_Exception $e ) {
			if ( self::REDIRECT_EXCEPTION_MESSAGE !== $e->getMessage() ) {
				throw $e;
			}
		}
	}

	/**
	 * Ensure random content redirects are neither cached nor indexed, whatever the permalink.
	 *
	 * @ticket 66625
	 *
	 * @requires function xdebug_get_headers
	 *
	 * @dataProvider data_permalinks
	 *
	 * @param string|null $permalink Permalink of the selected post, or null to keep the real one.
	 */
	public function test_random_content_response_is_not_cached_or_indexed( ?string $permalink ): void {
		if ( null !== $permalink ) {
			add_filter(
				'post_link',
				static function () use ( $permalink ): string {
					return $permalink;
				}
			);
		}

		$this->go_to( home_url( '/random/' ) );

		$headers = xdebug_get_headers();

		$this->assertContains( 'Cache-Control: ' . wp_get_nocache_headers()['Cache-Control'], $headers, 'The random content response should not be cached.' );
		$this->assertContains( 'X-Robots-Tag: noindex, follow', $headers, 'The random content response should not be indexed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string|null}>
	 */
	public static function data_permalinks(): array {
		return array(
			'permalink on this site'    => array( null ),
			'permalink on another site' => array( 'https://elsewhere.example/post/' ),
			'empty permalink'           => array( '' ),
		);
	}
}
