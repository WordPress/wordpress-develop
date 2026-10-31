<?php

/**
 * Tests for rest_get_route_for_taxonomy_items().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_taxonomy_items
 */
class Tests_REST_API_RestGetRouteForTaxonomyItems extends WP_UnitTestCase {

	public function tear_down() {
		unregister_taxonomy( 'not-in-rest' );
		unregister_taxonomy( 'with-rest-base' );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_route_for_taxonomy() {
		$this->assertSame( '/wp/v2/categories', rest_get_route_for_taxonomy_items( 'category' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_for_invalid_taxonomy() {
		$this->assertSame( '', rest_get_route_for_taxonomy_items( 'not-a-real-taxonomy' ) );
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

		$this->assertSame( '', rest_get_route_for_taxonomy_items( 'not-in-rest' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_custom_rest_namespace_and_base() {
		register_taxonomy(
			'with-rest-base',
			'post',
			array(
				'public'         => true,
				'show_in_rest'   => true,
				'rest_base'      => 'custom-terms',
				'rest_namespace' => 'my-plugin/v1',
			)
		);

		$this->assertSame( '/my-plugin/v1/custom-terms', rest_get_route_for_taxonomy_items( 'with-rest-base' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		add_filter(
			'rest_route_for_taxonomy_items',
			static function () {
				return '/custom/taxonomy-route';
			}
		);

		$this->assertSame( '/custom/taxonomy-route', rest_get_route_for_taxonomy_items( 'category' ) );
	}
}
