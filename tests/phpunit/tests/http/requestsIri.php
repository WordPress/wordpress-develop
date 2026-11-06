<?php

/**
 * Tests the unserialize guard that core adds to the Requests library's IRI class.
 *
 * @group http
 */
class Tests_HTTP_RequestsIri extends WP_UnitTestCase {

	/**
	 * @ticket 60077
	 *
	 * @dataProvider data_invalid_property_types
	 *
	 * @covers WpOrg\Requests\Iri::__wakeup
	 *
	 * @param string $property Name of the protected property.
	 * @param string $value    Serialized value of the wrong type.
	 */
	public function test_wakeup_throws_unexpected_value_exception_for_invalid_property_types( $property, $value ) {
		$class   = 'WpOrg\Requests\Iri';
		$name    = "\0*\0" . $property;
		$payload = sprintf( 'O:%d:"%s":1:{s:%d:"%s";%s}', strlen( $class ), $class, strlen( $name ), $name, $value );

		$this->expectException( UnexpectedValueException::class );

		unserialize( $payload );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_invalid_property_types() {
		return array(
			'string property holding an integer' => array( 'scheme', 'i:1;' ),
			'array property holding a string'    => array( 'normalization', 's:1:"x";' ),
		);
	}
}
