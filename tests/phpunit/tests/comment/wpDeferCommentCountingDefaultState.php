<?php

/**
 * Tests for the default state of wp_defer_comment_counting().
 *
 * The state is kept in a static variable that tests of the same process can change, so the default state is
 * checked in a separate process, before anything calls the function with a value.
 *
 * @group comment
 *
 * @covers ::wp_defer_comment_counting
 */
class Tests_Comment_WpDeferCommentCountingDefaultState extends WP_UnitTestCase {

	/**
	 * Tests that counting is not deferred by default.
	 *
	 * @ticket 66494
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_not_defer_by_default() {
		$this->assertFalse( wp_defer_comment_counting() );
	}
}
