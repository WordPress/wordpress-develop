<?php

/**
 * @group post
 *
 * @covers ::get_post_custom
 * @covers ::get_post_custom_keys
 * @covers ::get_post_custom_values
 */
class Tests_Post_GetPostCustom extends WP_UnitTestCase {

	protected static int $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$post_id = $factory->post->create();
		add_post_meta( self::$post_id, 'test_key_1', 'value_a' );
		add_post_meta( self::$post_id, 'test_key_1', 'value_b' );
		add_post_meta( self::$post_id, 'test_key_2', 'value_c' );
	}

	/**
	 * Test get_post_custom() happy path.
	 */
	public function test_get_post_custom_happy_path(): void {
		$custom = get_post_custom( self::$post_id );

		$this->assertIsArray( $custom );
		$this->assertArrayHasKey( 'test_key_1', $custom );
		$this->assertCount( 2, $custom['test_key_1'] );
		$this->assertSame( array( 'value_a', 'value_b' ), $custom['test_key_1'] );
	}

	/**
	 * Test get_post_custom_keys() happy path.
	 */
	public function test_get_post_custom_keys_happy_path(): void {
		$keys = get_post_custom_keys( self::$post_id );

		$this->assertIsArray( $keys );
		$this->assertContains( 'test_key_1', $keys );
		$this->assertContains( 'test_key_2', $keys );
	}

	/**
	 * Test get_post_custom_values() happy path.
	 */
	public function test_get_post_custom_values_happy_path(): void {
		$values = get_post_custom_values( 'test_key_1', self::$post_id );

		$this->assertIsArray( $values );
		$this->assertSame( array( 'value_a', 'value_b' ), $values );
	}

	/**
	 * Test functions with non-existent post ID (The core of ticket #65044).
	 *
	 * @ticket 65044
	 */
	public function test_custom_functions_with_invalid_post_id(): void {
		$invalid_id = 99999;

		// get_post_custom returns empty array for non-existent post in current WP
		$this->assertSame( array(), get_post_custom( $invalid_id ) );

		// get_post_custom_keys returns null/void if array is empty/invalid
		$this->assertNull( get_post_custom_keys( $invalid_id ) );

		// get_post_custom_values should return null (Fix for #65044)
		$this->assertNull( get_post_custom_values( 'test_key_1', $invalid_id ) );
	}

	/**
	 * Test get_post_custom_values() with empty key.
	 *
	 * @ticket 65044
	 */
	public function test_get_post_custom_values_empty_key(): void {
		$this->assertNull( get_post_custom_values( '', self::$post_id ) );
		$this->assertNull( get_post_custom_values( null, self::$post_id ) );
	}

	/**
	 * Test behavior when post_id is 0 and no global post is set.
	 */
	public function test_custom_functions_with_zero_id_no_global_post(): void {
		// Ensure global post is null
		$GLOBALS['post'] = null;

		$custom = get_post_custom( 0 );
		$this->assertEmpty( $custom, 'get_post_custom(0) should return an empty value (false or empty array).' );
		$this->assertNull( get_post_custom_keys( 0 ) );
		$this->assertNull( get_post_custom_values( 'test_key_1', 0 ) );
	}

	/**
	 * Test get_post_custom_values() when key does not exist.
	 */
	public function test_get_post_custom_values_non_existent_key(): void {
		$this->assertNull( get_post_custom_values( 'non_existent_key', self::$post_id ) );
	}
}
