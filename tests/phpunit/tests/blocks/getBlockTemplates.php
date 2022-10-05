<?php
/**
 * @group block-templates
 *
 * @covers ::get_block_templates
 */
class Tests_Blocks_GetBlockTemplates extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme';

	/**
	 * @var WP_Post
	 */
	private static $index_template;

	/**
	 * @var WP_Post
	 */
	private static $custom_single_post_template;

	/**
	 * @var WP_Post
	 */
	private static $small_header_template_part;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		/*
		 * This template has to have the same ID ("block-theme/index") as the template
		 * that is shipped with the "block-theme" theme. This is needed for testing purposes.
		 */
		self::$index_template = $factory->post->create_and_get(
			array(
				'post_type' => 'wp_template',
				'post_name' => 'index',
				'tax_input' => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);

		wp_set_post_terms( self::$index_template->ID, self::TEST_THEME, 'wp_theme' );

		self::$custom_single_post_template = $factory->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'custom-single-post-template',
				'post_title'   => 'Custom Single Post template (modified)',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of custom single post template',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);

		wp_set_post_terms( self::$custom_single_post_template->ID, self::TEST_THEME, 'wp_theme' );

		/*
		 * This template part has to have the same ID ("block-theme/small-header") as the template part
		 * that is shipped with the "block-theme" theme. This is needed for testing purposes.
		 */
		self::$small_header_template_part = $factory->post->create_and_get(
			array(
				'post_type' => 'wp_template_part',
				'post_name' => 'small-header',
				'tax_input' => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);

		wp_set_post_terms( self::$small_header_template_part->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( self::$small_header_template_part->ID, self::TEST_THEME, 'wp_theme' );
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$index_template->ID );
		wp_delete_post( self::$custom_single_post_template->ID );
		wp_delete_post( self::$small_header_template_part->ID );
	}

	public function set_up() {
		parent::set_up();
		switch_theme( self::TEST_THEME );
	}

	/**
	 * Gets the template IDs from the given array.
	 *
	 * @param object[] $templates Array of template objects to parse.
	 * @return string[] The template IDs.
	 */
	private function get_template_ids( $templates ) {
		return array_map(
			static function( $template ) {
				return $template->id;
			},
			$templates
		);
	}

	/**
	 * Should retrieve block templates (file and CPT)
	 */
	public function test_get_block_templates() {
		// All results.
		$templates    = get_block_templates( array(), 'wp_template' );
		$template_ids = $this->get_template_ids( $templates );

		// Avoid testing the entire array because the theme might add/remove templates.
		$this->assertContains( get_stylesheet() . '//' . 'custom-single-post-template', $template_ids );

		// The result might change in a block theme.
		$this->assertContains( get_stylesheet() . '//' . 'index', $template_ids );

		// Filter by slug.
		$templates    = get_block_templates( array( 'slug__in' => array( 'custom-single-post-template' ) ), 'wp_template' );
		$template_ids = $this->get_template_ids( $templates );
		$this->assertSame( array( get_stylesheet() . '//' . 'custom-single-post-template' ), $template_ids );

		// Filter by CPT ID.
		$templates    = get_block_templates( array( 'wp_id' => self::$custom_single_post_template->ID ), 'wp_template' );
		$template_ids = $this->get_template_ids( $templates );
		$this->assertSame( array( get_stylesheet() . '//' . 'custom-single-post-template' ), $template_ids );

		// Filter template part by area.
		// Requires a block theme.
		$templates    = get_block_templates( array( 'area' => WP_TEMPLATE_PART_AREA_HEADER ), 'wp_template_part' );
		$template_ids = $this->get_template_ids( $templates );
		$this->assertSame(
			array(
				get_stylesheet() . '//' . 'small-header',
			),
			$template_ids
		);
	}

	/**
	 * @ticket 56271
	 *
	 * @dataProvider data_get_block_templates_returns_unique_entities
	 *
	 * @param string $template_type        The template type.
	 * @param string $original_template_id ID (slug) of the default entity.
	 * @param string $error_message        An error message to display if the test fails.
	 */
	public function test_get_block_templates_returns_unique_entities( $template_type, $original_template_id, $error_message ) {
		$original_template = _get_block_template_file( $template_type, $original_template_id );
		$this->assertNotEmpty( $original_template, 'An original (non-duplicate) template must exist for this test to work correctly.' );

		$block_templates = get_block_templates( array(), $template_type );
		$this->assertNotEmpty( $block_templates, 'get_block_templates() must return a non-empty value.' );

		$block_template_ids = wp_list_pluck( $block_templates, 'id' );
		$this->assertSame( count( array_unique( $block_template_ids ) ), count( $block_template_ids ), $error_message );
	}

	/**
	 * Data provider for test_get_block_templates_returns_unique_entities().
	 *
	 * @return array
	 */
	public function data_get_block_templates_returns_unique_entities() {
		return array(
			'wp_template template type'      => array(
				'template_type'        => 'wp_template',
				'original_template_id' => 'index',
				'error_message'        => 'get_block_templates() must return unique templates.',
			),
			'wp_template_part template type' => array(
				'template_type'        => 'wp_template_part',
				'original_template_id' => 'small-header',
				'error_message'        => 'get_block_templates() must return unique template parts.',
			),
		);
	}

	/**
	 * @dataProvider data_get_block_templates_should_respect_posttypes_property
	 * @ticket 55881
	 *
	 * @param string $post_type Post type for query.
	 * @param array  $expected  Expected template IDs.
	 */
	public function test_get_block_templates_should_respect_posttypes_property( $post_type, $expected ) {
		$templates = get_block_templates( array( 'post_type' => $post_type ) );

		$this->assertSameSets(
			$expected,
			$this->get_template_ids( $templates )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_block_templates_should_respect_posttypes_property() {
		return array(
			'post' => array(
				'post_type' => 'post',
				'expected'  => array(
					'block-theme//custom-single-post-template',
				),
			),
			'page' => array(
				'post_type' => 'page',
				'expected'  => array(
					'block-theme//page-home',
				),
			),
		);
	}
}
