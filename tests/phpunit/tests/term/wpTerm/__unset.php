<?php

require_once __DIR__ . '/base.php';

/**
 * @group term
 * @group taxonomy
 *
 * @covers WP_Term::__unset
 */
class Tests_Term_WpTerm__Unset extends WP_Term_UnitTestCase {

	/**
	 * @dataProvider data_handle_dynamic_property
	 * @ticket 61890
	 *
	 * @param string $name Dynamic property name to test.
	 * @param mixed  $value Test value.
	 */
	public function test_should_unset_dynamic_property( $name, $value ) {
		$term = WP_Term::get_instance( self::$term_id, self::$taxonomy );

		// Add the dynamic property.
		$term->$name = $value;

		unset( $term->$name );

		$this->assertArrayNotHasKey( $name, $this->get_actual_dynamic_property_value( $term ) );
	}
}
