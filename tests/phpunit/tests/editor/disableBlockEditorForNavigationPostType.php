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
	 *
	 * @dataProvider data_test_it_correctly_handles_different_post_types
	 *
	 * @param string $post_type The post type.
	 * @param bool   $value     Whether to disable the block editor.
	 * @param bool   $expected  The expected result.
	 */
	public function test_it_correctly_handles_different_post_types( $post_type, $value, $expected ) {
		$filtered_result = _disable_block_editor_for_navigation_post_type( $value, $post_type );
		$this->assertSame( $expected, $filtered_result );
	}

	/**
	 * Data provider for test_it_correctly_handles_different_post_types().
	 *
	 * @return array
	 */
	public function data_test_it_correctly_handles_different_post_types() {
		return array(
			'non-navigation post type and false' => array(
				'post_type' => static::NON_NAVIGATION_POST_TYPE,
				'value'     => false,
				'expected'  => false,
			),
			'non-navigation post type and true'  => array(
				'post_type' => static::NON_NAVIGATION_POST_TYPE,
				'value'     => true,
				'expected'  => true,
			),
			'navigation post type and false'     => array(
				'post_type' => static::NAVIGATION_POST_TYPE,
				'value'     => false,
				'expected'  => false,
			),
			'navigation post type and true'      => array(
				'post_type' => static::NAVIGATION_POST_TYPE,
				'value'     => true,
				'expected'  => false,
			),
		);
	}
}
