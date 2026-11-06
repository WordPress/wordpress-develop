<?php

/**
 * Tests for wp_direct_php_update_button().
 *
 * @group functions
 *
 * @covers ::wp_direct_php_update_button
 */
class Tests_Functions_WpDirectPhpUpdateButton extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_outputs_nothing_when_direct_update_url_is_empty() {
		add_filter( 'wp_direct_php_update_url', '__return_empty_string' );

		ob_start();
		wp_direct_php_update_button();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @ticket 65819
	 */
	public function test_outputs_button_when_direct_update_url_is_provided() {
		add_filter(
			'wp_direct_php_update_url',
			static function () {
				return 'https://example.com/update-php?foo=bar&baz=1';
			}
		);

		ob_start();
		wp_direct_php_update_button();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'button-container', $output );
		$this->assertStringContainsString( 'href="https://example.com/update-php?foo=bar&#038;baz=1"', $output );
		$this->assertStringContainsString( 'button-primary', $output );
		$this->assertStringContainsString( 'target="_blank"', $output );
		$this->assertStringContainsString( 'dashicons-external', $output );
	}
}
