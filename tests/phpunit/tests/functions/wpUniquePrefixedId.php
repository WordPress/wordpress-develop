<?php

/**
 * Test cases for the `wp_unique_prefixed_id()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.4.0
 *
 * @group functions.php
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
	 * @param mixed  $non_string_prefix Non-string prefix.
	 * @param string $expected_message  Expected notice message.
	 * @param string $expected_value    Expected unique ID.
	 */
	public function test_should_raise_notice_and_use_empty_string_prefix_when_nonstring_given( $non_string_prefix, $expected_message, $expected_value ) {
		$this->expectNotice();
		$this->expectNoticeMessage( $expected_message );

		$actual = wp_unique_prefixed_id( $non_string_prefix );
		$this->assertSame( $expected_value, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_raise_notice_and_use_empty_string_prefix_when_nonstring_given() {
		$message = 'wp_unique_prefixed_id(): The prefix must be a string. "%s" data type given.';
		return array(
			'prefix as null'         => array(
				'non_string_prefix' => null,
				'expected_message'  => sprintf( $message, 'NULL' ),
				'expected_value'    => '3',
			),
			'prefix as (int) 0'      => array(
				'non_string_prefix' => 0,
				'expected_message'  => sprintf( $message, 'integer' ),
				'expected_value'    => '4',
			),
			'prefix as (int) 1'      => array(
				'non_string_prefix'  => 1,
				'expected_data_type' => sprintf( $message, 'integer' ),
				'expected_value'     => '5',
			),
			'prefix as (bool) false' => array(
				'non_string_prefix'  => false,
				'expected_data_type' => sprintf( $message, 'boolean' ),
				'expected_value'     => '6',
			),
		);
	}
}
