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
	 *
	 * @dataProvider data_default_labels
	 *
	 * @param string $comment_type Comment type fixture.
	 * @param string $expected     Expected echoed label.
	 */
	public function test_comment_type_echoes_default_labels( $comment_type, $expected ) {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => $comment_type,
			)
		);

		$GLOBALS['comment'] = $comment;

		$this->expectOutputString( $expected );
		comment_type();
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_default_labels() {
		return array(
			'comment'   => array( 'comment', 'Comment' ),
			'pingback'  => array( 'pingback', 'Pingback' ),
			'trackback' => array( 'trackback', 'Trackback' ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @covers ::comment_type
	 * @covers ::get_comment_type
	 *
	 * @dataProvider data_custom_labels
	 *
	 * @param string $comment_type Comment type fixture.
	 * @param string $expected     Expected echoed label.
	 */
	public function test_comment_type_echoes_custom_labels( $comment_type, $expected ) {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => $comment_type,
			)
		);

		$GLOBALS['comment'] = $comment;

		$this->expectOutputString( $expected );
		comment_type( 'Custom Comment', 'Custom Trackback', 'Custom Pingback' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_custom_labels() {
		return array(
			'comment'   => array( 'comment', 'Custom Comment' ),
			'pingback'  => array( 'pingback', 'Custom Pingback' ),
			'trackback' => array( 'trackback', 'Custom Trackback' ),
		);
	}
}
