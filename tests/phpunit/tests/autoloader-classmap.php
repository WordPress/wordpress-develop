<?php

/**
 * Tests related to the WP_Autoload class.
 *
 * @group basic
 */
class Tests_Autoloader_Classmap extends WP_UnitTestCase {

	/**
	 * Tests that all classes in the classmap are lowercase.
	 *
	 * @dataProvider data_autoloader_classmap_is_lowercase
	 *
	 * @covers WP_Autoload::CLASSES_PATHS
	 *
	 * @param string $class_name Class name.
	 */
	public function test_autoloader_classmap_is_lowercase( $class_name ) {
		$this->assertSame( strtolower( $class_name ), $class_name, "Class name '$class_name' is not lowercase." );
	}

	/**
	 * Data provider for test_autoloader_classmap_is_lowercase.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_is_lowercase() {
		$class_names = array_keys( WP_Autoload::CLASSES_PATHS );
		return $this->text_array_to_dataprovider( $class_names );
	}

	/**
	 * Tests that all files in the classmap exist.
	 *
	 * @covers WP_Autoload::CLASSES_PATHS
	 *
	 * @dataProvider data_autoloader_classmap_files_exist
	 *
	 * @param string $file_path File path relative to WP root directory.
	 */
	public function test_autoloader_classmap_files_exist( $file_path ) {
		$this->assertFileExists( ABSPATH . $file_path );
	}

	/**
	 * Data provider for test_autoloader_classmap_files_exist.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_files_exist() {
		$file_paths = array_unique( array_values( WP_Autoload::CLASSES_PATHS ) );
		return $this->text_array_to_dataprovider( $file_paths );
	}

	/**
	 * Tests that all classes in the classmap are in the correct file.
	 *
	 * @covers WP_Autoload::CLASSES_PATHS
	 *
	 * @dataProvider data_autoloader_classmap_is_in_correct_file
	 *
	 * @param string $class_name Class name.
	 * @param string $file_path  File path relative to WP root directory.
	 */
	public function test_autoloader_classmap_is_in_correct_file( $class_name, $file_path ) {
		$file_contents = strtolower( file_get_contents( ABSPATH . $file_path ) );

		$this->assertTrue(
			str_contains( $file_contents, "class $class_name" )
				|| str_contains( $file_contents, "interface $class_name" )
				|| str_contains( $file_contents, "trait $class_name" )
		);
	}

	/**
	 * Data provider for test_autoloader_classmap_is_in_correct_file.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_classmap_is_in_correct_file() {
		$data = array();
		foreach ( WP_Autoload::CLASSES_PATHS as $class_name => $file_path ) {
			$data[ $class_name ] = array(
				'class_name' => $class_name,
				'file_path'  => $file_path,
			);
		}

		return $data;
	}

	/**
	 * Tests that all `class-*.php` files in the WP core directory are in the classmap.
	 *
	 * @covers WP_Autoload::CLASSES_PATHS
	 *
	 * @dataProvider data_autoloader_class_files_exist_in_classmap
	 *
	 * @param string $class_name Class name.
	 * @param string $file_path  File path relative to WP root directory.
	 */
	public function test_autoloader_class_files_exist_in_classmap( $class_name, $file_path ) {
		$this->assertArrayHasKey(
			$class_name,
			WP_Autoload::CLASSES_PATHS,
			"Class '$class_name' is missing from the classmap."
		);
	}

	/**
	 * Data provider for test_autoloader_class_files_exist_in_classmap.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_class_files_exist_in_classmap() {
		$files = $this->get_all_wp_class_files();
		$data  = array();
		foreach ( $files as $class_name => $file_path ) {
			$data[ $class_name ] = array(
				'class_name' => $class_name,
				'file_path'  => $file_path,
			);
		}
		return $data;
	}

	/**
	 * Tests that bundled PHPMailer classes are autoloaded.
	 *
	 * @dataProvider data_autoloader_phpmailer_classes
	 *
	 * @covers WP_Autoload::autoload_phpmailer
	 *
	 * @param string $class_name Class name.
	 */
	public function test_autoloader_phpmailer_classes( $class_name ) {
		$this->assertTrue(
			class_exists( $class_name ) || interface_exists( $class_name ),
			"PHPMailer class '$class_name' could not be autoloaded."
		);
	}

	/**
	 * Data provider for test_autoloader_phpmailer_classes.
	 *
	 * @return array Data provider.
	 */
	public function data_autoloader_phpmailer_classes() {
		return $this->text_array_to_dataprovider(
			array(
				'PHPMailer\\PHPMailer\\DSNConfigurator',
				'PHPMailer\\PHPMailer\\Exception',
				'PHPMailer\\PHPMailer\\OAuth',
				'PHPMailer\\PHPMailer\\OAuthTokenProvider',
				'PHPMailer\\PHPMailer\\PHPMailer',
				'PHPMailer\\PHPMailer\\POP3',
				'PHPMailer\\PHPMailer\\SMTP',
			)
		);
	}

	/**
	 * Gets all `class-*.php` files in the WP core directory.
	 *
	 * @return array
	 */
	public function get_all_wp_class_files() {
		static $files = array();
		if ( ! empty( $files ) ) {
			return $files;
		}

		$directory    = new RecursiveDirectoryIterator( ABSPATH . WPINC );
		$iterator     = new RecursiveIteratorIterator( $directory );
		$regex        = new RegexIterator( $iterator, '/^.+\/class\-[a-z-]+\.php$/i', RecursiveRegexIterator::GET_MATCH );
		$ltrim_length = strlen( trailingslashit( ABSPATH ) );

		$package_paths_to_ignore = array(
			'wp-includes/class-wp-autoload.php', // The autoloader itself; loaded explicitly, never autoloaded.
			'wp-includes/class-requests.php',  // 3rd-party library.
			'wp-includes/Requests/',           // 3rd-party library.
			'wp-includes/sodium_compat/',      // 3rd-party library.
			'wp-includes/class-avif-info.php', // 3rd-party library.
			'wp-includes/class-simplepie.php', // 3rd-party library.
			'wp-includes/class-snoopy.php',    // Deprecated.

			/*
			 * SimplePie wrapper classes. These extend SimplePie classes, so they
			 * depend on SimplePie's autoloader being registered first. They are
			 * loaded explicitly in fetch_feed() rather than via the WP autoloader.
			 */
			'wp-includes/class-wp-feed-cache.php',
			'wp-includes/class-wp-feed-cache-transient.php',
			'wp-includes/class-wp-simplepie-file.php',
			'wp-includes/class-wp-simplepie-sanitize-kses.php',
		);

		foreach ( $regex as $file ) {
			$class_file    = $file[0];
			$relative_file = substr( $class_file, $ltrim_length );
			foreach ( $package_paths_to_ignore as $package_path ) {
				if ( str_contains( $relative_file, $package_path ) !== false ) {
					continue 2;
				}
			}

			$file_contents = file_get_contents( $class_file );
			// Extract the class name from the file, allowing optional abstract/final modifiers and leading whitespace.
			preg_match( '/^\s*(?:(?:abstract|final)\s+)?class\s+([a-zA-Z0-9_]+)/m', $file_contents, $matches );
			if ( empty( $matches ) ) {
				continue;
			}
			$class_name           = strtolower( $matches[1] );
			$files[ $class_name ] = $relative_file;
		}

		return $files;
	}
}
