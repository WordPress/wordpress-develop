<?php

/**
 * Test cases for the `wp_unique_prefixed_id()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.4
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
			'prefix as null'               => array(
				'prefix'   => null,
				'expected' => array( '1', '2' ),
			),
			'prefix as empty string'       => array(
				'prefix'   => '',
				'expected' => array( '3', '4' ),
			),
			'prefix as (string) "0"'       => array(
				'prefix'   => '0',
				'expected' => array( '01', '02' ),
			),
			'prefix as (int) 0'            => array(
				'prefix'   => 0,
				'expected' => array( '03', '04' ),
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
			'prefix as (int) 1'            => array(
				'prefix'   => 1,
				'expected' => array( '13', '14' ),
			),
		);
	}
}
