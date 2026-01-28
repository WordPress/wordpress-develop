<?php

/**
 * Tests for wp_get_wp_version().
 *
 * @group functions
 *
 * @covers ::wp_get_wp_version
 */
class Tests_Functions_WpGetWpVersion extends WP_UnitTestCase {

	/**
	 * Tests that the WordPress version is returned.
	 *
	 * @ticket 61627
	 */
	public function test_should_return_wp_version() {
		$this->assertSame( $GLOBALS['wp_version'], wp_get_wp_version() );
	}

	/**
	 * Tests that changes to the `$wp_version` global are ignored.
	 *
	 * @ticket 61627
	 */
	public function test_should_ignore_changes_to_wp_version_global() {
		$original_wp_version   = $GLOBALS['wp_version'];
		$GLOBALS['wp_version'] = 'modified_wp_version';
		$actual                = wp_get_wp_version();
		$GLOBALS['wp_version'] = $original_wp_version;

		$this->assertSame( $original_wp_version, $actual );
	}

	/**
	 * Tests the `$wp_version` global matches the return value of requiring version.php.
	 */
	public function test_versions_returned_by_version_php() {
		$versions = require ABSPATH . WPINC . '/version.php';
		$this->assertIsArray( $versions, 'Expected requiring version.php to return an array.' );

		$this->assertEqualSets(
			array(
				'wp_version',
				'wp_db_version',
				'tinymce_version',
				'required_php_version',
				'required_php_extensions',
				'required_mysql_version',
			),
			array_keys( $versions ),
			'Expected the same keys to be returned in the array when requiring version.php.'
		);

		$this->assertSame( wp_get_wp_version(), $versions['wp_version'], 'Expected global $wp_version to match the "wp_version" key.' );
		$this->assertIsString( $versions['wp_version'], 'Expected type for wp_version.' );
		$this->assertIsInt( $versions['wp_db_version'], 'Expected type for wp_db_version.' );
		$this->assertIsString( $versions['tinymce_version'], 'Expected type for tinymce_version.' );
		$this->assertIsString( $versions['required_php_version'], 'Expected type for required_php_version.' );
		$this->assertIsArray( $versions['required_php_extensions'], 'Expected type for required_php_extensions.' );
		$this->assertIsString( $versions['required_mysql_version'], 'Expected type for required_mysql_version.' );
	}
}
