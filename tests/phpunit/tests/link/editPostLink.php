<?php

/**
 * Tests for the edit_post_link() function.
 *
 * @group link
 * @group post
 *
 * @covers ::edit_post_link
 */
class Tests_Link_EditPostLink extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test user IDs.
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Test Post for edit_post_link',
			)
		);

		self::$user_ids = array(
			'admin'      => $factory->user->create( array( 'role' => 'administrator' ) ),
			'subscriber' => $factory->user->create( array( 'role' => 'subscriber' ) ),
		);
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );

		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$user_ids['admin'] );
		$GLOBALS['post'] = get_post( self::$post_id );
	}

	public function tear_down() {
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Tests that edit_post_link() outputs the default anchor markup for a permitted user.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_default_link_for_permitted_user() {
		$output = get_echo( 'edit_post_link' );

		$expected = '<a class="post-edit-link" href="' . esc_url( get_edit_post_link( self::$post_id ) ) . '">' . __( 'Edit This' ) . '</a>';
		$this->assertSame( $expected, $output, 'The full output should match the expected default markup.' );
	}

	/**
	 * Tests that edit_post_link() prepends $before and appends $after markup.
	 *
	 * @ticket 65819
	 */
	public function test_should_prepend_and_append_markup() {
		$before = '<p>';
		$after  = '</p>';

		$output = get_echo( 'edit_post_link', array( 'Edit My Post', $before, $after ) );

		$link     = '<a class="post-edit-link" href="' . esc_url( get_edit_post_link( self::$post_id ) ) . '">Edit My Post</a>';
		$expected = $before . $link . $after;

		$this->assertSame( $expected, $output, 'The output should include the prepended and appended markup.' );
	}

	/**
	 * Tests that edit_post_link() uses a custom CSS class when provided.
	 *
	 * @ticket 65819
	 */
	public function test_should_use_custom_css_class() {
		$custom_class = 'custom-post-edit-btn';
		$output       = get_echo( 'edit_post_link', array( null, '', '', 0, $custom_class ) );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'a' ), 'The output should contain an anchor tag.' );
		$this->assertSame( $custom_class, $processor->get_attribute( 'class' ), 'The anchor should have the custom CSS class.' );
	}

	/**
	 * Tests that edit_post_link() uses an explicit post ID or post object.
	 *
	 * @ticket 65819
	 */
	public function test_should_use_explicit_post() {
		$other_post_id = self::factory()->post->create(
			array(
				'post_title' => 'Other Post',
			)
		);

		$output_by_id   = get_echo( 'edit_post_link', array( null, '', '', $other_post_id ) );
		$expected_by_id = '<a class="post-edit-link" href="' . esc_url( get_edit_post_link( $other_post_id ) ) . '">' . __( 'Edit This' ) . '</a>';
		$this->assertSame( $expected_by_id, $output_by_id, 'Should accept post ID.' );

		$other_post_obj = get_post( $other_post_id );
		$output_by_obj  = get_echo( 'edit_post_link', array( null, '', '', $other_post_obj ) );
		$this->assertSame( $expected_by_id, $output_by_obj, 'Should accept post object.' );
	}

	/**
	 * Tests that edit_post_link() outputs nothing for an invalid post.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_output_link_for_invalid_post() {
		unset( $GLOBALS['post'] );

		$output = get_echo( 'edit_post_link', array( null, '', '', 999999 ) );

		$this->assertSame( '', $output, 'No link should be output for an invalid post.' );
	}

	/**
	 * Tests that edit_post_link() outputs nothing for a user without edit permissions.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_output_link_for_user_without_permission() {
		wp_set_current_user( self::$user_ids['subscriber'] );

		$output = get_echo( 'edit_post_link' );

		$this->assertSame( '', $output, 'No link should be output for users who cannot edit posts.' );
	}

	/**
	 * Tests that edit_post_link() outputs nothing when logged out.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_output_link_when_logged_out() {
		wp_set_current_user( 0 );

		$output = get_echo( 'edit_post_link' );

		$this->assertSame( '', $output, 'No link should be output for logged-out visitors.' );
	}

	/**
	 * Tests that the edit_post_link filter filters the output and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_should_apply_edit_post_link_filter() {
		$filter_calls = array();

		add_filter(
			'edit_post_link',
			static function ( $link, $post_id, $text ) use ( &$filter_calls ) {
				$filter_calls[] = array(
					'link'    => $link,
					'post_id' => $post_id,
					'text'    => $text,
				);

				return '<span class="custom-edit">' . $text . '</span>';
			},
			10,
			3
		);

		$output = get_echo( 'edit_post_link', array( 'Custom Text', '<p>', '</p>' ) );

		$this->assertCount( 1, $filter_calls, 'The edit_post_link filter should be called once.' );
		$this->assertSame( self::$post_id, $filter_calls[0]['post_id'], 'Filter should receive the post ID.' );
		$this->assertSame( 'Custom Text', $filter_calls[0]['text'], 'Filter should receive the link text.' );
		$this->assertStringContainsString( 'post-edit-link', $filter_calls[0]['link'], 'Filter should receive the generated link.' );
		$this->assertSame( '<p><span class="custom-edit">Custom Text</span></p>', $output, 'The output should use the filtered content.' );
	}
}
