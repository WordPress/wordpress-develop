<?php
/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_wpLocalizeScript extends WP_UnitTestCase {
	/**
	 * @var WP_Scripts
	 */
	protected $old_wp_scripts;

	public function set_up() {
		parent::set_up();

		$this->old_wp_scripts  = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts'] = null;
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		parent::tear_down();
	}

	/**
	 * Verifies that wp_localize_script() works if global has not been initialized yet.
	 *
	 * @ticket 60862
	 * @covers ::wp_localize_script
	 */
	public function test_wp_localize_script_works_before_enqueue_script() {
		$this->assertTrue(
			wp_localize_script(
				'wp-util',
				'salcodeExample',
				array(
					'answerToTheUltimateQuestionOfLifeTheUniverseAndEverything' => 42,
				)
			)
		);
	}

	/**
	 * Verifies that wp_localize_script() outputs safe JSON whe harmful data is provided.
	 *
	 * @ticket 63851
	 * @covers ::wp_localize_script
	 */
	public function test_wp_localize_script_outputs_safe_json() {
		$unsafe_data     = array( '<!--' => '<script>' );
		$expected_unsafe = '{"\\u003C!--":"\\u003Cscript\\u003E"}';

		$path     = '/test.js';
		$base_url = site_url( $path );

		wp_register_script( 'test-script', $path, array(), null );
		wp_localize_script( 'test-script', 'testData', $unsafe_data );

		ob_start();
		wp_print_scripts( array( 'test-script' ) );
		$output = ob_get_clean();

		$expected = "<script type=\"text/javascript\" id=\"test-script-js-extra\">\n/* <![CDATA[ */\nvar testData = {$expected_unsafe};\n/* ]]> */\n</script>\n";
		$expected .= "<script type=\"text/javascript\" src=\"{$base_url}\" id=\"test-script-js\"></script>\n";

		$this->assertEqualHTML( $expected, $output );
	}
}
