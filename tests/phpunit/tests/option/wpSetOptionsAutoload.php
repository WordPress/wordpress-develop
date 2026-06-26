<?php
/**
 * Test wp_set_options_autoload().
 *
 * @group option
 *
 * @covers ::wp_set_options_autoload
 */
class Tests_Option_WpSetOptionsAutoload extends WP_UnitTestCase {

	/**
	 * Tests that setting options' autoload value to 'yes' works as expected.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_yes() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		$expected = array();
		foreach ( $options as $option => $value ) {
			add_option( $option, $value, '', false );
			$expected[ $option ] = true;
		}

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), 'yes' ), 'Function did not succeed' );
		$this->assertSame( $num_queries + 2, get_num_queries(), 'Updating options autoload value ran too many queries' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $value ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), sprintf( 'Option %s not deleted from individual cache', $option ) );
		}
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'Alloptions cache not cleared' );
	}

	/**
	 * Tests that setting options' autoload value to 'no' works as expected.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_no() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		$expected = array();
		foreach ( $options as $option => $value ) {
			add_option( $option, $value, '', true );
			$expected[ $option ] = true;
		}

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), 'no' ), 'Function did not succeed' );
		$this->assertSame( $num_queries + 2, get_num_queries(), 'Updating options autoload value ran too many queries' );
		$this->assertSame( array( 'off', 'off' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $value ) {
			$this->assertArrayNotHasKey( $option, wp_cache_get( 'alloptions', 'options' ), sprintf( 'Option %s not deleted from alloptions cache', $option ) );
		}
	}

	/**
	 * Tests that setting options' autoload value to the same value as prior works as expected.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_same() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		$expected = array();
		foreach ( $options as $option => $value ) {
			add_option( $option, $value, '', true );
			$expected[ $option ] = false;
		}

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), true ), 'Function did unexpectedly succeed' );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'Function attempted to update options autoload value in database' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Options autoload value unexpectedly updated in database' );
	}

	/**
	 * Tests that setting missing option's autoload value does not do anything.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_missing() {
		global $wpdb;

		$options = array(
			'test_option1',
			'test_option2',
		);

		$expected = array();
		foreach ( $options as $option ) {
			$expected[ $option ] = false;
		}

		$this->assertSame( $expected, wp_set_options_autoload( $options, true ), 'Function did unexpectedly succeed' );
		$this->assertSame( array(), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Missing options autoload value was set in database' );
	}

	/**
	 * Tests that setting option's autoload value only updates those that need to be updated.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_mixed() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		add_option( 'test_option1', $options['test_option1'], '', true );
		add_option( 'test_option2', $options['test_option2'], '', false );
		$expected = array(
			'test_option1' => false,
			'test_option2' => true,
		);

		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), true ), 'Function produced unexpected result' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $value ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), sprintf( 'Option %s not deleted from individual cache', $option ) );
		}
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'Alloptions cache not cleared' );
	}

	/**
	 * Tests setting option's autoload value with boolean true.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_true() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		add_option( 'test_option1', $options['test_option1'], '', false );
		add_option( 'test_option2', $options['test_option2'], '', false );
		$expected = array(
			'test_option1' => true,
			'test_option2' => true,
		);

		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), true ), 'Function produced unexpected result' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
	}

	/**
	 * Tests setting option's autoload value with boolean false.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_options_autoload_false() {
		global $wpdb;

		$options = array(
			'test_option1' => 'value1',
			'test_option2' => 'value2',
		);

		add_option( 'test_option1', $options['test_option1'], '', true );
		add_option( 'test_option2', $options['test_option2'], '', true );
		$expected = array(
			'test_option1' => true,
			'test_option2' => true,
		);

		$this->assertSame( $expected, wp_set_options_autoload( array_keys( $options ), false ), 'Function produced unexpected result' );
		$this->assertSame( array( 'off', 'off' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
	}
}
