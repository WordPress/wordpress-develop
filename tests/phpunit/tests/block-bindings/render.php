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
}
