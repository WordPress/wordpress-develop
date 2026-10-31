<?php

/**
 * Tests for rest_get_route_for_post().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_post
 */
class Tests_REST_API_RestGetRouteForPost extends WP_UnitTestCase {

	public function tear_down() {
		unregister_post_type( 'not-in-rest' );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_post() {
		$post_id = self::factory()->post->create();

		$this->assertSame( '/wp/v2/posts/' . $post_id, rest_get_route_for_post( $post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_post() {
		$this->assertSame( '', rest_get_route_for_post( 0 ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_post_type_not_in_rest() {
		register_post_type(
			'not-in-rest',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'not-in-rest',
			)
		);

		$this->assertSame( '', rest_get_route_for_post( $post_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		$post_id = self::factory()->post->create();

		add_filter(
			'rest_route_for_post',
			static function () {
				return '/custom/route';
			}
		);

		$this->assertSame( '/custom/route', rest_get_route_for_post( $post_id ) );
	}
}
