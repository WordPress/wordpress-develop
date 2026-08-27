<?php
/**
 * Tests for `core/list-item` with block bindings.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group block-bindings
 */
class WP_Block_Bindings_List_Item extends WP_UnitTestCase {

	const CONTENT_SOURCE_NAME      = 'test/list-item-content';
	const INNER_BLOCKS_SOURCE_NAME = 'test/list-item-inner-blocks';

	/**
	 * Cleans up any block bindings sources registered during a test.
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
	 * Registers a source supplying a List Item's `content` rich text.
	 *
	 * @param string $content_value The value returned for the `content` attribute.
	 */
	private function register_content_source( $content_value ) {
		register_block_bindings_source(
			self::CONTENT_SOURCE_NAME,
			array(
				'label'              => 'List item content source',
				'get_value_callback' => static function ( $source_args, $block_instance, $attribute_name ) use ( $content_value ) {
					if ( 'content' === $attribute_name ) {
						return $content_value;
					}
					return null;
				},
			)
		);
	}

	/**
	 * Registers a source supplying a List Item's serialized inner blocks.
	 *
	 * @param mixed $inner_blocks_value The value returned for the `innerBlocks` attribute.
	 */
	private function register_inner_blocks_source( $inner_blocks_value ) {
		register_block_bindings_source(
			self::INNER_BLOCKS_SOURCE_NAME,
			array(
				'label'              => 'List item inner blocks source',
				'get_value_callback' => static function ( $source_args, $block_instance, $attribute_name ) use ( $inner_blocks_value ) {
					if ( 'innerBlocks' === $attribute_name ) {
						return $inner_blocks_value;
					}
					return null;
				},
			)
		);
	}

	/**
	 * A List Item with both a bound `content` attribute and bound `innerBlocks`
	 * renders its inner blocks exactly once.
	 *
	 * @covers ::_block_bindings_replace_inner_blocks
	 */
	public function test_content_and_inner_blocks_binding_renders_inner_blocks_once() {
		$this->register_content_source( 'Bound item text' );
		$this->register_inner_blocks_source(
			'<!-- wp:list {"ordered":false} --><ul class="wp-block-list"><!-- wp:list-item --><li>Substituted nested item</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
		);

		$markup = '<!-- wp:list-item {"metadata":{"bindings":{"content":{"source":"' . self::CONTENT_SOURCE_NAME . '"},"innerBlocks":{"source":"' . self::INNER_BLOCKS_SOURCE_NAME . '"}}}} -->' .
			'<li>Original text<!-- wp:list {"ordered":false} --><ul class="wp-block-list"><!-- wp:list-item --><li>Original nested item</li><!-- /wp:list-item --></ul><!-- /wp:list --></li>' .
			'<!-- /wp:list-item -->';

		$parsed = parse_blocks( $markup );
		$result = render_block( $parsed[0] );

		$this->assertStringContainsString(
			'Bound item text',
			$result,
			'The content binding should supply the list item rich text.'
		);
		$this->assertStringContainsString(
			'Substituted nested item',
			$result,
			'The innerBlocks binding should supply the nested list.'
		);
		$this->assertStringNotContainsString(
			'Original nested item',
			$result,
			'The original serialized inner blocks must not render when innerBlocks is bound.'
		);
		$this->assertSame(
			1,
			substr_count( $result, 'Substituted nested item' ),
			'The bound inner blocks must render exactly once.'
		);
	}
}
