<?php
/**
 * @group editor
 *
 * @covers ::_disable_content_editor_for_navigation_post_type
 */
class Tests_Editor_DisableContentEditorForNavigationPostType extends WP_UnitTestCase {

	const NAVIGATION_POST_TYPE     = 'wp_navigation';
	const NON_NAVIGATION_POST_TYPE = 'wp_non_navigation';

	/**
	 * @ticket 56266
	 */
	public function test_it_doesnt_disable_content_editor_for_non_navigation_type_posts() {
		$post = $this->create_non_navigation_post();
		$this->assertTrue( $this->supports_block_editor() );

		_disable_content_editor_for_navigation_post_type( $post );

		$this->assertTrue( $this->supports_block_editor() );
	}

	/**
	 * @ticket 56266
	 */
	public function test_it_disables_content_editor_for_navigation_type_posts() {
		$post = $this->create_navigation_post();
		$this->assertTrue( $this->supports_block_editor() );

		_disable_content_editor_for_navigation_post_type( $post );

		$this->assertFalse( $this->supports_block_editor() );
	}

	private function supports_block_editor() {
		return post_type_supports( static::NAVIGATION_POST_TYPE, 'editor' );
	}

	private function create_post( $type ) {
		$post            = new WP_Post( new StdClass() );
		$post->post_type = $type;
		$post->filter    = 'raw';
		return $post;
	}

	private function create_non_navigation_post() {
		return $this->create_post( static::NON_NAVIGATION_POST_TYPE );
	}

	private function create_navigation_post() {
		return $this->create_post( static::NAVIGATION_POST_TYPE );
	}
}
