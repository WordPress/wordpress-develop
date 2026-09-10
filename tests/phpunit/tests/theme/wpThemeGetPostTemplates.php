<?php

/**
 * Tests for WP_Theme::get_post_templates().
 *
 * @group themes
 *
 * @covers WP_Theme::get_post_templates
 */
class Tests_Theme_wpThemeGetPostTemplates extends WP_UnitTestCase {

	/**
	 * @ticket 41717
	 */
	public function test_get_post_templates_child_theme() {
		$theme = wp_get_theme( 'page-templates-child' );
		$this->assertNotEmpty( $theme );

		switch_theme( $theme['Template'], $theme['Stylesheet'] );

		$post_templates = $theme->get_post_templates();

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level-post-types.php'       => 'Top Level',
				'subdir/template-sub-dir-post-types.php'  => 'Sub Dir',
				'template-top-level-post-types-child.php' => 'Top Level In A Child Theme',
				'subdir/template-sub-dir-post-types-child.php' => 'Sub Dir In A Child Theme',
			),
			$post_templates['foo']
		);

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level-post-types.php'      => 'Top Level',
				'subdir/template-sub-dir-post-types.php' => 'Sub Dir',
			),
			$post_templates['post']
		);

		$this->assertSameSetsWithIndex(
			array(
				'template-top-level.php'      => 'Top Level',
				'subdir/template-sub-dir.php' => 'Sub Dir',
				'template-header.php'         => 'This Template Header Is On One Line',
			),
			$post_templates['page']
		);
	}

	/**
	 * @ticket 42513
	 */
	public function test_get_post_templates_caches_results() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		$filter = new MockAction();
		add_filter( 'extra_theme_headers', array( $filter, 'filter' ) );

		// First call populates the cache.
		$first_result = $theme->get_post_templates();
		$this->assertNotEmpty( $first_result );
		$filter_call_count = $filter->get_call_count();
		$this->assertGreaterThan( 0, $filter_call_count, 'The `extra_theme_headers` filter should be called at least once.' );

		// Second call should return the same result from cache.
		$second_result = $theme->get_post_templates();
		$this->assertSame( $first_result, $second_result );
		$this->assertSame( $filter_call_count, $filter->get_call_count(), 'The `extra_theme_headers` filter should not have extra calls.' );
	}

	/**
	 * @ticket 42513
	 */
	public function test_get_post_templates_clears_cache_on_theme_switch() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		$filter = new MockAction();
		add_filter( 'extra_theme_headers', array( $filter, 'filter' ) );

		// Populate cache.
		$theme->get_post_templates();

		$filter_call_count = $filter->get_call_count();
		$this->assertGreaterThan( 0, $filter_call_count, 'The `extra_theme_headers` filter should be called at least once.' );

		$child_theme = wp_get_theme( 'page-templates-child' );
		switch_theme( $child_theme['Template'], $child_theme['Stylesheet'] );

		$child_templates = $child_theme->get_post_templates();

		// Child theme should include its own templates.
		$this->assertArrayHasKey( 'foo', $child_templates );
		$this->assertArrayHasKey( 'template-top-level-post-types-child.php', $child_templates['foo'] );
		$this->assertGreaterThan( $filter_call_count, $filter->get_call_count(), 'The `extra_theme_headers` filter should have extra calls.' );
	}

	/**
	 * @ticket 42513
	 */
	public function test_get_post_templates_uses_get_file_data() {
		$theme = wp_get_theme( 'page-templates' );
		$this->assertNotEmpty( $theme );

		$filter = new MockAction();
		add_filter( 'extra_theme_headers', array( $filter, 'filter' ) );

		$post_templates = $theme->get_post_templates();

		// Verify single-line header format is parsed correctly via get_file_data().
		$this->assertArrayHasKey( 'page', $post_templates );
		$this->assertArrayHasKey( 'template-header.php', $post_templates['page'] );
		$this->assertSame( 'This Template Header Is On One Line', $post_templates['page']['template-header.php'] );

		// Verify the `extra_theme_headers` filter is called.
		$this->assertGreaterThan( 0, $filter->get_call_count(), 'The `extra_theme_headers` filter should be called at least once.' );
	}
}
