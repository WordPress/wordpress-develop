<?php

/**
 * Tests for wp_installing().
 *
 * @group load
 *
 * @covers ::wp_installing
 */
class Tests_Load_WpInstalling extends WP_UnitTestCase {

	public function tear_down() {
		/*
		 * Reset the static installing flag for subsequent tests in the same process.
		 */
		wp_installing( false );

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_defaults_to_false() {
		$this->assertFalse( wp_installing() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_setter_returns_previous_value_and_updates_state() {
		$this->assertFalse( wp_installing( true ) );
		$this->assertTrue( wp_installing() );

		$this->assertTrue( wp_installing( false ) );
		$this->assertFalse( wp_installing() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_setter_is_idempotent_for_same_value() {
		wp_installing( true );

		$this->assertTrue( wp_installing( true ) );
		$this->assertTrue( wp_installing() );

		$this->assertTrue( wp_installing( false ) );
		$this->assertFalse( wp_installing( false ) );
		$this->assertFalse( wp_installing() );
	}
}
