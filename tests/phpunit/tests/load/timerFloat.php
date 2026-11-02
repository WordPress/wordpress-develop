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
	 *
	 * @dataProvider data_request_ages
	 *
	 * @param float $request_age     Age of the request in seconds.
	 * @param float $minimum_elapsed Minimum expected elapsed seconds.
	 */
	public function test_returns_elapsed_seconds_as_float( $request_age, $minimum_elapsed ) {
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - $request_age;

		$result = timer_float();

		$this->assertIsFloat( $result );
		$this->assertGreaterThanOrEqual( $minimum_elapsed, $result );
		$this->assertLessThanOrEqual( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'], $result );
	}

	/**
	 * @return array[]
	 */
	public static function data_request_ages() {
		return array(
			'recent request' => array( 1.25, 1.25 ),
			'older request'  => array( 10.0, 10.0 ),
		);
	}
}
