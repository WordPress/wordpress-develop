<?php

require_once __DIR__ . '/base.php';

/**
 * @group block-templates
 * @covers ::_build_block_template_result_from_post
 */
class Tests_Block_Templates_BuildBlockTemplateResultFromPost extends WP_Block_Templates_UnitTestCase {

	/**
	 * Tear down each test method.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'tests/my-block' ) ) {
			$registry->unregister( 'tests/my-block' );
		}

		if ( $registry->is_registered( 'tests/ignored' ) ) {
			$registry->unregister( 'tests/ignored' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 54335
	 */
	public function test_should_build_template() {
		$template = _build_block_template_result_from_post(
			self::$template_post,
			'wp_template'
		);

		$this->assertNotWPError( $template );
		$this->assertSame( get_stylesheet() . '//my_template', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'My Template', $template->title );
		$this->assertSame( 'Description of my template', $template->description );
		$this->assertSame( 'wp_template', $template->type );
		$this->assertSame( self::$template_post->post_modified, $template->modified, 'Template result properties match' );
	}

	/**
	 * @ticket 54335
	 */
	public function test_should_build_template_part() {
		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertNotWPError( $template_part );
		$this->assertSame( get_stylesheet() . '//my_template_part', $template_part->id );
		$this->assertSame( get_stylesheet(), $template_part->theme );
		$this->assertSame( 'my_template_part', $template_part->slug );
		$this->assertSame( 'publish', $template_part->status );
		$this->assertSame( 'custom', $template_part->source );
		$this->assertSame( 'My Template Part', $template_part->title );
		$this->assertSame( 'Description of my template part', $template_part->description );
		$this->assertSame( 'wp_template_part', $template_part->type );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $template_part->area );
		$this->assertSame( self::$template_part_post->post_modified, $template_part->modified, 'Template part result properties match' );
	}

	/**
	 * @ticket 59646
	 * @ticket 60506
	 */
	public function test_should_inject_hooked_block_into_template() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/heading' => 'before',
				),
			)
		);

		$template = _build_block_template_result_from_post(
			self::$template_post,
			'wp_template'
		);
		$this->assertStringStartsWith( '<!-- wp:tests/my-block /-->', $template->content );
	}

	/**
	 * @ticket 59646
	 * @ticket 60506
	 */
	public function test_should_inject_hooked_block_into_template_part() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/heading' => 'after',
				),
			)
		);

		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}

	/**
	 * @ticket 59646
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

		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertStringStartsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}

	/**
	 * @ticket 59646
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

		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertStringEndsWith( '<!-- wp:tests/my-block /-->', $template_part->content );
	}

	/**
	 * @ticket 59646
	 * @ticket 60506
	 */
	public function test_should_not_inject_ignored_hooked_block_into_template() {
		register_block_type(
			'tests/ignored',
			array(
				'block_hooks' => array(
					'core/heading' => 'after',
				),
			)
		);

		$template = _build_block_template_result_from_post(
			self::$template_post,
			'wp_template'
		);
		$this->assertStringNotContainsString( '<!-- wp:tests/ignored /-->', $template->content );
	}

	/**
	 * @ticket 59646
	 * @ticket 60506
	 */
	public function test_should_not_inject_ignored_hooked_block_into_template_part() {
		register_block_type(
			'tests/ignored',
			array(
				'block_hooks' => array(
					'core/heading' => 'after',
				),
			)
		);

		$template_part = _build_block_template_result_from_post(
			self::$template_part_post,
			'wp_template_part'
		);
		$this->assertStringNotContainsString( '<!-- wp:tests/ignored /-->', $template_part->content );
	}
}
