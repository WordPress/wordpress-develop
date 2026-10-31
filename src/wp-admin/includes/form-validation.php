<?php
/**
 * Form validation API.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 7.2.0
 */

/**
 * Registers an error for a form field.
 *
 * @since 7.2.0
 *
 * @param string $field_id Field ID.
 * @param string $message  Error message.
 * @param string $code     Optional. Error code. Default empty.
 */
function wp_register_form_error( $field_id, $message, $code = '' ) {
	global $wp_form_errors;

	if ( ! isset( $wp_form_errors ) ) {
		$wp_form_errors = array();
	}

	$wp_form_errors[ $field_id ] = array(
		'field_id' => $field_id,
		'message'  => $message,
		'code'     => $code,
	);
}

/**
 * Retrieves all form errors registered during the current request.
 *
 * Errors saved in the user's `wp_form_errors` transient are loaded when the
 * `form-errors` query argument is present.
 *
 * @since 7.2.0
 *
 * @return array[] Form errors keyed by field ID.
 */
function wp_get_form_errors() {
	global $wp_form_errors;

	if ( ! isset( $wp_form_errors ) ) {
		$wp_form_errors = array();
	}

	if ( ! empty( $_GET['form-errors'] ) ) {
		$key   = 'wp_form_errors_' . get_current_user_id();
		$saved = get_transient( $key );

		if ( $saved ) {
			$wp_form_errors = array_merge( $wp_form_errors, (array) $saved );
			delete_transient( $key );
		}
	}

	return $wp_form_errors;
}

/**
 * Saves form errors for the current user so they can be shown after a redirect.
 *
 * @since 7.2.0
 */
function wp_save_form_errors() {
	$errors = wp_get_form_errors();

	if ( empty( $errors ) ) {
		return;
	}

	set_transient( 'wp_form_errors_' . get_current_user_id(), $errors, 30 );
}

/**
 * Retrieves the error registered for a form field.
 *
 * @since 7.2.0
 *
 * @param string $field_id Field ID.
 * @return array|null Form error data, or null if no error is registered.
 */
function wp_get_form_error( $field_id ) {
	$errors = wp_get_form_errors();

	return isset( $errors[ $field_id ] ) ? $errors[ $field_id ] : null;
}

/**
 * Displays the error registered for a form field.
 *
 * @since 7.2.0
 *
 * @param string $field_id Field ID.
 */
function wp_render_form_error( $field_id ) {
	$error = wp_get_form_error( $field_id );

	if ( ! $error ) {
		return;
	}

	printf(
		'<p id="%1$s-error" class="form-error" role="alert">%2$s</p>',
		esc_attr( $field_id ),
		esc_html( $error['message'] )
	);
}
