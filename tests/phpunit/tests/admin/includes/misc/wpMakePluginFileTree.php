<?php
/**
 * Tests for the wp_make_plugin_file_tree() function.
 *
 * @group admin
 * @group admin-includes
 * @covers ::wp_make_plugin_file_tree
 */
class Tests_wp_make_plugin_file_tree extends WP_UnitTestCase {

	/**
	 * Tests wp_make_plugin_file_tree() with various inputs using a data provider.
	 *
	 * @ticket 65176
	 * @dataProvider data_wp_make_plugin_file_tree
	 *
	 * @param array $plugin_editable_files List of plugin file paths.
	 * @param array $expected              The expected tree structure.
	 */
	public function test_wp_make_plugin_file_tree( $plugin_editable_files, $expected ) {
		$this->assertSame( $expected, wp_make_plugin_file_tree( $plugin_editable_files ) );
	}

	/**
	 * Data provider for test_wp_make_plugin_file_tree.
	 *
	 * @return array<string, array{
	 *     plugin_editable_files: array<int, string>,
	 *     expected:              array<string, mixed>,
	 * }>
	 */
	public function data_wp_make_plugin_file_tree(): array {
		return array(
			'empty_list'          => array(
				'plugin_editable_files' => array(),
				'expected'              => array(),
			),
			'flat_list'           => array(
				'plugin_editable_files' => array(
					'plugin/plugin.php',
					'plugin/readme.txt',
				),
				'expected'              => array(
					'plugin.php' => 'plugin/plugin.php',
					'readme.txt' => 'plugin/readme.txt',
				),
			),
			'nested_list'         => array(
				'plugin_editable_files' => array(
					'plugin/plugin.php',
					'plugin/includes/class-plugin.php',
					'plugin/includes/functions.php',
				),
				'expected'              => array(
					'plugin.php' => 'plugin/plugin.php',
					'includes'   => array(
						'class-plugin.php' => 'plugin/includes/class-plugin.php',
						'functions.php'    => 'plugin/includes/functions.php',
					),
				),
			),
			'deeply_nested_list'  => array(
				'plugin_editable_files' => array(
					'plugin/plugin.php',
					'plugin/src/components/button/index.js',
				),
				'expected'              => array(
					'plugin.php' => 'plugin/plugin.php',
					'src'        => array(
						'components' => array(
							'button' => array(
								'index.js' => 'plugin/src/components/button/index.js',
							),
						),
					),
				),
			),
			'multiple_base_names' => array(
				'plugin_editable_files' => array(
					'plugin-a/plugin.php',
					'plugin-b/plugin.php',
				),
				'expected'              => array(
					'plugin.php' => 'plugin-b/plugin.php', // Note: wp_make_plugin_file_tree replaces the file name if multiple base paths exist but result in same relative path.
				),
			),
		);
	}
}
