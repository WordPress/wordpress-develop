<?php

/**
 * @group admin
 *
 * @covers ::wp_make_theme_file_tree
 */
class Tests_Admin_Includes_Misc_WpMakeThemeFileTree_Test extends WP_UnitTestCase {

	/**
	 * Tests wp_make_theme_file_tree() with various file structures.
	 *
	 * @ticket 65175
	 * @dataProvider data_wp_make_theme_file_tree
	 *
	 * @param array $allowed_files The list of theme files.
	 * @param array $expected      The expected tree structure.
	 */
	public function test_wp_make_theme_file_tree( $allowed_files, $expected ) {
		$this->assertSame( $expected, wp_make_theme_file_tree( $allowed_files ) );
	}

	/**
	 * Data provider for test_wp_make_theme_file_tree().
	 *
	 * @return array<string, array{
	 *     allowed_files: array<string, string>,
	 *     expected:      array<string, string|array>,
	 * }>
	 */
	public function data_wp_make_theme_file_tree(): array {
		return array(
			'empty list'         => array(
				'allowed_files' => array(),
				'expected'      => array(),
			),
			'flat list'          => array(
				'allowed_files' => array(
					'style.css' => '/path/to/theme/style.css',
					'index.php' => '/path/to/theme/index.php',
				),
				'expected'      => array(
					'style.css' => 'style.css',
					'index.php' => 'index.php',
				),
			),
			'nested list'        => array(
				'allowed_files' => array(
					'style.css'       => '/path/to/theme/style.css',
					'inc/header.php'  => '/path/to/theme/inc/header.php',
					'inc/footer.php'  => '/path/to/theme/inc/footer.php',
					'templates/a.php' => '/path/to/theme/templates/a.php',
				),
				'expected'      => array(
					'style.css' => 'style.css',
					'inc'       => array(
						'header.php' => 'inc/header.php',
						'footer.php' => 'inc/footer.php',
					),
					'templates' => array(
						'a.php' => 'templates/a.php',
					),
				),
			),
			'deeply nested list' => array(
				'allowed_files' => array(
					'a/b/c/d.php' => '/path/to/theme/a/b/c/d.php',
				),
				'expected'      => array(
					'a' => array(
						'b' => array(
							'c' => array(
								'd.php' => 'a/b/c/d.php',
							),
						),
					),
				),
			),
			'mixed nesting'      => array(
				'allowed_files' => array(
					'index.php'       => '/path/to/theme/index.php',
					'inc/header.php'  => '/path/to/theme/inc/header.php',
					'inc/utils/a.php' => '/path/to/theme/inc/utils/a.php',
				),
				'expected'      => array(
					'index.php' => 'index.php',
					'inc'       => array(
						'header.php' => 'inc/header.php',
						'utils'      => array(
							'a.php' => 'inc/utils/a.php',
						),
					),
				),
			),
		);
	}
}
