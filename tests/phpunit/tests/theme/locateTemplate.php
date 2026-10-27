<?php

/**
 * Tests for the path traversal restriction in locate_template().
 *
 * @group themes
 *
 * @covers ::locate_template
 */
class Tests_Theme_LocateTemplate extends WP_UnitTestCase {

	/**
	 * Tests that a template name containing a parent directory segment
	 * is only located when it resolves inside an allowed directory.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_template_names_with_parent_directory_segment
	 *
	 * @param string          $stylesheet     Stylesheet of the theme to switch to.
	 * @param string|string[] $template_names Template file(s) to search for.
	 * @param string          $expected       Expected located template.
	 */
	public function test_should_restrict_template_names_with_parent_directory_segment( $stylesheet, $template_names, $expected ) {
		switch_theme( $stylesheet );

		$this->assertSame( $expected, locate_template( $template_names ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_template_names_with_parent_directory_segment() {
		$themes       = DIR_TESTDATA . '/themedir1';
		$theme_compat = ABSPATH . WPINC . '/theme-compat';

		return array(
			'a file inside the theme'                      => array(
				'default',
				'../default/index.php',
				$themes . '/default/../default/index.php',
			),
			'a file in a sibling theme'                    => array(
				'default',
				'../theme1/index.php',
				'',
			),
			'a file inside theme-compat'                   => array(
				'default',
				'../theme-compat/embed.php',
				$theme_compat . '/../theme-compat/embed.php',
			),
			'a file next to theme-compat'                  => array(
				'default',
				'../version.php',
				'',
			),
			'a rejected name followed by a theme template' => array(
				'default',
				array( '../theme1/index.php', 'index.php' ),
				$themes . '/default/index.php',
			),
			'a rejected name followed by a theme-compat template' => array(
				'default',
				array( '../version.php', 'embed.php' ),
				$theme_compat . '/embed.php',
			),
			'a file inside the parent theme'               => array(
				'page-templates-child',
				'../page-templates/template-header.php',
				$themes . '/page-templates-child/../page-templates/template-header.php',
			),
			'a file in a theme unrelated to the child theme' => array(
				'page-templates-child',
				'../default/index.php',
				'',
			),
			'a file next to a theme in a subdirectory'     => array(
				'subdir/theme2',
				'../theme with spaces/index.php',
				$themes . '/subdir/theme2/../theme with spaces/index.php',
			),
			'a file above the subdirectory of a theme'     => array(
				'subdir/theme2',
				'../../default/index.php',
				'',
			),
		);
	}
}
