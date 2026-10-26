<?php

/**
 * Tests for timer_float().
 *
 * @group load
 *
 * @covers ::timer_float
 */
class Tests_Load_TimerFloat extends WP_UnitTestCase {

	/**
	 * Original $_SERVER['REQUEST_TIME_FLOAT'] value.
	 *
	 * @var float|null
	 */
	private $original_request_time_float;

	public function set_up() {
		parent::set_up();

		$this->original_request_time_float = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
	}

	public function tear_down() {
		if ( null !== $this->original_request_time_float ) {
			$_SERVER['REQUEST_TIME_FLOAT'] = $this->original_request_time_float;
		} else {
			unset( $_SERVER['REQUEST_TIME_FLOAT'] );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_elapsed_seconds_as_float() {
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 1.25;

		$result = timer_float();

		$this->assertIsFloat( $result );
		$this->assertGreaterThanOrEqual( 1.25, $result );
		$this->assertLessThanOrEqual( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'], $result );
	}
}
