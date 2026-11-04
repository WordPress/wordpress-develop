<?php

/**
 * Tests for separate_comments().
 *
 * @group comment
 *
 * @covers ::separate_comments
 */
class Tests_Comment_SeparateComments extends WP_UnitTestCase {

	/**
	 * Creates an in-memory comment of the given type.
	 *
	 * @param string $type Comment type.
	 * @return WP_Comment The created comment.
	 */
	private function create_comment( $type ) {
		return new WP_Comment( (object) array( 'comment_type' => $type ) );
	}

	/**
	 * Tests that an empty list returns the four default groups, all empty.
	 *
	 * @ticket 65819
	 */
	public function test_empty_list_returns_default_groups() {
		$comments = array();

		$this->assertSame(
			array(
				'comment'   => array(),
				'trackback' => array(),
				'pingback'  => array(),
				'pings'     => array(),
			),
			separate_comments( $comments )
		);
	}

	/**
	 * Tests that comments are grouped by type, and trackbacks and pingbacks are also grouped as pings.
	 *
	 * @ticket 65819
	 */
	public function test_groups_comments_by_type() {
		$comment   = $this->create_comment( 'comment' );
		$trackback = $this->create_comment( 'trackback' );
		$pingback  = $this->create_comment( 'pingback' );
		$comments  = array( $comment, $trackback, $pingback );

		$grouped = separate_comments( $comments );

		$this->assertSame( array( $comment ), $grouped['comment'], 'The comment should be in the comment group only.' );
		$this->assertSame( array( $trackback ), $grouped['trackback'], 'The trackback should be in the trackback group.' );
		$this->assertSame( array( $pingback ), $grouped['pingback'], 'The pingback should be in the pingback group.' );
		$this->assertSame( array( $trackback, $pingback ), $grouped['pings'], 'Trackbacks and pingbacks should both be in the pings group, in order.' );
	}

	/**
	 * Tests that a comment with an empty type is grouped as a comment.
	 *
	 * @ticket 65819
	 */
	public function test_empty_type_is_grouped_as_comment() {
		$comment               = $this->create_comment( 'comment' );
		$comment->comment_type = '';
		$comments              = array( $comment );

		$grouped = separate_comments( $comments );

		$this->assertSame( array( $comment ), $grouped['comment'], 'A comment with an empty type should be in the comment group.' );
		$this->assertArrayNotHasKey( '', $grouped, 'There should be no group for an empty type.' );
	}

	/**
	 * Tests that a custom comment type gets its own group and is not added to pings.
	 *
	 * @ticket 65819
	 */
	public function test_custom_type_gets_its_own_group() {
		$note     = $this->create_comment( 'note' );
		$comments = array( $note );

		$grouped = separate_comments( $comments );

		$this->assertSame( array( $note ), $grouped['note'], 'A custom type should get its own group.' );
		$this->assertSame( array(), $grouped['comment'], 'A custom type should not be in the comment group.' );
		$this->assertSame( array(), $grouped['pings'], 'A custom type should not be in the pings group.' );
	}

	/**
	 * Tests that the groups hold references to the elements of the passed array.
	 *
	 * @ticket 65819
	 */
	public function test_groups_hold_references_to_the_passed_array() {
		$comments = array( $this->create_comment( 'pingback' ) );

		$grouped = separate_comments( $comments );

		$replacement            = $this->create_comment( 'comment' );
		$grouped['pingback'][0] = $replacement;

		$this->assertSame( $replacement, $comments[0], 'Replacing an element in a group should replace it in the passed array.' );
		$this->assertSame( $replacement, $grouped['pings'][0], 'The pings group should reference the same element.' );
	}
}
