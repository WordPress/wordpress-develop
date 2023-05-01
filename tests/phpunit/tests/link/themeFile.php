<?php
/**
 * @group link
 */
class Tests_Link_ThemeFile extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$themes = array(
			'theme-file-parent',
			'theme-file-child',
		);

		// Copy themes from tests/phpunit/data to wp-content/themes.
		foreach ( $themes as $theme ) {
			$source_dir = DIR_TESTDATA . '/' . $theme;
			$dest_dir   = WP_CONTENT_DIR . '/themes/' . $theme;

			mkdir( $dest_dir );

			foreach ( glob( $source_dir . '/*.*' ) as $theme_file ) {
				copy( $theme_file, $dest_dir . '/' . basename( $theme_file ) );
			}
		}
	}

	public static function wpTearDownAfterClass() {
		$themes = array(
			'theme-file-parent',
			'theme-file-child',
		);

		// Remove previously copied themes from wp-content/themes.
		foreach ( $themes as $theme ) {
			$dest_dir = WP_CONTENT_DIR . '/themes/' . $theme;

			foreach ( glob( $dest_dir . '/*.*' ) as $theme_file ) {
				unlink( $theme_file );
			}

			rmdir( $dest_dir );
		}
	}

	/**
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 *
	 * @covers ::get_theme_file_uri
	 * @covers ::get_parent_theme_file_uri
	 */
	public function test_theme_file_uri_with_parent_theme( $file, $expected_theme, $existence ) {
		switch_theme( 'theme-file-parent' );

		// Ensure the returned URL always uses the parent theme:
		$this->assertSame( content_url( "themes/theme-file-parent/{$file}" ), get_theme_file_uri( $file ) );
		$this->assertSame( content_url( "themes/theme-file-parent/{$file}" ), get_parent_theme_file_uri( $file ) );
	}

	/**
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 *
	 * @covers ::get_theme_file_uri
	 * @covers ::get_parent_theme_file_uri
	 */
	public function test_theme_file_uri_with_child_theme( $file, $expected_theme, $existence ) {
		switch_theme( 'theme-file-child' );

		// Ensure the returned URL uses the expected theme:
		$this->assertSame( content_url( "themes/{$expected_theme}/{$file}" ), get_theme_file_uri( $file ) );

		// Ensure the returned URL always uses the parent theme:
		$this->assertSame( content_url( "themes/theme-file-parent/{$file}" ), get_parent_theme_file_uri( $file ) );
	}

	/**
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 *
	 * @covers ::get_theme_file_path
	 * @covers ::get_parent_theme_file_path
	 */
	public function test_theme_file_path_with_parent_theme( $file, $expected_theme, $existence ) {
		switch_theme( 'theme-file-parent' );

		// Ensure the returned path always uses the parent theme:
		$this->assertSame( WP_CONTENT_DIR . "/themes/theme-file-parent/{$file}", get_theme_file_path( $file ) );
		$this->assertSame( WP_CONTENT_DIR . "/themes/theme-file-parent/{$file}", get_parent_theme_file_path( $file ) );
	}

	/**
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 *
	 * @covers ::get_theme_file_path
	 * @covers ::get_parent_theme_file_path
	 */
	public function test_theme_file_path_with_child_theme( $file, $expected_theme, $existence ) {
		switch_theme( 'theme-file-child' );

		// Ensure the returned path uses the expected theme:
		$this->assertSame( WP_CONTENT_DIR . "/themes/{$expected_theme}/{$file}", get_theme_file_path( $file ) );

		// Ensure the returned path always uses the parent theme:
		$this->assertSame( WP_CONTENT_DIR . "/themes/theme-file-parent/{$file}", get_parent_theme_file_path( $file ) );
	}

	/**
	 * Test the tests.
	 *
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 */
	public function test_theme_file_existance( $file, $expected_theme, $existence ) {

		if ( in_array( 'theme-file-child', $existence, true ) ) {
			$this->assertFileExists( WP_CONTENT_DIR . "/themes/theme-file-child/{$file}" );
		} else {
			$this->assertFileDoesNotExist( WP_CONTENT_DIR . "/themes/theme-file-child/{$file}" );
		}

		if ( in_array( 'theme-file-parent', $existence, true ) ) {
			$this->assertFileExists( WP_CONTENT_DIR . "/themes/theme-file-parent/{$file}" );
		} else {
			$this->assertFileDoesNotExist( WP_CONTENT_DIR . "/themes/theme-file-parent/{$file}" );
		}

	}

	/**
	 * @ticket 18302
	 *
	 * @dataProvider data_theme_files
	 *
	 * @covers ::get_theme_file_uri
	 * @covers ::get_parent_theme_file_uri
	 */
	public function test_theme_file_uri_returns_valid_uri( $file, $expected_theme, $existence ) {
		$uri        = get_theme_file_uri( $file );
		$parent_uri = get_parent_theme_file_uri( $file );

		$this->assertSame( sanitize_url( $uri ), $uri );
		$this->assertSame( sanitize_url( $parent_uri ), $parent_uri );
	}

	public function data_theme_files() {
		$parent = 'theme-file-parent';
		$child  = 'theme-file-child';

		return array(
			array(
				'parent-only.php',
				$parent,
				array(
					$parent,
				),
			),
			array(
				'child-only.php',
				$child,
				array(
					$child,
				),
			),
			array(
				'parent-and-child.php',
				$child,
				array(
					$parent,
					$child,
				),
			),
			array(
				'neither.php',
				$parent,
				array(),
			),
		);
	}

}
