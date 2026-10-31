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

		$this->assertSame( 'A &amp; B &lt;Site&gt;', $output );
	}
}
