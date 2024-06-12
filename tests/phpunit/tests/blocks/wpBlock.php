<?php
/**
 * WP_Block tests
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 */

/**
 * Tests for WP_Block.
 *
 * @since 5.5.0
 *
 * @group blocks
 */
class Tests_Blocks_wpBlock extends WP_UnitTestCase {

	/**
	 * Fake block type registry.
	 *
	 * @var WP_Block_Type_Registry
	 */
	private $registry = null;

	/**
	 * Set up each test method.
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Type_Registry();
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		$this->registry = null;

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
				'render_callback' => static function() {
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
				'render_callback' => static function( $attributes, $content, $block ) {
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
				'render_callback' => static function( $block_attributes ) {
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
				'render_callback' => static function( $block_attributes, $content ) {
					return $content;
				},
			)
		);
		$this->registry->register(
			'core/inner',
			array(
				'render_callback' => static function() {
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
			$query,
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
			)
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
			$query,
			array(
				'post_type'    => 'post',
				'order'        => 'DESC',
				'orderby'      => 'date',
				'post__not_in' => array(),
			)
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
			$query,
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'offset'         => 0,
				'posts_per_page' => 2,
			)
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
			$query,
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'offset'         => 10,
				'posts_per_page' => 5,
			)
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
			$query,
			array(
				'post_type'      => 'post',
				'order'          => 'DESC',
				'orderby'        => 'date',
				'post__not_in'   => array(),
				'offset'         => 12,
				'posts_per_page' => 5,
			)
		);
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
		function filterQuery( $query, $block, $page ) {
			$query['post_type'] = 'book';
			return $query;
		}
		add_filter( 'query_loop_block_query_vars', 'filterQuery', 10, 3 );
		$query = build_query_vars_from_query_block( $block, 1 );
		remove_filter( 'query_loop_block_query_vars', 'filterQuery' );
		$this->assertSame(
			$query,
			array(
				'post_type'    => 'book',
				'order'        => 'DESC',
				'orderby'      => 'title',
				'post__not_in' => array(),
			)
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
	 * @ticket 51612
	 */
	public function test_block_filters_for_inner_blocks() {
		$pre_render_callback           = new MockAction();
		$render_block_data_callback    = new MockAction();
		$render_block_context_callback = new MockAction();

		$this->registry->register(
			'core/outer',
			array(
				'render_callback' => function( $block_attributes, $content ) {
					return $content;
				},
			)
		);

		$this->registry->register(
			'core/inner',
			array(
				'render_callback' => function() {
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
