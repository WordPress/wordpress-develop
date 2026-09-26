<?php

/**
 * Tests for the comment_type() template function.
 *
 * @group comment
 *
 * @covers ::comment_type
 */
class Tests_Comment_CommentType extends WP_UnitTestCase {

	/**
	 * Post to attach comments to.
	 *
	 * @var int
	 */
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Returns the output of comment_type() for a comment of the given type.
	 *
	 * @param string $type Comment type stored on the comment.
	 * @param mixed  ...$args Optional arguments passed through to comment_type().
	 * @return string Captured output.
	 */
	private function get_comment_type_output( $type, ...$args ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => $type,
			)
		);

		$GLOBALS['comment'] = get_comment( $comment_id );

		ob_start();
		comment_type( ...$args );
		$output = ob_get_clean();

		unset( $GLOBALS['comment'] );

		return $output;
	}

	/**
	 * @ticket 35214
	 */
	public function test_built_in_types_output_is_unchanged() {
		$this->assertSame( 'Comment', $this->get_comment_type_output( 'comment' ) );
		$this->assertSame( 'Trackback', $this->get_comment_type_output( 'trackback' ) );
		$this->assertSame( 'Pingback', $this->get_comment_type_output( 'pingback' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_custom_text_overrides_are_respected() {
		$this->assertSame( 'C', $this->get_comment_type_output( 'comment', 'C', 'T', 'P' ) );
		$this->assertSame( 'T', $this->get_comment_type_output( 'trackback', 'C', 'T', 'P' ) );
		$this->assertSame( 'P', $this->get_comment_type_output( 'pingback', 'C', 'T', 'P' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_registered_custom_type_outputs_its_label() {
		register_comment_type(
			'foo',
			array(
				'labels' => array(
					'singular_name' => 'Foo',
				),
			)
		);

		$this->assertSame( 'Foo', $this->get_comment_type_output( 'foo' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_unregistered_custom_type_falls_back_to_default_label() {
		$this->assertSame( _x( 'Comment', 'noun' ), $this->get_comment_type_output( 'bar' ) );
	}

	/**
	 * A comment stored with the legacy empty string type is treated as 'comment'.
	 *
	 * @ticket 35214
	 */
	public function test_legacy_empty_type_outputs_comment() {
		$this->assertSame( 'Comment', $this->get_comment_type_output( '' ) );
	}

	/**
	 * The label fallback must not apply to built-in types: 'note' has 'Note' labels
	 * but comment_type() output stays 'Comment'.
	 *
	 * @ticket 35214
	 */
	public function test_built_in_note_type_outputs_default_comment_text() {
		$this->assertSame( 'Comment', $this->get_comment_type_output( 'note' ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_custom_text_override_wins_over_registered_label() {
		register_comment_type(
			'foo',
			array(
				'labels' => array(
					'singular_name' => 'Foo',
				),
			)
		);

		$this->assertSame( 'Custom', $this->get_comment_type_output( 'foo', 'Custom' ) );
	}

	/**
	 * The registered label is escaped on output to guard against HTML/script injection.
	 *
	 * @ticket 35214
	 */
	public function test_registered_label_is_escaped_on_output() {
		register_comment_type(
			'foo',
			array(
				'labels' => array(
					'singular_name' => '<img src=x onerror=alert(1)>Foo',
				),
			)
		);

		$this->assertSame(
			esc_html( '<img src=x onerror=alert(1)>Foo' ),
			$this->get_comment_type_output( 'foo' )
		);
	}
}
