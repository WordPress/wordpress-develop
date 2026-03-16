<?php
/**
 * Tests for _wp_connectors_resolve_ai_provider_logo_url().
 *
 * @group connectors
 * @covers ::_wp_connectors_resolve_ai_provider_logo_url
 */
class Tests_Connectors_WpConnectorsResolveAiProviderLogoUrl extends WP_UnitTestCase {

	/**
	 * Files to clean up after each test.
	 *
	 * @var string[]
	 */
	private array $created_files = array();

	/**
	 * Directories to clean up after each test.
	 *
	 * @var string[]
	 */
	private array $created_dirs = array();

	/**
	 * Clean up any files and directories created during the test.
	 */
	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}
		parent::tear_down();
	}

	/**
	 * Creates a temporary file and tracks it for cleanup.
	 *
	 * @param string $path File path.
	 */
	private function create_file( string $path ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$this->created_dirs[] = $dir;
		}
		file_put_contents( $path, '<svg></svg>' );
		$this->created_files[] = $path;
	}

	/**
	 * @ticket 64791
	 */
	public function test_returns_null_when_path_is_empty() {
		$this->assertNull( _wp_connectors_resolve_ai_provider_logo_url( '' ) );
	}

	/**
	 * @ticket 64791
	 */
	public function test_resolves_plugin_dir_path_to_url() {
		$logo_path = WP_PLUGIN_DIR . '/my-plugin/logo.svg';
		$this->create_file( $logo_path );

		$result = _wp_connectors_resolve_ai_provider_logo_url( $logo_path );

		$this->assertSame( site_url( '/wp-content/plugins/my-plugin/logo.svg' ), $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_resolves_mu_plugin_dir_path_to_url() {
		$logo_path = WPMU_PLUGIN_DIR . '/my-mu-plugin/logo.svg';
		$this->create_file( $logo_path );

		$result = _wp_connectors_resolve_ai_provider_logo_url( $logo_path );

		$this->assertSame( site_url( '/wp-content/mu-plugins/my-mu-plugin/logo.svg' ), $result );
	}

	/**
	 * @ticket 64791
	 */
	public function test_returns_null_when_file_does_not_exist() {
		$this->assertNull(
			_wp_connectors_resolve_ai_provider_logo_url( WP_PLUGIN_DIR . '/nonexistent/logo.svg' )
		);
	}

	/**
	 * @ticket 64791
	 * @expectedIncorrectUsage _wp_connectors_resolve_ai_provider_logo_url
	 */
	public function test_returns_null_and_triggers_doing_it_wrong_for_path_outside_plugin_dirs() {
		$tmp_file = tempnam( sys_get_temp_dir(), 'logo_' );
		file_put_contents( $tmp_file, '<svg></svg>' );
		$this->created_files[] = $tmp_file;

		$this->assertNull( _wp_connectors_resolve_ai_provider_logo_url( $tmp_file ) );
	}
}
