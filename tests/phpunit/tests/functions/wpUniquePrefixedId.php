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

	public function test_wp_unique_prefixed_id() {
		$first          = wp_unique_prefixed_id( 'test' );
		$second         = wp_unique_prefixed_id( 'test' );
		$null_id        = wp_unique_prefixed_id( null );
		$second_null_id = wp_unique_prefixed_id( null );
		$default        = wp_unique_prefixed_id();
		$second_default = wp_unique_prefixed_id();
		$this->assertNotEquals( $first, $second );
		$this->assertNotEquals( $default, $second_default );
		$this->assertNotEquals( $null_id, $second_null_id );
	}
}
