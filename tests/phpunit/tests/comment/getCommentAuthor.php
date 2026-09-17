<?php

/**
 * @group comment
 *
 * @covers ::get_comment_author
 */
class Tests_Comment_GetCommentAuthor extends WP_UnitTestCase {

	private static $comment;
	private static $non_existent_comment_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => 0,
			)
		);
	}

	public function get_comment_author_filter( $comment_author, $comment_id, $comment ) {
		$this->assertSame( $comment_id, self::$comment->comment_ID, 'Comment IDs do not match.' );
		$this->assertIsString( $comment_id, '$comment_id parameter is not a string.' );

		return $comment_author;
	}

	public function test_comment_author_passes_correct_comment_id_for_comment_object() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter' ), 99, 3 );

		get_comment_author( self::$comment );
	}

	public function test_comment_author_passes_correct_comment_id_for_int() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter' ), 99, 3 );

		get_comment_author( (int) self::$comment->comment_ID );
	}

	public function get_comment_author_filter_non_existent_id( $comment_author, $comment_id, $comment ) {
		$this->assertSame( $comment_id, (string) self::$non_existent_comment_id, 'Comment IDs do not match.' );
		$this->assertIsString( $comment_id, '$comment_id parameter is not a string.' );

		return $comment_author;
	}

	/**
	 * @ticket 60475
	 */
	public function test_comment_author_passes_correct_comment_id_for_non_existent_comment() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_filter_non_existent_id' ), 99, 3 );

		self::$non_existent_comment_id = self::$comment->comment_ID + 1;

		get_comment_author( self::$non_existent_comment_id ); // Non-existent comment ID.
	}

	/**
	 * @ticket 61681
	 *
	 * @dataProvider data_should_return_author_when_given_object_without_comment_id
	 *
	 * @param stdClass $comment_props Comment properties test data.
	 * @param string   $expected      The expected result.
	 * @param array    $user_data     Optional. User data for creating an author. Default empty array.
	 */
	public function test_should_return_author_when_given_object_without_comment_id( $comment_props, $expected, $user_data = array() ) {
		if ( ! empty( $comment_props->user_id ) ) {
			$user                   = self::factory()->user->create_and_get( $user_data );
			$comment_props->user_id = $user->ID;
		}

		$comment = new WP_Comment( $comment_props );

		$this->assertSame( $expected, get_comment_author( $comment ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_return_author_when_given_object_without_comment_id() {
		return array(
			'with no author'             => array(
				'comment_props' => new stdClass(),
				'expected'      => 'Anonymous',
			),
			'with author name'           => array(
				'comment_props' => (object) array(
					'comment_author' => 'tester1',
				),
				'expected'      => 'tester1',
			),
			'with author name, empty ID' => array(
				'comment_props' => (object) array(
					'comment_author' => 'tester2',
					'comment_ID'     => '',
				),
				'expected'      => 'tester2',
			),
			'with author ID'             => array(
				'comment_props' => (object) array(
					'user_id' => 1, // Populates in the test with an actual user ID.
				),
				'expected'      => 'Tester3',
				'user_data'     => array(
					'display_name' => 'Tester3',
				),
			),
		);
	}

	/**
	 * @ticket 10653
	 */
	public function test_comment_author_with_name_different_from_user_name() {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Commenter Name',
			)
		);
		$comment = self::factory()->comment->create_and_get(
			array(
				'user_id' => $user_id,
				'comment_author' => 'Old Commenter Name',
			)
		);
		$this->assertEquals( 'Commenter Name (Initially posted by Old Commenter Name)', get_comment_author( $comment ) );
	}

	public function test_comment_author_with_name_different_from_user_name_with_custom_before_after() {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Commenter Name',
			)
		);
		$comment = self::factory()->comment->create_and_get(
			array(
				'user_id' => $user_id,
				'comment_author' => 'Old Commenter Name',
			)
		);
		$this->assertEquals( 'Commenter Name AKA Old Commenter Name', get_comment_author( $comment, ' AKA ', '' ) );
	}
}
