<?php
/**
 * @group block-supports
 *
 * @covers ::wp_apply_shadow_support
 */
class Test_Block_Supports_Shadow extends WP_UnitTestCase {
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
		parent::set_up();
	}

	/**
	 * @ticket 58590
	 */
	public function test_shadow_style_is_applied() {
		$this->test_block_name = 'test/shadow-style-is-applied';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'shadow' => true,
				),
			)
		);
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $this->test_block_name );
		$block_atts = array(
			'style' => array(
				'shadow' => '60px -16px teal',
			),
		);

		$actual   = wp_apply_shadow_support( $block_type, $block_atts );
		$expected = array(
			'style' => 'box-shadow:60px -16px teal;',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 58590
	 */
	public function test_shadow_without_block_supports() {
		$this->test_block_name = 'test/shadow-with-skipped-serialization-block-supports';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(),
			)
		);
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $this->test_block_name );
		$block_atts = array(
			'style' => array(
				'shadow' => '60px -16px teal',
			),
		);

		$actual   = wp_apply_spacing_support( $block_type, $block_atts );
		$expected = array();

		$this->assertSame( $expected, $actual );
	}
}
