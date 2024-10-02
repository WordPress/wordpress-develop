<?php

require_once __DIR__ . '/base.php';

/**
 * @group block-templates
 * @covers ::_build_block_template_result_from_file
 */
class Tests_Block_Templates_BuildBlockTemplateResultFromFile extends WP_Block_Templates_UnitTestCase {
	/**
	 * Tear down each test method.
	 *
	 * @since 6.7.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'tests/my-block' ) ) {
			$registry->unregister( 'tests/my-block' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 54335
	 */
	public function test_should_build_template() {
		$template = _build_block_template_result_from_file(
			array(
				'slug' => 'single',
				'path' => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template'
		);

		$this->assertSame( get_stylesheet() . '//single', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'single', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'theme', $template->source );
		$this->assertSame( 'Single Posts', $template->title );
		$this->assertSame( 'Displays a single post on your website unless a custom template has been applied to that post or a dedicated template exists.', $template->description );
		$this->assertSame( 'wp_template', $template->type );
		$this->assertEmpty( $template->modified );
	}

	/**
	 * @ticket 59325
	 */
	public function test_should_build_template_using_custom_properties() {
		$template = _build_block_template_result_from_file(
			array(
				'slug'  => 'custom',
				'title' => 'Custom Title',
				'path'  => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template'
		);

		$this->assertSame( 'custom', $template->slug );
		$this->assertSame( 'Custom Title', $template->title );
		$this->assertTrue( $template->is_custom );
	}

	/**
	 * @ticket 59325
	 */
	public function test_should_enforce_default_properties_when_building_template() {
		$template = _build_block_template_result_from_file(
			array(
				'slug'  => 'single',
				'title' => 'Custom title',
				'path'  => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template'
		);

		$this->assertSame( 'single', $template->slug );
		$this->assertSame( 'Single Posts', $template->title );
		$this->assertSame( 'Displays a single post on your website unless a custom template has been applied to that post or a dedicated template exists.', $template->description );
		$this->assertFalse( $template->is_custom );
	}

	/**
	 * @ticket 59325
	 */
	public function test_should_respect_post_types_property_when_building_template() {
		$template = _build_block_template_result_from_file(
			array(
				'slug'      => 'single',
				'postTypes' => array( 'post' ),
				'path'      => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template'
		);

		$this->assertSameSets( array( 'post' ), $template->post_types );
	}

	/**
	 * @ticket 59325
	 *
	 * @dataProvider data_build_template_injects_theme_attribute
	 *
	 * @param string $filename The template's filename.
	 * @param string $expected The expected block markup.
	 */
	public function test_should_build_template_and_inject_theme_attribute( $filename, $expected ) {
		$template = _build_block_template_result_from_file(
			array(
				'slug' => 'single',
				'path' => DIR_TESTDATA . "/templates/$filename",
			),
			'wp_template'
		);
		$this->assertSame( $expected, $template->content );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_build_template_injects_theme_attribute() {
		return array(
			'a template with a template part block'  => array(
				'filename' => 'template-with-template-part.html',
				'expected' => sprintf(
					'<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->',
					self::TEST_THEME
				),
			),
			'a template with a template part block nested inside another block' => array(
				'filename' => 'template-with-nested-template-part.html',
				'expected' => sprintf(
					'<!-- wp:group -->
<!-- wp:template-part {"slug":"header","align":"full","tagName":"header","className":"site-header","theme":"%s"} /-->
<!-- /wp:group -->',
					self::TEST_THEME
				),
			),
			'a template with a template part block with an existing theme attribute' => array(
				'filename' => 'template-with-template-part-with-existing-theme-attribute.html',
				'expected' => '<!-- wp:template-part {"slug":"header","theme":"fake-theme","align":"full","tagName":"header","className":"site-header"} /-->',
			),
			'a template with no template part block' => array(
				'filename' => 'template.html',
				'expected' => '<!-- wp:paragraph -->
<p>Just a paragraph</p>
<!-- /wp:paragraph -->',
			),
		);
	}

	/**
	 * @ticket 54335
	 */
	public function test_should_build_template_part() {
		$template_part = _build_block_template_result_from_file(
			array(
				'slug' => 'header',
				'path' => DIR_TESTDATA . '/templates/template.html',
				'area' => WP_TEMPLATE_PART_AREA_HEADER,
			),
			'wp_template_part'
		);
		$this->assertSame( get_stylesheet() . '//header', $template_part->id );
		$this->assertSame( get_stylesheet(), $template_part->theme );
		$this->assertSame( 'header', $template_part->slug );
		$this->assertSame( 'publish', $template_part->status );
		$this->assertSame( 'theme', $template_part->source );
		$this->assertSame( 'header', $template_part->title );
		$this->assertSame( '', $template_part->description );
		$this->assertSame( 'wp_template_part', $template_part->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template_part->area );
		$this->assertEmpty( $template_part->modified );
	}

	/**
	 * @ticket 59325
	 */
	public function test_should_ignore_post_types_property_when_building_template_part() {
		$template = _build_block_template_result_from_file(
			array(
				'slug'      => 'header',
				'postTypes' => array( 'post' ),
				'path'      => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template_part'
		);

		$this->assertEmpty( $template->post_types );
	}

	/**
	 * @ticket 60506
	 */
	public function test_should_inject_hooked_block_into_template_part() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/paragraph' => 'after',
				),
			)
		);

		$template_part = _build_block_template_result_from_file(
			array(
				'slug'      => 'header',
				'postTypes' => array( 'post' ),
				'path'      => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template_part'
		);
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}

	/**
	 * @ticket 60506
	 * @ticket 60854
	 */
	public function test_should_injected_hooked_block_into_template_part_first_child() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/template-part' => 'first_child',
				),
			)
		);

		$template_part = _build_block_template_result_from_file(
			array(
				'slug'      => 'header',
				'postTypes' => array( 'post' ),
				'path'      => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template_part'
		);
		$this->assertStringStartsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}

	/**
	 * @ticket 60506
	 * @ticket 60854
	 */
	public function test_should_injected_hooked_block_into_template_part_last_child() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/template-part' => 'last_child',
				),
			)
		);

		$template_part = _build_block_template_result_from_file(
			array(
				'slug'      => 'header',
				'postTypes' => array( 'post' ),
				'path'      => DIR_TESTDATA . '/templates/template.html',
			),
			'wp_template_part'
		);
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}
}
