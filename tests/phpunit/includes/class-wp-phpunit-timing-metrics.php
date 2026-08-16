<?php

/**
 * Extracts aggregate timing metrics from a PHPUnit JUnit report.
 */
final class WP_PHPUnit_Timing_Metrics {

	/**
	 * Extracts timing metrics from a JUnit XML file.
	 *
	 * @param string $file Path to the JUnit XML file.
	 * @return array<string, float|int> Timing metrics keyed for CodeVitals.
	 * @throws RuntimeException If the file cannot be read or contains invalid timing data.
	 */
	public static function from_file( $file ) {
		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( 'The JUnit timing report could not be read.' );
		}

		$reader = new XMLReader();
		if ( ! $reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
			throw new RuntimeException( 'The JUnit timing report could not be opened.' );
		}

		$suite_time = null;
		$test_times = array();

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
			if ( ! is_numeric( $time ) ) {
				$reader->close();
				throw new RuntimeException( 'A JUnit testcase is missing numeric timing data.' );
			}

			$test_times[] = (float) $time;
		}

		$reader->close();

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
