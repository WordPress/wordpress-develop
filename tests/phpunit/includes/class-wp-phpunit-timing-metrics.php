<?php

/**
 * Extracts aggregate timing metrics from a PHPUnit JUnit report.
 */
final class WP_PHPUnit_Timing_Metrics {

	/**
	 * Extracts timing metrics from a JUnit XML file.
	 *
	 * @param string        $file              Path to the JUnit XML file.
	 * @param callable|null $testcase_callback Optional callback invoked for each timed testcase.
	 * @return array<string, float|int> Timing metrics keyed for CodeVitals.
	 * @throws RuntimeException If the file cannot be read or contains invalid XML.
	 */
	public static function from_file( $file, $testcase_callback = null ) {
		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( 'The JUnit timing report could not be read.' );
		}

		if ( null !== $testcase_callback && ! is_callable( $testcase_callback ) ) {
			throw new InvalidArgumentException( 'The testcase callback must be callable.' );
		}

		$reader                = new XMLReader();
		$previous_libxml_state = libxml_use_internal_errors( true );
		$reader_is_open        = false;
		$suite_time            = null;
		$test_times            = array();
		$xml_errors            = array();

		libxml_clear_errors();

		try {
			if ( ! $reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
				throw new RuntimeException( 'The JUnit timing report could not be opened.' );
			}

			$reader_is_open = true;

			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}

				if ( null === $suite_time && 'testsuite' === $reader->name ) {
					$time = $reader->getAttribute( 'time' );
					if ( is_numeric( $time ) ) {
						$suite_time = (float) $time;
					}
					continue;
				}

				if ( 'testcase' !== $reader->name ) {
					continue;
				}

				$time = $reader->getAttribute( 'time' );

				// A testcase without numeric timing, such as a skipped test, carries
				// no timing signal and is excluded from the aggregate metrics.
				if ( ! is_numeric( $time ) ) {
					continue;
				}

				$test_time    = (float) $time;
				$test_times[] = $test_time;

				if ( null !== $testcase_callback ) {
					$testcase_callback(
						array(
							'name'         => (string) $reader->getAttribute( 'name' ),
							'class'        => (string) $reader->getAttribute( 'class' ),
							'file'         => (string) $reader->getAttribute( 'file' ),
							'line'         => (string) $reader->getAttribute( 'line' ),
							'time'         => $test_time,
							'time_display' => (string) $time,
						)
					);
				}
			}

			$xml_errors = libxml_get_errors();
		} finally {
			if ( $reader_is_open ) {
				$reader->close();
			}

			libxml_clear_errors();
			libxml_use_internal_errors( $previous_libxml_state );
		}

		foreach ( $xml_errors as $xml_error ) {
			if ( LIBXML_ERR_WARNING < $xml_error->level ) {
				throw new RuntimeException( 'The JUnit timing report contains invalid XML.' );
			}
		}

		if ( ! $test_times ) {
			throw new RuntimeException( 'The JUnit timing report contains no testcases.' );
		}

		if ( null === $suite_time ) {
			$suite_time = array_sum( $test_times );
		}

		sort( $test_times, SORT_NUMERIC );

		return array(
			'phpunit-suite-time'       => round( $suite_time, 6 ),
			'phpunit-p95-test-time'    => round( self::percentile( $test_times, 0.95 ) * 1000, 3 ),
			'phpunit-p99-test-time'    => round( self::percentile( $test_times, 0.99 ) * 1000, 3 ),
			'phpunit-max-test-time'    => round( max( $test_times ) * 1000, 3 ),
			'phpunit-tests-over-500ms' => count( array_filter( $test_times, static fn ( $time ) => $time > 0.5 ) ),
			'phpunit-tests-over-1s'    => count( array_filter( $test_times, static fn ( $time ) => $time > 1 ) ),
		);
	}

	/**
	 * Calculates a nearest-rank percentile from a sorted list.
	 *
	 * @param float[] $values     Sorted values.
	 * @param float   $percentile Percentile between zero and one.
	 * @return float Percentile value.
	 */
	private static function percentile( $values, $percentile ) {
		$index = (int) ceil( $percentile * count( $values ) ) - 1;

		return $values[ max( 0, $index ) ];
	}
}
