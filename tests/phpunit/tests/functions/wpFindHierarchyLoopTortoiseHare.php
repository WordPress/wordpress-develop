<?php

/**
 * Tests for the wp_find_hierarchy_loop_tortoise_hare function.
 *
 * @group functions.php
 *
 * @covers ::wp_find_hierarchy_loop_tortoise_hare
 */
class Tests_Functions_wpFindHierarchyLoopTortoiseHare extends WP_UnitTestCase {

	/**
	 * @ticket 59854
	 */
	public function test_wp_find_hierarchy_loop_tortoise_hare() {

		$result = wp_find_hierarchy_loop_tortoise_hare(
			static function ( $id ) {
				return 1;
			},
			1
		);
		$this->assertEquals( 1, $result );
	}

	/**
	 * @ticket 59854
	 */
	public function test_wp_find_hierarchy_loop_tortoise_hare_2() {

		$result = wp_find_hierarchy_loop_tortoise_hare(
			static function ( $id ) {
				return 2;
			},
			1
		);
		$this->assertEquals( 2, $result );
	}
	/**
	 * @ticket 59854
	 */
	public function test_wp_find_hierarchy_loop_tortoise_hare_return_loop() {

		$result   = wp_find_hierarchy_loop_tortoise_hare(
			static function ( $id ) {
				return 2;
			},
			1,
			array(),
			array(),
			true
		);
		$expected = array(
			1 => true,
			2 => true,
		);
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 59854
	 */
	public function test_wp_find_hierarchy_loop_tortoise_hare_return_loop_2() {

		$result   = wp_find_hierarchy_loop_tortoise_hare(
			static function ( $id ) {
				return 1;
			},
			1,
			array(),
			array(),
			true
		);
		$expected = array( 1 => true );
		$this->assertSame( $expected, $result );
	}
}
