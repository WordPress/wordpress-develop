<?php
/**
 * Tests for the wp_print_speculation_rules() function.
 *
 * @package WordPress
 * @subpackage Speculative Loading
 */

/**
 * @group speculative-loading
 * @covers ::wp_print_speculation_rules
 */
class Tests_Speculative_Loading_wpPrintSpeculationRules extends WP_UnitTestCase {

	private $original_wp_theme_features = array();

	public function set_up() {
		parent::set_up();
		$this->original_wp_theme_features = $GLOBALS['_wp_theme_features'];
	}

	public function tear_down() {
		$GLOBALS['_wp_theme_features'] = $this->original_wp_theme_features;
		parent::tear_down();
	}

	/**
	 * Tests that the hook for printing speculation rules is set up.
	 *
	 * @ticket 62503
	 */
	public function test_hook() {
		$this->assertSame( 10, has_action( 'wp_footer', 'wp_print_speculation_rules' ) );
	}

	/**
	 * Tests speculation rules script output with HTML5 support.
	 *
	 * @ticket 62503
	 */
	public function test_wp_print_speculation_rules_with_html5_support() {
		add_theme_support( 'html5', array( 'script' ) );

		add_filter(
			'wp_speculation_rules_configuration',
			static function () {
				return array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				);
			}
		);

		$output = get_echo( 'wp_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		$json  = str_replace( array( '<script type="speculationrules">', '</script>' ), '', $output );
		$rules = json_decode( $json, true );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'prerender', $rules );
	}

	/**
	 * Tests speculation rules script output without HTML5 support.
	 *
	 * @ticket 62503
	 */
	public function test_wp_print_speculation_rules_without_html5_support() {
		remove_theme_support( 'html5' );

		add_filter(
			'wp_speculation_rules_configuration',
			static function () {
				return array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				);
			}
		);

		$output = get_echo( 'wp_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		$json  = str_replace( array( '<script type="speculationrules">', '</script>' ), '', $output );
		$rules = json_decode( $json, true );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'prerender', $rules );
	}
}
