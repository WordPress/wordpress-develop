<?php

/**
 * Tests for rest_get_route_for_post_type_items().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_post_type_items
 */
class Tests_REST_API_RestGetRouteForPostTypeItems extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_post_type() {
		$this->assertSame( '/wp/v2/posts', rest_get_route_for_post_type_items( 'post' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_post_type() {
		$this->assertSame( '', rest_get_route_for_post_type_items( 'not-a-real-type' ) );
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

		$this->assertSame( '', rest_get_route_for_post_type_items( 'not-in-rest' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_custom_rest_namespace_and_base() {
		register_post_type(
			'with-rest-base',
			array(
				'public'         => true,
				'show_in_rest'   => true,
				'rest_base'      => 'custom-base',
				'rest_namespace' => 'my-plugin/v1',
			)
		);

		$this->assertSame( '/my-plugin/v1/custom-base', rest_get_route_for_post_type_items( 'with-rest-base' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		add_filter(
			'rest_route_for_post_type_items',
			static function () {
				return '/custom/post-type-route';
			}
		);

		$this->assertSame( '/custom/post-type-route', rest_get_route_for_post_type_items( 'post' ) );
	}
}
