<?php

/**
 * @group block-supports
 *
 * @covers ::wp_apply_typography_support
 */
class Tests_Block_Supports_WpApplyTypographySupport extends WP_UnitTestCase {
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
	 * Tests that text indent block support works as expected.
	 *
	 * @ticket 64326
	 *
	 * @covers ::wp_apply_typography_support
	 *
	 * @dataProvider data_text_indent_block_support
	 *
	 * @param string $block_name The test block name to register.
	 * @param mixed  $typography The typography block support settings.
	 * @param mixed  $expected   The expected results.
	 */
	public function test_text_indent_block_support( $block_name, $typography, $expected ) {
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
					'typography' => $typography,
				),
			)
		);
		$registry    = WP_Block_Type_Registry::get_instance();
		$block_type  = $registry->get_registered( $this->test_block_name );
		$block_attrs = array(
			'style' => array(
				'typography' => array(
					'textIndent' => '2em',
				),
			),
		);

		$actual = wp_apply_typography_support( $block_type, $block_attrs );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_text_indent_block_support() {
		return array(
			'style is applied'                                                         => array(
				'block_name' => 'test/text-indent-block-supports',
				'typography' => array(
					'textIndent' => true,
				),
				'expected'   => array(
					'style' => 'text-indent:2em;',
				),
			),
			'style output is skipped when serialization is skipped'                    => array(
				'block_name' => 'test/text-indent-with-skipped-serialization-block-supports',
				'typography' => array(
					'textIndent'                      => true,
					'__experimentalSkipSerialization' => true,
				),
				'expected'   => array(),
			),
			'style output is skipped when individual feature serialization is skipped' => array(
				'block_name' => 'test/text-indent-with-individual-skipped-serialization-block-supports',
				'typography' => array(
					'textIndent'                      => true,
					'__experimentalSkipSerialization' => array( 'textIndent' ),
				),
				'expected'   => array(),
			),
		);
	}
}
