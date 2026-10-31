<?php

/**
 * Tests for rest_get_queried_resource_route().
 *
 * @group restapi
 *
 * @covers ::rest_get_queried_resource_route
 */
class Tests_REST_API_RestGetQueriedResourceRoute extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_singular_post() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame( '/wp/v2/posts/' . $post_id, rest_get_queried_resource_route() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_category_archive() {
		$term_id = self::factory()->category->create();

		$this->go_to( get_term_link( $term_id ) );

		$this->assertSame( '/wp/v2/categories/' . $term_id, rest_get_queried_resource_route() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_author_archive() {
		$user_id = self::factory()->user->create();

		$this->go_to( get_author_posts_url( $user_id ) );

		$this->assertSame( '/wp/v2/users/' . $user_id, rest_get_queried_resource_route() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_no_resource_identified() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', rest_get_queried_resource_route() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$received = null;
		add_filter(
			'rest_queried_resource_route',
			static function ( $route ) use ( &$received ) {
				$received = $route;
				return '/custom/queried-route';
			}
		);

		$this->assertSame( '/custom/queried-route', rest_get_queried_resource_route() );
		$this->assertSame( '/wp/v2/posts/' . $post_id, $received );
	}
}
