<?php

/**
 * Tests for rest_get_avatar_sizes() and rest_get_avatar_urls().
 *
 * @group restapi
 */
class Tests_REST_API_RestGetAvatarSizes extends WP_UnitTestCase {

	/**
	 * User fixture.
	 *
	 * @var int
	 */
	private static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_email' => 'avatar-test@example.com',
			)
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::rest_get_avatar_sizes
	 */
	public function test_returns_default_avatar_sizes() {
		$this->assertSame( array( 24, 48, 96 ), rest_get_avatar_sizes() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::rest_get_avatar_sizes
	 */
	public function test_filter_can_modify_avatar_sizes() {
		add_filter(
			'rest_avatar_sizes',
			static function () {
				return array( 32, 64 );
			}
		);

		$this->assertSame( array( 32, 64 ), rest_get_avatar_sizes() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::rest_get_avatar_urls
	 * @covers ::rest_get_avatar_sizes
	 */
	public function test_returns_urls_keyed_by_default_sizes() {
		$urls = rest_get_avatar_urls( self::$user_id );

		$this->assertSame( array( 24, 48, 96 ), array_keys( $urls ) );

		foreach ( $urls as $size => $url ) {
			$this->assertSame(
				get_avatar_url( self::$user_id, array( 'size' => $size ) ),
				$url
			);
		}
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::rest_get_avatar_urls
	 * @covers ::rest_get_avatar_sizes
	 */
	public function test_returns_urls_for_filtered_sizes() {
		add_filter(
			'rest_avatar_sizes',
			static function () {
				return array( 32, 64 );
			}
		);

		$urls = rest_get_avatar_urls( self::$user_id );

		$this->assertSame( array( 32, 64 ), array_keys( $urls ) );
		$this->assertSame(
			get_avatar_url( self::$user_id, array( 'size' => 32 ) ),
			$urls[32]
		);
		$this->assertSame(
			get_avatar_url( self::$user_id, array( 'size' => 64 ) ),
			$urls[64]
		);
	}
}
