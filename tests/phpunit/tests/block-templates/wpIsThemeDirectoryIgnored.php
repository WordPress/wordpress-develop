<?php
/**
 * Tests for wp_is_theme_directory_ignored().
 *
 * @group block-templates
 *
 * @covers ::wp_is_theme_directory_ignored
 */
class Tests_Block_Templates_WpIsThemeDirectoryIgnored extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_wp_is_theme_directory_ignored
	 *
	 * @param string $path     Path relative to the theme directory.
	 * @param bool   $expected Whether the path is expected to be ignored.
	 */
	public function test_wp_is_theme_directory_ignored( $path, $expected ) {
		$this->assertSame( $expected, wp_is_theme_directory_ignored( $path ) );
	}

	/**
	 * Data provider for test_wp_is_theme_directory_ignored().
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_wp_is_theme_directory_ignored() {
		return array(
			'git directory'          => array( '.git/templates/index.html', true ),
			'svn directory'          => array( '.svn/templates/index.html', true ),
			'node_modules directory' => array( 'node_modules/package/templates/index.html', true ),
			'vendor directory'       => array( 'vendor/package/templates/index.html', true ),
			'templates directory'    => array( 'templates/index.html', false ),
			'parts directory'        => array( 'parts/header.html', false ),
			'nested vendor'          => array( 'templates/vendor/index.html', false ),
		);
	}
}
