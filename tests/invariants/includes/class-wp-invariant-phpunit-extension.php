<?php
/**
 * Connects the invariants to PHPUnit.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Runner\AfterLastTestHook;
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
 */
final class WP_Invariant_PHPUnit_Extension implements BeforeTestHook, AfterLastTestHook {

	/**
	 * Base name of the report files.
	 *
	 * @var string
	 */
	private $report_name;

	/**
	 * Constructor.
	 *
	 * @param string $report_name Base name of the report files.
	 */
	public function __construct( $report_name = 'invariants' ) {
		$this->report_name = $report_name;
	}

	/**
	 * Starts a test.
	 *
	 * @param string $test Name of the test.
	 */
	public function executeBeforeTest( string $test ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		WP_Invariant::start_test( $test );
	}

	/**
	 * Writes the report.
	 */
	public function executeAfterLastTest(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		WP_Invariant::start_test( '(after the last test)' );

		$directory = getenv( 'WP_INVARIANTS_REPORT_DIR' );
		if ( ! $directory ) {
			$directory = dirname( __DIR__, 2 ) . '/phpunit/build/logs';
		}

		WP_Invariant_Report::write( $directory, $this->report_name );
		fwrite( STDOUT, sprintf( "\nInvariant report: %s/%s.md\n", $directory, $this->report_name ) );
	}
}
