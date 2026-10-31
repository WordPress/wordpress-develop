<?php

/**
 * Tests for wp_is_recovery_mode().
 *
 * @group load
 * @group error-protection
 *
 * @covers ::wp_is_recovery_mode
 */
class Tests_Load_WpIsRecoveryMode extends WP_UnitTestCase {

	/**
	 * Original recovery mode is_active property.
	 *
	 * @var bool
	 */
	private $original_is_active;

	public function set_up() {
		parent::set_up();

		$this->original_is_active = $this->get_recovery_mode_property( 'is_active' );
	}

	public function tear_down() {
		$this->set_recovery_mode_active( $this->original_is_active );

		parent::tear_down();
	}

	/**
	 * Gets a WP_Recovery_Mode property value.
	 *
	 * @param string $property Property name.
	 * @return mixed Property value.
	 */
	private function get_recovery_mode_property( $property ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->getValue( wp_recovery_mode() );
	}

	/**
	 * Sets whether recovery mode is active.
	 *
	 * @param bool $is_active Whether recovery mode is active.
	 */
	private function set_recovery_mode_active( $is_active ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), 'is_active' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$reflection->setValue( wp_recovery_mode(), $is_active );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_recovery_mode_is_inactive() {
		$this->set_recovery_mode_active( false );

		$this->assertFalse( wp_is_recovery_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_recovery_mode_is_active() {
		$this->set_recovery_mode_active( true );

		$this->assertTrue( wp_is_recovery_mode() );
	}
}
