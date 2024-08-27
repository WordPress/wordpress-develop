<?php

require_once __DIR__ . '/base.php';

/**
 * @group term
 * @group taxonomy
 *
 * @covers WP_Term::__set
 */
class Tests_Term_WpTerm__Set extends WP_Term_UnitTestCase {

	/**
	 * @dataProvider data_handle_dynamic_property
	 * @ticket 61890
	 *
	 * @param string $name     Dynamic property name to test.
	 * @param mixed  $value    Test value.
	 */
	public function test_should_set_dynamic_property( $name, $value ) {
		$term = WP_Term::get_instance( self::$term_id, self::$taxonomy );

		$term->$name = $value;

		$actual = $this->get_actual_dynamic_property_value( $term );

		$this->assertArrayHasKey( $name, $actual, "The WP_Term::\$dynamic_properties array should have a $name key" );
		$this->assertSame( $value, $actual[ $name ], "Dynamic property $name should be set" );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_handle_dynamic_property() {
		$term_args = self::get_term_args();

		return array(
			'unknown null'       => array(
				'name'     => 'unknown',
				'value'    => null,
				'expected' => false,
			),
			'unknown with value' => array(
				'name'  => 'unknown_with_value',
				'value' => array( 'unknown', 'dynamic', 'property' ),
			),
			// Used by wp_update_term().
			'data'               => array(
				'name'  => 'data',
				'value' => array(
					'term_id'          => 2,
					'name'             => $term_args['name'],
					'slug'             => $term_args['slug'],
					'term_group'       => 0,
					'term_taxonomy_id' => 2,
					'taxonomy'         => self::$taxonomy,
					'description'      => $term_args['description'],
					'parent'           => 0,
					'count'            => 1,
					'filter'           => 'raw',
				),
			),
			// Added by WP_Term_Query::get_terms().
			'object_id'          => array(
				'name'  => 'object_id',
				'value' => 5,
			),
		);
	}
}
