<?php

/**
 * @group  comment
 *
 * @covers ::comment_form
 */
class Tests_Comment_CommentForm extends WP_UnitTestCase {
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function test_default_markup_for_submit_button_and_wrapper() {
		$p = self::factory()->post->create();

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
		);

		$form = get_echo( 'comment_form', array( $args, $p ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( $p );
		$this->assertMatchesRegularExpression( '|<p class="form\-submit">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	public function test_custom_submit_button() {
		$p = self::factory()->post->create();

		$args = array(
			'name_submit'   => 'foo-name',
			'id_submit'     => 'foo-id',
			'class_submit'  => 'foo-class',
			'label_submit'  => 'foo-label',
			'submit_button' => '<input name="custom-%1$s" type="submit" id="custom-%2$s" class="custom-%3$s" value="custom-%4$s" />',
		);

		$form = get_echo( 'comment_form', array( $args, $p ) );

		$button = '<input name="custom-foo-name" type="submit" id="custom-foo-id" class="custom-foo-class" value="custom-foo-label" />';
		$this->assertStringContainsString( $button, $form );
	}

	public function test_custom_submit_field() {
		$p = self::factory()->post->create();

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
			'submit_field' => '<p class="my-custom-submit-field">%1$s %2$s</p>',
		);

		$form = get_echo( 'comment_form', array( $args, $p ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( $p );
		$this->assertMatchesRegularExpression( '|<p class="my\-custom\-submit\-field">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	/**
	 * @ticket 32312
	 */
	public function test_submit_button_and_submit_field_should_fall_back_on_defaults_when_filtered_defaults_do_not_contain_the_keys() {
		$p = self::factory()->post->create();

		$args = array(
			'name_submit'  => 'foo-name',
			'id_submit'    => 'foo-id',
			'class_submit' => 'foo-class',
			'label_submit' => 'foo-label',
		);

		add_filter( 'comment_form_defaults', array( $this, 'filter_comment_form_defaults' ) );
		$form = get_echo( 'comment_form', array( $args, $p ) );
		remove_filter( 'comment_form_defaults', array( $this, 'filter_comment_form_defaults' ) );

		$button = '<input name="foo-name" type="submit" id="foo-id" class="foo-class" value="foo-label" />';
		$hidden = get_comment_id_fields( $p );
		$this->assertMatchesRegularExpression( '|<p class="form\-submit">\s*' . $button . '\s*' . $hidden . '\s*|', $form );
	}

	public function filter_comment_form_defaults( $defaults ) {
		unset( $defaults['submit_field'] );
		unset( $defaults['submit_button'] );
		return $defaults;
	}

	/**
	 * @ticket 44126
	 */
	public function test_fields_should_include_cookies_consent() {
		$p = self::factory()->post->create();

		add_filter( 'option_show_comments_cookies_opt_in', '__return_true' );

		$args = array(
			'fields' => array(
				'author' => 'Hello World!',
			),
		);

		$form = get_echo( 'comment_form', array( $args, $p ) );

		remove_filter( 'option_show_comments_cookies_opt_in', '__return_true' );

		$this->assertMatchesRegularExpression( '|<p class="comment\-form\-cookies\-consent">.*?</p>|', $form );
	}

	/**
	 * @ticket 47975
	 */
	public function test_aria_describedby_email_notes_should_not_be_added_if_no_email_notes() {
		$p = self::factory()->post->create();

		$form_with_aria = get_echo( 'comment_form', array( array(), $p ) );

		$this->assertStringContainsString( 'aria-describedby="email-notes"', $form_with_aria );

		$args = array(
			'comment_notes_before' => '',
		);

		$form_without_aria = get_echo( 'comment_form', array( $args, $p ) );

		$this->assertStringNotContainsString( 'aria-describedby="email-notes"', $form_without_aria );
	}

	/**
	 * @ticket 32767
	 */
	public function test_when_thread_comments_enabled() {
		update_option( 'thread_comments', true );

		$form     = get_echo( 'comment_form', array( array(), self::$post_id ) );
		$expected = '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">Cancel reply</a>';
		$this->assertStringContainsString( $expected, $form );
	}

	/**
	 * @ticket 32767
	 */
	public function test_when_thread_comments_disabled() {
		delete_option( 'thread_comments' );

		$form     = get_echo( 'comment_form', array( array(), self::$post_id ) );
		$expected = '<a rel="nofollow" id="cancel-comment-reply-link" href="#respond" style="display:none;">Cancel reply</a>';
		$this->assertStringNotContainsString( $expected, $form );
	}

	/**
	 * @ticket 56243
	 */
	public function test_comment_form_should_not_display_for_global_post_when_called_with_invalid_id() {
		// Go to permalink to ensure global post ID is set.
		$this->go_to( get_permalink( self::$post_id ) );

		$impossibly_high_post_id = PHP_INT_MAX;

		$form = get_echo( 'comment_form', array( array(), $impossibly_high_post_id ) );
		$this->assertEmpty( $form );
	}

	/**
	 * @ticket 56243
	 */
	public function test_comment_form_should_display_for_global_post_with_falsey_post_id() {
		$post_id = self::$post_id;
		$this->go_to( get_permalink( $post_id ) );

		$form = get_echo( 'comment_form', array( array(), false ) );
		$this->assertNotEmpty( $form );

		$post_hidden_field = "<input type='hidden' name='comment_post_ID' value='{$post_id}' id='comment_post_ID' />";
		$this->assertStringContainsString( $post_hidden_field, $form );
	}

	/**
	 * @ticket 56243
	 */
	public function test_comment_form_should_display_for_specified_post_when_passed_a_valid_post_id() {
		$post_id = self::$post_id;

		$form = get_echo( 'comment_form', array( array(), $post_id ) );
		$this->assertNotEmpty( $form );

		$post_hidden_field = "<input type='hidden' name='comment_post_ID' value='{$post_id}' id='comment_post_ID' />";
		$this->assertStringContainsString( $post_hidden_field, $form );
	}
}
