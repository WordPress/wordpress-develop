<?php

/**
 * Tests for get_comment_type() and comment_type().
 *
 * @group comment
 */
class Tests_Comment_GetCommentType extends WP_UnitTestCase {

	/**
	 * Post fixture.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function tear_down() {
		unset( $GLOBALS['comment'] );
		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_empty_type_normalizes_to_comment() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'pingback',
			)
		);

		// Bypass wp_insert_comment() normalization so get_comment_type() is exercised.
		$comment->comment_type = '';

		$this->assertSame( 'comment', get_comment_type( $comment ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_returns_pingback_type() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'pingback',
			)
		);

		$this->assertSame( 'pingback', get_comment_type( $comment_id ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_returns_trackback_type() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'trackback',
			)
		);

		$this->assertSame( 'trackback', get_comment_type( $comment_id ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_accepts_comment_object() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'pingback',
			)
		);

		$this->assertSame( 'pingback', get_comment_type( $comment ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_uses_global_comment_when_unspecified() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'trackback',
			)
		);

		$GLOBALS['comment'] = $comment;

		$this->assertSame( 'trackback', get_comment_type() );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::get_comment_type
	 */
	public function test_filter_can_modify_comment_type() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'comment',
			)
		);

		add_filter(
			'get_comment_type',
			static function () {
				return 'filtered-type';
			}
		);

		$this->assertSame( 'filtered-type', get_comment_type( $comment_id ) );
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_type
	 * @covers ::get_comment_type
	 */
	public function test_comment_type_echoes_default_labels() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'pingback',
			)
		);

		$GLOBALS['comment'] = $comment;

		$this->expectOutputString( 'Pingback' );
		comment_type();
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_type
	 * @covers ::get_comment_type
	 */
	public function test_comment_type_echoes_custom_labels() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'trackback',
			)
		);

		$GLOBALS['comment'] = $comment;

		$this->expectOutputString( 'Custom Trackback' );
		comment_type( 'Custom Comment', 'Custom Trackback', 'Custom Pingback' );
	}
}
