<?php

/**
 * Tests for rest_get_route_for_term().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_term
 */
class Tests_REST_API_RestGetRouteForTerm extends WP_UnitTestCase {

	public function tear_down() {
		unregister_taxonomy( 'not-in-rest' );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_term() {
		$term_id = self::factory()->category->create();

		$this->assertSame( '/wp/v2/categories/' . $term_id, rest_get_route_for_term( $term_id ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_term() {
		$this->assertSame( '', rest_get_route_for_term( 0 ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_taxonomy_not_in_rest() {
		register_taxonomy(
			'not-in-rest',
			'post',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'not-in-rest',
			)
		);

		$this->assertSame( '', rest_get_route_for_term( $term_id ) );
	}

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
