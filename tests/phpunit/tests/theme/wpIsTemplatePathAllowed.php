<?php

/**
 * Tests for _wp_is_template_path_allowed().
 *
 * @group themes
 *
 * @covers ::_wp_is_template_path_allowed
 */
class Tests_Theme_wpIsTemplatePathAllowed extends WP_UnitTestCase {

	/**
	 * Tests that a path without a parent directory segment is allowed without further checks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_paths_without_parent_directory_segment
	 *
	 * @param string $path Path to check.
	 */
	public function test_should_allow_path_without_parent_directory_segment( $path ) {
		switch_theme( 'default' );

		$this->assertTrue( _wp_is_template_path_allowed( $path ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_paths_without_parent_directory_segment() {
		return array(
			'an empty string'                  => array( '' ),
			'a string zero'                    => array( '0' ),
			'a file name'                      => array( 'index.php' ),
			'a current directory segment'      => array( './index.php' ),
			'two dots inside a file name'      => array( 'index..php' ),
			'two dots starting a file name'    => array( '..index.php' ),
			'two dots ending a directory name' => array( 'templates../index.php' ),
			'two dots starting a directory'    => array( '..templates/index.php' ),
		);
	}

	/**
	 * Tests that a path with a parent directory segment is only allowed
	 * when it resolves inside a directory that templates are loaded from.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_paths_with_parent_directory_segment
	 *
	 * @param string $stylesheet Stylesheet of the theme to switch to.
	 * @param string $path       Path to check.
	 * @param bool   $expected   Whether the path is expected to be allowed.
	 */
	public function test_should_resolve_path_with_parent_directory_segment( $stylesheet, $path, $expected ) {
		switch_theme( $stylesheet );

		$this->assertSame( $expected, _wp_is_template_path_allowed( $path ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_paths_with_parent_directory_segment() {
		$themes       = DIR_TESTDATA . '/themedir1';
		$theme_compat = ABSPATH . WPINC . '/theme-compat';

		return array(
			'a file inside the theme'                  => array(
				'default',
				$themes . '/default/../default/index.php',
				true,
			),
			'a missing file inside the theme'          => array(
				'default',
				$themes . '/default/../default/missing.php',
				false,
			),
			'a file in a sibling theme'                => array(
				'default',
				$themes . '/default/../theme1/index.php',
				false,
			),
			'a file in a sibling theme sharing the theme name as a prefix' => array(
				'default',
				$themes . '/default/../default-child-no-theme-json/style.css',
				false,
			),
			'the parent directory of the theme'        => array(
				'default',
				$themes . '/default/..',
				false,
			),
			'a segment of three dots'                  => array(
				'default',
				$theme_compat . '/.../embed.php',
				false,
			),
			'a segment of two dots and a space'        => array(
				'default',
				$theme_compat . '/.. /embed.php',
				false,
			),
			'a file inside theme-compat'               => array(
				'default',
				$theme_compat . '/../theme-compat/embed.php',
				true,
			),
			'a file next to theme-compat'              => array(
				'default',
				$theme_compat . '/../version.php',
				false,
			),
			'a file inside the child theme'            => array(
				'page-templates-child',
				$themes . '/page-templates-child/subdir/../style.css',
				true,
			),
			'a file inside the parent theme'           => array(
				'page-templates-child',
				$themes . '/page-templates-child/../page-templates/template-header.php',
				true,
			),
			'a file in a theme unrelated to the child theme' => array(
				'page-templates-child',
				$themes . '/page-templates-child/../default/index.php',
				false,
			),
			'a file next to a theme in a subdirectory' => array(
				'subdir/theme2',
				$themes . '/subdir/theme2/../theme with spaces/index.php',
				true,
			),
			'a file above the subdirectory of a theme' => array(
				'subdir/theme2',
				$themes . '/subdir/theme2/../../default/index.php',
				false,
			),
		);
	}

	/**
	 * Tests that the direct parent directory of a child or parent theme in a subdirectory is allowed.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_themes_in_subdirectory
	 *
	 * @param string $stylesheet Stylesheet of the child theme.
	 * @param string $template   Stylesheet of the parent theme.
	 * @param string $path       Path to check.
	 * @param bool   $expected   Whether the path is expected to be allowed.
	 */
	public function test_should_allow_parent_directory_of_theme_in_subdirectory( $stylesheet, $template, $path, $expected ) {
		update_option( 'stylesheet', $stylesheet );
		update_option( 'template', $template );
		wp_set_template_globals();

		$this->assertSame( $expected, _wp_is_template_path_allowed( $path ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_themes_in_subdirectory() {
		$themes = DIR_TESTDATA . '/themedir1';

		return array(
			'a file next to a child theme in a subdirectory' => array(
				'subdir/theme2',
				'default',
				$themes . '/subdir/theme2/../theme with spaces/index.php',
				true,
			),
			'a file next to a parent theme in a subdirectory' => array(
				'default',
				'subdir/theme2',
				$themes . '/default/../subdir/theme with spaces/index.php',
				true,
			),
			'a file next to a child theme in the theme root' => array(
				'default',
				'subdir/theme2',
				$themes . '/default/../theme1/index.php',
				false,
			),
			'a file next to a parent theme in the theme root' => array(
				'subdir/theme2',
				'default',
				$themes . '/subdir/theme2/../../theme1/index.php',
				false,
			),
		);
	}

	/**
	 * Tests that the remaining directories are still checked when the theme directory is missing.
	 *
	 * @ticket 65819
	 */
	public function test_should_skip_missing_theme_directory() {
		update_option( 'stylesheet', 'theme-that-does-not-exist' );
		update_option( 'template', 'theme-that-does-not-exist' );
		wp_set_template_globals();

		$this->assertDirectoryDoesNotExist( get_stylesheet_directory() );

		$theme_compat = ABSPATH . WPINC . '/theme-compat';

		$this->assertTrue(
			_wp_is_template_path_allowed( $theme_compat . '/../theme-compat/embed.php' ),
			'A file inside theme-compat should be allowed.'
		);
		$this->assertFalse(
			_wp_is_template_path_allowed( $theme_compat . '/../version.php' ),
			'A file next to theme-compat should not be allowed.'
		);
	}
}
