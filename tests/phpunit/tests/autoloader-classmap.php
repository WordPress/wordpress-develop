<?php

/**
 * Tests related to the WP_Autoload class.
 *
 * @group basic
 */
class Tests_Autoloader_Classmap extends WP_UnitTestCase {

	/**
	 * Test that all classes in the classmap are lowercase.
	 */
	public function test_autoloader_classmap_is_lowercase() {
		foreach ( array_keys( WP_Autoload::CLASSES_PATHS ) as $class_name ) {
			$this->assertSame( strtolower( $class_name ), $class_name );
		}
	}

	/**
	 * Test that all files in the classmap exist.
	 */
	public function test_autoloader_classmap_files_exist() {
		foreach ( WP_Autoload::CLASSES_PATHS as $file_path ) {
			$this->assertFileExists( ABSPATH . $file_path );
		}
	}

	/**
	 * Test that all classes in the classmap are in the correct file.
	 */
	public function test_autoloader_classmap_is_in_correct_file() {
		foreach ( WP_Autoload::CLASSES_PATHS as $class_name => $file_path ) {
			$this->assertTrue(
				str_contains(
					strtolower( file_get_contents( ABSPATH . $file_path ) ),
					"class $class_name"
				)
			);
		}
	}
}
