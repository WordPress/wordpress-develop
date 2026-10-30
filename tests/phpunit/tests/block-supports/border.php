<?php
/**
 * @group block-supports
 *
 * @covers ::wp_apply_border_support
 */
class Tests_Block_Supports_Border extends WP_UnitTestCase {
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
	 * Registers a new block for testing border support.
	 *
	 * @param string $block_name Name for the test block.
	 * @param array  $supports   Array defining block support configuration.
	 * @return WP_Block_Type The block type for the newly registered test block.
	 */
	private function register_bordered_block_with_support( $block_name, $supports = array() ) {
		$this->test_block_name = $block_name;
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'borderColor' => array(
						'type' => 'string',
					),
					'style'       => array(
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
	 * @ticket 55505
	 */
	public function test_border_color_slug_with_numbers_is_kebab_cased_properly() {
		$this->test_block_name = 'test/border-color-slug-with-numbers-is-kebab-cased-properly';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'borderColor' => array(
						'type' => 'string',
					),
					'style'       => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'__experimentalBorder' => array(
						'color'  => true,
						'radius' => true,
						'width'  => true,
						'style'  => true,
					),
				),
			)
		);
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $this->test_block_name );
		$block_atts = array(
			'borderColor' => 'red',
			'style'       => array(
				'border' => array(
					'radius' => '10px',
					'width'  => '1px',
					'style'  => 'dashed',
				),
			),
		);

		$actual   = wp_apply_border_support( $block_type, $block_atts );
		$expected = array(
			'class' => 'has-border-color has-red-border-color',
			'style' => 'border-radius:10px;border-style:dashed;border-width:1px;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	public function test_border_with_skipped_serialization_block_supports() {
		$this->test_block_name = 'test/border-with-skipped-serialization-block-supports';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'__experimentalBorder' => array(
						'color'                           => true,
						'radius'                          => true,
						'width'                           => true,
						'style'                           => true,
						'__experimentalSkipSerialization' => true,
					),
				),
			)
		);
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $this->test_block_name );
		$block_atts = array(
			'style' => array(
				'border' => array(
					'color'  => '#eeeeee',
					'width'  => '1px',
					'style'  => 'dotted',
					'radius' => '10px',
				),
			),
		);

		$actual   = wp_apply_border_support( $block_type, $block_atts );
		$expected = array();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	public function test_radius_with_individual_skipped_serialization_block_supports() {
		$this->test_block_name = 'test/radius-with-individual-skipped-serialization-block-supports';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'__experimentalBorder' => array(
						'color'                           => true,
						'radius'                          => true,
						'width'                           => true,
						'style'                           => true,
						'__experimentalSkipSerialization' => array( 'radius', 'color' ),
					),
				),
			)
		);
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $this->test_block_name );
		$block_atts = array(
			'style' => array(
				'border' => array(
					'color'  => '#eeeeee',
					'width'  => '1px',
					'style'  => 'dotted',
					'radius' => '10px',
				),
			),
		);

		$actual   = wp_apply_border_support( $block_type, $block_atts );
		$expected = array(
			'style' => 'border-style:dotted;border-width:1px;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 66269
	 */
	public function test_border_color_preset_with_skipped_color_serialization() {
		$block_type  = $this->register_bordered_block_with_support(
			'test/border-color-preset-with-skipped-color-serialization',
			array(
				'__experimentalBorder' => array(
					'color'                           => true,
					'width'                           => true,
					'__experimentalSkipSerialization' => array( 'color' ),
				),
			)
		);
		$block_attrs = array(
			'borderColor' => 'red',
			'style'       => array( 'border' => array( 'width' => '1px' ) ),
		);

		$this->assertSame(
			array( 'style' => 'border-width:1px;' ),
			wp_apply_border_support( $block_type, $block_attrs )
		);
	}

	/**
	 * @ticket 66269
	 */
	public function test_split_borders_with_color_support_only_keeps_side_style() {
		$block_type  = $this->register_bordered_block_with_support(
			'test/split-borders-with-color-support-only',
			array(
				'__experimentalBorder' => array(
					'color' => true,
				),
			)
		);
		$block_attrs = array(
			'style' => array(
				'border' => array(
					'style' => 'solid',
					'top'   => array(
						'color' => '#72aee6',
						'style' => 'dashed',
					),
				),
			),
		);
		$actual      = wp_apply_border_support( $block_type, $block_attrs );
		$expected    = array(
			'style' => 'border-top-color:#72aee6;border-top-style:dashed;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 66269
	 */
	public function test_split_borders_with_width_support_only_keeps_side_color() {
		$block_type  = $this->register_bordered_block_with_support(
			'test/split-borders-with-width-support-only',
			array(
				'__experimentalBorder' => array(
					'width' => true,
				),
			)
		);
		$block_attrs = array(
			'borderColor' => 'red',
			'style'       => array(
				'border' => array(
					'top' => array(
						'color' => '#72aee6',
						'width' => '2px',
					),
				),
			),
		);
		$actual      = wp_apply_border_support( $block_type, $block_attrs );
		$expected    = array(
			'style' => 'border-top-width:2px;border-top-color:#72aee6;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 66269
	 */
	public function test_split_borders_without_color_or_width_support_are_dropped() {
		$block_type  = $this->register_bordered_block_with_support(
			'test/split-borders-without-color-or-width-support',
			array(
				'__experimentalBorder' => array(
					'radius' => true,
					'style'  => true,
				),
			)
		);
		$block_attrs = array(
			'style' => array(
				'border' => array(
					'radius' => '5px',
					'top'    => array(
						'style' => 'dashed',
						'width' => '2px',
					),
				),
			),
		);
		$actual      = wp_apply_border_support( $block_type, $block_attrs );
		$expected    = array(
			'style' => 'border-radius:5px;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 66269
	 */
	public function test_non_array_border_and_sides_are_ignored() {
		$block_type = $this->register_bordered_block_with_support(
			'test/non-array-border-and-sides',
			array(
				'__experimentalBorder' => true,
			)
		);

		$this->assertSame( array(), wp_apply_border_support( $block_type, array( 'style' => 'invalid' ) ) );
		$this->assertSame( array(), wp_apply_border_support( $block_type, array( 'style' => array( 'border' => 'invalid' ) ) ) );
		$this->assertSame(
			array( 'style' => 'border-bottom-width:1px;' ),
			wp_apply_border_support(
				$block_type,
				array(
					'style' => array(
						'border' => array(
							'top'    => 'invalid',
							'bottom' => array( 'width' => '1px' ),
						),
					),
				)
			)
		);
	}
}
