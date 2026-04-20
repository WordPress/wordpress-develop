<?php

/**
 * Tests for wp_using_ext_object_cache().
 *
 * @group load
 *
 * @covers ::wp_using_ext_object_cache
 */
class Tests_Load_wpUsingExtObjectCache extends WP_UnitTestCase {

	private $orig_using_ext_cache;

	public function set_up() {
		parent::set_up();
		global $_wp_using_ext_object_cache;

		$this->orig_using_ext_cache = $_wp_using_ext_object_cache;
	}

	public function tear_down() {
		global $_wp_using_ext_object_cache;

		$_wp_using_ext_object_cache = $this->orig_using_ext_cache;
		parent::tear_down();
	}

	public function test_should_always_return_boolean() {
		wp_using_ext_object_cache( 1 );
		$this->assertIsBool( wp_using_ext_object_cache() );
	}
}
