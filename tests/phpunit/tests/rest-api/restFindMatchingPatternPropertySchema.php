<?php

/**
 * Tests for rest_find_matching_pattern_property_schema().
 *
 * @group restapi
 *
 * @covers ::rest_find_matching_pattern_property_schema
 */
class Tests_REST_API_RestFindMatchingPatternPropertySchema extends WP_UnitTestCase {

	/**
	 * Tests that the schema of the matching pattern is returned.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_matching_pattern_properties
	 *
	 * @param string|int $property           Property name. Numeric names arrive as integers from array keys.
	 * @param array      $pattern_properties Value of the patternProperties keyword.
	 * @param array      $expected           Expected schema.
	 */
	public function test_returns_schema_of_matching_pattern( $property, $pattern_properties, $expected ) {
		$schema = array( 'patternProperties' => $pattern_properties );

		$this->assertSame( $expected, rest_find_matching_pattern_property_schema( $property, $schema ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matching_pattern_properties() {
		return array(
			'integer property name'     => array(
				123,
				array(
					'^[0-9]+$' => array( 'type' => 'integer' ),
				),
				array( 'type' => 'integer' ),
			),
			'match on a later pattern'  => array(
				'bar',
				array(
					'^a' => array( 'type' => 'integer' ),
					'^b' => array( 'type' => 'string' ),
				),
				array( 'type' => 'string' ),
			),
			'pattern containing a hash' => array(
				'a#b',
				array(
					'^a#b$' => array( 'type' => 'string' ),
				),
				array( 'type' => 'string' ),
			),
			'multibyte property name'   => array(
				'é',
				array(
					'^.$' => array( 'type' => 'string' ),
				),
				array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Tests that null is returned when no pattern matches.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_matching_pattern_properties
	 *
	 * @param string $property Property name.
	 * @param array  $schema   Schema to search.
	 */
	public function test_returns_null_without_matching_pattern( $property, $schema ) {
		$this->assertNull( rest_find_matching_pattern_property_schema( $property, $schema ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_matching_pattern_properties() {
		return array(
			'no patternProperties'                 => array(
				'prop',
				array( 'type' => 'object' ),
			),
			'no pattern matches'                   => array(
				'prop',
				array(
					'patternProperties' => array(
						'^[0-9]+$' => array( 'type' => 'integer' ),
					),
				),
			),
			'hash in pattern is matched literally' => array(
				'ab',
				array(
					'patternProperties' => array(
						'^a#b$' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}
}
