<?php

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-phpunit-timing-metrics.php';

/**
 * @group core-vitals-tooling
 *
 * @ticket 65887
 */
class Tests_Includes_JUnit_Timing_Metrics extends WP_UnitTestCase {

	/**
	 * Temporary files created by a test.
	 *
	 * @var string[]
	 */
	private $temporary_files = array();

	public function tear_down() {
		foreach ( $this->temporary_files as $file ) {
			unlink( $file );
		}

		parent::tear_down();
	}

	public function test_extracts_aggregate_timing_metrics() {
		$times = array_map(
			static fn ( $millisecond ) => $millisecond / 1000,
			range( 1, 100 )
		);
		$file  = $this->create_junit_file( $times, 5.05 );

		$this->assertSame(
			array(
				'phpunit-suite-time'       => 5.05,
				'phpunit-p95-test-time'    => 95.0,
				'phpunit-p99-test-time'    => 99.0,
				'phpunit-max-test-time'    => 100.0,
				'phpunit-tests-over-500ms' => 0,
				'phpunit-tests-over-1s'    => 0,
			),
			WP_PHPUnit_Timing_Metrics::from_file( $file )
		);
	}

	public function test_counts_only_tests_above_slow_test_thresholds() {
		$file = $this->create_junit_file( array( 0.5, 0.500001, 1.0, 1.000001 ), 3.000002 );

		$metrics = WP_PHPUnit_Timing_Metrics::from_file( $file );

		$this->assertSame( 3, $metrics['phpunit-tests-over-500ms'] );
		$this->assertSame( 1, $metrics['phpunit-tests-over-1s'] );
	}

	public function test_uses_testcase_time_when_suite_time_is_missing() {
		$file = $this->create_junit_file( array( 0.1, 0.2, 0.3 ) );

		$metrics = WP_PHPUnit_Timing_Metrics::from_file( $file );

		$this->assertSame( 0.6, $metrics['phpunit-suite-time'] );
	}

	public function test_rejects_report_without_testcases() {
		$file = $this->create_junit_file( array(), 0.0 );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The JUnit timing report contains no testcases.' );

		WP_PHPUnit_Timing_Metrics::from_file( $file );
	}

	/**
	 * Creates a JUnit XML file for a test.
	 *
	 * @param float[]   $times      Testcase times in seconds.
	 * @param float|int $suite_time Optional testsuite time in seconds.
	 * @return string Path to the temporary file.
	 */
	private function create_junit_file( $times, $suite_time = null ) {
		$file                    = tempnam( sys_get_temp_dir(), 'junit-timing-' );
		$this->temporary_files[] = $file;
		$suite_time_attribute    = null === $suite_time ? '' : sprintf( ' time="%s"', $suite_time );
		$testcases               = '';

		foreach ( $times as $index => $time ) {
			$testcases .= sprintf( '<testcase name="test_%1$d" time="%2$s"/>', $index, $time );
		}

		file_put_contents(
			$file,
			sprintf( '<?xml version="1.0"?><testsuites><testsuite tests="%1$d"%2$s>%3$s</testsuite></testsuites>', count( $times ), $suite_time_attribute, $testcases )
		);

		return $file;
	}
}
