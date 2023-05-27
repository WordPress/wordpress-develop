<?php
/**
 * Test cases for the `is_avatar_comment_type()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 5.1.0
 *
 * @group comment
 *
 * @covers ::is_avatar_comment_type
 */
class Tests_Comment_IsAvatarCommentType extends WP_UnitTestCase {
	/**
	 * Test the `is_avatar_comment_type()` function.
	 *
	 * @since 5.1.0
	 *
	 * @dataProvider data_is_avatar_comment_type
	 */
	public function test_function( $comment_type, $expected ) {
		$this->assertSame( $expected, is_avatar_comment_type( $comment_type ) );
	}

	/**
	 * Dataprovider for `is_avatar_comment_type()`.
	 *
	 * @since 5.1.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type string Comment type.
	 *         @type bool   Expected values.
	 *     }
	 * }
	 */
	public function data_is_avatar_comment_type() {
		return array(
			array( null, false ),
			array( '', false ),
			array( 'non-existing-comment-type', false ),
			array( 'comment', true ),
		);
	}

	/**
	 * The function should be filterable with the `get_avatar_comment_types` filter.
	 *
	 * @since 5.1.0
	 */
	public function test_function_should_be_filterable() {
		$this->assertFalse( is_avatar_comment_type( 'review' ) );

		add_filter( 'get_avatar_comment_types', array( $this, '_filter_avatar_comment_types' ) );
		$actual_comment = is_avatar_comment_type( 'comment' );
		$actual_review  = is_avatar_comment_type( 'review' );
		remove_filter( 'get_avatar_comment_types', array( $this, '_filter_avatar_comment_types' ) );

		$this->assertTrue( $actual_comment );
		$this->assertTrue( $actual_review );
	}

	/**
	 * Filters callback that modifies the list of allowed comment types for retrieving avatars.
	 *
	 * @since 5.1.0
	 *
	 * @param  array $types An array of content types.
	 * @return array An array of content types.
	 */
	public function _filter_avatar_comment_types( $types ) {
		$types[] = 'review';
		return $types;
	}

}
