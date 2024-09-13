<?php
/**
 * Test wp_set_option_autoload_values().
 *
 * @group option
 *
 * @covers ::wp_set_option_autoload_values
 */
class Tests_Option_WpSetOptionAutoloadValues extends WP_UnitTestCase {

	/**
	 * Tests setting options' autoload to 'yes' where for some options this is already the case.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_all_yes_partial_update() {
		global $wpdb;

		$options = array(
			'test_option1' => 'yes',
			'test_option2' => 'yes',
		);
		add_option( 'test_option1', 'value1', '', true );
		add_option( 'test_option2', 'value2', '', false );
		$expected = array(
			'test_option1' => false,
			'test_option2' => true,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 2, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $autoload ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), sprintf( 'Option %s not deleted from individual cache', $option ) );
		}
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'Alloptions cache not cleared' );
	}

	/**
	 * Tests setting options' autoload to 'no' where for some options this is already the case.
	 *
	 * In this case, the 'alloptions' cache should not be cleared, but only its options set to 'no' should be deleted.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_all_no_partial_update() {
		global $wpdb;

		$options = array(
			'test_option1' => 'no',
			'test_option2' => 'no',
		);
		add_option( 'test_option1', 'value1', '', true );
		add_option( 'test_option2', 'value2', '', false );
		$expected = array(
			'test_option1' => true,
			'test_option2' => false,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 2, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSame( array( 'off', 'off' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $autoload ) {
			$this->assertArrayNotHasKey( $option, wp_cache_get( 'alloptions', 'options' ), sprintf( 'Option %s not deleted from alloptions cache', $option ) );
		}
	}

	/**
	 * Tests setting options' autoload to 'yes' where for all of them this is already the case.
	 *
	 * The values 'yes' and 'no' are only supported for backward compatibility.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_all_yes_no_update() {
		global $wpdb;

		$options = array(
			'test_option1' => 'yes',
			'test_option2' => 'yes',
		);
		add_option( 'test_option1', 'value1', '', true );
		add_option( 'test_option2', 'value2', '', true );
		$expected = array(
			'test_option1' => false,
			'test_option2' => false,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSame( array( 'on', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $autoload ) {
			$this->assertArrayHasKey( $option, wp_cache_get( 'alloptions', 'options' ), sprintf( 'Option %s unexpectedly deleted from alloptions cache', $option ) );
		}
	}

	/**
	 * Tests setting options' autoload to either true or false where for some options this is already the case.
	 *
	 * The test also covers one option that is entirely missing.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_mixed_partial_update() {
		global $wpdb;

		$options = array(
			'test_option1' => true,
			'test_option2' => false,
			'test_option3' => true,
			'missing_opt'  => true,
		);
		add_option( 'test_option1', 'value1', '', false );
		add_option( 'test_option2', 'value2', '', true );
		add_option( 'test_option3', 'value3', '', true );
		$expected = array(
			'test_option1' => true,
			'test_option2' => true,
			'test_option3' => false,
			'missing_opt'  => false,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 3, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSameSets( array( 'on', 'off', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $autoload ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), sprintf( 'Option %s not deleted from individual cache', $option ) );
		}
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'Alloptions cache not cleared' );
	}

	/**
	 * Tests setting options' autoload to either true or false while only the false options actually need to be updated.
	 *
	 * In this case, the 'alloptions' cache should not be cleared, but only its options set to 'no' should be deleted.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_mixed_only_update_no() {
		global $wpdb;

		$options = array(
			'test_option1' => true,
			'test_option2' => false,
			'test_option3' => true,
		);
		add_option( 'test_option1', 'value1', '', true );
		add_option( 'test_option2', 'value2', '', true );
		add_option( 'test_option3', 'value3', '', true );
		$expected = array(
			'test_option1' => false,
			'test_option2' => true,
			'test_option3' => false,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 2, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSameSets( array( 'on', 'off', 'on' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
		foreach ( $options as $option => $autoload ) {
			if ( false === $autoload ) {
				$this->assertArrayNotHasKey( $option, wp_cache_get( 'alloptions', 'options' ), sprintf( 'Option %s not deleted from alloptions cache', $option ) );
			} else {
				$this->assertArrayHasKey( $option, wp_cache_get( 'alloptions', 'options' ), sprintf( 'Option %s unexpectedly deleted from alloptions cache', $option ) );
			}
		}
	}

	/**
	 * Tests setting options' autoload with a simulated SQL query failure.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_with_sql_query_failure() {
		global $wpdb;

		$options = array(
			'test_option1' => true,
			'test_option2' => true,
		);
		add_option( 'test_option1', 'value1', '', false );
		add_option( 'test_option2', 'value2', '', false );

		// Force UPDATE queries to fail, leading to no autoload values being updated.
		add_filter(
			'query',
			static function ( $query ) {
				if ( str_starts_with( $query, 'UPDATE ' ) ) {
					return '';
				}
				return $query;
			}
		);
		$expected = array(
			'test_option1' => false,
			'test_option2' => false,
		);

		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( array( 'off', 'off' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
	}

	/**
	 * Tests setting options' autoload with now encouraged boolean values.
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_with_bool() {
		global $wpdb;

		$options = array(
			'test_option1' => true,
			'test_option2' => false,
		);
		add_option( 'test_option1', 'value1', '', false );
		add_option( 'test_option2', 'value2', '', true );
		$expected = array(
			'test_option1' => true,
			'test_option2' => true,
		);

		$num_queries = get_num_queries();
		$this->assertSame( $expected, wp_set_option_autoload_values( $options ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries + 3, get_num_queries(), 'Function made unexpected amount of database queries' );
		$this->assertSameSets( array( 'on', 'off' ), $wpdb->get_col( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name IN (" . implode( ',', array_fill( 0, count( $options ), '%s' ) ) . ')', ...array_keys( $options ) ) ), 'Option autoload values not updated in database' );
	}

	/**
	 * Tests calling the function with an empty array (i.e. do nothing).
	 *
	 * @ticket 58964
	 */
	public function test_wp_set_option_autoload_values_with_empty_array() {
		$num_queries = get_num_queries();
		$this->assertSame( array(), wp_set_option_autoload_values( array() ), 'Function produced unexpected result' );
		$this->assertSame( $num_queries, get_num_queries(), 'Function made unexpected amount of database queries' );
	}
}
