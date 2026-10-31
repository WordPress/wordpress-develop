<?php

/**
 * Tests for wp_using_ext_object_cache().
 *
 * @group load
 *
 * @covers ::wp_using_ext_object_cache
 */
class Tests_Load_WpUsingExtObjectCache extends WP_UnitTestCase {

	/**
	 * Original external object cache flag.
	 *
	 * @var bool|null
	 */
	private $original_using_ext_object_cache;

	public function set_up() {
		parent::set_up();

		global $_wp_using_ext_object_cache;
		$this->original_using_ext_object_cache = $_wp_using_ext_object_cache;
	}

	public function tear_down() {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = $this->original_using_ext_object_cache;

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_current_flag_without_changing_it() {
		global $_wp_using_ext_object_cache;

		$_wp_using_ext_object_cache = false;
		$this->assertFalse( wp_using_ext_object_cache() );
		$this->assertFalse( $_wp_using_ext_object_cache );

		$_wp_using_ext_object_cache = true;
		$this->assertTrue( wp_using_ext_object_cache() );
		$this->assertTrue( $_wp_using_ext_object_cache );
	}

	/**
	 * @ticket 65819
	 */
	public function test_setter_returns_previous_value_and_updates_flag() {
		global $_wp_using_ext_object_cache;

		$_wp_using_ext_object_cache = false;

		$this->assertFalse( wp_using_ext_object_cache( true ) );
		$this->assertTrue( $_wp_using_ext_object_cache );
		$this->assertTrue( wp_using_ext_object_cache() );

		$this->assertTrue( wp_using_ext_object_cache( false ) );
		$this->assertFalse( $_wp_using_ext_object_cache );
		$this->assertFalse( wp_using_ext_object_cache() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_setter_is_idempotent_for_same_value() {
		global $_wp_using_ext_object_cache;

		$_wp_using_ext_object_cache = true;

		$this->assertTrue( wp_using_ext_object_cache( true ) );
		$this->assertTrue( $_wp_using_ext_object_cache );

		$this->assertTrue( wp_using_ext_object_cache( false ) );
		$this->assertFalse( wp_using_ext_object_cache( false ) );
		$this->assertFalse( $_wp_using_ext_object_cache );
	}
}
