<?php

require_once __DIR__ . '/base.php';

/**
 * @group term
 * @group taxonomy
 *
 * @covers WP_Term::__isset
 */
class Tests_Term_WpTerm__Iset extends WP_Term_UnitTestCase {

	/**
	 * @dataProvider data_handle_dynamic_property
	 * @ticket 61890
	 *
	 * @param string $name     Dynamic property name to test.
	 * @param mixed  $value    Test value.
	 * @param bool   $expected Optional. Expected result.
	 */
	public function test_should_isset_dynamic_property( $name, $value, $expected = true ) {
		$term = WP_Term::get_instance( self::$term_id, self::$taxonomy );

		// Set the value first to make WP_Term aware of this dynamic property.
		$term->$name = $value;

		$this->assertSame( $expected, isset( $term->$name ) );
	}
}
