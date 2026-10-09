<?php

/**
 * @group admin
 * @group site-health
 *
 * @coversDefaultClass WP_Debug_Data
 */
class Tests_Admin_wpDebugData extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	private $original_path;

	/**
	 * @var string
	 */
	private $tmp_dir;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';

		// Avoid the external request made by WP_Debug_Data.
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'test_http_request', 'HTTP request disabled for this test.' );
			}
		);

		$this->original_path = (string) getenv( 'PATH' );
		$this->tmp_dir       = sys_get_temp_dir() . '/gs-test-' . uniqid();
		mkdir( $this->tmp_dir );
	}

	public function tear_down() {
		putenv( 'PATH=' . $this->original_path );

		if ( file_exists( $this->tmp_dir . '/gs' ) ) {
			unlink( $this->tmp_dir . '/gs' );
		}
		rmdir( $this->tmp_dir );

		parent::tear_down();
	}

	/**
	 * Runs debug_data() with a custom PATH and returns the Ghostscript field.
	 */
	private function get_ghostscript_field( string $path ): array {
		putenv( 'PATH=' . $path );

		try {
			$info = WP_Debug_Data::debug_data();
		} finally {
			// Restore PATH right away, even if debug_data() throws.
			putenv( 'PATH=' . $this->original_path );
		}

		return $info['wp-media']['fields']['ghostscript_version'];
	}

	/**
	 * @ticket 66245
	 * @covers ::debug_data()
	 */
	public function test_ghostscript_version_is_reported_when_available() {
		// Fake gs binary that prints a version.
		file_put_contents( $this->tmp_dir . '/gs', "#!/bin/sh\necho 10.02.1\n" );
		chmod( $this->tmp_dir . '/gs', 0755 );

		$field = $this->get_ghostscript_field( $this->tmp_dir . ':' . $this->original_path );

		$this->assertSame( '10.02.1', $field['debug'] );
	}

	/**
	 * @ticket 66245
	 * @covers ::debug_data()
	 */
	public function test_ghostscript_is_reported_as_not_available_when_missing() {
		// PATH points to an empty directory: gs cannot be found.
		$field = $this->get_ghostscript_field( $this->tmp_dir );

		$this->assertSame( 'not available', $field['debug'] );
	}
}
