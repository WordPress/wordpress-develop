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
}
