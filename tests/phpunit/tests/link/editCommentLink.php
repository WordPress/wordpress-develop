<?php

/**
 * Tests for the edit_comment_link() function.
 *
 * @group link
 * @group comment
 *
 * @covers ::edit_comment_link
 */
class Tests_Link_EditCommentLink extends WP_UnitTestCase {

	/**
	 * Test comment ID.
	 *
	 * @var int
	 */
	protected static $comment_id;

	/**
	 * Test user IDs.
	 *
	 * @var array<string, int>
	 */
	protected static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$comment_id = $factory->comment->create(
			array(
				'comment_content' => 'Test comment for edit_comment_link',
			)
		);

		self::$user_ids = array(
			'admin'      => $factory->user->create( array( 'role' => 'administrator' ) ),
			'subscriber' => $factory->user->create( array( 'role' => 'subscriber' ) ),
		);
	}

	public static function wpTearDownAfterClass() {
		wp_delete_comment( self::$comment_id, true );

		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$user_ids['admin'] );
		$GLOBALS['comment'] = get_comment( self::$comment_id );
	}

	public function tear_down() {
		unset( $GLOBALS['comment'] );

		parent::tear_down();
	}

	/**
	 * Tests that edit_comment_link() outputs the default anchor markup for a permitted user.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_default_link_for_permitted_user() {
		$output = get_echo( 'edit_comment_link' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag( 'a' ), 'The output should contain an anchor tag.' );
		$this->assertSame( 'comment-edit-link', $processor->get_attribute( 'class' ), 'The anchor should have the comment-edit-link class.' );
		$this->assertSame( get_edit_comment_link( self::$comment_id, 'url' ), $processor->get_attribute( 'href' ), 'The anchor href should match get_edit_comment_link().' );

		$expected = '<a class="comment-edit-link" href="' . esc_url( get_edit_comment_link( self::$comment_id ) ) . '">' . __( 'Edit This' ) . '</a>';
		$this->assertSame( $expected, $output, 'The full output should match the expected default markup.' );
	}

	/**
	 * Tests that edit_comment_link() outputs custom link text when provided.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_custom_link_text() {
		$custom_text = 'Edit My Comment';
		$output      = get_echo( 'edit_comment_link', array( $custom_text ) );

		$expected = '<a class="comment-edit-link" href="' . esc_url( get_edit_comment_link( self::$comment_id ) ) . '">' . $custom_text . '</a>';
		$this->assertSame( $expected, $output, 'The link should use the custom link text.' );
	}

	/**
	 * Tests that edit_comment_link() prepends $before and appends $after markup.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_before_and_after_markup
	 *
	 * @param string $before The markup to prepend.
	 * @param string $after  The markup to append.
	 */
	public function test_should_prepend_and_append_markup( $before, $after ) {
		$output = get_echo( 'edit_comment_link', array( null, $before, $after ) );

		$link     = '<a class="comment-edit-link" href="' . esc_url( get_edit_comment_link( self::$comment_id ) ) . '">' . __( 'Edit This' ) . '</a>';
		$expected = $before . $link . $after;

		$this->assertSame( $expected, $output, 'The output should include the prepended and appended markup.' );
	}

	/**
	 * Data provider for test_should_prepend_and_append_markup().
	 *
	 * @return array<string, array<string>>
	 */
	public static function data_before_and_after_markup() {
		return array(
			'paragraph tags' => array( '<p>', '</p>' ),
		);
	}

	/**
	 * Tests that edit_comment_link() outputs nothing for a user without edit permissions.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_output_link_for_user_without_permission() {
		wp_set_current_user( self::$user_ids['subscriber'] );

		$output = get_echo( 'edit_comment_link' );

		$this->assertSame( '', $output, 'No link should be output for users who cannot edit comments.' );
	}

	/**
	 * Tests that edit_comment_link() outputs nothing when logged out.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_output_link_when_logged_out() {
		wp_set_current_user( 0 );

		$output = get_echo( 'edit_comment_link' );

		$this->assertSame( '', $output, 'No link should be output for logged-out visitors.' );
	}

	/**
	 * Tests that the edit_comment_link filter filters the output and receives expected arguments.
	 *
	 * @ticket 65819
	 */
	public function test_should_apply_edit_comment_link_filter() {
		$filter_calls = array();

		add_filter(
			'edit_comment_link',
			static function ( $link, $comment_id, $text ) use ( &$filter_calls ) {
				$filter_calls[] = array(
					'link'       => $link,
					'comment_id' => $comment_id,
					'text'       => $text,
				);

				return '<span class="custom-edit">' . $text . '</span>';
			},
			10,
			3
		);

		$output = get_echo( 'edit_comment_link', array( 'Custom Text', '<p>', '</p>' ) );

		$this->assertCount( 1, $filter_calls, 'The edit_comment_link filter should be called once.' );
		$this->assertSame( (string) self::$comment_id, (string) $filter_calls[0]['comment_id'], 'Filter should receive the comment ID.' );
		$this->assertSame( 'Custom Text', $filter_calls[0]['text'], 'Filter should receive the link text.' );
		$this->assertStringContainsString( 'comment-edit-link', $filter_calls[0]['link'], 'Filter should receive the generated link.' );
		$this->assertSame( '<p><span class="custom-edit">Custom Text</span></p>', $output, 'The output should use the filtered content.' );
	}
}
