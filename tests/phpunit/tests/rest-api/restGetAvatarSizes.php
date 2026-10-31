<?php

/**
 * Tests for rest_get_avatar_sizes() and rest_get_avatar_urls().
 *
 * @group restapi
 */
class Tests_REST_API_RestGetAvatarSizes extends WP_UnitTestCase {

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

		$email = 'avatar-test@example.com';
		$hash  = hash( 'sha256', strtolower( trim( $email ) ) );
		$urls  = rest_get_avatar_urls( $email );

		$this->assertSame( array( 32, 64 ), array_keys( $urls ) );

		foreach ( array( 32, 64 ) as $size ) {
			$this->assertStringContainsString(
				'https://secure.gravatar.com/avatar/' . $hash,
				$urls[ $size ],
				"Avatar URL for size {$size} should include the Gravatar path for the email."
			);

			$query = array();
			wp_parse_str( wp_parse_url( $urls[ $size ], PHP_URL_QUERY ), $query );

			$this->assertSame(
				(string) $size,
				$query['s'] ?? null,
				"Avatar URL for size {$size} should request that exact pixel size."
			);
		}
	}
}
