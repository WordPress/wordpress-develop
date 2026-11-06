<?php

/**
 * Tests for get_comment_statuses().
 *
 * @group comment
 *
 * @covers ::get_comment_statuses
 */
class Tests_Comment_GetCommentStatuses extends WP_UnitTestCase {

	/**
	 * Tests that the statuses and their labels are returned in order.
	 *
	 * @ticket 66494
	 */
	public function test_should_return_the_statuses_with_their_labels() {
		$this->assertSame(
			array(
				'hold'    => 'Unapproved',
				'approve' => 'Approved',
				'spam'    => 'Spam',
				'trash'   => 'Trash',
			),
			get_comment_statuses()
		);
	}
}
