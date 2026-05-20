<?php
/**
 * Tests the states block support.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 7.1.0
 *
 * @group block-supports
 *
 * @covers ::wp_render_block_states_support
 */
class Tests_Block_Supports_States extends WP_UnitTestCase {

	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
	}

	public function tear_down() {
		if ( $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
		parent::tear_down();
	}

	/**
	 * Registers a block for tests when the block is not already registered.
	 *
	 * @param string $block_name Block name.
	 * @param array  $selectors  Optional block selectors, e.g. array( 'root' => '.foo .bar' ).
	 * @return WP_Block_Type
	 */
	private function ensure_block_registered( $block_name, $selectors = array() ) {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( $registered_block ) {
			return $registered_block;
		}

		$this->test_block_name = $block_name;
		$args                  = array(
			'api_version' => 3,
			'attributes'  => array(
				'style' => array(
					'type' => 'object',
				),
			),
		);
		if ( ! empty( $selectors ) ) {
			$args['selectors'] = $selectors;
		}
		register_block_type( $block_name, $args );

		return WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
	}

	/**
	 * Tests that a responsive root state generates media-query scoped CSS.
	 *
	 * @ticket 64099
	 */
	public function test_responsive_root_state_generates_media_query_scoped_css() {
		$this->ensure_block_registered( 'test/responsive-root-state' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'test/responsive-root-state',
			'attrs'     => array(
				'style' => array(
					'mobile' => array(
						'color' => array(
							'text' => '#ff0000',
						),
					),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertMatchesRegularExpression(
			'/^<div class="wp-block-test (wp-states-[a-f0-9]{8})">Hello<\/div>$/',
			$actual
		);
		preg_match( '/wp-states-[a-f0-9]{8}/', $actual, $matches );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $matches[0] . '{color:#ff0000 !important;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a responsive pseudo-state generates media-query scoped CSS.
	 *
	 * @ticket 64099
	 */
	public function test_responsive_pseudo_state_generates_media_query_scoped_css() {
		$this->ensure_block_registered(
			'core/button',
			array(
				'root' => '.wp-block-button .wp-block-button__link',
			)
		);

		$block_content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					'mobile' => array(
						':hover' => array(
							'color' => array(
								'background' => '#ff00d0',
							),
						),
					),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertMatchesRegularExpression(
			'/^<div class="wp-block-button"><a class="wp-block-button__link (wp-states-[a-f0-9]{8})">Click me<\/a><\/div>$/',
			$actual
		);
		preg_match( '/wp-states-[a-f0-9]{8}/', $actual, $matches );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $matches[0] . ':hover{background-color:#ff00d0 !important;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that state declarations are marked important.
	 *
	 * @ticket 64099
	 */
	public function test_state_declarations_generate_important_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					':hover' => array(
						'border' => array(
							'radius' => '8px',
						),
					),
				),
			),
		);

		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'border-radius:8px !important;',
			$actual_stylesheet
		);
	}
}
