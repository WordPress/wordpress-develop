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

	public function test_passes_timed_testcase_details_to_callback() {
		$file      = $this->create_junit_file( array( 0.25, 1.5 ), 1.75 );
		$testcases = array();

		WP_PHPUnit_Timing_Metrics::from_file(
			$file,
			static function ( $testcase ) use ( &$testcases ) {
				$testcases[] = $testcase;
			}
		);

		$this->assertSame(
			array(
				array(
					'name'         => 'test_0',
					'class'        => 'Tests_Example',
					'file'         => '/var/www/tests/phpunit/tests/example.php',
					'line'         => '100',
					'time'         => 0.25,
					'time_display' => '0.25',
				),
				array(
					'name'         => 'test_1',
					'class'        => 'Tests_Example',
					'file'         => '/var/www/tests/phpunit/tests/example.php',
					'line'         => '101',
					'time'         => 1.5,
					'time_display' => '1.5',
				),
			),
			$testcases
		);
	}

	public function test_ignores_testcase_without_numeric_timing() {
		$file      = $this->create_junit_file( array( 0.5, null, 1.5 ), 2.0 );
		$testcases = array();

		$metrics = WP_PHPUnit_Timing_Metrics::from_file(
			$file,
			static function ( $testcase ) use ( &$testcases ) {
				$testcases[] = $testcase;
			}
		);

		$this->assertCount( 2, $testcases );
		$this->assertSame( 1500.0, $metrics['phpunit-max-test-time'] );
	}

	public function test_rejects_report_without_testcases() {
		$file = $this->create_junit_file( array(), 0.0 );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The JUnit timing report contains no testcases.' );

		WP_PHPUnit_Timing_Metrics::from_file( $file );
	}

	public function test_rejects_invalid_xml() {
		$file = $this->create_temporary_file( '<?xml version="1.0"?><testsuites><testsuite><testcase time="1">' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The JUnit timing report contains invalid XML.' );

		WP_PHPUnit_Timing_Metrics::from_file( $file );
	}

	/**
	 * Creates a JUnit XML file for a test.
	 *
	 * @param array<float|null> $times      Testcase times in seconds. Null omits the timing attribute.
	 * @param float|int|null    $suite_time Optional testsuite time in seconds.
	 * @return string Path to the temporary file.
	 */
	private function create_junit_file( $times, $suite_time = null ) {
		$suite_time_attribute = null === $suite_time ? '' : sprintf( ' time="%s"', $suite_time );
		$testcases            = '';

		foreach ( $times as $index => $time ) {
			$time_attribute = null === $time ? '' : sprintf( ' time="%s"', $time );
			$testcases     .= sprintf(
				'<testcase name="test_%1$d" class="Tests_Example" file="/var/www/tests/phpunit/tests/example.php" line="%2$d"%3$s/>',
				$index,
				100 + $index,
				$time_attribute
			);
		}

		return $this->create_temporary_file(
			sprintf( '<?xml version="1.0"?><testsuites><testsuite tests="%1$d"%2$s>%3$s</testsuite></testsuites>', count( $times ), $suite_time_attribute, $testcases )
		);
	}

	/**
	 * Creates a temporary file containing the provided data.
	 *
	 * @param string $data File contents.
	 * @return string Path to the temporary file.
	 */
	private function create_temporary_file( $data ) {
		$file = tempnam( sys_get_temp_dir(), 'junit-timing-' );

		if ( false === $file ) {
			$this->fail( 'Failed to create a temporary JUnit file.' );
		}

		$this->temporary_files[] = $file;
		file_put_contents( $file, $data );

		return $file;
	}
}
