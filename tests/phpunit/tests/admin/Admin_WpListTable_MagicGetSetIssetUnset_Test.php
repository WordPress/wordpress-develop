<?php

require_once __DIR__ . '/Admin_WpListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_List_Table::__get()
 * @covers WP_List_Table::__set()
 * @covers WP_List_Table::__isset()
 * @covers WP_List_Table::__unset()
 */
class Admin_WpListTable_MagicGetSetIssetUnset_Test extends Admin_WpListTable_TestCase {

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @param string $property_name Property name to get.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_get_compat_fields( $property_name, $expected ) {
		$list_table = new WP_List_Table( array( 'plural' => '_wp_tests__get' ) );

		if ( 'screen' === $property_name ) {
			$this->assertInstanceOf( $expected, $list_table->$property_name );
		} else {
			$this->assertSame( $expected, $list_table->$property_name );
		}
	}

	/**
	 * @ticket 58896
	 */
	public function test_should_throw_deprecation_when_getting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__get(): ' .
			'The property `undeclared_property` is not declared. Getting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertNull( $this->list_table->undeclared_property, 'Getting a dynamic property should return null from WP_List_Table::__get()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @param string $property_name Property name to set.
	 */
	public function test_should_set_compat_fields_defined_property( $property_name ) {
		$value                            = uniqid();
		$this->list_table->$property_name = $value;

		$this->assertSame( $value, $this->list_table->$property_name );
	}

	/**
	 * @ticket 58896
	 */
	public function test_should_throw_deprecation_when_setting_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__set(): ' .
			'The property `undeclared_property` is not declared. Setting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->list_table->undeclared_property = 'some value';
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @param string $property_name Property name to check.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_isset_compat_fields( $property_name, $expected ) {
		$actual = isset( $this->list_table->$property_name );
		if ( is_null( $expected ) ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertTrue( $actual );
		}
	}

	/**
	 * @ticket 58896
	 */
	public function test_should_throw_deprecation_when_isset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__isset(): ' .
			'The property `undeclared_property` is not declared. Checking `isset()` on a dynamic property ' .
			'is deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		$this->assertFalse( isset( $this->list_table->undeclared_property ), 'Checking a dynamic property should return false from WP_List_Table::__isset()' );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58896
	 *
	 * @param string $property_name Property name to unset.
	 */
	public function test_should_unset_compat_fields_defined_property( $property_name ) {
		unset( $this->list_table->$property_name );
		$this->assertFalse( isset( $this->list_table->$property_name ) );
	}

	/**
	 * @ticket 58896
	 */
	public function test_should_throw_deprecation_when_unset_of_dynamic_property() {
		$this->expectDeprecation();
		$this->expectDeprecationMessage(
			'WP_List_Table::__unset(): ' .
			'A property `undeclared_property` is not declared. Unsetting a dynamic property is ' .
			'deprecated since version 6.4.0! Instead, declare the property on the class.'
		);
		unset( $this->list_table->undeclared_property );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_compat_fields() {
		return array(
			'_args'            => array(
				'property_name' => '_args',
				'expected'      => array(
					'plural'   => '_wp_tests__get',
					'singular' => '',
					'ajax'     => false,
					'screen'   => null,
				),
			),
			'_pagination_args' => array(
				'property_name' => '_pagination_args',
				'expected'      => array(),
			),
			'screen'           => array(
				'property_name' => 'screen',
				'expected'      => WP_Screen::class,
			),
			'_actions'         => array(
				'property_name' => '_actions',
				'expected'      => null,
			),
			'_pagination'      => array(
				'property_name' => '_pagination',
				'expected'      => null,
			),
		);
	}
}
