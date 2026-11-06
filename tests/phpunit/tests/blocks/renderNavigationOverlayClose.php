<?php
/**
 * Tests for the server-side rendering of the Navigation Overlay Close block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_navigation_overlay_close
 */
class Tests_Blocks_RenderNavigationOverlayClose extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/navigation-overlay-close',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Returns the attributes of the button of the output.
	 *
	 * @param string $output The output.
	 * @return array|null The attributes, or null when there is no button.
	 */
	private function get_button( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( 'button' ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests the button for the default display mode.
	 *
	 * @ticket 66578
	 */
	public function test_should_render_the_icon_by_default() {
		$output = render_block_core_navigation_overlay_close( array() );
		$button = $this->get_button( $output );

		$this->assertSame( 'wp-block-navigation-overlay-close', $button['class'] );
		$this->assertSame( 'button', $button['type'] );
		$this->assertStringContainsString( '<svg', $output, 'The icon should be output.' );
		$this->assertStringNotContainsString( 'wp-block-navigation-overlay-close__text', $output, 'The text should not be output.' );
		$this->assertSame( 'Close', $button['aria-label'], 'The button without text should have a label.' );
	}

	/**
	 * Tests the parts of the button for each display mode.
	 *
	 * @ticket 66578
	 *
	 * @dataProvider data_display_modes
	 *
	 * @param array $attributes The attributes of the block.
	 * @param bool  $icon       Whether the icon is expected.
	 * @param bool  $text       Whether the text is expected.
	 */
	public function test_should_render_the_parts_of_the_display_mode( $attributes, $icon, $text ) {
		$output = render_block_core_navigation_overlay_close( $attributes );
		$button = $this->get_button( $output );

		$this->assertSame( $icon, str_contains( $output, '<svg' ), 'The icon should match.' );
		$this->assertSame( $text, str_contains( $output, 'class="wp-block-navigation-overlay-close__text"' ), 'The text should match.' );
		$this->assertSame( ! $text, isset( $button['aria-label'] ), 'The label should be set only without the text.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_display_modes() {
		return array(
			'icon'             => array( array( 'displayMode' => 'icon' ), true, false ),
			'text'             => array( array( 'displayMode' => 'text' ), false, true ),
			'both'             => array( array( 'displayMode' => 'both' ), true, true ),
			'empty'            => array( array( 'displayMode' => '' ), true, false ),
			'a mode not known' => array( array( 'displayMode' => 'other' ), false, false ),
		);
	}

	/**
	 * Tests the default text.
	 *
	 * @ticket 66578
	 *
	 * @dataProvider data_empty_texts
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_use_the_default_text( $attributes ) {
		$output = render_block_core_navigation_overlay_close( $attributes + array( 'displayMode' => 'text' ) );

		$this->assertStringContainsString( '<span class="wp-block-navigation-overlay-close__text">Close</span>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_texts() {
		return array(
			'not set' => array( array() ),
			'empty'   => array( array( 'text' => '' ) ),
		);
	}

	/**
	 * Tests the text of the attributes with the markup that is allowed in posts.
	 *
	 * @ticket 66578
	 */
	public function test_should_use_the_text_of_the_attributes_filtered_for_posts() {
		$output = render_block_core_navigation_overlay_close(
			array(
				'displayMode' => 'both',
				'text'        => 'Shut <strong>this</strong><script>alert(1)</script>',
			)
		);

		$this->assertStringContainsString( '>Shut <strong>this</strong></span>', $output );
		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '>Close</span>', $output );
	}

	/**
	 * Tests that the button of the text has no label.
	 *
	 * @ticket 66578
	 */
	public function test_should_not_label_a_button_that_has_text() {
		$button = $this->get_button( render_block_core_navigation_overlay_close( array( 'displayMode' => 'both' ) ) );

		$this->assertArrayNotHasKey( 'aria-label', $button );
	}

	/**
	 * Tests that the icon is hidden from the assistive technologies.
	 *
	 * @ticket 66578
	 */
	public function test_should_hide_the_icon_from_assistive_technologies() {
		$output = render_block_core_navigation_overlay_close( array( 'displayMode' => 'icon' ) );

		$this->assertStringContainsString( 'aria-hidden="true" focusable="false"', $output );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66578
	 *
	 * @covers ::register_block_core_navigation_overlay_close
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/navigation-overlay-close' );

		if ( $original_block ) {
			unregister_block_type( 'core/navigation-overlay-close' );
		}

		try {
			register_block_core_navigation_overlay_close();
			$block_type = $registry->get_registered( 'core/navigation-overlay-close' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_navigation_overlay_close', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/navigation-overlay-close' ) ) {
				unregister_block_type( 'core/navigation-overlay-close' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
