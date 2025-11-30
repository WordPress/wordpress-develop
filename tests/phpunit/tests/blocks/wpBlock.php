<?php
/**
 * Tests for WP_Block.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlock extends WP_UnitTestCase {

	/**
	 * Fake block type registry.
	 *
	 * @var WP_Block_Type_Registry|null
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_styles, $wp_scripts, $wp_script_modules;
		$wp_styles         = null;
		$wp_scripts        = null;
		$wp_script_modules = null;

		$this->registry = new WP_Block_Type_Registry();
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		$this->registry = null;

		global $wp_styles, $wp_scripts, $wp_script_modules;
		$wp_styles         = null;
		$wp_scripts        = null;
		$wp_script_modules = null;

		parent::tear_down();
	}

	public function filter_render_block( $content, $parsed_block ) {
		return 'Original: "' . $content . '", from block "' . $parsed_block['blockName'] . '"';
	}

	/**
	 * @ticket 49927
	 */
	public function test_constructor_assigns_properties_from_parsed_block() {
		$this->registry->register( 'core/example', array() );

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( $parsed_block, $block->parsed_block );
		$this->assertSame( $parsed_block['blockName'], $block->name );
		$this->assertSame( $parsed_block['attrs'], $block->attributes );
		$this->assertSame( $parsed_block['innerContent'], $block->inner_content );
		$this->assertSame( $parsed_block['innerHTML'], $block->inner_html );
	}

	/**
	 * @ticket 49927
	 * @ticket 59797
	 */
	public function test_constructor_assigns_block_type_from_registry() {
		$block_type_settings = array(
			'attributes' => array(
				'defaulted' => array(
					'type'    => 'number',
					'default' => 10,
				),
			),
		);
		$this->registry->register( 'core/example', $block_type_settings );

		$parsed_block = array( 'blockName' => 'core/example' );
		$context      = array();
		$block        = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertInstanceOf( WP_Block_Type::class, $block->block_type );
		$this->assertSameSetsWithIndex(
			array(
				'defaulted' => array(
					'type'    => 'number',
					'default' => 10,
				),
				'lock'      => array( 'type' => 'object' ),
				'metadata'  => array( 'type' => 'object' ),
			),
			$block->block_type->attributes
		);
	}

	/**
	 * @ticket 49927
	 */
	public function test_lazily_assigns_attributes_with_defaults() {
		$this->registry->register(
			'core/example',
			array(
				'attributes' => array(
					'defaulted' => array(
						'type'    => 'number',
						'default' => 10,
					),
				),
			)
		);

		$parsed_block = array(
			'blockName' => 'core/example',
			'attrs'     => array(
				'explicit' => 20,
			),
		);
		$context      = array();
		$block        = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame(
			array(
				'explicit'  => 20,
				'defaulted' => 10,
			),
			$block->attributes
		);
	}

	/**
	 * @ticket 49927
	 */
	public function test_lazily_assigns_attributes_with_only_defaults() {
		$this->registry->register(
			'core/example',
			array(
				'attributes' => array(
					'defaulted' => array(
						'type'    => 'number',
						'default' => 10,
					),
				),
			)
		);

		$parsed_block = array(
			'blockName' => 'core/example',
			'attrs'     => array(),
		);
		$context      = array();
		$block        = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( array( 'defaulted' => 10 ), $block->attributes );
		// Intentionally call a second time, to ensure property was assigned.
		$this->assertSame( array( 'defaulted' => 10 ), $block->attributes );
	}

	/**
	 * @ticket 49927
	 */
	public function test_constructor_assigns_context_from_block_type() {
		$this->registry->register(
			'core/example',
			array(
				'uses_context' => array( 'requested' ),
			)
		);

		$parsed_block = array( 'blockName' => 'core/example' );
		$context      = array(
			'requested'   => 'included',
			'unrequested' => 'not included',
		);
		$block        = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( array( 'requested' => 'included' ), $block->context );
	}

	/**
	 * @ticket 49927
	 */
	public function test_constructor_maps_inner_blocks() {
		$this->registry->register( 'core/example', array() );

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertCount( 1, $block->inner_blocks );
		$this->assertInstanceOf( WP_Block::class, $block->inner_blocks[0] );
		$this->assertSame( 'core/example', $block->inner_blocks[0]->name );
	}

	/**
	 * @ticket 49927
	 */
	public function test_constructor_prepares_context_for_inner_blocks() {
		$this->registry->register(
			'core/outer',
			array(
				'attributes'       => array(
					'recordId' => array(
						'type' => 'number',
					),
				),
				'provides_context' => array(
					'core/recordId' => 'recordId',
				),
			)
		);
		$this->registry->register(
			'core/inner',
			array(
				'uses_context' => array( 'core/recordId' ),
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:outer {"recordId":10} --><!-- wp:inner /--><!-- /wp:outer -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array( 'unrequested' => 'not included' );
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertCount( 0, $block->context );
		$this->assertSame(
			array( 'core/recordId' => 10 ),
			$block->inner_blocks[0]->context
		);
	}

	/**
	 * @ticket 49927
	 */
	public function test_constructor_assigns_merged_context() {
		$this->registry->register(
			'core/example',
			array(
				'attributes'       => array(
					'value' => array(
						'type' => array( 'string', 'null' ),
					),
				),
				'provides_context' => array(
					'core/value' => 'value',
				),
				'uses_context'     => array( 'core/value' ),
			)
		);

		$parsed_blocks = parse_blocks(
			'<!-- wp:example {"value":"merged"} -->' .
			'<!-- wp:example {"value":null} -->' .
			'<!-- wp:example /-->' .
			'<!-- /wp:example -->' .
			'<!-- /wp:example -->'
		);
		$parsed_block  = $parsed_blocks[0];
		$context       = array( 'core/value' => 'original' );
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame(
			array( 'core/value' => 'original' ),
			$block->context
		);
		$this->assertSame(
			array( 'core/value' => 'merged' ),
			$block->inner_blocks[0]->context
		);
		$this->assertSame(
			array( 'core/value' => null ),
			$block->inner_blocks[0]->inner_blocks[0]->context
		);
	}

	/**
	 * @ticket 49927
	 */
	public function test_render_static_block_type_returns_own_content() {
		$this->registry->register( 'core/static', array() );
		$this->registry->register(
			'core/dynamic',
			array(
				'render_callback' => static function () {
					return 'b';
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:static -->a<!-- wp:dynamic /-->c<!-- /wp:static -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( 'abc', $block->render() );
	}

	/**
	 * @ticket 49927
	 */
	public function test_render_passes_block_for_render_callback() {
		$this->registry->register(
			'core/greeting',
			array(
				'render_callback' => static function ( $attributes, $content, $block ) {
					return sprintf( 'Hello from %s', $block->name );
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:greeting /-->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( 'Hello from core/greeting', $block->render() );
	}

	/**
	 * @ticket 49927
	 */
	public function test_render_applies_render_block_filter() {
		$this->registry->register( 'core/example', array() );

		add_filter( 'render_block', array( $this, 'filter_render_block' ), 10, 2 );

		$parsed_blocks = parse_blocks( '<!-- wp:example -->Static<!-- wp:example -->Inner<!-- /wp:example --><!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$rendered_content = $block->render();

		remove_filter( 'render_block', array( $this, 'filter_render_block' ) );

		$this->assertSame( 'Original: "StaticOriginal: "Inner", from block "core/example"", from block "core/example"', $rendered_content );
	}

	/**
	 * @ticket 46187
	 */
	public function test_render_applies_dynamic_render_block_filter() {
		$this->registry->register( 'core/example', array() );

		add_filter( 'render_block_core/example', array( $this, 'filter_render_block' ), 10, 2 );

		$parsed_blocks = parse_blocks( '<!-- wp:example -->Static<!-- wp:example -->Inner<!-- /wp:example --><!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$rendered_content = $block->render();

		remove_filter( 'render_block_core/example', array( $this, 'filter_render_block' ) );

		$this->assertSame( 'Original: "StaticOriginal: "Inner", from block "core/example"", from block "core/example"', $rendered_content );
	}

	/**
	 * Data provider for test_render_enqueues_scripts_and_styles.
	 *
	 * @return array
	 */
	public function data_provider_test_render_enqueues_scripts_and_styles(): array {
		$block_markup = '
			<!-- wp:static -->
			<div class="static">
				<!-- wp:static-child -->
				<div class="static-child">First child</div>
				<!-- /wp:static-child -->
				<!-- wp:dynamic /-->
				<!-- wp:static-child -->
				<div class="static-child">Last child</div>
				<!-- /wp:static-child -->
			</div>
			<!-- /wp:static -->
		';

		// TODO: Add case where a dynamic block renders other blocks?
		return array(
			'all_printed'                             => array(
				'set_up'                  => null,
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<div class="static-child">First child</div>
						<p class="dynamic">Hello World!</p>
						<div class="static-child">Last child</div>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'static-child-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module', 'dynamic-view-script-module' ),
			),
			'all_printed_with_extra_asset_via_filter' => array(
				'set_up'                  => static function () {
					add_filter(
						'render_block_core/dynamic',
						static function ( $content ) {
							wp_enqueue_style( 'dynamic-extra', home_url( '/dynamic-extra.css' ), array(), null );
							$processor = new WP_HTML_Tag_Processor( $content );
							if ( $processor->next_tag() ) {
								$processor->add_class( 'filtered' );
								$content = $processor->get_updated_html();
							}
							return $content;
						}
					);
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<div class="static-child">First child</div>
						<p class="dynamic filtered">Hello World!</p>
						<div class="static-child">Last child</div>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'dynamic-extra', 'static-child-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module', 'dynamic-view-script-module' ),
			),
			'dynamic_hidden_assets_omitted'           => array(
				'set_up'                  => static function () {
					add_filter( 'render_block_core/dynamic', '__return_empty_string' );
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<div class="static-child">First child</div>
						<div class="static-child">Last child</div>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'static-child-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module' ),
			),
			'dynamic_hidden_assets_included'          => array(
				'set_up'                  => static function () {
					add_filter( 'render_block_core/dynamic', '__return_empty_string' );
					add_filter(
						'enqueue_empty_block_content_assets',
						static function ( $enqueue, $block_name ) {
							if ( 'core/dynamic' === $block_name ) {
								$enqueue = true;
							}
							return $enqueue;
						},
						10,
						2
					);
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<div class="static-child">First child</div>
						<div class="static-child">Last child</div>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'static-child-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module', 'dynamic-view-script-module' ),
			),
			'static_hidden_assets_omitted'            => array(
				'set_up'                  => static function () {
					add_filter( 'render_block_core/static', '__return_empty_string' );
					add_filter(
						'render_block_core/dynamic',
						static function ( $content ) {
							wp_enqueue_style( 'dynamic-extra', home_url( '/dynamic-extra.css' ), array(), null );
							return $content;
						}
					);
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '',
				'expected_styles'         => array(),
				'expected_scripts'        => array(),
				'expected_script_modules' => array(),
			),
			'static_child_hidden_assets_omitted'      => array(
				'set_up'                  => static function () {
					add_filter( 'render_block_core/static-child', '__return_empty_string' );
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<p class="dynamic">Hello World!</p>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'dynamic-view-script-module' ),
			),
			'last_static_child_hidden_assets_omitted' => array(
				'set_up'                  => static function () {
					add_filter(
						'render_block_core/static-child',
						static function ( $content ) {
							if ( str_contains( $content, 'Last child' ) ) {
								$content = '';
							}
							return $content;
						},
						10,
						3
					);
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '
					<div class="static">
						<div class="static-child">First child</div>
						<p class="dynamic">Hello World!</p>
					</div>
				',
				'expected_styles'         => array( 'static-view-style', 'static-child-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module', 'dynamic-view-script-module' ),
			),
			'all_hidden_assets_omitted'               => array(
				'set_up'                  => static function () {
					add_filter( 'render_block', '__return_empty_string' );
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '',
				'expected_styles'         => array(),
				'expected_scripts'        => array(),
				'expected_script_modules' => array(),
			),
			'all_hidden_assets_included'              => array(
				'set_up'                  => static function () {
					add_filter( 'render_block', '__return_empty_string' );
					add_filter( 'enqueue_empty_block_content_assets', '__return_true' );
				},
				'block_markup'            => $block_markup,
				'expected_rendered_block' => '',
				'expected_styles'         => array( 'static-view-style', 'static-child-view-style', 'dynamic-view-style' ),
				'expected_scripts'        => array( 'static-view-script', 'static-child-view-script', 'dynamic-view-script' ),
				'expected_script_modules' => array( 'static-view-script-module', 'static-child-view-script-module', 'dynamic-view-script-module' ),
			),
			'admin_bar_assets_enqueued_in_block'      => array(
				'set_up'                  => static function () {
					wp_enqueue_script( 'admin-bar' );
					wp_enqueue_style( 'admin-bar' );

					add_filter(
						'render_block_core/static',
						static function ( $content ) {
							$processor = new WP_HTML_Tag_Processor( $content );
							$processor->next_tag();
							$processor->add_class( wp_script_is( 'admin-bar', 'enqueued' ) ? 'yes-admin-bar-script-enqueued' : 'not-admin-bar-script-enqueued' );
							$processor->add_class( wp_style_is( 'admin-bar', 'enqueued' ) ? 'yes-admin-bar-style-enqueued' : 'not-admin-bar-style-enqueued' );
							return $processor->get_updated_html();
						},
						10,
						3
					);
				},
				'block_markup'            => '<!-- wp:static --><div class="static"></div><!-- /wp:static -->',
				'expected_rendered_block' => '
					<div class="static yes-admin-bar-script-enqueued yes-admin-bar-style-enqueued"></div>
				',
				'expected_styles'         => array( 'static-view-style', 'admin-bar' ),
				'expected_scripts'        => array( 'static-view-script', 'admin-bar' ),
				'expected_script_modules' => array( 'static-view-script-module' ),
			),
			'enqueues_in_wp_head_block'               => array(
				'set_up'                  => static function () {
					remove_all_actions( 'wp_head' );
					remove_all_actions( 'wp_enqueue_scripts' );

					add_action( 'wp_head', 'wp_enqueue_scripts', 1 );
					add_action( 'wp_head', 'wp_print_styles', 8 );
					add_action( 'wp_head', 'wp_print_head_scripts', 9 );
					remove_action( 'wp_print_styles', 'print_emoji_styles' );

					add_action(
						'wp_enqueue_scripts',
						static function () {
							wp_enqueue_script( 'for-footer', '/footer.js', array(), null, array( 'in_footer' => true ) );
						}
					);
					add_action(
						'wp_head',
						static function () {
							wp_enqueue_style( 'for-footer', '/footer.css', array(), null );
						},
						10000
					);
				},
				'block_markup'            => '<!-- wp:wp-head /-->',
				'expected_rendered_block' => '',
				'expected_styles'         => array( 'for-footer' ),
				'expected_scripts'        => array( 'for-footer' ),
				'expected_script_modules' => array(),
			),
		);
	}

	/**
	 * @ticket 63676
	 * @covers WP_Block::render()
	 *
	 * @dataProvider data_provider_test_render_enqueues_scripts_and_styles
	 *
	 * @param Closure|null $set_up
	 * @param string       $block_markup
	 * @param string[]     $expected_styles
	 * @param string[]     $expected_scripts
	 * @param string[]     $expected_script_modules
	 */
	public function test_render_enqueues_scripts_and_styles( ?Closure $set_up, string $block_markup, string $expected_rendered_block, array $expected_styles, array $expected_scripts, array $expected_script_modules ) {
		if ( $set_up instanceof Closure ) {
			$set_up();
		}

		$this->registry->register(
			'core/wp-head',
			array(
				'render_callback' => static function () {
					return get_echo( 'wp_head' );
				},
			)
		);

		wp_register_style( 'static-view-style', home_url( '/static-view-style.css' ) );
		wp_register_script( 'static-view-script', home_url( '/static-view-script.js' ) );
		wp_register_script_module( 'static-view-script-module', home_url( '/static-view-script-module.js' ) );
		$this->registry->register(
			'core/static',
			array(
				'view_style_handles'     => array( 'static-view-style' ),
				'view_script_handles'    => array( 'static-view-script' ),
				'view_script_module_ids' => array( 'static-view-script-module' ),
			)
		);

		wp_register_style( 'static-child-view-style', home_url( '/static-child-view-style.css' ) );
		wp_register_script( 'static-child-view-script', home_url( '/static-child-view-script.js' ) );
		wp_register_script_module( 'static-child-view-script-module', home_url( '/static-child-view-script-module.js' ) );
		$this->registry->register(
			'core/static-child',
			array(
				'view_style_handles'     => array( 'static-child-view-style' ),
				'view_script_handles'    => array( 'static-child-view-script' ),
				'view_script_module_ids' => array( 'static-child-view-script-module' ),
			)
		);

		wp_register_style( 'dynamic-view-style', home_url( '/dynamic-view-style.css' ) );
		wp_register_script( 'dynamic-view-script', home_url( '/dynamic-view-script.js' ) );
		wp_register_script_module( 'dynamic-view-script-module', home_url( '/dynamic-view-script-module.js' ) );
		$this->registry->register(
			'core/dynamic',
			array(
				'render_callback'        => static function () {
					return '<p class="dynamic">Hello World!</p>';
				},
				'view_style_handles'     => array( 'dynamic-view-style' ),
				'view_script_handles'    => array( 'dynamic-view-script' ),
				'view_script_module_ids' => array( 'dynamic-view-script-module' ),
			)
		);

		// TODO: Why not use do_blocks() instead?
		$parsed_blocks  = parse_blocks( trim( $block_markup ) );
		$parsed_block   = $parsed_blocks[0];
		$context        = array();
		$block          = new WP_Block( $parsed_block, $context, $this->registry );
		$rendered_block = $block->render();

		$this->assertSameSets( $expected_styles, wp_styles()->queue, 'Enqueued styles do not meet expectations' );
		$this->assertSameSets( $expected_scripts, wp_scripts()->queue, 'Enqueued scripts do not meet expectations' );
		$this->assertSameSets( $expected_script_modules, wp_script_modules()->get_queue(), 'Enqueued script modules do not meet expectations' );

		$this->assertEqualHTML(
			$expected_rendered_block,
			$rendered_block,
			'<body>',
			"Rendered block does not contain expected HTML:\n$rendered_block"
		);
	}

	/**
	 * @ticket 49927
	 */
	public function test_passes_attributes_to_render_callback() {
		$this->registry->register(
			'core/greeting',
			array(
				'attributes'      => array(
					'toWhom'      => array(
						'type' => 'string',
					),
					'punctuation' => array(
						'type'    => 'string',
						'default' => '!',
					),
				),
				'render_callback' => static function ( $block_attributes ) {
					return sprintf(
						'Hello %s%s',
						$block_attributes['toWhom'],
						$block_attributes['punctuation']
					);
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:greeting {"toWhom":"world"} /-->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( 'Hello world!', $block->render() );
	}

	/**
	 * @ticket 49927
	 */
	public function test_passes_content_to_render_callback() {
		$this->registry->register(
			'core/outer',
			array(
				'render_callback' => static function ( $block_attributes, $content ) {
					return $content;
				},
			)
		);
		$this->registry->register(
			'core/inner',
			array(
				'render_callback' => static function () {
					return 'b';
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:outer -->a<!-- wp:inner /-->c<!-- /wp:outer -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array();
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		$this->assertSame( 'abc', $block->render() );
	}

	/**
	 * @ticket 52991
	 */
	public function test_build_query_vars_from_query_block() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType'    => 'page',
				'exclude'     => array( 1, 2 ),
				'categoryIds' => array( 56 ),
				'orderBy'     => 'title',
				'tagIds'      => array( 3, 11, 10 ),
				'parents'     => array( 1, 2 ),
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'       => 'page',
				'order'           => 'DESC',
				'orderby'         => 'title',
				'post__not_in'    => array( 1, 2 ),
				'tax_query'       => array(
					array(
						'taxonomy'         => 'category',
						'terms'            => array( 56 ),
						'include_children' => false,
					),
					array(
						'taxonomy'         => 'post_tag',
						'terms'            => array( 3, 11, 10 ),
						'include_children' => false,
					),
				),
				'post_parent__in' => array( 1, 2 ),
			),
			$query
		);
	}

	/**
	 * @ticket 62014
	 */
	public function test_build_query_vars_from_query_block_standard_post_formats() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType' => 'post',
				'format'   => array( 'standard' ),
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
				'tax_query'    => array(
					'relation' => 'OR',
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'operator' => 'NOT EXISTS',
					),
				),
			),
			$query
		);
	}

	/**
	 * @ticket 62014
	 */
	public function test_build_query_vars_from_query_block_post_format() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType' => 'post',
				'format'   => array( 'aside' ),
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
				'tax_query'    => array(
					'relation' => 'OR',
					array(
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => array( 'post-format-aside' ),
						'operator' => 'IN',
					),
				),
			),
			$query
		);
	}
	/**
	 * @ticket 62014
	 */
	public function test_build_query_vars_from_query_block_post_formats_with_category() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType'    => 'post',
				'format'      => array( 'standard' ),
				'categoryIds' => array( 56 ),
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
				'tax_query'    => array(
					'relation' => 'AND',
					array(
						array(
							'taxonomy'         => 'category',
							'terms'            => array( 56 ),
							'include_children' => false,
						),
					),
					array(
						'relation' => 'OR',
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'operator' => 'NOT EXISTS',
						),
					),
				),
			),
			$query
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_build_query_vars_from_query_block_no_context() {
		$this->registry->register( 'core/example', array() );

		$parsed_blocks    = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block     = $parsed_blocks[0];
		$block_no_context = new WP_Block( $parsed_block, array(), $this->registry );
		$query            = build_query_vars_from_query_block( $block_no_context, 1 );

		$this->assertSame(
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
				'tax_query'    => array(),
			),
			$query
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_build_query_vars_from_query_block_first_page() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'perPage' => 2,
				'offset'  => 0,
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'tax_query'      => array(),
				'offset'         => 0,
				'posts_per_page' => 2,
			),
			$query
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_build_query_vars_from_query_block_page_no_offset() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'perPage' => 5,
				'offset'  => 0,
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 3 );
		$this->assertSame(
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'tax_query'      => array(),
				'offset'         => 10,
				'posts_per_page' => 5,
			),
			$query
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_build_query_vars_from_query_block_page_with_offset() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'perPage' => 5,
				'offset'  => 2,
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 3 );
		$this->assertSame(
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'tax_query'      => array(),
				'offset'         => 12,
				'posts_per_page' => 5,
			),
			$query
		);
	}

	/**
	 * @ticket 62901
	 */
	public function test_build_query_vars_from_query_block_with_top_level_parent() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType' => 'page',
				'parents'  => array( 0 ),
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query         = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'       => 'page',
				'order'           => 'DESC',
				'orderby'         => 'date',
				'post__not_in'    => array(),
				'tax_query'       => array(),
				'post_parent__in' => array( 0 ),
			),
			$query
		);
	}

	/**
	 * Ensure requesting only sticky posts returns only sticky posts.
	 *
	 * @ticket 62908
	 */
	public function test_build_query_vars_from_block_query_only_sticky_posts() {
		$this->factory()->post->create_many( 5 );
		$sticky_post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Sticky Post',
			)
		);
		stick_post( $sticky_post_id );

		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'sticky' => 'only',
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query_args    = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'           => 'post',
				'order'               => 'DESC',
				'orderby'             => 'date',
				'post__not_in'        => array(),
				'tax_query'           => array(),
				'post__in'            => array( $sticky_post_id ),
				'ignore_sticky_posts' => 1,
			),
			$query_args
		);

		$query = new WP_Query( $query_args );
		$this->assertSame( array( $sticky_post_id ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Ensure excluding sticky posts returns only non-sticky posts.
	 *
	 * @ticket 62908
	 */
	public function test_build_query_vars_from_block_query_exclude_sticky_posts() {
		$not_sticky_post_ids = $this->factory()->post->create_many( 5 );
		$sticky_post_id      = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Sticky Post',
			)
		);
		stick_post( $sticky_post_id );

		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'sticky' => 'exclude',
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query_args    = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
				'tax_query'    => array(),
				'post__not_in' => array( $sticky_post_id ),
			),
			$query_args
		);

		$query = new WP_Query( $query_args );
		$this->assertNotContains( $sticky_post_id, wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertSameSets( $not_sticky_post_ids, wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Ensure ignoring sticky posts includes both sticky and non-sticky posts.
	 *
	 * @ticket 62908
	 */
	public function test_build_query_vars_from_block_query_ignore_sticky_posts() {
		$not_sticky_post_ids = $this->factory()->post->create_many( 5 );
		$sticky_post_id      = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Sticky Post',
			)
		);
		stick_post( $sticky_post_id );

		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'sticky' => 'ignore',
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );
		$query_args    = build_query_vars_from_query_block( $block, 1 );

		$this->assertSame(
			array(
				'post_type'           => 'post',
				'order'               => 'DESC',
				'orderby'             => 'date',
				'post__not_in'        => array(),
				'tax_query'           => array(),
				'ignore_sticky_posts' => 1,
			),
			$query_args
		);

		$query = new WP_Query( $query_args );
		$this->assertSameSets( array_merge( $not_sticky_post_ids, array( $sticky_post_id ) ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_query_loop_block_query_vars_filter() {
		$this->registry->register(
			'core/example',
			array( 'uses_context' => array( 'query' ) )
		);

		$parsed_blocks = parse_blocks( '<!-- wp:example {"ok":true} -->a<!-- wp:example /-->b<!-- /wp:example -->' );
		$parsed_block  = $parsed_blocks[0];
		$context       = array(
			'query' => array(
				'postType' => 'page',
				'orderBy'  => 'title',
			),
		);
		$block         = new WP_Block( $parsed_block, $context, $this->registry );

		add_filter(
			'query_loop_block_query_vars',
			static function ( $query, $block, $page ) {
				$query['post_type'] = 'book';
				return $query;
			},
			10,
			3
		);

		$query = build_query_vars_from_query_block( $block, 1 );
		$this->assertSame(
			array(
				'post_type'    => 'book',
				'order'        => 'DESC',
				'orderby'      => 'title',
				'post__not_in' => array(),
				'tax_query'    => array(),
			),
			$query
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_block_has_support() {
		$this->registry->register(
			'core/example',
			array(
				'supports' => array(
					'align'    => array( 'wide', 'full' ),
					'fontSize' => true,
					'color'    => array(
						'link'     => true,
						'gradient' => false,
					),
				),
			)
		);
		$block_type    = $this->registry->get_registered( 'core/example' );
		$align_support = block_has_support( $block_type, array( 'align' ) );
		$this->assertTrue( $align_support );
		$gradient_support = block_has_support( $block_type, array( 'color', 'gradient' ) );
		$this->assertFalse( $gradient_support );
		$link_support = block_has_support( $block_type, array( 'color', 'link' ), false );
		$this->assertTrue( $link_support );
		$text_support = block_has_support( $block_type, array( 'color', 'text' ) );
		$this->assertFalse( $text_support );
		$font_nested = block_has_support( $block_type, array( 'fontSize', 'nested' ) );
		$this->assertFalse( $font_nested );
	}

	/**
	 * @ticket 52991
	 */
	public function test_block_has_support_no_supports() {
		$this->registry->register( 'core/example', array() );
		$block_type  = $this->registry->get_registered( 'core/example' );
		$has_support = block_has_support( $block_type, array( 'color' ) );
		$this->assertFalse( $has_support );
	}

	/**
	 * @ticket 52991
	 */
	public function test_block_has_support_provided_defaults() {
		$this->registry->register(
			'core/example',
			array(
				'supports' => array(
					'color' => array(
						'gradient' => false,
					),
				),
			)
		);
		$block_type    = $this->registry->get_registered( 'core/example' );
		$align_support = block_has_support( $block_type, array( 'align' ), true );
		$this->assertTrue( $align_support );
		$gradient_support = block_has_support( $block_type, array( 'color', 'gradient' ), true );
		$this->assertFalse( $gradient_support );
	}

	/**
	 * @ticket 58532
	 *
	 * @dataProvider data_block_has_support_string
	 *
	 * @param array  $block_data Block data.
	 * @param string $support    Support string to check.
	 * @param bool   $expected   Expected result.
	 */
	public function test_block_has_support_string( $block_data, $support, $expected, $message ) {
		$this->registry->register( 'core/example', $block_data );
		$block_type  = $this->registry->get_registered( 'core/example' );
		$has_support = block_has_support( $block_type, $support );
		$this->assertSame( $expected, $has_support, $message );
	}

	/**
	 * Data provider for test_block_has_support_string
	 */
	public function data_block_has_support_string() {
		return array(
			array(
				array(),
				'color',
				false,
				'Block with empty support array.',
			),
			array(
				array(
					'supports' => array(
						'align'    => array( 'wide', 'full' ),
						'fontSize' => true,
						'color'    => array(
							'link'     => true,
							'gradient' => false,
						),
					),
				),
				'align',
				true,
				'Feature present in support array.',
			),
			array(
				array(
					'supports' => array(
						'align'    => array( 'wide', 'full' ),
						'fontSize' => true,
						'color'    => array(
							'link'     => true,
							'gradient' => false,
						),
					),
				),
				'anchor',
				false,
				'Feature not present in support array.',
			),
			array(
				array(
					'supports' => array(
						'align'    => array( 'wide', 'full' ),
						'fontSize' => true,
						'color'    => array(
							'link'     => true,
							'gradient' => false,
						),
					),
				),
				array( 'align' ),
				true,
				'Feature present in support array, single element array.',
			),
		);
	}

	/**
	 * @ticket 51612
	 */
	public function test_block_filters_for_inner_blocks() {
		$pre_render_callback           = new MockAction();
		$render_block_data_callback    = new MockAction();
		$render_block_context_callback = new MockAction();

		$this->registry->register(
			'core/outer',
			array(
				'render_callback' => static function ( $block_attributes, $content ) {
					return $content;
				},
			)
		);

		$this->registry->register(
			'core/inner',
			array(
				'render_callback' => static function () {
					return 'b';
				},
			)
		);

		$parsed_blocks = parse_blocks( '<!-- wp:outer -->a<!-- wp:inner /-->c<!-- /wp:outer -->' );
		$parsed_block  = $parsed_blocks[0];

		add_filter( 'pre_render_block', array( $pre_render_callback, 'filter' ) );
		add_filter( 'render_block_data', array( $render_block_data_callback, 'filter' ) );
		add_filter( 'render_block_context', array( $render_block_context_callback, 'filter' ) );

		render_block( $parsed_block );

		$this->assertSame( 2, $pre_render_callback->get_call_count() );
		$this->assertSame( 2, $render_block_data_callback->get_call_count() );
		$this->assertSame( 2, $render_block_context_callback->get_call_count() );
	}
}
