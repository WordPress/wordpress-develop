<?php
/**
 * Security Analyzer - AI-powered vulnerability scanning.
 *
 * @package WordPress
 * @subpackage AI_Security
 * @since 7.1.0
 */

declare( strict_types = 1 );

namespace WordPress\AI_Security;

/**
 * Security Analyzer class.
 *
 * @since 7.1.0
 */
class Analyzer {

	/**
	 * Instance of this class.
	 *
	 * @since 7.1.0
	 * @var Analyzer|null
	 */
	private static ?Analyzer $instance = null;

	/**
	 * Get instance.
	 *
	 * @since 7.1.0
	 * @return Analyzer
	 */
	public static function get_instance(): Analyzer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Scan all installed plugins and themes.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function scan_all_extensions(): array {
		$results = array(
			'plugins' => $this->scan_plugins(),
			'themes'  => $this->scan_themes(),
			'scan_time' => current_time( 'mysql' ),
		);

		// Log the scan
		$logger = Audit_Logger::get_instance();
		$logger->log( 'scan_completed', 'info', 'Security scan completed. Plugins: ' . count( $results['plugins'] ) . ', Themes: ' . count( $results['themes'] ) );

		return $results;
	}

	/**
	 * Scan all installed plugins.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function scan_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$results = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$results[ $plugin_file ] = $this->scan_plugin( $plugin_file );
		}

		return $results;
	}

	/**
	 * Scan a single plugin.
	 *
	 * @since 7.1.0
	 * @param string $plugin_file Plugin file path.
	 * @return array
	 */
	public function scan_plugin( string $plugin_file ): array {
		$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$files      = $this->get_php_files( $plugin_dir );

		$findings = array();
		$file_count = 0;

		foreach ( $files as $file ) {
			$file_count++;
			$content = file_get_contents( $file );

			if ( empty( $content ) ) {
				continue;
			}

			// Use AI to analyze if available
			$client = Client::get_instance();
			$analysis = $client->analyze_code( $content );

			if ( $analysis && ! empty( $analysis['vulnerabilities'] ) ) {
				$findings = array_merge( $findings, $analysis['vulnerabilities'] );
			}
		}

		return array(
			'name'       => $plugin_file,
			'files_scanned' => $file_count,
			'findings'   => $findings,
			'status'     => empty( $findings ) ? 'clean' : 'issues_found',
		);
	}

	/**
	 * Scan all installed themes.
	 *
	 * @since 7.1.0
	 * @return array
	 */
	public function scan_themes(): array {
		$themes = wp_get_themes();
		$results = array();

		foreach ( $themes as $theme ) {
			$results[ $theme->get_stylesheet() ] = $this->scan_theme( $theme );
		}

		return $results;
	}

	/**
	 * Scan a single theme.
	 *
	 * @since 7.1.0
	 * @param \WP_Theme $theme Theme object.
	 * @return array
	 */
	public function scan_theme( \WP_Theme $theme ): array {
		$theme_dir  = $theme->get_stylesheet_directory();
		$files      = $this->get_php_files( $theme_dir );

		$findings = array();
		$file_count = 0;

		foreach ( $files as $file ) {
			$file_count++;
			$content = file_get_contents( $file );

			if ( empty( $content ) ) {
				continue;
			}

			$client = Client::get_instance();
			$analysis = $client->analyze_code( $content );

			if ( $analysis && ! empty( $analysis['vulnerabilities'] ) ) {
				$findings = array_merge( $findings, $analysis['vulnerabilities'] );
			}
		}

		return array(
			'name'       => $theme->get_name(),
			'files_scanned' => $file_count,
			'findings'   => $findings,
			'status'     => empty( $findings ) ? 'clean' : 'issues_found',
		);
	}

	/**
	 * Get all PHP files in a directory.
	 *
	 * @since 7.1.0
	 * @param string $dir Directory path.
	 * @return array
	 */
	private function get_php_files( string $dir ): array {
		$files = array();

		if ( ! is_dir( $dir ) ) {
			return $files;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}
}