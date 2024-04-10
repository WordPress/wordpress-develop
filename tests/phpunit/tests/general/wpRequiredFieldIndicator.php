<?php
/**
 * Test wp_required_field_indicator().
 *
 * @group general
 * @group template
 *
 * @covers ::wp_required_field_indicator
 */
class Tests_General_wpRequiredFieldIndicator extends WP_UnitTestCase {

	/**
	 * Tests that `wp_required_field_indicator()` returns the expected default value.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_indicator_should_return_default_value() {
		$this->assertSame( '<span class="required">*</span>', wp_required_field_indicator() );
	}

	/**
	 * Tests that `wp_required_field_indicator()` applies 'wp_required_field_indicator' filters.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_indicator_should_apply_wp_required_field_indicator_filters() {
		$filter = new MockAction();
		add_filter( 'wp_required_field_indicator', array( &$filter, 'filter' ) );

		wp_required_field_indicator();

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * Tests that the final return value of `wp_required_field_indicator()` is the result of
	 * 'wp_required_field_indicator' filters.
	 *
	 * @ticket 56389
	 */
	public function test_wp_required_field_indicator_should_return_wp_required_field_indicator_filters() {
		add_filter( 'wp_required_field_indicator', '__return_empty_string' );
		$this->assertSame( '', wp_required_field_indicator() );
	}
}
