<?php

/**
 * Tests the WP_Term::$data dynamic property.
 *
 * @group taxonomy
 */
class Tests_Term_Data extends WP_UnitTestCase {

	/**
	 * @var WP_Term
	 */
	private $term;

	public function set_up() {
		parent::set_up();

		register_taxonomy( 'wptests_tax', 'post' );

		$this->term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
	}

	/**
	 * @covers WP_Term::__get
	 * @ticket 58087
	 */
	public function test_getting_class_properties_should_work_correctly() {
		$this->expect_deprecation_message( 'WP_Term::__get(): Getting the dynamic property "foo" on WP_Term is deprecated.' );
		$this->term->foo;
	}

	/**
	 * @covers WP_Term::__get
	 * @ticket 58087
	 */
	public function test_data_should_reflect_changes_in_the_term_object() {
		// Check if initial term names match.
		$this->assertSame(
			$this->term->data->name,
			$this->term->name,
			'Initial term name should match the name in term data object.'
		);

		// Modify the term name.
		$this->term->name = 'foo';

		// Check if modified term names match.
		$this->assertSame(
			$this->term->data->name,
			$this->term->name,
			'Modified term name should be updated in the term data object.'
		);
	}

	/**
	 * @covers WP_Term::__isset
	 * @ticket 58087
	 */
	public function test_checking_class_properties_should_work_correctly() {
		$this->assertFalse( isset( $this->term->foo ), 'The WP_Term::$foo property should not be set.' );
		$this->assertTrue( isset( $this->term->data ), 'The WP_Term::$data property should be set (it\'s a read-only property).' );
		$this->term->data; // Activates __get().
		$this->assertTrue( isset( $this->term->data ), 'The WP_Term::$data property should be set.' );
	}

	/**
	 * @covers WP_Term::__set
	 * @ticket 58087
	 */
	public function test_setting_class_properties_should_work_correctly() {
		$this->term->data = new stdClass();
		$this->expect_deprecation_message( 'WP_Term::__set(): Setting the dynamic property "foo" on WP_Term is deprecated.' );
		$this->term->foo = 'foo';
	}

	/**
	 * @covers WP_Term::__unset
	 * @ticket 58087
	 */
	public function test_unsetting_class_properties_should_work_correctly() {
		unset( $this->term->data );
		$this->expect_deprecation_message( 'WP_Term::__unset(): Unsetting the dynamic property "foo" on WP_Term is deprecated.' );
		unset( $this->term->foo );
	}

	/**
	 * @covers WP_Term::to_array
	 * @ticket 58087
	 */
	public function test_to_array_does_not_return_the_data_property() {
		$object_data = $this->term->to_array();
		$this->assertArrayNotHasKey( 'data', $object_data, 'The data property should not be returned.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array An array of public WP_Term properties.
	 */
	public function data_unsetting_public_declared_properties_should_not_trigger_a_deprecation_error() {
		$reflection     = new ReflectionClass( WP_Term::class );
		$properties     = $reflection->getProperties( ReflectionProperty::IS_PUBLIC );
		$property_names = array();

		foreach ( $properties as $property ) {
			$property_name                                   = $property->getName();
			$property_names[ 'WP_Term::$' . $property_name ] = array( 'property_name' => $property_name );
		}

		return $property_names;
	}

	/**
	 * @dataProvider data_unsetting_public_declared_properties_should_not_trigger_a_deprecation_error
	 *
	 * @covers WP_Term::to_array
	 * @ticket 58087
	 *
	 * @param string $property_name A class property name to test.
	 */
	public function test_unsetting_public_declared_properties_should_not_trigger_a_deprecation_error( $property_name ) {
		$this->assertObjectHasProperty( $property_name, $this->term, "WP_Term does not have the expected property '{$property_name}'." );

		unset( $this->term->$property_name );
		$this->assertFalse( isset( $this->term->$property_name ), "Property '{$property_name}' should be unset but is still set." );

		// Set the property to null and verify through addToAssertionCount() that __set doesn't trigger a deprecation error.
		$this->term->$property_name = null;
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @dataProvider data_unsetting_public_declared_properties_should_not_trigger_a_deprecation_error
	 *
	 * @covers WP_Term::check_if_public_class_property
	 * @ticket 58087
	 *
	 * @param string $property_name A class property name to test.
	 */
	public function test_public_properties_should_be_correctly_detected( $property_name ) {
		$method = new ReflectionMethod( $this->term, 'check_if_public_class_property' );

		// Set the method to be accessible
		$method->setAccessible( true );

		$this->assertTrue(
			$method->invokeArgs( $this->term, array( $property_name ) ),
			"Have you forgotten to add the \"$property_name\" property to the array in WP_Term::check_if_public_class_property()?"
		);
	}

	public function tear_down() {
		unregister_taxonomy( 'wptests_tax' );
		parent::tear_down();
	}

	/**
	 * Provides a workaround to ensure compatibility with PHPUnit 10,
	 * as TestCase::expectDeprecation() is deprecated.
	 *
	 * @throws Exception If a PHP error is triggered.
	 *
	 * @param string $message The deprecation message expected.
	 */
	private function expect_deprecation_message( $message ) {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );
		set_error_handler(
			static function ( $errno, $errstr ) {
				restore_error_handler();
				throw new Exception( $errstr, $errno );
			},
			E_USER_DEPRECATED
		);
	}
}
