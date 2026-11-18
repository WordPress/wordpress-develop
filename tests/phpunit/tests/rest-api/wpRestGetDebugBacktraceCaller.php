<?php

/**
 * Tests for the _wp_rest_get_debug_backtrace_caller() function.
 *
 * The function only reports frames from files inside WP_CONTENT_DIR, so each
 * test calls it from a fixture file written there, the way a plugin would.
 *
 * @group restapi
 *
 * @covers ::_wp_rest_get_debug_backtrace_caller
 */
class Tests_REST_API_WpRestGetDebugBacktraceCaller extends WP_UnitTestCase {

	/**
	 * Fixture files written by the current test, to be deleted afterwards.
	 *
	 * @var string[]
	 */
	private $fixture_files = array();

	/**
	 * Fixture directories created by the current test, to be deleted afterwards,
	 * as pairs of the innermost directory and the outermost one that was created.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private $fixture_dirs = array();

	public function tear_down() {
		foreach ( $this->fixture_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		foreach ( array_reverse( $this->fixture_dirs ) as list( $dir, $top ) ) {
			while ( is_dir( $dir ) ) {
				rmdir( $dir );
				if ( $dir === $top ) {
					break;
				}
				$dir = dirname( $dir );
			}
		}

		parent::tear_down();
	}

	/**
	 * Writes a PHP fixture file, creating its directory if needed.
	 *
	 * @param string   $dir   Directory to write the file to.
	 * @param string[] $lines Lines of the file, without the opening PHP tag.
	 * @return string Path of the written file.
	 */
	private function write_fixture( $dir, array $lines ) {
		if ( ! is_dir( $dir ) ) {
			$top = $dir;
			while ( ! is_dir( dirname( $top ) ) ) {
				$top = dirname( $top );
			}
			mkdir( $dir, 0777, true );
			$this->fixture_dirs[] = array( $dir, $top );
		}

		$file = $dir . '/' . uniqid( 'fixture-', true ) . '.php';
		file_put_contents( $file, "<?php\n" . implode( "\n", $lines ) . "\n" );
		$this->fixture_files[] = $file;

		return $file;
	}

	/**
	 * Gets the directory for fixtures inside WP_CONTENT_DIR.
	 *
	 * @return string Directory path.
	 */
	private function content_dir() {
		return WP_CONTENT_DIR . '/plugins/wp-rest-backtrace-fixture';
	}

	/**
	 * Tests that a call made outside WP_CONTENT_DIR has no caller to report.
	 *
	 * @ticket 64260
	 */
	public function test_should_return_empty_string_without_a_content_dir_frame() {
		$this->assertSame( '', _wp_rest_get_debug_backtrace_caller() );
	}

	/**
	 * Tests that a call from a named function reports the function, file and line.
	 *
	 * @ticket 64260
	 */
	public function test_should_report_the_calling_function() {
		$function = 'wp_rest_backtrace_fixture_' . wp_rand();
		$file     = $this->write_fixture(
			$this->content_dir(),
			array(
				"function {$function}() {",
				'	return _wp_rest_get_debug_backtrace_caller();',
				'}',
			)
		);
		require $file;

		$this->assertSame(
			' called from ' . $function . '() in ' . wp_normalize_path( $file ) . ' on line 3',
			$function()
		);
	}

	/**
	 * Tests that a call from a method reports the class and the call type.
	 *
	 * @ticket 64260
	 *
	 * @dataProvider data_method_calls
	 *
	 * @param string $call_type How the method is called: '->' or '::'.
	 */
	public function test_should_report_the_calling_method( $call_type ) {
		$class = 'WP_REST_Backtrace_Fixture_' . wp_rand();
		$file  = $this->write_fixture(
			$this->content_dir(),
			array(
				"class {$class} {",
				'	public function instance_method() {',
				'		return _wp_rest_get_debug_backtrace_caller();',
				'	}',
				'	public static function static_method() {',
				'		return _wp_rest_get_debug_backtrace_caller();',
				'	}',
				'}',
			)
		);
		require $file;

		if ( '->' === $call_type ) {
			$result   = ( new $class() )->instance_method();
			$expected = ' called from ' . $class . '->instance_method() in ' . wp_normalize_path( $file ) . ' on line 4';
		} else {
			$result   = $class::static_method();
			$expected = ' called from ' . $class . '::static_method() in ' . wp_normalize_path( $file ) . ' on line 7';
		}

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_method_calls() {
		return array(
			'instance method' => array( '->' ),
			'static method'   => array( '::' ),
		);
	}

	/**
	 * Tests that a call from a closure is reported as an anonymous function.
	 *
	 * @ticket 64260
	 */
	public function test_should_report_a_closure_as_an_anonymous_function() {
		$file    = $this->write_fixture(
			$this->content_dir(),
			array(
				'return function () {',
				'	return _wp_rest_get_debug_backtrace_caller();',
				'};',
			)
		);
		$closure = require $file;

		$this->assertSame(
			' called from (anonymous function) in ' . wp_normalize_path( $file ) . ' on line 3',
			$closure()
		);
	}

	/**
	 * Tests that a call from a file's global scope reports only the location,
	 * not the include or require that loaded the file.
	 *
	 * @ticket 64260
	 */
	public function test_should_report_only_the_location_for_a_global_scope_call() {
		$file = $this->write_fixture(
			$this->content_dir(),
			array(
				'return _wp_rest_get_debug_backtrace_caller();',
			)
		);

		$this->assertSame(
			' in ' . wp_normalize_path( $file ) . ' on line 2',
			require $file
		);
	}

	/**
	 * Tests that a directory whose name only starts with the content directory's
	 * name, such as wp-content-backup, is not treated as part of it.
	 *
	 * @ticket 64260
	 */
	public function test_should_ignore_a_sibling_directory_sharing_the_content_dir_prefix() {
		$function = 'wp_rest_backtrace_fixture_' . wp_rand();
		$file     = $this->write_fixture(
			untrailingslashit( WP_CONTENT_DIR ) . '-backup/plugins/wp-rest-backtrace-fixture',
			array(
				"function {$function}() {",
				'	return _wp_rest_get_debug_backtrace_caller();',
				'}',
			)
		);
		require $file;

		$this->assertSame( '', $function() );
	}

	/**
	 * Tests that the frame closest to the call is reported when several frames
	 * are inside WP_CONTENT_DIR, such as a plugin calling a helper in another plugin.
	 *
	 * @ticket 64260
	 */
	public function test_should_report_the_innermost_content_dir_frame() {
		$inner = 'wp_rest_backtrace_fixture_inner_' . wp_rand();
		$outer = 'wp_rest_backtrace_fixture_outer_' . wp_rand();
		$file  = $this->write_fixture(
			$this->content_dir(),
			array(
				"function {$inner}() {",
				'	return _wp_rest_get_debug_backtrace_caller();',
				'}',
				"function {$outer}() {",
				"	return {$inner}();",
				'}',
			)
		);
		require $file;

		$this->assertSame(
			' called from ' . $inner . '() in ' . wp_normalize_path( $file ) . ' on line 3',
			$outer()
		);
	}
}
