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
	const SOURCE_LABEL = array(
		'label' => 'Test source',
	);

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
	 * Test if the block content is updated with the value returned by the source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_update_block_with_value_from_source() {
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

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source"}}}} -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertSame(
			'test source value',
			$block->attributes['content'],
			"The 'content' attribute should be updated with the value returned by the source."
		);
		$this->assertSame(
			'<p>test source value</p>',
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Test passing arguments to the source.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_passing_arguments_to_source() {
		$get_value_callback = function ( $source_args, $block_instance, $attribute_name ) {
			$value = $source_args['key'];
			return "The attribute name is '$attribute_name' and its binding has argument 'key' with value '$value'.";
		};

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
			"The attribute name is 'content' and its binding has argument 'key' with value 'test'.",
			$block->attributes['content'],
			"The 'content' attribute should be updated with the value returned by the source."
		);
		$this->assertSame(
			"<p>The attribute name is 'content' and its binding has argument 'key' with value 'test'.</p>",
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Tests passing `uses_context` as argument to the source.
	 *
	 * @ticket 60525
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_passing_uses_context_to_source() {
		$get_value_callback = function ( $source_args, $block_instance, $attribute_name ) {
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
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source", "args": {"key": "test"}}}}} -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0], array( 'sourceContext' => 'source context value' ) );
		$result        = $block->render();

		$this->assertSame(
			'Value: source context value',
			$block->attributes['content'],
			"The 'content' should be updated with the value of the source context."
		);
		$this->assertSame(
			'<p>Value: source context value</p>',
			trim( $result ),
			'The block content should be updated with the value of the source context.'
		);
	}

	/**
	 * Tests that blocks can only access the context from the specific source.
	 *
	 * @ticket 61642
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_blocks_can_just_access_the_specific_uses_context() {
		register_block_bindings_source(
			'test/source-one',
			array(
				'label'              => 'Test Source One',
				'get_value_callback' => function () {
					return;
				},
				'uses_context'       => array( 'contextOne' ),
			)
		);

		register_block_bindings_source(
			'test/source-two',
			array(
				'label'              => 'Test Source Two',
				'get_value_callback' => function ( $source_args, $block_instance, $attribute_name ) {
					$value = $block_instance->context['contextTwo'];
					// Try to use the context from source one, which shouldn't be available.
					if ( ! empty( $block_instance->context['contextOne'] ) ) {
						$value = $block_instance->context['contextOne'];
					}
					return "Value: $value";
				},
				'uses_context'       => array( 'contextTwo' ),
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source-two", "args": {"key": "test"}}}}} -->
<p>Default content</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block(
			$parsed_blocks[0],
			array(
				'contextOne' => 'source one context value',
				'contextTwo' => 'source two context value',
			)
		);
		$result        = $block->render();

		$this->assertSame(
			'Value: source two context value',
			$block->attributes['content'],
			"The 'content' should be updated with the value of the second source context value."
		);
		$this->assertSame(
			'<p>Value: source two context value</p>',
			trim( $result ),
			'The block content should be updated with the value of the source context.'
		);
	}

	/**
	 * Tests if the block content is updated with the value returned by the source
	 * for the Image block in the placeholder state.
	 *
	 * @ticket 60282
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_update_block_with_value_from_source_image_placeholder() {
		$get_value_callback = function () {
			return 'https://example.com/image.jpg';
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:image {"metadata":{"bindings":{"url":{"source":"test/source"}}}} -->
<figure class="wp-block-image"><img alt=""/></figure>
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
			'<figure class="wp-block-image"><img src="https://example.com/image.jpg" alt=""/></figure>',
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Tests if the block content is sanitized when unsafe HTML is passed.
	 *
	 * @ticket 60651
	 *
	 * @covers ::register_block_bindings_source
	 */
	public function test_source_value_with_unsafe_html_is_sanitized() {
		$get_value_callback = function () {
			return '<script>alert("Unsafe HTML")</script>';
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source"}}}} -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertSame(
			'<p>alert("Unsafe HTML")</p>',
			trim( $result ),
			'The block content should be updated with the value returned by the source.'
		);
	}

	/**
	 * Tests that including symbols and numbers works well with bound attributes.
	 *
	 * @ticket 61385
	 *
	 * @covers WP_Block::process_block_bindings
	 */
	public function test_using_symbols_in_block_bindings_value() {
		$get_value_callback = function () {
			return '$12.50';
		};

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => self::SOURCE_LABEL,
				'get_value_callback' => $get_value_callback,
			)
		);

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"test/source"}}}} -->
<p>Default content</p>
<!-- /wp:paragraph -->
HTML;
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertSame(
			'<p>$12.50</p>',
			trim( $result ),
			'The block content should properly show the symbol and numbers.'
		);
	}

	/**
	 * Tests if the `__default` attribute is replaced with real attribues for
	 * pattern overrides.
	 *
	 * @ticket 61333
	 *
	 * @covers WP_Block::process_block_bindings
	 */
	public function test_default_binding_for_pattern_overrides() {
		$expected_content = 'This is the content value';

		$block_content = <<<HTML
<!-- wp:paragraph {"metadata":{"bindings":{"__default":{"source":"core/pattern-overrides"}},"name":"Test"}} -->
<p>This should not appear</p>
<!-- /wp:paragraph -->
HTML;

		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0], array( 'pattern/overrides' => array( 'Test' => array( 'content' => $expected_content ) ) ) );
		$result        = $block->render();

		$this->assertSame(
			"<p>$expected_content</p>",
			trim( $result ),
			'The `__default` attribute should be replaced with the real attribute prior to the callback.'
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
			'<p>Filtered value: test_arg. Block instance: core/paragraph. Attribute name: content.</p>',
			trim( $result ),
			'The block content should show the filtered value.'
		);
	}
}
