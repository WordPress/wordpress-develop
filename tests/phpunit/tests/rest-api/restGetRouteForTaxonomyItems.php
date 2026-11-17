<?php

/**
 * Tests for rest_get_route_for_taxonomy_items().
 *
 * @group restapi
 *
 * @covers ::rest_get_route_for_taxonomy_items
 */
class Tests_REST_API_RestGetRouteForTaxonomyItems extends WP_UnitTestCase {

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
	public function test_uses_registration_defaults_for_rest_enabled_taxonomy() {
		register_taxonomy(
			'fallback-tax',
			'post',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		$this->assertSame( '/wp/v2/fallback-tax', rest_get_route_for_taxonomy_items( 'fallback-tax' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_falls_back_to_taxonomy_name_when_rest_base_empty() {
		register_taxonomy(
			'fallback-tax',
			'post',
			array(
				'public'         => true,
				'show_in_rest'   => true,
				'rest_namespace' => 'my-plugin/v1',
			)
		);

		$taxonomy            = get_taxonomy( 'fallback-tax' );
		$taxonomy->rest_base = '';

		$this->assertSame( '/my-plugin/v1/fallback-tax', rest_get_route_for_taxonomy_items( 'fallback-tax' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_falls_back_to_default_namespace_when_rest_namespace_empty() {
		register_taxonomy(
			'fallback-tax',
			'post',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'custom-terms',
			)
		);

		$taxonomy                 = get_taxonomy( 'fallback-tax' );
		$taxonomy->rest_namespace = '';

		$this->assertSame( '/wp/v2/custom-terms', rest_get_route_for_taxonomy_items( 'fallback-tax' ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_route() {
		$received = null;

		add_filter(
			'rest_route_for_taxonomy_items',
			static function ( $route, $taxonomy ) use ( &$received ) {
				$received = array( $route, $taxonomy );
				return '/custom/taxonomy-route';
			},
			10,
			2
		);

		$this->assertSame( '/custom/taxonomy-route', rest_get_route_for_taxonomy_items( 'category' ) );
		$this->assertSame( '/wp/v2/categories', $received[0] );
		$this->assertInstanceOf( 'WP_Taxonomy', $received[1] );
		$this->assertSame( 'category', $received[1]->name );
	}
}
