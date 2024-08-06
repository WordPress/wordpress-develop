<?php

/**
 * @group block-supports
 *
 * @covers ::wp_apply_dimensions_support
 */
class Tests_Block_Supports_WpApplyDimensionsSupport extends WP_UnitTestCase {
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
	 * Tests that minimum height block support works as expected.
	 *
	 * @ticket 57582
	 *
	 * @covers ::wp_apply_dimensions_support
	 *
	 * @dataProvider data_minimum_height_block_support
	 *
	 * @param string $block_name The test block name to register.
	 * @param mixed  $dimensions The dimensions block support settings.
	 * @param mixed  $expected   The expected results.
	 */
	public function test_minimum_height_block_support( $block_name, $dimensions, $expected ) {
		$this->test_block_name = $block_name;
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
					'dimensions' => $dimensions,
				),
			)
		);
		$registry    = WP_Block_Type_Registry::get_instance();
		$block_type  = $registry->get_registered( $this->test_block_name );
		$block_attrs = array(
			'style' => array(
				'dimensions' => array(
					'minHeight' => '50vh',
				),
			),
		);

		$actual = wp_apply_dimensions_support( $block_type, $block_attrs );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_minimum_height_block_support() {
		return array(
			'style is applied' => array(
				'block_name' => 'test/dimensions-block-supports',
				'dimensions' => array(
					'minHeight' => true,
				),
				'expected'   => array(
					'style' => 'min-height:50vh;',
				),
			),
			'style output is skipped when serialization is skipped' => array(
				'block_name' => 'test/dimensions-with-skipped-serialization-block-supports',
				'dimensions' => array(
					'minHeight'                       => true,
					'__experimentalSkipSerialization' => true,
				),
				'expected'   => array(),
			),
			'style output is skipped when individual feature serialization is skipped' => array(
				'block_name' => 'test/min-height-with-individual-skipped-serialization-block-supports',
				'dimensions' => array(
					'minHeight'                       => true,
					'__experimentalSkipSerialization' => array( 'minHeight' ),
				),
				'expected'   => array(),
			),
		);
	}
}
