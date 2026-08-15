<?php

/**
 * @group functions
 *
 * @covers ::wp_unique_id
 */
class Tests_Functions_WpUniqueId extends WP_UnitTestCase {
	/**
	 * Tests wp_unique_id().
	 *
	 * @covers ::wp_unique_id
	 * @ticket 44883
	 */
	public function test_wp_unique_id() {

		// Test without prefix.
		$ids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$id = wp_unique_id();
			$this->assertIsString( $id );
			$this->assertIsNumeric( $id );
			$ids[] = $id;
		}
		$this->assertSame( $ids, array_unique( $ids ) );

		// Test with prefix.
		$ids = array();
		for ( $i = 0; $i < 20; $i += 1 ) {
			$id = wp_unique_id( 'foo-' );
			$this->assertMatchesRegularExpression( '/^foo-\d+$/', $id );
			$ids[] = $id;
		}
		$this->assertSame( $ids, array_unique( $ids ) );
	}
}
