<?php
/**
 * @group block-supports
 *
 * @covers ::wp_apply_anchor_support
 */
class Tests_Block_Supports_Anchor extends WP_UnitTestCase {
	/**
	 * @var string
	 */
	const TEST_BLOCK_NAME = 'test/anchor-block';

	public function tear_down() {
		unregister_block_type( self::TEST_BLOCK_NAME );
		parent::tear_down();
	}

	/**
	 * Registers a new block for testing anchor support.
	 * @param array<string, string|bool> $supports Array defining block support configuration.
	 * @return WP_Block_Type The block type for the newly registered test block.
	 */
	private function register_anchor_block_with_support( array $supports = array() ): WP_Block_Type {
		register_block_type(
			self::TEST_BLOCK_NAME,
			array(
				'api_version' => 3,
				'supports'    => $supports,
			)
		);
		$registry = WP_Block_Type_Registry::get_instance();

		return $registry->get_registered( self::TEST_BLOCK_NAME );
	}

	/**
	 * Tests that anchor block support works as expected.
	 *
	 * @dataProvider data_anchor_block_support
	 *
	 * @param boolean|array $support  Anchor block support configuration.
	 * @param string        $value    Anchor value for attribute object.
	 * @param array         $expected Expected anchor block support output.
	 */
	public function test_wp_apply_anchor_support( $support, $value, array $expected ) {
		$block_type  = self::register_anchor_block_with_support(
			array( 'anchor' => $support )
		);
		$block_attrs = array( 'anchor' => $value );
		$actual      = wp_apply_anchor_support( $block_type, $block_attrs );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_anchor_block_support(): array {
		return array(
			'anchor id attribute is applied'          => array(
				'support'  => true,
				'value'    => 'my-anchor',
				'expected' => array( 'id' => 'my-anchor' ),
			),
			'anchor id attribute is not applied if block does not support it' => array(
				'support'  => false,
				'value'    => 'my-anchor',
				'expected' => array(),
			),
			'empty anchor value returns empty array'  => array(
				'support'  => true,
				'value'    => '',
				'expected' => array(),
			),
			'null anchor value returns empty array'   => array(
				'support'  => true,
				'value'    => null,
				'expected' => array(),
			),
			'whitespace-only anchor value is applied' => array(
				'support'  => true,
				'value'    => '   ',
				'expected' => array( 'id' => '   ' ),
			),
			'anchor with hyphen and numbers'          => array(
				'support'  => true,
				'value'    => 'section-123',
				'expected' => array( 'id' => 'section-123' ),
			),
			'anchor with underscore'                  => array(
				'support'  => true,
				'value'    => 'my_anchor_id',
				'expected' => array( 'id' => 'my_anchor_id' ),
			),
			'anchor with colon (valid in HTML5)'      => array(
				'support'  => true,
				'value'    => 'my:anchor',
				'expected' => array( 'id' => 'my:anchor' ),
			),
			'anchor with period (valid in HTML5)'     => array(
				'support'  => true,
				'value'    => 'my.anchor',
				'expected' => array( 'id' => 'my.anchor' ),
			),
			'numeric anchor value'                    => array(
				'support'  => true,
				'value'    => '123',
				'expected' => array( 'id' => '123' ),
			),
			'zero string anchor value is applied'     => array(
				'support'  => true,
				'value'    => '0',
				'expected' => array( 'id' => '0' ),
			),
			'false value is treated as empty'         => array(
				'support'  => true,
				'value'    => false,
				'expected' => array(),
			),
		);
	}
}
