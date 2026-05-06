<?php
/**
 * Tests for the wp_print_plugin_file_tree() function.
 *
 * @group admin
 * @group admin-includes
 * @covers ::wp_print_plugin_file_tree
 */
class Tests_wp_print_plugin_file_tree extends WP_UnitTestCase {

	/**
	 * @global string $file
	 * @global string $plugin
	 */
	public function set_up() {
		parent::set_up();
		global $file, $plugin;
		$file   = 'hello-dolly/hello.php';
		$plugin = 'hello-dolly/hello.php';
	}

	public function tear_down() {
		global $file, $plugin;
		unset( $file, $plugin );
		parent::tear_down();
	}

	/**
	 * Tests wp_print_plugin_file_tree() output using a data provider.
	 *
	 * @ticket 65179
	 * @dataProvider data_wp_print_plugin_file_tree
	 *
	 * @param array|string $tree     List of file/folder paths, or filename.
	 * @param string       $label    Name of file or folder to print.
	 * @param string       $expected The expected HTML output.
	 */
	public function test_wp_print_plugin_file_tree( $tree, $label, $expected ) {
		ob_start();
		wp_print_plugin_file_tree( $tree, $label );
		$output = ob_get_clean();

		$this->assertSame( $expected, $this->normalize_whitespace( $output ) );
	}

	/**
	 * Data provider for test_wp_print_plugin_file_tree.
	 *
	 * @return array<string, array{
	 *     tree:     array<string, mixed>|string,
	 *     label:    string,
	 *     expected: string,
	 * }>
	 */
	public function data_wp_print_plugin_file_tree(): array {
		return array(
			'single_file_current'     => array(
				'tree'     => 'hello-dolly/hello.php',
				'label'    => 'hello.php',
				'expected' => '<li role="none" class="current-file"><a role="treeitem" tabindex="0" href="http://example.org/wp-admin/plugin-editor.php?file=hello-dolly%2Fhello.php&amp;plugin=hello-dolly%2Fhello.php" aria-level="2" aria-setsize="1" aria-posinset="1"><span class="notice notice-info">hello.php</span></a></li>',
			),
			'single_file_not_current' => array(
				'tree'     => 'hello-dolly/readme.txt',
				'label'    => 'readme.txt',
				'expected' => '<li role="none" class=""><a role="treeitem" tabindex="-1" href="http://example.org/wp-admin/plugin-editor.php?file=hello-dolly%2Freadme.txt&amp;plugin=hello-dolly%2Fhello.php" aria-level="2" aria-setsize="1" aria-posinset="1">readme.txt</a></li>',
			),
			'nested_tree'             => array(
				'tree'     => array(
					'includes' => array(
						'class-test.php' => 'my-plugin/includes/class-test.php',
					),
				),
				'label'    => '',
				'expected' => '<li role="treeitem" aria-expanded="true" tabindex="-1" aria-level="2" aria-setsize="1" aria-posinset="1"><span class="folder-label">includes <span class="screen-reader-text">folder</span><span aria-hidden="true" class="icon"></span></span><ul role="group" class="tree-folder"><li role="none" class=""><a role="treeitem" tabindex="-1" href="http://example.org/wp-admin/plugin-editor.php?file=my-plugin%2Fincludes%2Fclass-test.php&amp;plugin=hello-dolly%2Fhello.php" aria-level="3" aria-setsize="1" aria-posinset="1">class-test.php</a></li></ul></li>',
			),
		);
	}

	/**
	 * Normalizes whitespace in HTML output for comparison.
	 *
	 * @param string $html The HTML to normalize.
	 * @return string The normalized HTML.
	 */
	private function normalize_whitespace( $html ) {
		// Use DOMDocument to normalize the HTML.
		$dom = new DOMDocument();
		// Suppress errors due to HTML5 tags or other issues.
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$normalized = $dom->saveHTML( $dom->documentElement );

		// Remove the wrapping <div> and </div>
		$normalized = substr( $normalized, 5, -6 );

		// Still some basic normalization for predictable comparison.
		$normalized = str_replace( array( "\r", "\n", "\t" ), '', $normalized );
		$normalized = preg_replace( '/\s+/', ' ', $normalized );
		$normalized = str_replace( '> <', '><', $normalized );
		$normalized = str_replace( '&#38;', '&amp;', $normalized );

		return trim( $normalized );
	}
}
