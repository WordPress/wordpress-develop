<?php
/**
 * @group editor
 *
 * @covers ::_enable_content_editor_for_navigation_post_type
 */
class Tests_Editor_EnableContentEditorForNavigationPostType extends WP_UnitTestCase {

	const NAVIGATION_POST_TYPE     = 'wp_navigation';
	const NON_NAVIGATION_POST_TYPE = 'wp_non_navigation';

	public function tear_down() {
		$this->enable_editor_support();
		parent::tear_down();
	}

	/**
	 * @ticket 56266
	 */
	public function test_it_enables_content_editor_for_navigation_type_posts_after_the_content_editor_form() {
		$this->disable_editor_support();
		$post = $this->create_navigation_post();
		$this->assertFalse( $this->supports_block_editor(), 'Editor support must be disabled before running the test.' );

		_enable_content_editor_for_navigation_post_type( $post );

		$this->assertTrue( $this->supports_block_editor(), '_enable_content_editor_for_navigation_post_type() must enable editor support for navigation type posts.' );
	}

	/**
	 * @ticket 56266
	 */
	public function test_it_doesnt_enable_content_editor_for_non_navigation_type_posts_after_the_content_editor_form() {
		$this->disable_editor_support();
		$post = $this->create_non_navigation_post();
		$this->assertFalse( $this->supports_block_editor(), 'Editor support must be disabled before running the test.' );

		_enable_content_editor_for_navigation_post_type( $post );

		$this->assertFalse( $this->supports_block_editor(), '_enable_content_editor_for_navigation_post_type() must enable editor support for non-navigation type posts.' );
	}

	private function supports_block_editor() {
		return post_type_supports( static::NAVIGATION_POST_TYPE, 'editor' );
	}

	private function create_post( $post_type ) {
		return $this->factory()->post->create(
			array( 'post_type' => $post_type )
		);
	}

	private function create_non_navigation_post() {
		return $this->create_post( static::NON_NAVIGATION_POST_TYPE );
	}

	private function create_navigation_post() {
		return $this->create_post( static::NAVIGATION_POST_TYPE );
	}

	private function enable_editor_support() {
		add_post_type_support( static::NAVIGATION_POST_TYPE, 'editor' );
	}

	private function disable_editor_support() {
		remove_post_type_support( static::NAVIGATION_POST_TYPE, 'editor' );
	}
}
