<?php

/**
 * @group  taxonomy
 * @covers WP_Term::get_raw_data
 */
class Tests_Taxonomy_WpTerm_GetRawData extends WP_UnitTestCase {
	/**
	 * @ticket 58087
	 *
	 * @dataProvider data_get_raw_data_should_return_correct_values
	 *
	 * @param stdClass $actual   An instance of stdClass containing the values to initialize the WP_Term object.
	 * @param array    $expected An array containing the expected values for the WP_Term object.
	 */
	public function test_get_raw_data_should_return_correct_values( stdClass $actual, array $expected ) {
		$term     = new WP_Term( $actual );
		$raw_data = $term->get_raw_data();

		$this->assertInstanceOf( stdClass::class, $raw_data, 'WP_Term::get_raw_data() should return an instance of stdClass.' );
		foreach ( $expected as $class_property_name => $expected_value ) {
			$this->assertSame(
				$expected_value,
				$raw_data->{$class_property_name},
				sprintf( 'WP_Term::get_raw_data() must return the correct value for the "%s" property', $class_property_name )
			);
		}
	}

	/**
	 * Data provider for test_get_raw_data_should_return_correct_values().
	 *
	 * @return array This contains the values that are used to initialize the WP_Term object, as well as the expected values to test the WP_Term::get_raw_data() method.
	 */
	public function data_get_raw_data_should_return_correct_values() {
		return array(
			'default values' => array(
				(object) array(
					'term_id'          => 1,
					'name'             => 'foo',
					'slug'             => 'foo',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => 'category',
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
				),
				array(
					'term_id'          => 1,
					'name'             => 'foo',
					'slug'             => 'foo',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => 'category',
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
			),
			'NULL values'    => array(
				(object) array(
					'term_id'          => null,
					'name'             => null,
					'slug'             => null,
					'term_group'       => null,
					'term_taxonomy_id' => null,
					'taxonomy'         => null,
					'description'      => null,
					'parent'           => null,
					'count'            => null,
				),
				array(
					'term_id'          => null,
					'name'             => null,
					'slug'             => null,
					'term_group'       => null,
					'term_taxonomy_id' => null,
					'taxonomy'         => null,
					'description'      => null,
					'parent'           => null,
					'count'            => null,
					'filter'           => 'raw',
				),
			),
		);
	}
}
