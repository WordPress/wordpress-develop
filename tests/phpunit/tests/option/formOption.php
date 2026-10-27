<?php

/**
 * Tests for form_option().
 *
 * @group option
 *
 * @covers ::form_option
 */
class Tests_Option_FormOption extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_echoes_escaped_option_value() {
		update_option( 'form_option_test_value', 'A & B <Site>' );

		ob_start();
		form_option( 'form_option_test_value' );
		$output = ob_get_clean();

		$this->assertSame( esc_attr( 'A & B <Site>' ), $output );
	}

	/**
	 * @ticket 65819
	 */
	public function test_echoes_empty_string_for_missing_option() {
		ob_start();
		form_option( 'form_option_missing_' . wp_generate_password( 8, false ) );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
