<?php

/**
 * Tests for the `wp_login_form()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_login_form
 */
class Tests_General_WpLoginForm extends WP_UnitTestCase {

	/**
	 * Tests returning the login form HTML when 'echo' is false.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_form_returns_markup_when_echo_false() {
		$form = wp_login_form( array( 'echo' => false ) );

		$this->assertStringContainsString( '<form name="loginform" id="loginform"', $form );
		$this->assertStringContainsString( 'name="log"', $form );
		$this->assertStringContainsString( 'name="pwd"', $form );
		$this->assertStringContainsString( 'name="rememberme"', $form );
		$this->assertStringContainsString( 'name="wp-submit"', $form );
		$this->assertStringContainsString( esc_url( site_url( 'wp-login.php', 'login_post' ) ), $form );
	}

	/**
	 * Tests that the form echoes its output and returns null when 'echo' is true (the default).
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_form_echoes_output_by_default() {
		ob_start();
		$returned = wp_login_form();
		$output   = ob_get_clean();

		$this->assertNull( $returned );
		$this->assertStringContainsString( '<form name="loginform" id="loginform"', $output );
	}

	/**
	 * Tests custom labels, IDs, and form attributes.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_form_custom_ids_and_labels() {
		$args = array(
			'echo'           => false,
			'form_id'        => 'custom-login-form',
			'id_username'    => 'custom_user_id',
			'label_username' => 'Custom User Label',
			'id_password'    => 'custom_pass_id',
			'label_password' => 'Custom Pass Label',
			'id_submit'      => 'custom_submit_id',
			'label_log_in'   => 'Custom Sign In',
			'value_username' => 'prefilled_user',
		);

		$form = wp_login_form( $args );

		$this->assertStringContainsString( 'id="custom-login-form"', $form );
		$this->assertStringContainsString( 'id="custom_user_id"', $form );
		$this->assertStringContainsString( 'Custom User Label', $form );
		$this->assertStringContainsString( 'id="custom_pass_id"', $form );
		$this->assertStringContainsString( 'Custom Pass Label', $form );
		$this->assertStringContainsString( 'id="custom_submit_id"', $form );
		$this->assertStringContainsString( 'value="Custom Sign In"', $form );
		$this->assertStringContainsString( 'value="prefilled_user"', $form );
	}

	/**
	 * Tests setting required attributes on username and password fields.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_form_required_attributes() {
		$form = wp_login_form(
			array(
				'echo'              => false,
				'required_username' => true,
				'required_password' => true,
			)
		);

		$this->assertMatchesRegularExpression( '/name="log"[^>]*required="required"/', $form );
		$this->assertMatchesRegularExpression( '/name="pwd"[^>]*required="required"/', $form );
	}

	/**
	 * Tests login_form_top, login_form_middle, and login_form_bottom filters.
	 *
	 * @ticket 65819
	 */
	public function test_wp_login_form_filters() {
		add_filter(
			'login_form_top',
			static function () {
				return '<p class="top-notice">Top Notice</p>';
			}
		);
		add_filter(
			'login_form_middle',
			static function () {
				return '<p class="middle-notice">Middle Notice</p>';
			}
		);
		add_filter(
			'login_form_bottom',
			static function () {
				return '<p class="bottom-notice">Bottom Notice</p>';
			}
		);

		$form = wp_login_form( array( 'echo' => false ) );

		$this->assertStringContainsString( '<p class="top-notice">Top Notice</p>', $form );
		$this->assertStringContainsString( '<p class="middle-notice">Middle Notice</p>', $form );
		$this->assertStringContainsString( '<p class="bottom-notice">Bottom Notice</p>', $form );
	}
}
