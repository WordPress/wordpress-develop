<?php

/**
 * Test cases for the `wp_unique_prefixed_id()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.4.0
 *
 * @group functions
 * @covers ::wp_unique_prefixed_id
 */
class Tests_Functions_WpUniquePrefixedId extends WP_UnitTestCase {

	/**
	 * Tests that the expected unique prefixed IDs are created.
	 *
	 * @ticket 59681
	 *
	 * @dataProvider data_should_create_unique_prefixed_ids
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param mixed $prefix   The prefix.
	 * @param array $expected The next two expected IDs.
	 */
	public function test_should_create_unique_prefixed_ids( $prefix, $expected ) {
		$id1 = wp_unique_prefixed_id( $prefix );
		$id2 = wp_unique_prefixed_id( $prefix );

		$this->assertNotSame( $id1, $id2, 'The IDs are not unique.' );
		$this->assertSame( $expected, array( $id1, $id2 ), 'The IDs did not match the expected values.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_create_unique_prefixed_ids() {
		return array(
			'prefix as empty string'       => array(
				'prefix'   => '',
				'expected' => array( '1', '2' ),
			),
			'prefix as (string) "0"'       => array(
				'prefix'   => '0',
				'expected' => array( '01', '02' ),
			),
			'prefix as string'             => array(
				'prefix'   => 'test',
				'expected' => array( 'test1', 'test2' ),
			),
			'prefix as string with spaces' => array(
				'prefix'   => '   ',
				'expected' => array( '   1', '   2' ),
			),
			'prefix as (string) "1"'       => array(
				'prefix'   => '1',
				'expected' => array( '11', '12' ),
			),
			'prefix as a (string) "."'     => array(
				'prefix'   => '.',
				'expected' => array( '.1', '.2' ),
			),
			'prefix as a block name'       => array(
				'prefix'   => 'core/list-item',
				'expected' => array( 'core/list-item1', 'core/list-item2' ),
			),
		);
	}

	/**
	 * @ticket 59681
	 *
	 * @dataProvider data_should_raise_notice_and_use_empty_string_prefix_when_nonstring_given
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param mixed  $non_string_prefix         Non-string prefix.
	 * @param int    $number_of_ids_to_generate Number of IDs to generate.
	 *                                          As the prefix will default to an empty string, changing the number of IDs generated within each dataset further tests ID uniqueness.
	 * @param string $expected_message          Expected notice message.
	 * @param array  $expected_ids              Expected unique IDs.
	 */
	public function test_should_raise_notice_and_use_empty_string_prefix_when_nonstring_given( $non_string_prefix, $number_of_ids_to_generate, $expected_message, $expected_ids ) {
		$this->expectNotice();
		$this->expectNoticeMessage( $expected_message );

		$ids = array();
		for ( $i = 0; $i < $number_of_ids_to_generate; $i++ ) {
			$ids[] = wp_unique_prefixed_id( $non_string_prefix );
		}

		$this->assertSameSets( $ids, array_unique( $ids ), 'IDs are not unique.' );
		$this->assertSameSets( $expected_ids, $ids, 'The IDs did not match the expected values.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_raise_notice_and_use_empty_string_prefix_when_nonstring_given() {
		$message = 'wp_unique_prefixed_id(): The prefix must be a string. "%s" data type given.';
		return array(
			'prefix as null'          => array(
				'non_string_prefix'         => null,
				'number_of_ids_to_generate' => 2,
				'expected_message'          => sprintf( $message, 'NULL' ),
				'expected_ids'              => array( '1', '2' ),
			),
			'prefix as (int) 0'       => array(
				'non_string_prefix'         => 0,
				'number_of_ids_to_generate' => 3,
				'expected_message'          => sprintf( $message, 'integer' ),
				'expected_ids'              => array( '1', '2', '3' ),
			),
			'prefix as (int) 1'       => array(
				'non_string_prefix'         => 1,
				'number_of_ids_to_generate' => 4,
				'expected_data_type'        => sprintf( $message, 'integer' ),
				'expected_ids'              => array( '1', '2', '3', '4' ),
			),
			'prefix as (bool) false'  => array(
				'non_string_prefix'         => false,
				'number_of_ids_to_generate' => 5,
				'expected_data_type'        => sprintf( $message, 'boolean' ),
				'expected_ids'              => array( '1', '2', '3', '4', '5' ),
			),
			'prefix as (double) 98.7' => array(
				'non_string_prefix'         => 98.7,
				'number_of_ids_to_generate' => 6,
				'expected_data_type'        => sprintf( $message, 'double' ),
				'expected_ids'              => array( '1', '2', '3', '4', '5', '6' ),
			),
		);
	}

	/**
	 * Prefixes that are or will become the same should generate unique IDs.
	 *
	 * This test is added to avoid future regressions if the function's prefix data type check is
	 * modified to type juggle or check for scalar data types.
	 *
	 * @ticket 59681
	 *
	 * @dataProvider data_same_prefixes_should_generate_unique_ids
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param array $prefixes The prefixes to check.
	 * @param array $expected The expected unique IDs.
	 */
	public function test_same_prefixes_should_generate_unique_ids( array $prefixes, array $expected ) {
		// Suppress E_USER_NOTICE, which will be raised when a prefix is non-string.
		$original_error_reporting = error_reporting();
		error_reporting( $original_error_reporting & ~E_USER_NOTICE );

		$ids = array();
		foreach ( $prefixes as $prefix ) {
			$ids[] = wp_unique_prefixed_id( $prefix );
		}

		// Reset error reporting.
		error_reporting( $original_error_reporting );

		$this->assertSameSets( $ids, array_unique( $ids ), 'IDs are not unique.' );
		$this->assertSameSets( $expected, $ids, 'The IDs did not match the expected values.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_same_prefixes_should_generate_unique_ids() {
		return array(
			'prefixes = empty string' => array(
				'prefixes' => array( null, true, '' ),
				'expected' => array( '1', '2', '3' ),
			),
			'prefixes = 0'            => array(
				'prefixes' => array( '0', 0, 0.0, false ),
				'expected' => array( '01', '1', '2', '3' ),
			),
			'prefixes = 1'            => array(
				'prefixes' => array( '1', 1, 1.0, true ),
				'expected' => array( '11', '1', '2', '3' ),
			),
		);
	}
}
