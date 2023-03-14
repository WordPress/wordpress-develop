<?php
/**
 * Test class for `_get_non_cached_ids()`.
 *
 * @package WordPress
 *
 * @group cache
 *
 * @covers ::_get_non_cached_ids
 * @covers ::_validate_cache_id
 */
class Tests_Functions_GetNonCachedIds extends WP_UnitTestCase {

	/**
	 * @ticket 57593
	 */
	public function test_uncached_valid_ids_should_be_unique() {
		$object_id = 1;

		$this->assertSame(
			array( $object_id ),
			_get_non_cached_ids( array( $object_id, $object_id, (string) $object_id ), 'fake-group' ),
			'Duplicate object IDs should be removed.'
		);
	}

	/**
	 * @ticket 57593
	 *
	 * @dataProvider data_valid_ids_should_be_returned_as_integers
	 *
	 * @param mixed $object_id The object ID.
	 */
	public function test_valid_ids_should_be_returned_as_integers( $object_id ) {
		$this->assertSame(
			array( (int) $object_id ),
			_get_non_cached_ids( array( $object_id ), 'fake-group' ),
			'Object IDs should be returned as integers.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_valid_ids_should_be_returned_as_integers() {
		return array(
			'(int) 1'    => array( 1 ),
			'(string) 1' => array( '1' ),
		);
	}

	/**
	 * @ticket 57593
	 */
	public function test_mix_of_valid_and_invalid_ids_should_return_the_valid_ids_and_throw_a_notice() {
		$object_id = 1;

		$this->setExpectedIncorrectUsage( '_get_non_cached_ids' );
		$this->assertSame(
			array( $object_id ),
			_get_non_cached_ids( array( $object_id, null ), 'fake-group' ),
			'Valid object IDs should be returned.'
		);
	}

	/**
	 * @ticket 57593
	 *
	 * @dataProvider data_invalid_cache_ids_should_throw_a_notice
	 *
	 * @param mixed $object_id The object ID.
	 */
	public function test_invalid_cache_ids_should_throw_a_notice( $object_id ) {
		$this->setExpectedIncorrectUsage( '_get_non_cached_ids' );
		$this->assertSame(
			array(),
			_get_non_cached_ids( array( $object_id ), 'fake-group' ),
			'Invalid object IDs should be dropped.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_invalid_cache_ids_should_throw_a_notice() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'true'         => array( true ),
			'(float) 1.0'  => array( 1.0 ),
			'(string) 5.0' => array( '5.0' ),
			'string'       => array( 'johnny cache' ),
			'empty string' => array( '' ),
			'array'        => array( array( 1 ) ),
			'empty array'  => array( array() ),
			'stdClass'     => array( new stdClass ),
		);
	}
}
