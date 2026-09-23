<?php

/**
 * Tests for the wp_find_hierarchy_loop function.
 *
 * @group Functions.php
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
}
