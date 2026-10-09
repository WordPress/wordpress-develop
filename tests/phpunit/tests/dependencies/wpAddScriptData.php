<?php
/**
 * Tests for wp_add_script_data() and WP_Scripts::print_script_data().
 *
 * @group dependencies
 * @group scripts
 * @covers ::wp_add_script_data
 * @covers WP_Scripts::print_script_data
 */
class Tests_Dependencies_WpAddScriptData extends WP_UnitTestCase {

	/**
	 * @var WP_Scripts|null
	 */
	protected $old_wp_scripts;

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		parent::tear_down();
	}

	/**
	 * Returns the output of printing a single script handle.
	 *
	 * @param string $handle Script handle to print.
	 * @return string Captured output.
	 */
	private function get_print_output( $handle ) {
		ob_start();
		wp_print_scripts( $handle );
		return ob_get_clean();
	}

	/**
	 * @ticket 58873
	 */
	public function test_wp_add_script_data_returns_false_for_unregistered_handle() {
		$this->assertFalse( wp_add_script_data( 'nonexistent-handle', array( 'key' => 'value' ) ) );
	}

	/**
	 * @ticket 58873
	 */
	public function test_print_script_data_outputs_json_tag_before_script_and_preserves_types() {
		wp_enqueue_script( 'test-handle', '/test.js' );
		wp_add_script_data(
			'test-handle',
			array(
				'str'    => 'value',
				'count'  => 42,
				'active' => true,
				'config' => array( 'url' => 'https://example.com' ),
			)
		);

		$output = $this->get_print_output( 'test-handle' );

		// Correct tag format and ID.
		$this->assertStringContainsString( '<script id="test-handle-js-data" type="application/json">', $output );
		// Type fidelity.
		$this->assertStringContainsString( '"count":42', $output );
		$this->assertStringContainsString( '"active":true', $output );
		$this->assertStringContainsString( '"url":"https://example.com"', $output );
		// Printed before the script tag.
		$this->assertLessThan(
			strpos( $output, 'id="test-handle-js"' ),
			strpos( $output, 'test-handle-js-data' ),
			'JSON data tag must appear before the script tag.'
		);
	}

	/**
	 * @ticket 58873
	 */
	public function test_print_script_data_not_printed_when_no_data() {
		wp_enqueue_script( 'test-handle', '/test.js' );

		$output = $this->get_print_output( 'test-handle' );

		$this->assertStringNotContainsString( 'test-handle-js-data', $output );
	}

	/**
	 * @ticket 58873
	 */
	public function test_multiple_calls_are_merged_and_filter_works_directly() {
		wp_enqueue_script( 'test-handle', '/test.js' );
		wp_add_script_data( 'test-handle', array( 'first' => 'one' ) );
		wp_add_script_data( 'test-handle', array( 'second' => 'two' ) );

		add_filter(
			'script_data_test-handle',
			static function ( array $data ): array {
				$data['from_filter'] = 'yes';
				return $data;
			}
		);

		$output = $this->get_print_output( 'test-handle' );

		$this->assertStringContainsString( '"first":"one"', $output );
		$this->assertStringContainsString( '"second":"two"', $output );
		$this->assertStringContainsString( '"from_filter":"yes"', $output );
	}

	/**
	 * @ticket 58873
	 */
	public function test_print_script_data_escapes_html_tags_in_json() {
		wp_enqueue_script( 'test-handle', '/test.js' );
		wp_add_script_data( 'test-handle', array( 'html' => '<script>alert(1)</script>' ) );

		$output = $this->get_print_output( 'test-handle' );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
		$this->assertStringContainsString( '\u003C', $output );
	}

	/**
	 * @ticket 58873
	 * @expectedIncorrectUsage WP_Scripts::print_script_data
	 */
	public function test_print_script_data_doing_it_wrong_for_non_array_filter_return() {
		wp_enqueue_script( 'test-handle', '/test.js' );

		add_filter(
			'script_data_test-handle',
			static function () {
				return 'not an array';
			}
		);

		$output = $this->get_print_output( 'test-handle' );

		$this->assertStringNotContainsString( 'test-handle-js-data', $output );
	}
}
