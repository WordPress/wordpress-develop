<?php
/**
 * Test `_get_non_cached_ids()`.
 *
 * @package WordPress
 */

/**
 * Test class for `_get_non_cached_ids()`.
 *
 * @group cache
 *
 * @covers ::_get_non_cached_ids
 */
class Tests_Functions_GetNonCachedIds extends WP_UnitTestCase {

	/**
	 * @ticket 57593
	 *
	 * @dataProvider data_valid_ids_should_be_returned_as_is
	 *
	 * @param mixed $object_id The object id.
	 */
	public function test_valid_ids_should_be_returned_as_integers( $object_id ) {
		$this->assertSame( array( (int) $object_id ), _get_non_cached_ids( array( $object_id ), 'posts' ), 'Object IDs should be returned as integers.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_valid_ids_should_be_returned_as_is() {
		// Valid ID that is not in the database and thus not cached.
		return array(
			'integer' => array( PHP_INT_MAX ),
			'string'  => array( (string) PHP_INT_MAX ),
		);
	}

	/**
	 * @ticket 57593
	 */
	public function test_mix_of_valid_and_invalid_ids_should_return_the_valid_ids_and_throw_a_notice() {
		$post_id = PHP_INT_MAX; // Valid ID that is not in the database and thus not cached.

		$this->setExpectedIncorrectUsage( '_get_non_cached_ids' );
		$this->assertSame( array( $post_id ), _get_non_cached_ids( array( $post_id, null ), 'posts' ), 'Valid object IDs should be returned.' );
	}

	/**
	 * @ticket 57593
	 *
	 * @dataProvider data_invalid_cache_ids_should_throw_a_notice
	 *
	 * @param mixed $value The object id.
	 */
	public function test_invalid_cache_ids_should_throw_a_notice( $value ) {
		$this->setExpectedIncorrectUsage( '_get_non_cached_ids' );
		$this->assertSame( array(), _get_non_cached_ids( array( $value ), 'posts' ), 'Invalid object IDs should be dropped.' );
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
