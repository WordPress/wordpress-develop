<?php

/**
 * @group taxonomy
 */
class Tests_Term_Data extends WP_UnitTestCase {

	/**
	 * @var WP_Term
	 */
	private static $term;

	public function set_up() {
		parent::set_up();

		register_taxonomy( 'wptests_tax', 'post' );

		static::$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
	}

	public function test_accessing_dynamic_property_should_work_correctly() {
		$this->expect_notice( 'WP_Term::__get(): Getting the dynamic property "foo" on WP_Term is deprecated.' );
		static::$term->foo;
	}

	public function test_checking_class_properties_should_work_correctly() {
		$this->assertFalse( isset( static::$term->foo ) );
		$this->assertFalse( isset( static::$term->data ) );
		static::$term->data; // Activates __get().
		$this->assertTrue( isset( static::$term->data ) );
	}

	public function test_setting_class_properties_should_work_correctly() {
		static::$term->data = new stdClass();
		$this->expect_notice( 'WP_Term::__set(): Setting the dynamic property "foo" on WP_Term is deprecated.' );
		static::$term->foo = 'foo';
	}

	public function test_unsetting_class_properties_should_work_correctly() {
		unset ( static::$term->data );
		$this->expect_notice( 'WP_Term::__unset(): Unsetting the dynamic property "foo" on WP_Term is deprecated.' );
		unset ( static::$term->foo );
	}

	protected function expect_notice( $message ) {
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

	public function tear_down() {
		unregister_taxonomy( 'wptests_tax' );
		parent::tear_down();
	}
}
