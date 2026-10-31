<?php
/**
 * @group block-supports
 *
 * @covers ::wp_apply_shadow_support
 *
 * @phpstan-type Shadow_Attributes array{
 *     style?: array{ type: 'object', default?: array{ shadow: non-empty-string } },
 *     shadow?: array{ type: 'string', default?: non-empty-string },
 * }
 */
class Tests_Block_Supports_Shadow extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;
	}

	public function tear_down() {
		unregister_block_type( $this->test_block_name );
		$this->test_block_name = null;
		parent::tear_down();
	}

	/**
	 * Registers a new block for testing shadow support.
	 *
	 * @param string $block_name Name for the test block.
	 * @param array  $supports   Array defining block support configuration.
	 * @return WP_Block_Type The block type for the newly registered test block.
	 */
	private function register_shadow_block_with_support( $block_name, $supports = array() ) {
		$this->test_block_name = $block_name;
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => $supports,
			)
		);
		$registry = WP_Block_Type_Registry::get_instance();

		return $registry->get_registered( $this->test_block_name );
	}

	/**
	 * Tests that shadow block support attribute registration works as expected.
	 *
	 * @ticket 65817
	 *
	 * @covers ::wp_register_shadow_support
	 *
	 * @dataProvider data_wp_register_shadow_support
	 *
	 * @param bool                   $support  Shadow block support configuration.
	 * @param Shadow_Attributes|null $value    Attributes array for the block.
	 * @param Shadow_Attributes      $expected Expected attributes for the block.
	 */
	public function test_wp_register_shadow_support( bool $support, ?array $value, array $expected ) {
		$this->test_block_name = 'test/shadow-attributes';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'supports'    => array( 'shadow' => $support ),
				'attributes'  => $value,
			)
		);
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $this->test_block_name );
		$this->assertInstanceOf( WP_Block_Type::class, $block_type );

		wp_register_shadow_support( $block_type );

		$actual = $block_type->attributes;
		$this->assertIsArray( $actual );
		$expected = array_merge( WP_Block_Type::GLOBAL_ATTRIBUTES, $expected );
		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider for {@see self::test_wp_register_shadow_support()}.
	 *
	 * @return array<non-falsy-string, array{ support: bool, value: Shadow_Attributes|null, expected: Shadow_Attributes }>
	 */
	public static function data_wp_register_shadow_support(): array {
		return array(
			'style and shadow attributes are registered when block supports shadow' => array(
				'support'  => true,
				'value'    => null,
				'expected' => array(
					'style'  => array(
						'type' => 'object',
					),
					'shadow' => array(
						'type' => 'string',
					),
				),
			),
			'no attributes are registered when block does not support shadow' => array(
				'support'  => false,
				'value'    => null,
				'expected' => array(),
			),
			'existing style and shadow attributes are not overwritten' => array(
				'support'  => true,
				'value'    => array(
					'style'  => array(
						'type'    => 'object',
						'default' => array(
							'shadow' => 'var:preset|shadow|natural',
						),
					),
					'shadow' => array(
						'type'    => 'string',
						'default' => 'natural',
					),
				),
				'expected' => array(
					'style'  => array(
						'type'    => 'object',
						'default' => array(
							'shadow' => 'var:preset|shadow|natural',
						),
					),
					'shadow' => array(
						'type'    => 'string',
						'default' => 'natural',
					),
				),
			),
		);
	}

	/**
	 * Tests the generation of shadow block support styles.
	 *
	 * @ticket 60784
	 *
	 * @dataProvider data_generate_shadow_fixtures
	 *
	 * @param bool|array $support  Shadow block support configuration.
	 * @param string     $value    Shadow style value for style attribute object.
	 * @param array      $expected Expected shadow block support styles.
	 */
	public function test_wp_apply_shadow_support( $support, $value, $expected ) {
		$block_type  = self::register_shadow_block_with_support(
			'test/shadow-block',
			array( 'shadow' => $support )
		);
		$block_attrs = array( 'style' => array( 'shadow' => $value ) );
		$actual      = wp_apply_shadow_support( $block_type, $block_attrs );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_generate_shadow_fixtures() {
		return array(
			'with no styles'               => array(
				'support'  => true,
				'value'    => '',
				'expected' => array(),
			),
			'without support'              => array(
				'support'  => false,
				'value'    => '1px 1px 1px #000',
				'expected' => array(),
			),
			'with single shadow'           => array(
				'support'  => true,
				'value'    => '1px 1px 1px #000',
				'expected' => array( 'style' => 'box-shadow:1px 1px 1px #000;' ),
			),
			'with comma separated shadows' => array(
				'support'  => true,
				'value'    => '1px 1px 1px #000, 2px 2px 2px #fff',
				'expected' => array( 'style' => 'box-shadow:1px 1px 1px #000, 2px 2px 2px #fff;' ),
			),
			'with preset shadow'           => array(
				'support'  => true,
				'value'    => 'var:preset|shadow|natural',
				'expected' => array( 'style' => 'box-shadow:var(--wp--preset--shadow--natural);' ),
			),
			'with serialization skipped'   => array(
				'support'  => array( '__experimentalSkipSerialization' => true ),
				'value'    => '1px 1px 1px #000',
				'expected' => array(),
			),
		);
	}
}
