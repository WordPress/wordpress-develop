<?php
/**
 * Tests that a file read only for reflection gets the docblocks the visitors derive.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Checks the redefinition of `currentPhpVersionSimpleParser` in base.neon.
 *
 * That redefinition makes a file that is only scanned, so that what it declares can be
 * reflected, go through the same parser node visitors as an analyzed one. PHPStan's own
 * test harness cannot show whether it works, because the harness already parses every
 * file with the rich parser (see `currentPhpVersionSimpleParser!` in PHPStan's
 * src/Testing/TestCase.neon). So this test runs a real analysis instead, with base.neon,
 * of a fixture calling a function declared in a scanned file.
 *
 * Should PHPStan rename the service that base.neon redefines, the scanned function is
 * reflected with the plain `array` its docblock declares, and this test fails.
 */
final class ReflectionParserTest extends TestCase {

	/**
	 * The directory PHPStan writes its cache to for the run.
	 *
	 * @var string
	 */
	private string $tmp_dir;

	/**
	 * Creates the cache directory, empty, so that no earlier run's reading is restored.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->tmp_dir = sys_get_temp_dir() . '/wp-phpstan-reflection-' . bin2hex( random_bytes( 8 ) );
		$this->assertTrue( mkdir( $this->tmp_dir ) );
	}

	/**
	 * Removes the cache directory.
	 */
	protected function tearDown(): void {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->tmp_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file ) {
			if ( ! $file instanceof SplFileInfo ) {
				continue;
			}
			if ( $file->isDir() && ! $file->isLink() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $this->tmp_dir );

		parent::tearDown();
	}

	/**
	 * Tests that the shape documented in a scanned file is reflected.
	 */
	public function test_scanned_file_is_parsed_with_the_visitors(): void {
		$data    = __DIR__ . '/data/reflection';
		$command = array(
			PHP_BINARY,
			dirname( __DIR__, 3 ) . '/vendor/phpstan/phpstan/phpstan',
			'analyse',
			'--configuration=' . $data . '/phpstan.neon',
			'--no-progress',
			'--error-format=json',
			$data . '/analyzed.php',
		);

		$env                             = getenv();
		$env['WP_PHPSTAN_TESTS_TMP_DIR'] = $this->tmp_dir;

		// Standard error goes to a file rather than a second pipe. Reading one pipe to
		// its end while the other fills up would leave both processes waiting on each
		// other, should PHPStan ever write more to standard error than a pipe holds.
		$errors_file = $this->tmp_dir . '/stderr.log';

		$process = proc_open(
			$command,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'file', $errors_file, 'w' ),
			),
			$pipes,
			null,
			$env
		);
		$this->assertIsResource( $process );

		$output = (string) stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$status = proc_close( $process );
		$errors = (string) file_get_contents( $errors_file );

		// PHPStan exits 1 when it reports errors, as it does for the dumped type.
		$this->assertSame( 1, $status, "PHPStan did not complete.\n" . $output . $errors );

		$report = json_decode( $output, true );
		$this->assertIsArray( $report, "PHPStan did not print a report.\n" . $output . $errors );

		$this->assertSame(
			array(
				$data . '/analyzed.php' => array(
					'errors'   => 1,
					'messages' => array(
						array(
							'message'    => 'Dumped type: array{name: string, count: int}',
							'line'       => 11,
							'ignorable'  => false,
							'identifier' => 'phpstan.dumpType',
						),
					),
				),
			),
			$report['files'] ?? null
		);
	}
}
