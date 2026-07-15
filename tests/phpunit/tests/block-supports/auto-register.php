<?php
/**
 * @group block-supports
 *
 * @covers ::_wp_enqueue_auto_register_blocks
 * @covers ::wp_apply_pattern_block_rendering
 * @covers ::wp_mark_auto_generate_control_attributes
 */
class Tests_Block_Supports_Auto_Register extends WP_UnitTestCase {

	const BLOCK_NAME = 'tests/pattern-block';

	const DYNAMIC_CHILD_BLOCK_NAME = 'tests/pattern-block-dynamic-child';

	const PATTERN = '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Default title</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>Plugin-owned paragraph.</p><!-- /wp:paragraph -->';

	/**
	 * Original scripts instance.
	 *
	 * @var WP_Scripts|null
	 */
	protected $original_wp_scripts;

	/**
	 * Block types registered during a test.
	 *
	 * @var string[]
	 */
	private $registered_block_names = array();

	public function set_up() {
		parent::set_up();

		global $wp_scripts;
		$this->original_wp_scripts = $wp_scripts;
		$wp_scripts                = null;
		wp_scripts();
	}

	public function tear_down() {
		foreach ( array_reverse( $this->registered_block_names ) as $block_name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				unregister_block_type( $block_name );
			}
		}

		global $wp_scripts;
		$wp_scripts = $this->original_wp_scripts;

		WP_Theme_JSON_Resolver::clean_cached_data();

		parent::tear_down();
	}

	/**
	 * Registers a block and records it for cleanup.
	 *
	 * @param string $block_name Block type name.
	 * @param array  $args       Arguments for registering the block type.
	 * @return WP_Block_Type|false Registered block type on success, false on failure.
	 */
	private function register_test_block( $block_name, $args ) {
		$block_type = register_block_type( $block_name, $args );

		if ( $block_type ) {
			$this->registered_block_names[] = $block_name;
		}

		return $block_type;
	}

	/**
	 * Registers the test pattern block.
	 *
	 * @param array $extra_args Extra `register_block_type()` arguments.
	 * @return WP_Block_Type|false Registered block type on success, false on failure.
	 */
	private function register_pattern_block( $extra_args = array() ) {
		return $this->register_test_block(
			self::BLOCK_NAME,
			array_merge(
				array(
					'supports' => array( 'autoRegister' => true ),
					'pattern'  => self::PATTERN,
				),
				$extra_args
			)
		);
	}

	/**
	 * Returns data from an auto-registration bootstrap global.
	 *
	 * @param string $global_name JavaScript global name, without the `window.` prefix.
	 * @return array|null Decoded bootstrap data, or null if the global was not added.
	 */
	private function get_auto_register_bootstrap_data( $global_name ) {
		$inline_scripts = wp_scripts()->get_data( 'wp-block-library', 'before' );
		$prefix         = 'window.' . $global_name . ' = ';

		foreach ( (array) $inline_scripts as $inline_script ) {
			if ( 0 === strpos( $inline_script, $prefix ) ) {
				return json_decode( substr( $inline_script, strlen( $prefix ), -1 ), true );
			}
		}

		return null;
	}

	/**
	 * Tests that attributes are marked when autoRegister is enabled.
	 *
	 * @ticket 64639
	 */
	public function test_marks_attributes_with_auto_register_flag() {
		$settings = array(
			'supports'   => array( 'autoRegister' => true ),
			'attributes' => array(
				'title' => array( 'type' => 'string' ),
				'count' => array( 'type' => 'integer' ),
			),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertTrue( $result['attributes']['title']['autoGenerateControl'] );
		$this->assertTrue( $result['attributes']['count']['autoGenerateControl'] );
	}

	/**
	 * Tests that attributes are not marked without autoRegister flag.
	 *
	 * @ticket 64639
	 */
	public function test_does_not_mark_attributes_without_auto_register() {
		$settings = array(
			'attributes' => array(
				'title' => array( 'type' => 'string' ),
			),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['title'] );
	}

	/**
	 * Tests that attributes with source are excluded.
	 *
	 * @ticket 64639
	 */
	public function test_excludes_attributes_with_source() {
		$settings = array(
			'supports'   => array( 'autoRegister' => true ),
			'attributes' => array(
				'title'   => array( 'type' => 'string' ),
				'content' => array(
					'type'   => 'string',
					'source' => 'html',
				),
			),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertTrue( $result['attributes']['title']['autoGenerateControl'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['content'] );
	}

	/**
	 * Tests that attributes with role: local are excluded.
	 *
	 * Example: The 'blob' attribute in media blocks (image, video, file, audio)
	 * stores a temporary blob URL during file upload. This is internal state
	 * that shouldn't be shown in the inspector or saved to the database.
	 *
	 * @ticket 64639
	 */
	public function test_excludes_attributes_with_role_local() {
		$settings = array(
			'supports'   => array( 'autoRegister' => true ),
			'attributes' => array(
				'title' => array( 'type' => 'string' ),
				'blob'  => array(
					'type' => 'string',
					'role' => 'local',
				),
			),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertTrue( $result['attributes']['title']['autoGenerateControl'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['blob'] );
	}

	/**
	 * Tests that empty attributes are handled gracefully.
	 *
	 * @ticket 64639
	 */
	public function test_handles_empty_attributes() {
		$settings = array(
			'supports' => array( 'autoRegister' => true ),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertSame( $settings, $result );
	}

	/**
	 * Tests that only allowed attributes are marked.
	 *
	 * @ticket 64639
	 */
	public function test_excludes_unsupported_types() {
		$settings = array(
			'supports'   => array( 'autoRegister' => true ),
			'attributes' => array(
				// Supported types
				'text'     => array( 'type' => 'string' ),
				'price'    => array( 'type' => 'number' ),
				'count'    => array( 'type' => 'integer' ),
				'enabled'  => array( 'type' => 'boolean' ),
				// Unsupported types
				'metadata' => array( 'type' => 'object' ),
				'items'    => array( 'type' => 'array' ),
				'config'   => array( 'type' => 'null' ),
				'unknown'  => array( 'type' => 'unknown' ),
			),
		);

		$result = wp_mark_auto_generate_control_attributes( $settings );

		$this->assertTrue( $result['attributes']['text']['autoGenerateControl'] );
		$this->assertTrue( $result['attributes']['price']['autoGenerateControl'] );
		$this->assertTrue( $result['attributes']['count']['autoGenerateControl'] );
		$this->assertTrue( $result['attributes']['enabled']['autoGenerateControl'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['metadata'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['items'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['config'] );
		$this->assertArrayNotHasKey( 'autoGenerateControl', $result['attributes']['unknown'] );
	}

	/**
	 * @ticket 65628
	 */
	public function test_renders_the_pattern_inside_the_block_wrapper() {
		$this->register_pattern_block();

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );

		$this->assertStringContainsString( 'Default title', $output );
		$this->assertStringContainsString( 'Plugin-owned paragraph.', $output );
		$this->assertStringContainsString( 'wp-block-tests-pattern-block', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_renders_instance_overrides_for_bound_fields() {
		$this->register_pattern_block();

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' {"content":{"Title":{"content":"Overridden title"}}} /-->' );

		$this->assertStringContainsString( 'Overridden title', $output );
		$this->assertStringNotContainsString( 'Default title', $output );
		$this->assertStringContainsString( 'Plugin-owned paragraph.', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_ignores_saved_inner_content_without_rendering_dynamic_children() {
		$render_calls = 0;
		$this->register_test_block(
			self::DYNAMIC_CHILD_BLOCK_NAME,
			array(
				'render_callback' => static function () use ( &$render_calls ) {
					++$render_calls;
					return '<p>DYNAMIC SAVED CHILD</p>';
				},
			)
		);
		$this->register_pattern_block();

		$output = do_blocks(
			'<!-- wp:' . self::BLOCK_NAME . ' -->'
			. '<p>INJECTED</p>'
			. '<!-- wp:' . self::DYNAMIC_CHILD_BLOCK_NAME . ' /-->'
			. '<!-- /wp:' . self::BLOCK_NAME . ' -->'
		);

		$this->assertSame( 0, $render_calls );
		$this->assertStringNotContainsString( 'INJECTED', $output );
		$this->assertStringNotContainsString( 'DYNAMIC SAVED CHILD', $output );
		$this->assertStringContainsString( 'Plugin-owned paragraph.', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_registering_a_render_callback_with_a_pattern_raises_a_notice_and_is_ignored() {
		$this->setExpectedIncorrectUsage( 'register_block_type' );

		$this->register_pattern_block(
			array(
				'render_callback' => static function () {
					return '<p>CALLBACK OUTPUT</p>';
				},
			)
		);

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );

		$this->assertStringNotContainsString( 'CALLBACK OUTPUT', $output );
		$this->assertStringContainsString( 'Plugin-owned paragraph.', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_replaces_an_author_declared_content_attribute() {
		$this->register_pattern_block(
			array(
				'attributes' => array(
					'content' => array( 'type' => 'string' ),
				),
			)
		);

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' {"content":{"Title":{"content":"Overridden title"}}} /-->' );

		$this->assertStringContainsString( 'Overridden title', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_renders_the_pattern_current_at_render_time() {
		$this->register_pattern_block();

		WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME )->pattern =
			'<!-- wp:paragraph --><p>Replaced pattern.</p><!-- /wp:paragraph -->';

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );

		$this->assertStringContainsString( 'Replaced pattern.', $output );
		$this->assertStringNotContainsString( 'Default title', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_embeds_urls_in_the_pattern() {
		$this->register_pattern_block(
			array(
				'pattern' => '<!-- wp:embed {"url":"https://example.com/video"} -->'
					. '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . "\n"
					. 'https://example.com/video' . "\n"
					. '</div></figure>'
					. '<!-- /wp:embed -->',
			)
		);

		$expected_embed = '<iframe src="https://example.com/embedded-video"></iframe>';
		$pre_oembed     = static function () use ( $expected_embed ) {
			return $expected_embed;
		};
		add_filter( 'pre_oembed_result', $pre_oembed );

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );
		remove_filter( 'pre_oembed_result', $pre_oembed );

		$this->assertStringContainsString( $expected_embed, $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_processes_embed_shortcodes_in_the_pattern() {
		$this->register_pattern_block(
			array(
				'pattern' => '[embed]https://example.com/shortcode-video[/embed]',
			)
		);

		$expected_embed = '<iframe src="https://example.com/embedded-shortcode-video"></iframe>';
		$pre_oembed     = static function () use ( $expected_embed ) {
			return $expected_embed;
		};
		add_filter( 'pre_oembed_result', $pre_oembed );

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );
		remove_filter( 'pre_oembed_result', $pre_oembed );

		$this->assertStringContainsString( $expected_embed, $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_does_not_render_saved_inner_content_when_current_pattern_is_empty() {
		$render_calls = 0;
		$this->register_test_block(
			self::DYNAMIC_CHILD_BLOCK_NAME,
			array(
				'render_callback' => static function () use ( &$render_calls ) {
					++$render_calls;
					return '<p>DYNAMIC SAVED CHILD</p>';
				},
			)
		);
		$this->register_pattern_block();

		WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME )->pattern = '';

		$output = do_blocks(
			'<!-- wp:' . self::BLOCK_NAME . ' -->'
			. '<p>INJECTED</p>'
			. '<!-- wp:' . self::DYNAMIC_CHILD_BLOCK_NAME . ' /-->'
			. '<!-- /wp:' . self::BLOCK_NAME . ' -->'
		);

		$this->assertSame( 0, $render_calls );
		$this->assertStringNotContainsString( 'INJECTED', $output );
		$this->assertStringNotContainsString( 'DYNAMIC SAVED CHILD', $output );
		$this->assertStringNotContainsString( 'Plugin-owned paragraph.', $output );
	}

	/**
	 * @ticket 65628
	 */
	public function test_applies_the_host_render_block_filters_once() {
		$this->register_pattern_block();

		$filter_calls = 0;
		$count_calls  = static function ( $block_content ) use ( &$filter_calls ) {
			++$filter_calls;
			return $block_content;
		};
		add_filter( 'render_block_' . self::BLOCK_NAME, $count_calls );

		do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );
		remove_filter( 'render_block_' . self::BLOCK_NAME, $count_calls );

		$this->assertSame( 1, $filter_calls );
	}

	/**
	 * @ticket 65628
	 */
	public function test_applies_the_per_child_render_filters_to_pattern_roots() {
		$this->register_pattern_block();

		$roots_seen = array();
		$collect    = static function ( $pre_render, $parsed_block, $parent_block ) use ( &$roots_seen ) {
			if ( $parent_block instanceof WP_Block && self::BLOCK_NAME === $parent_block->name ) {
				$roots_seen[] = $parsed_block['blockName'];
			}
			return $pre_render;
		};
		add_filter( 'pre_render_block', $collect, 10, 3 );

		do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );
		remove_filter( 'pre_render_block', $collect );

		$this->assertSame( array( 'core/heading', 'core/paragraph' ), $roots_seen );
	}

	/**
	 * @ticket 65628
	 */
	public function test_renders_a_self_referencing_pattern_without_recursing() {
		$this->register_pattern_block(
			array(
				'pattern' => '<!-- wp:paragraph --><p>Outer content.</p><!-- /wp:paragraph -->'
					. '<!-- wp:' . self::BLOCK_NAME . ' /-->',
			)
		);

		$output = do_blocks( '<!-- wp:' . self::BLOCK_NAME . ' /-->' );

		$this->assertSame( 1, substr_count( $output, 'Outer content.' ) );
	}

	/**
	 * @ticket 65628
	 */
	public function test_disables_html_support_by_default_for_pattern_blocks() {
		$block_type = $this->register_pattern_block();

		$this->assertFalse( $block_type->supports['html'] );
	}

	/**
	 * @ticket 65628
	 */
	public function test_preserves_explicit_html_support_for_pattern_blocks() {
		$block_type = $this->register_pattern_block(
			array(
				'supports' => array(
					'autoRegister' => true,
					'html'         => true,
				),
			)
		);

		$this->assertTrue( $block_type->supports['html'] );
	}

	/**
	 * @ticket 65628
	 */
	public function test_enqueues_exact_pattern_markup_for_eligible_blocks() {
		$pattern = '<!-- wp:paragraph --><p>Markup &amp; spacing.</p><!-- /wp:paragraph -->';
		$this->register_test_block(
			'tests/auto-register-eligible-pattern',
			array(
				'supports' => array( 'autoRegister' => true ),
				'pattern'  => $pattern,
			)
		);

		_wp_enqueue_auto_register_blocks();
		$patterns = $this->get_auto_register_bootstrap_data( '__unstableAutoRegisterBlockPatterns' );

		$this->assertIsArray( $patterns );
		$this->assertArrayHasKey( 'tests/auto-register-eligible-pattern', $patterns );
		$this->assertSame( $pattern, $patterns['tests/auto-register-eligible-pattern'] );
	}

	/**
	 * @ticket 65628
	 */
	public function test_excludes_ineligible_blocks_from_pattern_bootstrap() {
		$this->register_test_block(
			'tests/auto-register-without-flag',
			array( 'pattern' => self::PATTERN )
		);
		$this->register_test_block(
			'tests/auto-register-without-pattern',
			array( 'supports' => array( 'autoRegister' => true ) )
		);
		$this->register_test_block(
			'tests/auto-register-empty-pattern',
			array(
				'supports' => array( 'autoRegister' => true ),
				'pattern'  => '',
			)
		);
		$this->register_test_block(
			'tests/auto-register-non-string-pattern',
			array(
				'supports' => array( 'autoRegister' => true ),
				'pattern'  => array( 'not markup' ),
			)
		);

		_wp_enqueue_auto_register_blocks();

		$this->assertNull( $this->get_auto_register_bootstrap_data( '__unstableAutoRegisterBlockPatterns' ) );
	}

	/**
	 * @ticket 65628
	 */
	public function test_preserves_legacy_callback_bootstrap_without_adding_a_pattern_script() {
		$this->register_test_block(
			'tests/auto-register-callback',
			array(
				'supports'        => array( 'autoRegister' => true ),
				'render_callback' => static function () {
					return '<p>Callback output.</p>';
				},
			)
		);

		_wp_enqueue_auto_register_blocks();

		$auto_register_blocks = $this->get_auto_register_bootstrap_data( '__unstableAutoRegisterBlocks' );
		$this->assertIsArray( $auto_register_blocks );
		$this->assertContains( 'tests/auto-register-callback', $auto_register_blocks );
		$this->assertNull( $this->get_auto_register_bootstrap_data( '__unstableAutoRegisterBlockPatterns' ) );
	}
}
