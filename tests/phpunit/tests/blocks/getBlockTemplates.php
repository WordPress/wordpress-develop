<?php

class Tests_Blocks_GetBlockTemplates extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	public static function wpSetUpBeforeClass() {

		// This template has to have the same ID ("block-theme/index") as the template that is shipped with the
		// "block-theme" theme. This is needed for testing purposes.
		$args             = array(
			'post_type' => 'wp_template',
			'post_name' => 'index',
			'tax_input' => array(
				'wp_theme' => array(
					static::TEST_THEME,
				),
			),
		);
		static::$template = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( static::$template->ID, static::TEST_THEME, 'wp_theme' );

		// This template part has to have the same ID ("block-theme/small-header") as the template that is shipped with the
		// "block-theme" theme. This is needed for testing purposes.
		$template_part_args  = array(
			'post_type' => 'wp_template_part',
			'post_name' => 'small-header',
			'tax_input' => array(
				'wp_theme'              => array(
					static::TEST_THEME,
				),
				'wp_template_part_area' => array(
					WP_TEMPLATE_PART_AREA_HEADER,
				),
			),
		);
		self::$template_part = self::factory()->post->create_and_get( $template_part_args );
		wp_set_post_terms( self::$template_part->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( self::$template_part->ID, static::TEST_THEME, 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( static::$template->ID );
		wp_delete_post( static::$template_part->ID );
	}

	public function set_up() {
		parent::set_up();
		switch_theme( static::TEST_THEME );
	}

	public function test_it_returns_unique_templates_and_template_parts() {
		$templates    = get_block_templates( array(), 'wp_template' );
		$template_ids = array_map(
			static function( WP_Block_Template $template ) {
				return $template->id;
			},
			$templates
		);

		$this->assertNotEmpty( $template_ids, 'get_block_templates() must return a non-empty value.' );
		$this->assertSame( count( array_unique( $template_ids ) ), count( $template_ids ), 'get_block_templates() must return unique templates.' );
	}

	public function test_it_returns_unique_template_parts() {
		$templates    = get_block_templates( array(), 'wp_template_part' );
		$template_ids = array_map(
			static function( WP_Block_Template $template ) {
				return $template->id;
			},
			$templates
		);

		$this->assertNotEmpty( $template_ids, 'get_block_templates() must return a non-empty value.' );
		$this->assertSame( count( array_unique( $template_ids ) ), count( $template_ids ), 'get_block_templates() must return unique template parts.' );
	}
}
