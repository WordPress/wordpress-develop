<?php

/**
 * Tests CSRF protection for logged-in "create another site" sign-up.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ms-required
 * @group multisite
 *
 * @ticket 65562
 */
class Tests_Multisite_wpSignupAnotherBlogNonce extends WP_UnitTestCase {

	/**
	 * The another-blog sign-up form must include a nonce field.
	 */
	public function test_signup_another_blog_form_outputs_nonce_field() {
		$source = file_get_contents( ABSPATH . 'wp-signup.php' );

		$this->assertStringContainsString(
			"wp_nonce_field( 'add-another-blog' )",
			$source,
			'signup_another_blog() must output a nonce field.'
		);
	}

	/**
	 * validate_another_blog_signup() must verify the nonce before creating a site.
	 */
	public function test_validate_another_blog_signup_verifies_nonce_before_create() {
		$source   = file_get_contents( ABSPATH . 'wp-signup.php' );
		$function = $this->get_function_source( $source, 'validate_another_blog_signup' );

		$this->assertStringContainsString(
			"check_admin_referer( 'add-another-blog' )",
			$function,
			'validate_another_blog_signup() must call check_admin_referer().'
		);

		$check_pos  = strpos( $function, "check_admin_referer( 'add-another-blog' )" );
		$create_pos = strpos( $function, 'wpmu_create_blog' );

		$this->assertNotFalse( $check_pos, 'Nonce check must be present.' );
		$this->assertNotFalse( $create_pos, 'Site creation must be present.' );
		$this->assertLessThan(
			$create_pos,
			$check_pos,
			'Nonce must be verified before wpmu_create_blog() runs.'
		);
	}

	/**
	 * Missing or invalid nonce for the 'add-another-blog' action must cause check_admin_referer to die.
	 *
	 * This is the core CSRF gate used by validate_another_blog_signup().
	 */
	public function test_add_another_blog_nonce_check_dies_without_valid_nonce() {
		$original_request = $_REQUEST;
		$original_post    = $_POST;

		try {
			unset( $_REQUEST['_wpnonce'], $_POST['_wpnonce'] );

			$this->expectException( 'WPDieException' );
			check_admin_referer( 'add-another-blog' );
		} finally {
			$_REQUEST = $original_request;
			$_POST    = $original_post;
		}
	}

	/**
	 * A correctly signed nonce for 'add-another-blog' must pass check_admin_referer.
	 */
	public function test_add_another_blog_nonce_check_passes_with_valid_nonce() {
		$original_request = $_REQUEST;

		try {
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'add-another-blog' );

			$result = check_admin_referer( 'add-another-blog' );

			$this->assertNotFalse( $result );
			$this->assertGreaterThan( 0, $result );
		} finally {
			$_REQUEST = $original_request;
		}
	}

	/**
	 * Extracts a named function body from a PHP source string.
	 *
	 * @param string $source       File contents.
	 * @param string $function_name Function to extract.
	 * @return string
	 */
	private function get_function_source( $source, $function_name ) {
		if ( ! preg_match( '/function\s+' . preg_quote( $function_name, '/' ) . '\s*\([^)]*\)\s*\{/', $source, $match, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$start  = $match[0][1];
		$length = strlen( $source );
		$depth  = 0;
		$body   = '';

		for ( $i = $start; $i < $length; $i++ ) {
			$char  = $source[ $i ];
			$body .= $char;

			if ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			}
		}

		return $body;
	}
}
