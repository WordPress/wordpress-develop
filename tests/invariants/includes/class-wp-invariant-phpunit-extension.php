<?php
/**
 * Connects the invariants to PHPUnit.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;

/**
 * Tells the invariants which test is running, and writes the report after the last one.
 *
 * It only reports: it never fails a test. Register it in a PHPUnit configuration:
 *
 *     <extensions>
 *         <extension class="WP_Invariant_PHPUnit_Extension">
 *             <arguments><string>invariants</string></arguments>
 *         </extension>
 *     </extensions>
 *
 * The report is written to tests/phpunit/build/logs/, or to the directory in the
 * environment variable WP_INVARIANTS_REPORT_DIR.
 *
 * Running with invariants is several times slower than without, so it also
 * reports how far the run has got: every 1,000 tests, and every test which takes
 * longer than 30 seconds, on STDERR. The test which is running is kept in
 * `<name>-current-test.txt`, so that a run which is stopped can tell where.
 */
final class WP_Invariant_PHPUnit_Extension implements BeforeTestHook, AfterTestHook, AfterLastTestHook {

	/**
	 * Tests which take longer than this many seconds are reported.
	 */
	const SLOW_SECONDS = 30;

	/**
	 * Progress is reported after every this many tests.
	 */
	const PROGRESS_EVERY = 1000;

	/**
	 * Base name of the report files.
	 *
	 * @var string
	 */
	private $report_name;

	/**
	 * Directory for the report files.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * When the first test started, from hrtime().
	 *
	 * @var int|null
	 */
	private $started_at = null;

	/**
	 * Tests which have finished.
	 *
	 * @var int
	 */
	private $finished = 0;

	/**
	 * Constructor.
	 *
	 * @param string $report_name Base name of the report files.
	 */
	public function __construct( $report_name = 'invariants' ) {
		$this->report_name = $report_name;
		$this->directory   = getenv( 'WP_INVARIANTS_REPORT_DIR' );
		if ( ! $this->directory ) {
			$this->directory = dirname( __DIR__, 2 ) . '/phpunit/build/logs';
		}

		if ( array() !== WP_Invariant_Types::hooked() ) {
			WP_Invariant_Types::collect_from_processes( $this->processes_directory() );
		}
	}

	/**
	 * Starts a test.
	 *
	 * @param string $test Name of the test.
	 */
	public function executeBeforeTest( string $test ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		if ( null === $this->started_at ) {
			$this->started_at = hrtime( true );
			if ( ! is_dir( $this->directory ) ) {
				mkdir( $this->directory, 0777, true );
			}
		}

		file_put_contents( "{$this->directory}/{$this->report_name}-current-test.txt", sprintf( "%s after %s: %s\n", gmdate( 'H:i:s' ), $this->elapsed(), $test ) );
		WP_Invariant::start_test( $test );
	}

	/**
	 * Reports slow tests, and progress.
	 *
	 * @param string $test Name of the test.
	 * @param float  $time How long it took, in seconds.
	 */
	public function executeAfterTest( string $test, float $time ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		++$this->finished;
		if ( $time >= self::SLOW_SECONDS ) {
			fwrite( STDERR, sprintf( "\n[invariants] Slow test, %.0f s: %s\n", $time, $test ) );
		}
		if ( 0 === $this->finished % self::PROGRESS_EVERY ) {
			fwrite( STDERR, sprintf( "\n[invariants] %s tests after %s\n", number_format( $this->finished ), $this->elapsed() ) );
		}
	}

	/**
	 * Writes the report.
	 */
	public function executeAfterLastTest(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		WP_Invariant::start_test( '(after the last test)' );

		WP_Invariant_Report::write( $this->directory, $this->report_name );
		@unlink( "{$this->directory}/{$this->report_name}-current-test.txt" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- It may not exist.
		fwrite( STDOUT, sprintf( "\nInvariant report, after %s tests in %s: %s/%s.md\n", number_format( $this->finished ), $this->elapsed(), $this->directory, $this->report_name ) );

		if ( array() !== WP_Invariant_Types::hooked() ) {
			$processes = glob( $this->processes_directory() . '/*.serialized' );
			WP_Invariant_Types_Report::write( $this->directory, "{$this->report_name}-types", WP_Invariant_Types::hooked(), WP_Invariant_Types::profile(), $processes ? $processes : array() );
			// They exist only when a separate process saved what it collected, and the parent only while no other run uses it.
			@rmdir( $this->processes_directory() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@rmdir( dirname( $this->processes_directory() ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			fwrite( STDOUT, sprintf( "Types report: %s/%s-types.md\n", $this->directory, $this->report_name ) );
		}
	}

	/**
	 * Returns the directory where tests in separate processes leave the types they collected.
	 *
	 * It is this run's own, so that what an earlier run which was stopped left is not merged.
	 *
	 * @return string
	 */
	private function processes_directory() {
		return "{$this->directory}/{$this->report_name}-types-processes/" . getmypid();
	}

	/**
	 * Returns how long the run has taken, such as `12m 05s`.
	 *
	 * @return string
	 */
	private function elapsed() {
		$seconds = null === $this->started_at ? 0 : intdiv( hrtime( true ) - $this->started_at, 1000000000 );
		return sprintf( '%dm %02ds', intdiv( $seconds, 60 ), $seconds % 60 );
	}
}
