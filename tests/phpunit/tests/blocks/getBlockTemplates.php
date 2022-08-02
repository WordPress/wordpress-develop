<?php

class Tests_Blocks_GetBlockTemplates extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	public static function wpSetUpBeforeClass() {

		// This custom template has to be created for testing purposes.
		// It has the same ID ("wp_block/index") as the template that is shipped with the
		// wp_block theme.
		$args = array(
			'post_type' => 'wp_template',
			'post_name' => 'index',
			'tax_input' => array(
				'wp_theme' => array(
					static::TEST_THEME,
				),
			),
		);
		$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( $post->ID, static::TEST_THEME, 'wp_theme' );
	}

	public function set_up() {
		parent::set_up();
		switch_theme( static::TEST_THEME );
	}

	public function test_get_block_templates_returns_unique_templates() {
		$templates    = get_block_templates( array(), 'wp_template' );
		$template_ids = array_map(
			static function( WP_Block_Template $template ) {
				return $template->id;
			},
			$templates
		);

		$this->assertNotEmpty( $template_ids, 'get_block_templates must' );
		$this->assertSame( count( array_unique( $template_ids ) ), count( $template_ids ), 'get_block_templates() must return unique templates.' );
	}
}
