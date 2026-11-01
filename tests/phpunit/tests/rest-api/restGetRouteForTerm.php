<?php

/**
 * Tests for rest_get_route_for_term().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_term
 */
class Tests_REST_API_RestGetRouteForTerm extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		$term_id = self::factory()->category->create();

		add_filter(
			'rest_route_for_term',
			static function () {
				return '/custom/term-route';
			}
		);

		$this->assertSame( '/custom/term-route', rest_get_route_for_term( $term_id ) );
	}
}
