<?php

/**
 * Test avatar related functions
 *
 * @group avatar
 *
 * @covers ::get_avatar_data
 */
class Functions_GetAvatarData_Test extends WP_UnitTestCase {


	/**
	 * The `get_avatar_data()` function should return gravatar url when comment type allowed to retrieve avatars.
	 *
	 * @ticket 44033
	 */
	public function test_get_avatar_data_should_return_gravatar_url_when_input_avatar_comment_type() {
		$comment_type = 'comment';
		$comment      = self::factory()->comment->create_and_get(
			array(
				'comment_author_email' => 'commenter@example.com',
				'comment_type'         => $comment_type,
			)
		);

		$actual_data = get_avatar_data( $comment );

		$this->assertTrue( is_avatar_comment_type( $comment_type ) );
		$this->assertMatchesRegularExpression( '|^https?://secure.gravatar.com/avatar/[0-9a-f]{32}\?|', $actual_data['url'] );
	}

	/**
	 * The `get_avatar_data()` function should return invalid url when comment type not allowed to retrieve avatars.
	 *
	 * @ticket 44033
	 */
	public function test_get_avatar_data_should_return_invalid_url_when_input_not_avatar_comment_type() {
		$comment_type = 'review';
		$comment      = self::factory()->comment->create_and_get(
			array(
				'comment_author_email' => 'commenter@example.com',
				'comment_type'         => $comment_type,
			)
		);

		$actual_data = get_avatar_data( $comment );

		$this->assertFalse( is_avatar_comment_type( $comment_type ) );
		$this->assertFalse( $actual_data['url'] );
	}
}
