<?php
/**
 * Tests for block template files whose names change when sanitized as a slug.
 *
 * @group block-templates
 */
class Tests_Block_Templates_SanitizedTemplateSlugs extends WP_UnitTestCase {

	const TEST_THEME = 'block-theme-template-slugs';

	/**
	 * Stylesheet that was active before the test switched themes.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	public function set_up() {
		parent::set_up();
		$this->original_stylesheet = get_stylesheet();
		switch_theme( self::TEST_THEME );
	}

	public function tear_down() {
		switch_theme( $this->original_stylesheet );
		parent::tear_down();
	}

	/**
	 * Tests that a template file is listed with the slug that its customized version is saved with.
	 *
	 * @covers ::get_block_templates
	 * @covers ::_get_block_templates_files
	 */
	public function test_should_list_template_file_with_sanitized_slug() {
		$templates = get_block_templates();
		$ids       = wp_list_pluck( $templates, 'id' );

		$this->assertContains( self::TEST_THEME . '//one-two', $ids, 'The template file should be listed with the sanitized slug.' );
		$this->assertNotContains( self::TEST_THEME . '//one--two', $ids, 'The template file should not be listed with the file name as the slug.' );
	}

	/**
	 * Tests that the theme.json metadata of a template is matched by the file name.
	 *
	 * @covers ::_get_block_templates_files
	 */
	public function test_should_add_theme_json_metadata_by_file_name() {
		$template = get_block_file_template( self::TEST_THEME . '//one-two' );

		$this->assertSame( 'One Two', $template->title );
	}

	/**
	 * Tests that a template file can be retrieved by both its sanitized slug and its file name.
	 *
	 * @covers ::get_block_file_template
	 * @covers ::_get_block_template_file
	 */
	public function test_should_get_template_file_by_sanitized_slug_and_file_name() {
		$by_slug      = get_block_file_template( self::TEST_THEME . '//one-two' );
		$by_file_name = get_block_file_template( self::TEST_THEME . '//one--two' );

		$this->assertInstanceOf( WP_Block_Template::class, $by_slug, 'The template file should be found by its sanitized slug.' );
		$this->assertSame( 'one-two', $by_slug->slug );
		$this->assertInstanceOf( WP_Block_Template::class, $by_file_name, 'The template file should be found by its file name.' );
		$this->assertSame( 'one-two', $by_file_name->slug );
	}

	/**
	 * Tests that a customized template replaces the template file instead of being listed next to it.
	 *
	 * @covers ::get_block_templates
	 */
	public function test_should_return_customized_template_instead_of_template_file() {
		$this->create_customized_template( 'one--two' );

		$templates = get_block_templates( array( 'slug__in' => array( 'one--two' ) ) );

		$this->assertCount( 1, $templates, 'Only the customized template should be returned.' );
		$this->assertSame( 'custom', $templates[0]->source );
		$this->assertSame( self::TEST_THEME . '//one-two', $templates[0]->id );
	}

	/**
	 * Tests that resolving a template from a hierarchy that contains the file name finds the customized template.
	 *
	 * @covers ::resolve_block_template
	 */
	public function test_should_resolve_customized_template_from_file_name_in_hierarchy() {
		$this->create_customized_template( 'one--two' );

		$template = resolve_block_template( 'page', array( 'one--two', 'page', 'index' ), '' );

		$this->assertInstanceOf( WP_Block_Template::class, $template );
		$this->assertSame( 'one-two', $template->slug );
		$this->assertSame( 'custom', $template->source );
	}

	/**
	 * Tests that a template part file is listed with the sanitized slug.
	 *
	 * @covers ::_get_block_templates_files
	 */
	public function test_should_list_template_part_file_with_sanitized_slug() {
		$ids = wp_list_pluck( get_block_templates( array(), 'wp_template_part' ), 'id' );

		$this->assertContains( self::TEST_THEME . '//header', $ids );
	}

	/**
	 * Creates a customized version of a template, as saving it in the editor does.
	 *
	 * @param string $slug Template slug.
	 */
	private function create_customized_template( $slug ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => 'Customized',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Customized</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_post_terms( $post_id, self::TEST_THEME, 'wp_theme' );
	}
}
