<?php
/**
 * Tests for Block Bindings API "core/pattern-overrides" source.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Pattern_Overrides_Source extends WP_UnitTestCase {

	/**
	 * Helper to create a WP_Block test instance with explicit attributes and context.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $context    Block context.
	 * @return WP_Block The block instance.
	 */
	private function create_block( array $attributes = array(), array $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'test/block',
				'attrs'     => $attributes,
			)
		);

		$block->attributes = $attributes;
		$block->context    = $context;

		return $block;
	}

	/**
	 * Tests that the "core/pattern-overrides" source is correctly registered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_register_block_bindings_pattern_overrides_source
	 */
	public function test_source_registration() {
		$source = get_block_bindings_source( 'core/pattern-overrides' );

		$this->assertNotNull( $source, 'The "core/pattern-overrides" source should be registered.' );
		$this->assertSame( 'core/pattern-overrides', $source->name );
		$this->assertSame( 'Pattern Overrides', $source->label );
		$this->assertSame( array( 'pattern/overrides' ), $source->uses_context );
	}

	/**
	 * Tests that _block_bindings_pattern_overrides_get_value() returns null when metadata name is empty or missing.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_pattern_overrides_get_value
	 *
	 * @dataProvider data_empty_metadata_names
	 *
	 * @param array $attributes Block attributes.
	 */
	public function test_get_value_returns_null_when_metadata_name_empty( array $attributes ) {
		$block = $this->create_block(
			$attributes,
			array(
				'pattern/overrides' => array(
					'my-title' => array(
						'content' => 'Override Text',
					),
				),
			)
		);

		$this->assertNull( _block_bindings_pattern_overrides_get_value( array(), $block, 'content' ) );
	}

	/**
	 * Data provider for test_get_value_returns_null_when_metadata_name_empty.
	 *
	 * @return array[]
	 */
	public static function data_empty_metadata_names() {
		return array(
			'empty attributes'     => array( array() ),
			'empty metadata array' => array( array( 'metadata' => array() ) ),
			'empty name string'    => array( array( 'metadata' => array( 'name' => '' ) ) ),
		);
	}

	/**
	 * Tests that _block_bindings_pattern_overrides_get_value() returns null when context is empty.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_pattern_overrides_get_value
	 *
	 */
	public function test_get_value_returns_null_when_context_empty() {
		$block = $this->create_block(
			array(
				'metadata' => array(
					'name' => 'heading-1',
				),
			),
			array()
		);

		$this->assertNull( _block_bindings_pattern_overrides_get_value( array(), $block, 'content' ) );
	}

	/**
	 * Tests that _block_bindings_pattern_overrides_get_value() returns null when metadata name is not in overrides context.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_pattern_overrides_get_value
	 */
	public function test_get_value_returns_null_when_metadata_name_not_in_overrides() {
		$block = $this->create_block(
			array(
				'metadata' => array(
					'name' => 'unmatched-name',
				),
			),
			array(
				'pattern/overrides' => array(
					'different-name' => array(
						'content' => 'Matched Content',
					),
				),
			)
		);

		$this->assertNull( _block_bindings_pattern_overrides_get_value( array(), $block, 'content' ) );
	}

	/**
	 * Tests that _block_bindings_pattern_overrides_get_value() returns null when attribute name is not in overrides.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_pattern_overrides_get_value
	 */
	public function test_get_value_returns_null_when_attribute_name_not_in_overrides() {
		$block = $this->create_block(
			array(
				'metadata' => array(
					'name' => 'featured-image',
				),
			),
			array(
				'pattern/overrides' => array(
					'featured-image' => array(
						'url' => 'https://example.com/image.jpg',
					),
				),
			)
		);

		$this->assertNull( _block_bindings_pattern_overrides_get_value( array(), $block, 'alt' ) );
	}

	/**
	 * Tests that the registered source get_value() method executes correctly.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Block_Bindings_Source::get_value
	 */
	public function test_source_get_value_method() {
		$block = $this->create_block(
			array(
				'metadata' => array(
					'name' => 'cta-button',
				),
			),
			array(
				'pattern/overrides' => array(
					'cta-button' => array(
						'text' => 'Click Here',
					),
				),
			)
		);

		$source = get_block_bindings_source( 'core/pattern-overrides' );
		$this->assertSame( 'Click Here', $source->get_value( array(), $block, 'text' ) );
	}

	/**
	 * Tests that _block_bindings_pattern_overrides_get_value() handles multiple data types.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_pattern_overrides_get_value
	 *
	 * @dataProvider data_override_values_and_types
	 *
	 * @param mixed $expected Expected value.
	 */
	public function test_get_value_handles_different_data_types( $expected ) {
		$block = $this->create_block(
			array(
				'metadata' => array(
					'name' => 'hero-banner',
				),
			),
			array(
				'pattern/overrides' => array(
					'hero-banner' => array(
						'attributeKey' => $expected,
					),
				),
			)
		);

		$result = _block_bindings_pattern_overrides_get_value( array(), $block, 'attributeKey' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_get_value_handles_different_data_types.
	 *
	 * @return array[]
	 */
	public static function data_override_values_and_types() {
		return array(
			'empty string'  => array( '' ),
			'zero integer'  => array( 0 ),
			'boolean false' => array( false ),
			'array'         => array( array( 'nested' => 'value' ) ),
			'empty array'   => array( array() ),
		);
	}
}
