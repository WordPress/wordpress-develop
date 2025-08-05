<?php
/**
 * Test wp_required_field_message().
 *
 * @group general
 * @group template
 *
 * @covers ::wp_required_field_message
 */
class Tests_General_wpRequiredFieldMessage extends WP_UnitTestCase {

	/**
	 * Tests that `wp_required_field_message()` returns the expected default value.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_message_should_return_default_value() {
		$expected  = '<span class="required-field-message">';
		$expected .= 'Required fields are marked <span class="required">*</span>';
		$expected .= '</span>';
		$this->assertSame( $expected, wp_required_field_message() );
	}

	/**
	 * Tests that `wp_required_field_message()` applies 'wp_required_field_message' filters.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_message_should_apply_wp_required_field_message_filters() {
		$filter = new MockAction();
		add_filter( 'wp_required_field_message', array( &$filter, 'filter' ) );

		wp_required_field_message();

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that the final return value of `wp_required_field_message()` is the result of
	 * 'wp_required_field_message' filters.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_message_should_return_wp_required_field_message_filters() {
		add_filter( 'wp_required_field_message', '__return_empty_string' );
		$this->assertSame( '', wp_required_field_message() );
	}
}
