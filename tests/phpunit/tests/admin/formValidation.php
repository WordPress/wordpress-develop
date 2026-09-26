<?php
/**
 * Tests for the form validation API.
 *
 * @group admin
 *
 * @ticket 47505
 */
class Tests_Admin_FormValidation extends WP_UnitTestCase {

	/**
	 * Clears the form error global and any saved transient between tests.
	 */
	public function tear_down() {
		global $wp_form_errors;

		$wp_form_errors = array();
		delete_transient( 'wp_form_errors_' . get_current_user_id() );

		parent::tear_down();
	}

	/**
	 * @ticket 47505
	 */
	public function test_register_and_get_form_error() {
		wp_register_form_error( 'tag-name', 'A name is required for this term.', 'empty_term_name' );

		$error = wp_get_form_error( 'tag-name' );

		$this->assertIsArray( $error );
		$this->assertSame( 'tag-name', $error['field_id'] );
		$this->assertSame( 'A name is required for this term.', $error['message'] );
		$this->assertSame( 'empty_term_name', $error['code'] );
	}

	/**
	 * @ticket 47505
	 */
	public function test_get_form_error_returns_null_when_not_registered() {
		$this->assertNull( wp_get_form_error( 'tag-name' ) );
	}

	/**
	 * @ticket 47505
	 */
	public function test_render_form_error_outputs_accessible_markup() {
		wp_register_form_error( 'tag-name', 'A name is required for this term.', 'empty_term_name' );

		$output = $this->get_rendered_form_error( 'tag-name' );

		$this->assertStringContainsString( 'id="tag-name-error"', $output );
		$this->assertStringContainsString( 'class="form-error"', $output );
		$this->assertStringContainsString( 'role="alert"', $output );
		$this->assertStringContainsString( 'A name is required for this term.', $output );
	}

	/**
	 * @ticket 47505
	 */
	public function test_render_form_error_escapes_message() {
		wp_register_form_error( 'tag-name', '<script>alert(1)</script>', 'empty_term_name' );

		$output = $this->get_rendered_form_error( 'tag-name' );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * @ticket 47505
	 */
	public function test_render_form_error_outputs_nothing_when_not_registered() {
		$this->assertSame( '', $this->get_rendered_form_error( 'tag-name' ) );
	}

	/**
	 * @ticket 47505
	 */
	public function test_saved_errors_are_restored_from_transient() {
		global $wp_form_errors;

		wp_register_form_error( 'tag-name', 'A name is required for this term.', 'empty_term_name' );
		wp_save_form_errors();

		// Simulate the next request after the redirect.
		$wp_form_errors      = array();
		$_GET['form-errors'] = '1';

		$error = wp_get_form_error( 'tag-name' );

		unset( $_GET['form-errors'] );

		$this->assertIsArray( $error );
		$this->assertSame( 'A name is required for this term.', $error['message'] );
	}

	/**
	 * Captures the output of wp_render_form_error().
	 *
	 * @param string $field_id Field ID.
	 * @return string Rendered markup.
	 */
	private function get_rendered_form_error( $field_id ) {
		ob_start();
		wp_render_form_error( $field_id );
		return ob_get_clean();
	}
}
