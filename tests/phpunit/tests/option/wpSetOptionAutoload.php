<?php
/**
 * Test wp_set_option_autoload().
 *
 * @group option
 *
 * @covers ::wp_set_option_autoload
 */
class Tests_Option_WpSetOptionAutoload extends WP_UnitTestCase {

	/**
	 * Tests that setting an option's autoload value to 'yes' works as expected.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_yes() {
		global $wpdb;

		$option = 'test_option';
		$value  = 'value';

		add_option( $option, $value, '', 'no' );

		$this->assertTrue( wp_set_option_autoload( $option, 'yes' ), 'Function did not succeed' );
		$this->assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Option autoload value not updated in database' );
		$this->assertFalse( wp_cache_get( $option, 'options' ), 'Option not deleted from individual cache' );
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'Alloptions cache not cleared' );
	}

	/**
	 * Tests that setting an option's autoload value to 'no' works as expected.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_no() {
		global $wpdb;

		$option = 'test_option';
		$value  = 'value';

		add_option( $option, $value, '', 'yes' );

		$this->assertTrue( wp_set_option_autoload( $option, 'no' ), 'Function did not succeed' );
		$this->assertSame( 'off', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Option autoload value not updated in database' );
		$this->assertArrayNotHasKey( $option, wp_cache_get( 'alloptions', 'options' ), 'Option not deleted from alloptions cache' );
	}

	/**
	 * Tests that setting an option's autoload value to the same value as prior works as expected.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_same() {
		global $wpdb;

		$option = 'test_option';
		$value  = 'value';

		add_option( $option, $value, '', true );

		$this->assertFalse( wp_set_option_autoload( $option, true ), 'Function did unexpectedly succeed' );
		$this->assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Option autoload value unexpectedly updated in database' );
	}

	/**
	 * Tests that setting a missing option's autoload value does not do anything.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_missing() {
		global $wpdb;

		$option = 'test_option';

		$this->assertFalse( wp_set_option_autoload( $option, true ), 'Function did unexpectedly succeed' );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Missing option autoload value was set in database' );
		$this->assertArrayNotHasKey( $option, wp_cache_get( 'alloptions', 'options' ), 'Missing option found in alloptions cache' );
		$this->assertFalse( wp_cache_get( $option, 'options' ), 'Missing option found in individual cache' );
	}

	/**
	 * Tests setting an option's autoload value to boolean true.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_true() {
		global $wpdb;

		$option = 'test_option';
		$value  = 'value';

		add_option( $option, $value, '', false );

		$this->assertTrue( wp_set_option_autoload( $option, true ), 'Function did not succeed' );
		$this->assertSame( 'on', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Option autoload value not updated in database' );
	}

	/**
	 * Tests setting an option's autoload value to boolean false.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_false() {
		global $wpdb;

		$option = 'test_option';
		$value  = 'value';

		add_option( $option, $value, '', true );

		$this->assertTrue( wp_set_option_autoload( $option, false ), 'Function did not succeed' );
		$this->assertSame( 'off', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) ), 'Option autoload value not updated in database' );
	}
}
