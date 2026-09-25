<?php
/**
 * Tests for Block Bindings integration with block rendering.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class WP_Block_Bindings_Render extends WP_UnitTestCase {

	const SOURCE_NAME  = 'test/source';
	const SOURCE_LABEL = 'Test source';

	/**
	 * Sets up shared fixtures.
	 *
	 * @since 6.9.0
	 */
	public static function wpSetUpBeforeClass() {
		register_block_type(
			'test/block',
			array(
				'attributes'      => array(
					'myAttribute' => array(
						'type' => 'string',
					),
				),
				'render_callback' => function ( $attributes ) {
					if ( isset( $attributes['myAttribute'] ) ) {
						return '<p>' . esc_html( $attributes['myAttribute'] ) . '</p>';
					}
				},
			)
		);
	}

	/**
	 * Sets up the test fixture.
	 *
	 * @since 6.9.0
	 */
	public function set_up() {
		parent::set_up();

		add_filter(
			'block_bindings_supported_attributes_test/block',
			function ( $supported_attributes ) {
				$supported_attributes[] = 'myAttribute';
				return $supported_attributes;
			}
		);
	}

	/**
	 * Tear down after each test.
	 *
	 * @since 6.5.0
	 */
	public function tear_down() {
		foreach ( get_all_registered_block_bindings_sources() as $source_name => $source_properties ) {
			if ( str_starts_with( $source_name, 'test/' ) ) {
				unregister_block_bindings_source( $source_name );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tear down after class.
	 *
	 * @since 6.9.0
	 */
	public static function wpTearDownAfterClass() {
		unregister_block_type( 'test/block' );
	}

	public function data_update_block_with_value_from_source() {
		return array(
			'paragraph block' => array(
				'content',
				<<<HTML
<!-- wp:paragraph -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML
				,
				'<p class="wp-block-paragraph">test source value</p>',
			),
			'button block'    => array(
				'text',
				<<<HTML
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">This should not appear</a></div>
<!-- /wp:button -->
HTML
				,
				'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">test source value</a></div>',
			),
			'image block'     => array(
				'caption',
				<<<HTML
<!-- wp:image {"id":66,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="breakfast.jpg" alt="" class="wp-image-1"/><figcaption class="wp-element-caption">Breakfast at a <em>café</em> in Wrocław.</figcaption></figure>
<!-- /wp:image -->
HTML
			,
				'<figure class="wp-block-image size-large"><img src="breakfast.jpg" alt="" class="wp-image-1"/><figcaption class="wp-element-caption">test source value</figcaption></figure>',
			),
			'test block'      => array(
				'myAttribute',
				<<<HTML
<!-- wp:test/block -->
<p>This should not appear</p>
<!-- /wp:test/block -->
HTML
				,
				'<p>test source value</p>',
			),
			'list item block' => array(
				'content',
				<<<HTML
<!-- wp:list-item -->
<li>This should not appear</li>
<!-- /wp:list-item -->
HTML
				,
				'<li>test source value</li>',
			),
		);
	}

	/**
	 * Test if the block content is updated with the value returned by the source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 *
	 * @dataProvider data_update_block_with_value_from_source
	 */
	public function test_update_block_with_value_from_source( $bound_attribute, $block_content, $expected_result ) {
		$get_value_callback = function () {
			return 'test source value';
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$parsed_blocks = parse_blocks( $block_content );

		$parsed_blocks[0]['attrs']['metadata'] = array(
			'bindings' => array(
				$bound_attribute => array(
					'source' => self::SOURCE_NAME,
				),
			),
		);

		$block  = new WP_Block( $parsed_blocks[0] );
		$result = $block->render();

		$this->assertSame(
			'test source value',
			$block->attributes[ $bound_attribute ],
			"The '{$bound_attribute}' attribute should be updated with the value returned by the source."
		);
		$this->assertSame(
			$expected_result,
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	public function data_different_get_value_callbacks() {
		return array(
			'pass arguments to source'        => array(
				function ( $source_args, $block_instance, $attribute_name ) {
					$value = $source_args['key'];
					return "The attribute name is '$attribute_name' and its binding has argument 'key' with value '$value'.";
				},
				"<p class=\"wp-block-paragraph\">The attribute name is 'content' and its binding has argument 'key' with value 'test'.</p>",
			),
			'unsafe HTML should be sanitized' => array(
				function () {
					return '<script>alert("Unsafe HTML")</script>';
				},
				'<p class="wp-block-paragraph">alert("Unsafe HTML")</p>',
			),
			'symbols and numbers should be rendered correctly' => array(
				function () {
					return '$12.50';
				},
				'<p class="wp-block-paragraph">$12.50</p>',
			),
		);
	}

	/**
	 * Test passing arguments to the source.
	 *
	 * @ticket 60282
	 * @ticket 60651
	 * @ticket 61385
	 * @ticket 63840
	 *
	 * @covers ::register_block_bindings_source
	 *
	 * @dataProvider data_different_get_value_callbacks
	 */
	public function test_different_get_value_callbacks( $get_value_callback, $expected ) {
		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source", "args": {"key": "test"}}}}} -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertSame(
			$expected,
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Tests passing `uses_context` as argument to the source.
	 *
	 * @ticket 60525
	 * @ticket 61642
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_passing_uses_context_to_source() {
		$get_value_callback = function ( $source_args, $block_instance ) {
			$this->assertArrayNotHasKey(
				'forbiddenSourceContext',
				$block_instance->context,
				"Only context that was made available through the source's uses_context property should be accessible."
			);
			$value = $block_instance->context['sourceContext'];
			return "Value: $value";
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
				'uses_context'       => array( 'sourceContext' ),
			)
		);

		$block_content = <<<HTML
<!-- wp:test/block {"metadata":{"bindings":{"myAttribute":{"source":"test/source", "args": {"key": "test"}}}}} -->
<p>This should not appear</p>
<!-- /wp:test/block -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block(
			$parsed_blocks[0],
			array(
				'sourceContext'          => 'source context value',
				'forbiddenSourceContext' => 'forbidden donut',
			)
		);
		$result        = $block->render();

		$this->assertSame(
			'Value: source context value',
			$block->attributes['myAttribute'],
			"The 'myAttribute' should be updated with the value of the source context."
		);
		$this->assertSame(
			'<p>Value: source context value</p>',
			trim( $result ),
			'The block content should be updated with the value of the source context.'
		);
	}

	/**
	 * Tests if the block content is updated with the value returned by the source
	 * for the Image block in the placeholder state.
	 *
	 * Furthermore tests if the caption attribute is correctly processed.
	 *
	 * @ticket 60282
	 * @ticket 64031
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_update_block_with_value_from_source_image_placeholder() {
		$get_value_callback = function ( $source_args, $block_instance, $attribute_name ) {
			if ( 'url' === $attribute_name ) {
				return 'https://example.com/image.jpg';
			}
			if ( 'caption' === $attribute_name ) {
				return 'Example Image';
			}
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:image {"metadata":{"bindings":{"url":{"source":"test/source"},"caption":{"source":"test/source"}}}} -->
<figure class="wp-block-image"><img alt=""/><figcaption class="wp-element-caption"></figcaption></figure>
<!-- /wp:image -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertSame(
			'https://example.com/image.jpg',
			$block->attributes['url'],
			"The 'url' attribute should be updated with the value returned by the source."
		);
		$this->assertSame(
			'Example Image',
			$block->attributes['caption'],
			"The 'caption' attribute should be updated with the value returned by the source."
		);
		$this->assertSame(
			'<figure class="wp-block-image"><img src="https://example.com/image.jpg" alt=""/><figcaption class="wp-element-caption">Example Image</figcaption></figure>',
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Tests if the `__default` attribute is replaced with real attributes for
	 * pattern overrides.
	 *
	 * @ticket 61333
	 * @ticket 62069
	 *
	 * @covers WP_Block::process_block_bindings
	 */
	public function test_default_binding_for_pattern_overrides() {
		$block_content = <<<HTML
<!-- wp:test/block {"metadata":{"bindings":{"__default":{"source":"core/pattern-overrides"}},"name":"Test"}} -->
<p>This should not appear</p>
<!-- /wp:test/block -->
HTML;

		$expected_content = 'This is the content value';
		$parsed_blocks    = parse_blocks( $block_content );
		$block            = new WP_Block( $parsed_blocks[0], array( 'pattern/overrides' => array( 'Test' => array( 'myAttribute' => $expected_content ) ) ) );

		$result = $block->render();

		$this->assertSame(
			"<p>$expected_content</p>",
			trim( $result ),
			'The `__default` attribute should be replaced with the real attribute prior to the callback.'
		);

		$expected_bindings_metadata = array(
			'myAttribute' => array( 'source' => 'core/pattern-overrides' ),
		);
		$this->assertSame(
			$expected_bindings_metadata,
			$block->attributes['metadata']['bindings'],
			'The __default binding should be updated with the individual binding attributes in the block metadata.'
		);
	}

	/**
	 * Tests that filter `block_bindings_source_value` is applied.
	 *
	 * @ticket 61181
	 */
	public function test_filter_block_bindings_source_value() {
		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => function () {
					return '';
				},
			)
		);

		$filter_value = function ( $value, $source_name, $source_args, $block_instance, $attribute_name ) {
			if ( self::SOURCE_NAME !== $source_name ) {
				return $value;
			}
			return "Filtered value: {$source_args['test_key']}. Block instance: {$block_instance->name}. Attribute name: {$attribute_name}.";
		};

		add_filter( 'block_bindings_source_value', $filter_value, 10, 5 );

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source", "args":{"test_key":"test_arg"}}}}} -->
<p>Default content</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		remove_filter( 'block_bindings_source_value', $filter_value );

		$this->assertSame(
			'<p class="wp-block-paragraph">Filtered value: test_arg. Block instance: core/paragraph. Attribute name: content.</p>',
			trim( $result ),
			'The block content should show the filtered value.'
		);
	}

	/**
	 * Provides fuzz-style nested list fixtures for rich text binding tests.
	 *
	 * The fixtures vary whether fallback rich text exists before the first inner
	 * block, whether that fallback contains raw markup or multibyte text, whether
	 * nested lists are ordered, and whether siblings surround the bound item.
	 *
	 * @return array[]
	 */
	public function data_rich_text_binding_preserves_nested_inner_blocks() {
		$child_list = self::build_list_block(
			array(
				self::build_list_item_block( 'Nested child' ),
			)
		);

		$deep_child_list = self::build_list_block(
			array(
				self::build_list_item_block(
					'Nested parent' . self::build_list_block(
						array(
							self::build_list_item_block( 'Nested grandchild' ),
						)
					)
				),
			)
		);

		$ordered_child_list = self::build_list_block(
			array(
				self::build_list_item_block( 'Ordered child' ),
				self::build_list_item_block( 'Second ordered child' ),
			),
			array(
				'ordered' => true,
				'start'   => 3,
			)
		);

		return array(
			'nested list after fallback text'          => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( 'Default content' . $child_list, true ),
					)
				),
				'bound_value'             => 'Bound list item',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound list item
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
</li>
</ul>
HTML
				,
				'removed_strings'         => array( 'Default content' ),
				'preserved_strings'       => array( 'Nested child' ),
			),
			'raw markup before nested list'            => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( 'Default content<ul><li>Raw markup to replace</li></ul>' . $child_list, true ),
					)
				),
				'bound_value'             => 'Bound list item',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound list item
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
</li>
</ul>
HTML
				,
				'removed_strings'         => array( 'Default content', 'Raw markup to replace' ),
				'preserved_strings'       => array( 'Nested child' ),
			),
			'inner block starts at rich text boundary' => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( $child_list, true ),
					)
				),
				'bound_value'             => 'Bound list item',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound list item
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
</li>
</ul>
HTML
				,
				'removed_strings'         => array(),
				'preserved_strings'       => array( 'Nested child' ),
			),
			'multibyte fallback before nested list'    => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( 'Café fallback before <strong>nested</strong> list' . $child_list, true ),
					)
				),
				'bound_value'             => 'Bound <em>línea</em>',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound <em>línea</em>
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
</li>
</ul>
HTML
				,
				'removed_strings'         => array( 'Café fallback', '<strong>nested</strong>' ),
				'preserved_strings'       => array( 'Nested child', 'Bound <em>línea</em>' ),
			),
			'deep nested list with sibling item'       => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( 'Default parent' . $deep_child_list, true ),
						self::build_list_item_block( 'Sibling stays' ),
					)
				),
				'bound_value'             => 'Bound parent',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound parent
<ul class="wp-block-list">
<li>Nested parent
<ul class="wp-block-list">
<li>Nested grandchild</li>
</ul>
</li>
</ul>
</li>

<li>Sibling stays</li>
</ul>
HTML
				,
				'removed_strings'         => array( 'Default parent' ),
				'preserved_strings'       => array( 'Nested parent', 'Nested grandchild', 'Sibling stays' ),
			),
			'ordered nested list with attributes'      => array(
				'block_content'           => self::build_list_block(
					array(
						self::build_list_item_block( '<span>Default ordered parent</span>' . $ordered_child_list, true ),
					)
				),
				'bound_value'             => 'Bound ordered parent',
				'expected_rendered_block' => <<<HTML
<ul class="wp-block-list">
<li>Bound ordered parent
<ol class="wp-block-list" start="3">
<li>Ordered child</li>

<li>Second ordered child</li>
</ol>
</li>
</ul>
HTML
				,
				'removed_strings'         => array( 'Default ordered parent' ),
				'preserved_strings'       => array( 'Ordered child', 'Second ordered child', 'start="3"' ),
			),
		);
	}

	/**
	 * Tests that binding a List Item block's rich text preserves nested List
	 * inner blocks rendered inside the same `<li>` element.
	 *
	 * @ticket 65406
	 *
	 * @covers WP_Block::render
	 *
	 * @dataProvider data_rich_text_binding_preserves_nested_inner_blocks
	 */
	public function test_rich_text_binding_preserves_nested_inner_blocks( $block_content, $bound_value, $expected_rendered_block, $removed_strings, $preserved_strings ) {
		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => static function () use ( $bound_value ) {
					return $bound_value;
				},
			)
		);

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		foreach ( $removed_strings as $removed_string ) {
			$this->assertStringNotContainsString(
				$removed_string,
				$result,
				"Fallback content '{$removed_string}' should be replaced by the source value."
			);
		}

		foreach ( $preserved_strings as $preserved_string ) {
			$this->assertStringContainsString(
				$preserved_string,
				$result,
				"Nested inner block content '{$preserved_string}' should be preserved."
			);
		}

		$this->assertEqualHTML(
			$expected_rendered_block,
			trim( $result ),
			'<body>',
			'The bound list item rich text should be replaced without dropping nested inner blocks.'
		);
		$this->assertSame(
			$bound_value,
			$block->inner_blocks[0]->attributes['content'],
			'The bound list item content attribute should be updated with the source value.'
		);
	}

	/**
	 * Tests that inner-block preservation is block-agnostic.
	 *
	 * The replacement logic has no block-specific handling: it relies only on
	 * where inner blocks render. This registers an arbitrary block whose bound
	 * rich text and an inner block share the same element, and confirms the inner
	 * block is preserved exactly as it is for `core/list-item`.
	 *
	 * @ticket 65406
	 *
	 * @covers WP_Block::render
	 */
	public function test_rich_text_binding_preserves_inner_blocks_for_any_block() {
		register_block_type(
			'test/rich-text-with-inner-blocks',
			array(
				'attributes' => array(
					'content' => array(
						'type'     => 'rich-text',
						'source'   => 'rich-text',
						'selector' => 'div',
					),
				),
			)
		);

		$supported_attributes_filter = static function ( $supported_attributes, $block_type ) {
			if ( 'test/rich-text-with-inner-blocks' === $block_type ) {
				$supported_attributes[] = 'content';
			}
			return $supported_attributes;
		};

		add_filter(
			'block_bindings_supported_attributes',
			$supported_attributes_filter,
			10,
			2
		);

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => static function () {
					return 'Bound value';
				},
			)
		);

		$block_content = <<<HTML
<!-- wp:test/rich-text-with-inner-blocks {"metadata":{"bindings":{"content":{"source":"test/source"}}}} -->
<div><!-- wp:paragraph -->
<p>Inner paragraph stays</p>
<!-- /wp:paragraph --></div>
<!-- /wp:test/rich-text-with-inner-blocks -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		remove_filter( 'block_bindings_supported_attributes', $supported_attributes_filter, 10 );
		unregister_block_type( 'test/rich-text-with-inner-blocks' );

		$expected_rendered_block = <<<HTML
<div>Bound value
<p class="wp-block-paragraph">Inner paragraph stays</p>
</div>
HTML;

		$this->assertEqualHTML(
			$expected_rendered_block,
			trim( $result ),
			'<body>',
			'The inner block should be preserved for any block, not just core/list-item.'
		);
	}

	/**
	 * Tests that a pattern overrides `__default` binding preserves nested List
	 * inner blocks.
	 *
	 * Pattern overrides expand the `__default` binding into computed attributes
	 * that include the rewritten `metadata` attribute alongside `content`. The
	 * `metadata` attribute has no HTML source, so its no-op replacement must not
	 * invalidate the inner-block offsets used to preserve the nested list when
	 * `content` is replaced afterwards.
	 *
	 * @ticket 65406
	 *
	 * @covers WP_Block::render
	 */
	public function test_pattern_overrides_binding_preserves_nested_inner_blocks() {
		$block_content = <<<HTML
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item {"metadata":{"bindings":{"__default":{"source":"core/pattern-overrides"}},"name":"Editable List Item"}} -->
<li>Default content<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Nested child</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block(
			$parsed_blocks[0],
			array(
				'pattern/overrides' => array(
					'Editable List Item' => array( 'content' => 'Pattern <em>override</em>' ),
				),
			)
		);
		$result        = $block->render();

		$expected_rendered_block = <<<HTML
<ul class="wp-block-list">
<li>Pattern <em>override</em>
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
</li>
</ul>
HTML;

		$this->assertEqualHTML(
			$expected_rendered_block,
			trim( $result ),
			'<body>',
			'The pattern override should replace the list item rich text without dropping the nested list.'
		);
		$this->assertSame(
			'Pattern <em>override</em>',
			$block->inner_blocks[0]->attributes['content'],
			'The list item content attribute should be updated with the pattern override value.'
		);
	}

	/**
	 * Tests that binding degrades safely when rich text does not precede the
	 * inner block.
	 *
	 * The replacement assumes a block's own rich text comes before its inner
	 * blocks, which holds for a normally authored List Item. When markup is
	 * authored with the nested list first, the replacement stops at that inner
	 * block: the bound value is written ahead of it and the trailing rich text
	 * is left in place. The result is an incomplete replacement, never broken
	 * structure, and the nested inner block is still preserved.
	 *
	 * @ticket 65406
	 *
	 * @covers WP_Block::render
	 */
	public function test_rich_text_binding_with_inner_block_before_text() {
		$block_content = <<<HTML
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item {"metadata":{"bindings":{"content":{"source":"test/source"}}}} -->
<li><!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Nested child</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->trailing text</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML;

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => static function () {
					return 'Bound value';
				},
			)
		);

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$expected_rendered_block = <<<HTML
<ul class="wp-block-list">
<li>Bound value
<ul class="wp-block-list">
<li>Nested child</li>
</ul>
trailing text</li>
</ul>
HTML;

		$this->assertEqualHTML(
			$expected_rendered_block,
			trim( $result ),
			'<body>',
			'The bound value should be written before the inner block while preserving the nested list and the trailing rich text.'
		);
		$this->assertStringContainsString(
			'Nested child',
			$result,
			'The nested list inner block should be preserved.'
		);
		$this->assertStringContainsString(
			'trailing text',
			$result,
			'Rich text after the inner block should be left untouched.'
		);
	}

	/**
	 * Builds List block markup.
	 *
	 * @param string[] $items      Serialized List Item blocks.
	 * @param array    $attributes Optional List block attributes.
	 * @return string Serialized List block markup.
	 */
	private static function build_list_block( $items, $attributes = array() ) {
		$is_ordered       = ! empty( $attributes['ordered'] );
		$tag_name         = $is_ordered ? 'ol' : 'ul';
		$block_attributes = $attributes ? ' ' . wp_json_encode( $attributes ) : '';
		$html_attributes  = ' class="wp-block-list"';

		if ( isset( $attributes['start'] ) ) {
			$html_attributes .= ' start="' . (int) $attributes['start'] . '"';
		}

		return sprintf(
			"<!-- wp:list%s -->\n<%s%s>%s</%s>\n<!-- /wp:list -->",
			$block_attributes,
			$tag_name,
			$html_attributes,
			implode( '', $items ),
			$tag_name
		);
	}

	/**
	 * Builds List Item block markup.
	 *
	 * @param string $content  List item inner HTML.
	 * @param bool   $is_bound Optional. Whether to bind the content attribute.
	 * @return string Serialized List Item block markup.
	 */
	private static function build_list_item_block( $content, $is_bound = false ) {
		$block_attributes = $is_bound ? ' {"metadata":{"bindings":{"content":{"source":"test/source"}}}}' : '';

		return sprintf(
			"<!-- wp:list-item%s -->\n<li>%s</li>\n<!-- /wp:list-item -->",
			$block_attributes,
			$content
		);
	}
}
