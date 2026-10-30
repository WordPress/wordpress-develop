<?php

/**
 * Tests for the `get_the_password_form()` function.
 *
 * @group post
 * @group template
 *
 * @covers ::get_the_password_form
 */
class Tests_Post_GetThePasswordForm extends WP_UnitTestCase {

	/**
	 * Password-protected post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Original theme stylesheet.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create( array( 'post_password' => 'secret' ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_stylesheet = get_stylesheet();
	}

	public function tear_down() {
		unset( $_SERVER['HTTP_REFERER'], $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

		parent::tear_down();
	}

	/**
	 * Returns the expected form markup for the fixture post.
	 *
	 * Only the post ID is filled in; everything else is literal.
	 *
	 * @param string $form_class     Extra form class.
	 * @param string $error_html     Error markup after the redirect field.
	 * @param string $aria           Extra password input attributes.
	 * @param string $button_open    Markup before the submit button.
	 * @param string $button_class   Submit button class attribute.
	 * @param string $button_close   Markup after the submit button.
	 * @return string Expected form markup.
	 */
	private function expected_form( $form_class = '', $error_html = '', $aria = '', $button_open = '', $button_class = '', $button_close = '' ) {
		$id = self::$post_id;

		return '<form action="http://' . WP_TESTS_DOMAIN . '/wp-login.php?action=postpass" class="post-password-form' . $form_class . '" method="post">'
			. '<input type="hidden" name="redirect_to" value="http://' . WP_TESTS_DOMAIN . '/?p=' . $id . '" />'
			. $error_html
			. "\n\t<p>This content is password-protected. To view it, please enter the password below.</p>"
			. "\n\t" . '<p><label for="pwbox-' . $id . '">Password: <input name="post_password" id="pwbox-' . $id . '" type="password" spellcheck="false" required size="20"' . $aria . ' /></label> '
			. $button_open . '<input type="submit" name="Submit"' . $button_class . ' value="Enter" />' . $button_close . "</p></form>\n\t";
	}

	/**
	 * Tests that the global post is used by default.
	 *
	 * @ticket 65819
	 */
	public function test_uses_global_post_by_default() {
		$GLOBALS['post'] = get_post( self::$post_id );

		$this->assertSame( $this->expected_form(), get_the_password_form() );
	}

	/**
	 * Tests that the form has a random field ID and no redirect field without a post.
	 *
	 * @ticket 65819
	 */
	public function test_form_without_post() {
		$form = get_the_password_form();

		$this->assertStringNotContainsString( 'redirect_to', $form, 'There should be no redirect field.' );

		$processor = new WP_HTML_Tag_Processor( $form );
		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The label should exist.' );
		$field_id = $processor->get_attribute( 'for' );
		$this->assertMatchesRegularExpression( '/^pwbox-\d+$/', $field_id, 'The field ID should use a random number.' );
		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The password input should exist.' );
		$this->assertSame( $field_id, $processor->get_attribute( 'id' ), 'The label and the input should share the field ID.' );
	}

	/**
	 * Tests that no error is shown unless the referrer is the post and the password cookie is set.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_error_without_matching_referrer_and_cookie
	 *
	 * @param bool $same_referrer Whether the referrer is the post permalink.
	 * @param bool $cookie        Whether the password cookie is set.
	 */
	public function test_no_error_without_matching_referrer_and_cookie( $same_referrer, $cookie ) {
		$_SERVER['HTTP_REFERER'] = $same_referrer ? get_permalink( self::$post_id ) : 'http://' . WP_TESTS_DOMAIN . '/elsewhere/';

		if ( $cookie ) {
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = 'hash';
		}

		$this->assertSame( $this->expected_form(), get_the_password_form( self::$post_id ) );
	}

	/**
	 * Data provider for test_no_error_without_matching_referrer_and_cookie().
	 *
	 * @return array[]
	 */
	public static function data_no_error_without_matching_referrer_and_cookie() {
		return array(
			'same referrer, no cookie' => array( true, false ),
			'other referrer, cookie'   => array( false, true ),
		);
	}

	/**
	 * Tests the arguments and return value of the 'the_password_form_incorrect_password' filter.
	 *
	 * @ticket 65819
	 */
	public function test_incorrect_password_filter() {
		$_SERVER['HTTP_REFERER']                = get_permalink( self::$post_id );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = 'hash';

		$filter = new MockAction();
		add_filter( 'the_password_form_incorrect_password', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'the_password_form_incorrect_password',
			static function ( $message ) {
				return 'Try again. ' . $message;
			},
			20
		);

		$form = get_the_password_form( self::$post_id );
		$args = $filter->get_args()[0];

		$this->assertSame( 'Invalid password.', $args[0], 'The filter should receive the default message.' );
		$this->assertSame( self::$post_id, $args[1]->ID, 'The filter should receive the post.' );
		$this->assertStringContainsString( '<p id="error-pwbox-' . self::$post_id . '">Try again. Invalid password.</p>', $form, 'The filtered message should be displayed.' );
	}

	/**
	 * Tests the arguments and return value of the 'the_password_form' filter.
	 *
	 * @ticket 65819
	 */
	public function test_the_password_form_filter() {
		$_SERVER['HTTP_REFERER']                = get_permalink( self::$post_id );
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = 'hash';

		$filter = new MockAction();
		add_filter( 'the_password_form', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'the_password_form',
			static function ( $output ) {
				return '<div class="wrapped">' . $output . '</div>';
			},
			20
		);

		$form = get_the_password_form( self::$post_id );
		$args = $filter->get_args()[0];

		$expected = $this->expected_form(
			' password-form-error',
			'<div class="post-password-form-invalid-password" role="alert"><p id="error-pwbox-' . self::$post_id . '">Invalid password.</p></div>',
			' aria-describedby="error-pwbox-' . self::$post_id . '"'
		);

		$this->assertSame( $expected, $args[0], 'The filter should receive the form markup.' );
		$this->assertSame( self::$post_id, $args[1]->ID, 'The filter should receive the post.' );
		$this->assertSame( 'Invalid password.', $args[2], 'The filter should receive the error message.' );
		$this->assertSame( '<div class="wrapped">' . $expected . '</div>', $form, 'The filtered form should be returned.' );
	}

	/**
	 * Tests the button markup in a block theme.
	 *
	 * @ticket 65819
	 */
	public function test_block_theme_button() {
		switch_theme( 'block-theme' );

		$this->assertSame(
			$this->expected_form( '', '', '', '<span class="wp-block-button">', ' class="wp-block-button__link wp-element-button"', '</span>' ),
			get_the_password_form( self::$post_id )
		);
	}
}
