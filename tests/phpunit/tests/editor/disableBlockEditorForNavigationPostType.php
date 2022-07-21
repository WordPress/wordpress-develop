<?php
/**
 * @group editor
 *
 * @covers ::_disable_block_editor_for_navigation_post_type
 */
class Tests_Editor_DisableBlockEditorForNavigationPostType extends WP_UnitTestCase {

	const NAVIGATION_POST_TYPE     = 'wp_navigation';
	const NON_NAVIGATION_POST_TYPE = 'wp_non_navigation';

	/**
	 * @ticket 56266
	 */
	public function test_it_doesnt_disable_block_editor_for_non_navigation_post_types() {
		$filtered_result = _disable_block_editor_for_navigation_post_type( true, static::NON_NAVIGATION_POST_TYPE );
		$this->assertTrue( $filtered_result );
	}

	/**
	 * @ticket 56266
	 */
	public function test_it_disables_block_editor_for_navigation_post_types() {
		$filtered_result = _disable_block_editor_for_navigation_post_type( true, static::NAVIGATION_POST_TYPE );
		$this->assertFalse( $filtered_result );
	}
}
