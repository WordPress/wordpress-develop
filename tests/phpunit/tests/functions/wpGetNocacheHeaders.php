<?php
/**
 * Tests for wp_get_nocache_headers().
 *
 * @group functions
 *
 * @covers ::wp_get_nocache_headers
 */
class Tests_Functions_wpGetNocacheHeaders extends WP_UnitTestCase {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	public static $user_id;

	/**
	 * Creates a user account for tests.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create();
	}

	/**
	 * Tests nocache headers are as expected for a logged out user.
	 *
	 * @ticket 54490
	 */
	public function test_wp_get_nocache_headers_for_logged_out_user() {
		$this->assertSameSetsWithIndex(
			array(
				'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
				'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
				'Last-Modified' => false,
			),
			wp_get_nocache_headers()
		);
	}

	/**
	 * Tests nocache headers are as expected for a logged in user.
	 *
	 * @ticket 21938
	 * @ticket 54490
	 */
	public function test_wp_get_nocache_headers_for_logged_in_user() {
		wp_set_current_user( self::$user_id );

		$this->assertSameSetsWithIndex(
			array(
				'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
				'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private',
				'Last-Modified' => false,
			),
			wp_get_nocache_headers()
		);
	}

	/**
	 * Tests the `nocache_headers` filter fires and filters the headers.
	 *
	 * @ticket 54490
	 */
	public function test_filter_nocache_headers() {
		add_filter(
			'nocache_headers',
			static function () {
				return array( 'filter_name' => 'nocache_headers' );
			}
		);

		$this->assertSameSetsWithIndex(
			array(
				'filter_name'   => 'nocache_headers',
				'Last-Modified' => false,
			),
			wp_get_nocache_headers()
		);
	}
}
