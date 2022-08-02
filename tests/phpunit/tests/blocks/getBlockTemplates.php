<?php
/**
 * @group blocks
 *
 * @covers ::get_block_templates
 */
class Tests_Blocks_GetBlockTemplates extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	/**
	 * @var WP_Post
	 */
	private static $template;

	/**
	 * @var WP_Post
	 */
	private static $template_part;

	public static function wpSetUpBeforeClass() {

		// This template has to have the same ID ("block-theme/index") as the template that is shipped with the
		// "block-theme" theme. This is needed for testing purposes.
		static::$template = self::factory()->post->create_and_get(
			array(
				'post_type' => 'wp_template',
				'post_name' => 'index',
				'tax_input' => array(
					'wp_theme' => array(
						static::TEST_THEME,
					),
				),
			)
		);
		wp_set_post_terms( static::$template->ID, static::TEST_THEME, 'wp_theme' );

		// This template part has to have the same ID ("block-theme/small-header") as the template that is shipped with the
		// "block-theme" theme. This is needed for testing purposes.
		self::$template_part = self::factory()->post->create_and_get(
			array(
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
			)
		);
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

	/**
	 * Data provider for test_it_returns_unique_entities().
	 *
	 * @return array
	 */
	public function data_test_it_returns_unique_entities() {
		return array(
			'wp_template template type'      => array(
				'template_type' => 'wp_template',
				'error_message' => 'get_block_templates() must return unique templates.',
			),
			'wp_template_part template type' => array(
				'template_type' => 'wp_template_part',
				'error_message' => 'get_block_templates() must return unique template parts.',
			),
		);
	}

	/**
	 * @ticket 56271
	 *
	 * @dataProvider data_test_it_returns_unique_entities
	 *
	 * @param string $template_type The tempalte type.
	 * @param string $error_message An error message to display if the test fails.
	 */
	public function test_it_returns_unique_entities( $template_type, $error_message ) {
		$templates    = get_block_templates( array(), $template_type );
		$template_ids = array_map(
			static function( WP_Block_Template $template ) {
				return $template->id;
			},
			$templates
		);

		$this->assertNotEmpty( $template_ids, 'get_block_templates() must return a non-empty value.' );
		$this->assertSame( count( array_unique( $template_ids ) ), count( $template_ids ), $error_message );
	}
}
