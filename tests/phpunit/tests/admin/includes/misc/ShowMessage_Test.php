<?php

/**
 * @group admin
 *
 * @covers ::show_message
 */
class Tests_Admin_Includes_Misc_ShowMessage_Test extends WP_UnitTestCase {

	/**
	 * @ticket 65181
	 */
	public function test_show_message_returns_formatted_html_for_string() {
		$this->assertSame( "<p>A message.</p>\n", show_message( 'A message.', false ) );
	}

	/**
	 * @ticket 65181
	 */
	public function test_show_message_returns_formatted_html_for_wp_error_with_string_data() {
		$error = new WP_Error( 'test_error', 'A message.', 'Error details.' );

		$this->assertSame( "<p>A message.: Error details.</p>\n", show_message( $error, false ) );
	}

	/**
	 * @ticket 65181
	 */
	public function test_show_message_returns_formatted_html_for_wp_error_without_string_data() {
		$error = new WP_Error( 'test_error', 'A message.', array( 'Error details.' ) );

		$this->assertSame( "<p>A message.</p>\n", show_message( $error, false ) );
	}
}
