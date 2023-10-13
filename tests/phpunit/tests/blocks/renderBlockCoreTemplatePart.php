<?php
/**
 * Tests for `render_block_core_template_part()`.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderBlockCoreTemplatePart extends WP_UnitTestCase {

	/**
	 * Temporary storage for original block to render.
	 *
	 * @var array|null
	 */
	private $original_block_to_render = null;

	/**
	 * Flag to indicate whether to switch back to the default theme at tear down.
	 *
	 * @var bool
	 */
	private $switch_to_default_theme_at_teardown = false;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render = WP_Block_Supports::$block_to_render;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$this->original_block_to_render     = null;

		if ( $this->switch_to_default_theme_at_teardown ) {
			$this->switch_to_default_theme_at_teardown = false;
			switch_theme( WP_DEFAULT_THEME );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the core template part block assumes the current theme if no theme attribute provided.
	 *
	 * @ticket 59583
	 */
	public function test_render_block_core_template_part_without_theme_attribute() {
		$this->maybe_switch_theme( 'block-theme' );

		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/template-part' );

		$content = render_block_core_template_part( array( 'slug' => 'small-header' ) );

		$expected  = '<header class="wp-block-template-part">' . "\n";
		$expected .= '<p>Small Header Template Part</p>' . "\n";
		$expected .= '</header>';

		$this->assertSame( $expected, $content );
	}

	/**
	 * Tests that the core template part block returns the relevant part if current theme attribute provided.
	 *
	 * @ticket 59583
	 */
	public function test_render_block_core_template_part_with_current_theme_attribute() {
		$this->maybe_switch_theme( 'block-theme' );

		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/template-part' );

		$content = render_block_core_template_part(
			array(
				'slug'  => 'small-header',
				'theme' => 'block-theme',
			)
		);

		$expected  = '<header class="wp-block-template-part">' . "\n";
		$expected .= '<p>Small Header Template Part</p>' . "\n";
		$expected .= '</header>';

		$this->assertSame( $expected, $content );
	}

	/**
	 * Tests that the core template part block returns nothing if theme attribute for a different theme provided.
	 *
	 * @ticket 59583
	 */
	public function test_render_block_core_template_part_with_another_theme_attribute() {
		$this->maybe_switch_theme( 'block-theme' );

		WP_Block_Supports::$block_to_render = array( 'blockName' => 'core/template-part' );

		$content = render_block_core_template_part(
			array(
				'slug'  => 'small-header',
				'theme' => WP_DEFAULT_THEME,
			)
		);

		$expected = 'Template part has been deleted or is unavailable: small-header';
		$this->assertSame( $expected, $content );
	}

	/**
	 * Switches the theme when not the default theme.
	 *
	 * @param string $theme Theme name to switch to.
	 */
	private function maybe_switch_theme( $theme ) {
		if ( WP_DEFAULT_THEME === $theme ) {
			return;
		}

		switch_theme( $theme );
		$this->switch_to_default_theme_at_teardown = true;
	}
}
