<?php
/**
 * @group editor
 *
 * @covers ::_disable_content_editor_for_navigation_post_type
 */
class Tests_Editor_DisableContentEditorForNavigationPostType extends WP_UnitTestCase {

	const NAVIGATION_POST_TYPE     = 'wp_navigation';
	const NON_NAVIGATION_POST_TYPE = 'wp_non_navigation';

	public function tear_down() {
		$this->enable_editor_support();
		parent::tear_down();
	}

	/**
	 * @ticket 56266
	 *
	 * @dataProvider data_test_it_correctly_handles_different_post_types
	 */
	public function test_it_correctly_handles_different_post_types( $post_type, $enable_editor_support, $expected, $message ) {
		$post = $this->create_post( $post_type );
		if ( $enable_editor_support ) {
			$this->enable_editor_support();
			$this->assertTrue( $this->supports_block_editor(), 'Editor support must be enabled before running the test.' );
		} else {
			$this->disable_editor_support();
			$this->assertFalse( $this->supports_block_editor(), 'Editor support must be disabled before running the test.' );
		}

		_disable_content_editor_for_navigation_post_type( $post );

		$this->assertSame( $expected, $this->supports_block_editor(), $message );
	}

	/**
	 * @ticket 56266
	 */
	public function data_test_it_correctly_handles_different_post_types() {
		return array(
			'non-navigation post type and false' => array(
				'post_type'             => static::NON_NAVIGATION_POST_TYPE,
				'enable_editor_support' => false,
				'expected'              => false,
				'message'               => '_disable_content_editor_for_navigation_post_type() must not enable editor support for non-navigation type posts.',
			),
			'non-navigation post type and true'  => array(
				'post_type'             => static::NON_NAVIGATION_POST_TYPE,
				'enable_editor_support' => true,
				'expected'              => true,
				'message'               => '_disable_content_editor_for_navigation_post_type() must not disable editor support for non-navigation type posts.',
			),
			'navigation post type and false'     => array(
				'post_type'             => static::NAVIGATION_POST_TYPE,
				'enable_editor_support' => false,
				'expected'              => false,
				'message'               => '_disable_content_editor_for_navigation_post_type() must not enable editor support for navigation type posts.',
			),
			'navigation post type and true'      => array(
				'post_type'             => static::NAVIGATION_POST_TYPE,
				'enable_editor_support' => true,
				'expected'              => false,
				'message'               => '_disable_content_editor_for_navigation_post_type() must disable editor support for non-navigation type posts.',
			),
		);
	}

	private function supports_block_editor() {
		return post_type_supports( static::NAVIGATION_POST_TYPE, 'editor' );
	}

	private function create_post( $post_type ) {
		return $this->factory()->post->create(
			array( 'post_type' => $post_type )
		);
	}

	private function enable_editor_support() {
		add_post_type_support( static::NAVIGATION_POST_TYPE, 'editor' );
	}

	private function disable_editor_support() {
		remove_post_type_support( static::NAVIGATION_POST_TYPE, 'editor' );
	}
}
