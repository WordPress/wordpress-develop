<?php

/**
 * Tests for the wp_find_hierarchy_loop function.
 *
 * @group functions
 *
 * @covers ::wp_find_hierarchy_loop
 */
class Tests_Functions_wpFindHierarchyLoop extends WP_UnitTestCase {

	/**
	 * @ticket 59901
	 */
	public function test_wp_find_hierarchy_loop() {
		$result   = wp_find_hierarchy_loop(
			static function ( $id ) {
				return 1;
			},
			1,
			2
		);
		$expected = array(
			1 => true,
			2 => true,
		);
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 59901
	 */
	public function test_wp_find_hierarchy_loop_null_parent() {
		$result   = wp_find_hierarchy_loop(
			static function ( $id ) {
				return 1;
			},
			1,
			null
		);
		$expected = array(
			1 => true,
		);
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 59901
	 */
	public function test_wp_find_hierarchy_loop_no_loop() {
		$hierarchy = array(
			1 => 2,
			2 => 3,
			3 => 0,
		);

		$result = wp_find_hierarchy_loop(
			static function ( $id ) use ( $hierarchy ) {
				return isset( $hierarchy[ $id ] ) ? $hierarchy[ $id ] : 0;
			},
			1,
			null
		);

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 59901
	 */
	public function test_wp_find_hierarchy_loop_with_multiple_levels() {
		$result   = wp_find_hierarchy_loop(
			static function ( $id ) {
				return array(
					1 => 2,
					2 => 3,
					3 => 4,
					4 => 2,
				)[ $id ] ?? null;
			},
			1,
			null
		);
		$expected = array(
			2 => true,
			3 => true,
			4 => true,
		);
		$this->assertSame( $expected, $result );
	}
}
