<?php
/**
 * Tests for the wp_print_theme_file_tree() function.
 *
 * @group admin
 * @group admin-includes
 * @covers ::wp_print_theme_file_tree
 */
class Tests_wp_print_theme_file_tree extends WP_UnitTestCase {

	/**
	 * @global string $relative_file
	 * @global string $stylesheet
	 * @global array  $allowed_files
	 */
	public function set_up() {
		parent::set_up();
		global $relative_file, $stylesheet, $allowed_files;
		$relative_file = 'style.css';
		$stylesheet    = 'twentytwentyone';
		$allowed_files = array(
			'style.css'           => '/tmp/style.css',
			'functions.php'       => '/tmp/functions.php',
			'assets/css/main.css' => '/tmp/assets/css/main.css',
		);
	}

	public function tear_down() {
		global $relative_file, $stylesheet, $allowed_files;
		unset( $relative_file, $stylesheet, $allowed_files );
		parent::tear_down();
	}

	/**
	 * Tests wp_print_theme_file_tree() output using a data provider.
	 *
	 * @ticket 65178
	 * @dataProvider data_wp_print_theme_file_tree
	 *
	 * @param array|string $tree     List of file/folder paths, or filename.
	 * @param string       $expected The expected HTML output.
	 */
	public function test_wp_print_theme_file_tree( $tree, $expected ) {
		ob_start();
		wp_print_theme_file_tree( $tree );
		$output = ob_get_clean();

		$this->assertSame( $expected, $this->normalize_whitespace( $output ) );
	}

	/**
	 * Data provider for test_wp_print_theme_file_tree.
	 *
	 * @return array<string, array{
	 *     tree:     array<string, mixed>|string,
	 *     expected: string,
	 * }>
	 */
	public function data_wp_print_theme_file_tree(): array {
		return array(
			'single_file_current'     => array(
				'tree'     => 'style.css',
				'expected' => '<li role="none" class="current-file"><a role="treeitem" tabindex="0" href="http://example.org/wp-admin/theme-editor.php?file=style.css&amp;theme=twentytwentyone" aria-level="2" aria-setsize="1" aria-posinset="1"><span class="notice notice-info">Stylesheet<br><span class="nonessential">(style.css)</span></span></a></li>',
			),
			'single_file_not_current' => array(
				'tree'     => 'functions.php',
				'expected' => '<li role="none" class=""><a role="treeitem" tabindex="-1" href="http://example.org/wp-admin/theme-editor.php?file=functions.php&amp;theme=twentytwentyone" aria-level="2" aria-setsize="1" aria-posinset="1">Theme Functions<br><span class="nonessential">(functions.php)</span></a></li>',
			),
			'nested_tree'             => array(
				'tree'     => array(
					'assets' => array(
						'css' => array(
							'main.css' => 'assets/css/main.css',
						),
					),
				),
				'expected' => '<li role="treeitem" aria-expanded="true" tabindex="-1" aria-level="2" aria-setsize="1" aria-posinset="1"><span class="folder-label">assets <span class="screen-reader-text">folder</span><span aria-hidden="true" class="icon"></span></span><ul role="group" class="tree-folder"><li role="treeitem" aria-expanded="true" tabindex="-1" aria-level="3" aria-setsize="1" aria-posinset="1"><span class="folder-label">css <span class="screen-reader-text">folder</span><span aria-hidden="true" class="icon"></span></span><ul role="group" class="tree-folder"><li role="none" class=""><a role="treeitem" tabindex="-1" href="http://example.org/wp-admin/theme-editor.php?file=assets%2Fcss%2Fmain.css&amp;theme=twentytwentyone" aria-level="4" aria-setsize="1" aria-posinset="1">main.css</a></li></ul></li></ul></li>',
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
