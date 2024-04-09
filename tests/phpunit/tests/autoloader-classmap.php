<?php

/**
 * Tests related to the WP_Autoload class.
 *
 * @group basic
 */
class Tests_Autoloader_Classmap extends WP_UnitTestCase {

	/**
	 * Test that all classes in the classmap are lowercase.
	 *
	 * @dataProvider data_autoloader_classmap_is_lowercase
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

		return array_map(
			function ( $class_name ) {
				return array( $class_name );
			},
			$class_names
		);
	}

	/**
	 * Test that all files in the classmap exist.
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
		$file_paths = array_values( WP_Autoload::CLASSES_PATHS );

		return array_map(
			function ( $file_path ) {
				return array( $file_path );
			},
			$file_paths
		);
	}

	/**
	 * Test that all classes in the classmap are in the correct file.
	 *
	 * @dataProvider data_autoloader_classmap_is_in_correct_file
	 *
	 * @param string $class_name Class name.
	 * @param string $file_path  File path relative to WP root directory.
	 */
	public function test_autoloader_classmap_is_in_correct_file( $class_name, $file_path ) {
		$this->assertTrue(
			str_contains(
				strtolower( file_get_contents( ABSPATH . $file_path ) ),
				"class $class_name"
			)
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
			$data[] = array( $class_name, $file_path );
		}

		return $data;
	}
}
