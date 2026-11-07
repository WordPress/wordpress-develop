<?php
/**
 * Tests for the server-side rendering of the Button block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_button
 */
class Tests_Blocks_RenderButton extends WP_UnitTestCase {

	const LINK   = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.org/">Click</a></div>';
	const BUTTON = '<div class="wp-block-button"><button class="wp-block-button__link wp-element-button">Click</button></div>';

	public function tear_down() {
		parent::tear_down();

		// Remove the presets of the tests from the cached theme data.
		wp_clean_theme_json_cache();
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the classes of an element as a sorted list.
	 *
	 * @param array $element The attributes of the element.
	 * @return string[] The classes.
	 */
	private function get_classes( $element ) {
		$classes = preg_split( '/\s+/', $element['class'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		sort( $classes );

		return $classes;
	}

	/**
	 * Tests that the content is returned as it is without any attribute that changes it.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content The content of the block.
	 */
	public function test_should_return_the_content_as_it_is_without_a_background_or_a_width( $content ) {
		$this->assertSame( $content, render_block_core_button( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'link'                => array( self::LINK ),
			'button'              => array( self::BUTTON ),
			'link with a comment' => array( '<div class="wp-block-button"><a href="#">Click<!-- comment --></a></div>' ),
			'text only'           => array( 'Click' ),
			'empty content'       => array( '' ),
		);
	}

	/**
	 * Tests that an empty button is not rendered.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_empty_buttons
	 *
	 * @param string $content The content of the block.
	 */
	public function test_should_render_nothing_for_an_empty_button( $content ) {
		$this->assertSame( '', render_block_core_button( array(), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_buttons() {
		return array(
			'empty link'             => array( '<div class="wp-block-button"><a class="wp-block-button__link" href="#"></a></div>' ),
			'empty button'           => array( '<div class="wp-block-button"><button class="wp-block-button__link"></button></div>' ),
			'link with only comment' => array( '<div class="wp-block-button"><a class="wp-block-button__link" href="#"><!-- comment --></a></div>' ),
		);
	}

	/**
	 * Tests that a button with only a space is not empty.
	 *
	 * @ticket 66599
	 */
	public function test_should_render_a_button_with_only_a_space() {
		$content = '<div class="wp-block-button"><a class="wp-block-button__link" href="#"> </a></div>';

		$this->assertSame( $content, render_block_core_button( array(), $content ) );
	}

	/**
	 * Tests the background image on the link.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_the_background_image_to_the_link() {
		$output = render_block_core_button(
			array( 'style' => array( 'background' => array( 'backgroundImage' => array( 'url' => 'https://example.org/image.png' ) ) ) ),
			self::LINK
		);

		$link = $this->get_element( $output, 'a' );

		$this->assertContains( 'has-background', $this->get_classes( $link ) );
		$this->assertStringContainsString( 'background-image:url(\'https://example.org/image.png\')', $link['style'] );
		$this->assertStringContainsString( 'background-size:cover', $link['style'], 'The size should default to cover.' );
		$this->assertArrayNotHasKey( 'style', $this->get_element( $output, 'div' ), 'The wrapper should not get the background.' );
	}

	/**
	 * Tests the background on the button element.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_the_background_to_the_button_element() {
		$output = render_block_core_button(
			array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(#000,#fff)' ) ) ),
			self::BUTTON
		);

		$button = $this->get_element( $output, 'button' );

		$this->assertContains( 'has-background', $this->get_classes( $button ) );
		$this->assertStringContainsString( 'background-image:linear-gradient(#000,#fff)', $button['style'] );
	}

	/**
	 * Tests the position of a background image that is contained.
	 *
	 * @ticket 66599
	 */
	public function test_should_center_a_contained_background_image() {
		$background = array(
			'backgroundImage' => array( 'url' => 'https://example.org/image.png' ),
			'backgroundSize'  => 'contain',
		);

		$link = $this->get_element( render_block_core_button( array( 'style' => array( 'background' => $background ) ), self::LINK ), 'a' );

		$this->assertStringContainsString( 'background-size:contain', $link['style'] );
		$this->assertStringContainsString( 'background-position:50% 50%', $link['style'] );

		$background['backgroundPosition'] = '10% 20%';

		$link = $this->get_element( render_block_core_button( array( 'style' => array( 'background' => $background ) ), self::LINK ), 'a' );

		$this->assertStringContainsString( 'background-position:10% 20%', $link['style'] );
		$this->assertStringNotContainsString( '50% 50%', $link['style'] );
	}

	/**
	 * Tests the other background properties.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_the_repeat_and_the_attachment_of_the_background() {
		$link = $this->get_element(
			render_block_core_button(
				array(
					'style' => array(
						'background' => array(
							'backgroundImage'      => array( 'url' => 'https://example.org/image.png' ),
							'backgroundSize'       => 'auto',
							'backgroundRepeat'     => 'no-repeat',
							'backgroundAttachment' => 'fixed',
						),
					),
				),
				self::LINK
			),
			'a'
		);

		$this->assertStringContainsString( 'background-size:auto', $link['style'] );
		$this->assertStringContainsString( 'background-repeat:no-repeat', $link['style'] );
		$this->assertStringContainsString( 'background-attachment:fixed', $link['style'] );
		$this->assertStringNotContainsString( 'background-position', $link['style'], 'The position should only default for a contained image.' );
	}

	/**
	 * Tests that a gradient has no defaults of the background image.
	 *
	 * @ticket 66599
	 */
	public function test_should_not_add_the_defaults_of_the_background_image_to_a_gradient() {
		$link = $this->get_element(
			render_block_core_button(
				array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(#000,#fff)' ) ) ),
				self::LINK
			),
			'a'
		);

		$this->assertStringNotContainsString( 'background-size', $link['style'] );
	}

	/**
	 * Tests that the background is merged with the style of the link.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_existing_styles
	 *
	 * @param string $existing The style of the link.
	 * @param string $prefix   The expected start of the new style.
	 */
	public function test_should_merge_the_background_with_the_style_of_the_link( $existing, $prefix ) {
		$content = '<div class="wp-block-button"><a class="wp-block-button__link" href="#" style="' . $existing . '">Click</a></div>';
		$link    = $this->get_element(
			render_block_core_button(
				array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(#000,#fff)' ) ) ),
				$content
			),
			'a'
		);

		$this->assertStringStartsWith( $prefix . 'background-image:linear-gradient(#000,#fff)', $link['style'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_existing_styles() {
		return array(
			'style without a semicolon' => array( 'color:red', 'color:red;' ),
			'style with a semicolon'    => array( 'color:red;', 'color:red;' ),
			'empty style'               => array( '', '' ),
		);
	}

	/**
	 * Tests that only the first link is changed.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_the_background_to_the_first_link_only() {
		$content = '<div class="wp-block-button"><a class="wp-block-button__link" href="#one">One</a><a href="#two">Two</a></div>';
		$output  = render_block_core_button(
			array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(#000,#fff)' ) ) ),
			$content
		);

		$this->assertSame( 1, substr_count( $output, 'background-image' ) );
		$this->assertStringContainsString( '<a href="#two">Two</a>', $output );
	}

	/**
	 * Tests the backgrounds that do not change the content.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_backgrounds_that_do_nothing
	 *
	 * @param mixed $background The background attribute.
	 */
	public function test_should_not_change_the_content_for_a_background_without_styles( $background ) {
		$this->assertSame( self::LINK, render_block_core_button( array( 'style' => array( 'background' => $background ) ), self::LINK ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_backgrounds_that_do_nothing() {
		return array(
			'not an array'     => array( 'red' ),
			'empty array'      => array( array() ),
			'unknown property' => array( array( 'color' => 'red' ) ),
			'empty properties' => array(
				array(
					'backgroundImage' => null,
					'gradient'        => null,
				),
			),
		);
	}

	/**
	 * Tests the width as a percentage with a legacy class.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_legacy_widths
	 *
	 * @param string $width  The width.
	 * @param string $legacy The legacy class.
	 */
	public function test_should_apply_a_percentage_width_with_the_legacy_class( $width, $legacy ) {
		$wrapper = $this->get_element( render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => $width ) ) ), self::LINK ), 'div' );

		$this->assertSame( array( 'has-custom-width', 'wp-block-button', 'wp-block-button__width', $legacy ), $this->get_classes( $wrapper ) );
		$this->assertSame( '--wp--block-button--width: ' . (float) $width . ';', $wrapper['style'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_legacy_widths() {
		return array(
			'25'  => array( '25%', 'wp-block-button__width-25' ),
			'50'  => array( '50%', 'wp-block-button__width-50' ),
			'75'  => array( '75%', 'wp-block-button__width-75' ),
			'100' => array( '100%', 'wp-block-button__width-100' ),
		);
	}

	/**
	 * Tests the width as a percentage without a legacy class.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_a_percentage_width_without_a_legacy_class() {
		$wrapper = $this->get_element( render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => '33.5%' ) ) ), self::LINK ), 'div' );

		$this->assertSame( array( 'has-custom-width', 'wp-block-button', 'wp-block-button__width' ), $this->get_classes( $wrapper ) );
		$this->assertSame( '--wp--block-button--width: 33.5;', $wrapper['style'] );
	}

	/**
	 * Tests the width as a length.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_length_widths
	 *
	 * @param string $width The width.
	 */
	public function test_should_apply_a_width_that_is_not_a_percentage( $width ) {
		$wrapper = $this->get_element( render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => $width ) ) ), self::LINK ), 'div' );

		$this->assertSame( array( 'has-custom-width', 'wp-block-button' ), $this->get_classes( $wrapper ) );
		$this->assertSame( "width: $width;", $wrapper['style'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_length_widths() {
		return array(
			'pixels'      => array( '200px' ),
			'ems'         => array( '12em' ),
			'calculation' => array( 'calc(100% - 2rem)' ),
		);
	}

	/**
	 * Tests that the width goes before the existing style of the wrapper.
	 *
	 * @ticket 66599
	 */
	public function test_should_put_the_width_before_the_style_of_the_wrapper() {
		$content = '<div class="wp-block-button" style="color:red"><a class="wp-block-button__link" href="#">Click</a></div>';

		$length     = $this->get_element( render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => '200px' ) ) ), $content ), 'div' );
		$percentage = $this->get_element( render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => '50%' ) ) ), $content ), 'div' );

		$this->assertSame( 'width: 200px; color:red', $length['style'] );
		$this->assertSame( '--wp--block-button--width: 50; color:red', $percentage['style'] );
	}

	/**
	 * Tests the width as a preset that is not found.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_a_width_preset_that_is_not_found() {
		$wrapper = $this->get_element(
			render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => 'var:preset|dimension|Extra Wide' ) ) ), self::LINK ),
			'div'
		);

		$this->assertSame( array( 'has-custom-width', 'wp-block-button' ), $this->get_classes( $wrapper ) );
		$this->assertSame( 'width: var(--wp--preset--dimension--extra-wide);', $wrapper['style'] );
	}

	/**
	 * Registers width presets in the theme data.
	 *
	 * @param array $presets The presets.
	 */
	private function register_width_presets( $presets ) {
		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) use ( $presets ) {
				return $theme_json->update_with(
					array(
						'version'  => WP_Theme_JSON::LATEST_SCHEMA,
						'settings' => array(
							'blocks' => array(
								'core/button' => array( 'dimensions' => array( 'dimensionSizes' => $presets ) ),
							),
						),
					)
				);
			}
		);

		wp_clean_theme_json_cache();
	}

	/**
	 * Tests the width as a default preset that is a percentage.
	 *
	 * @ticket 66599
	 */
	public function test_should_resolve_a_default_width_preset_that_is_a_percentage() {
		$wrapper = $this->get_element(
			render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => 'var:preset|dimension|75' ) ) ), self::LINK ),
			'div'
		);

		$this->assertSame( array( 'has-custom-width', 'wp-block-button', 'wp-block-button__width', 'wp-block-button__width-75' ), $this->get_classes( $wrapper ) );
		$this->assertSame( '--wp--block-button--width: 75;', $wrapper['style'] );
	}

	/**
	 * Tests the width as a preset of the theme that is a percentage.
	 *
	 * @ticket 66599
	 */
	public function test_should_resolve_a_theme_width_preset_that_is_a_percentage() {
		$this->register_width_presets(
			array(
				array(
					'slug' => 'two-thirds',
					'name' => 'Two thirds',
					'size' => '66.5%',
				),
			)
		);

		$wrapper = $this->get_element(
			render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => 'var:preset|dimension|two-thirds' ) ) ), self::LINK ),
			'div'
		);

		$this->assertSame( array( 'has-custom-width', 'wp-block-button', 'wp-block-button__width' ), $this->get_classes( $wrapper ) );
		$this->assertSame( '--wp--block-button--width: 66.5;', $wrapper['style'] );
	}

	/**
	 * Tests that a preset of the theme wins over a default preset with the same slug.
	 *
	 * @ticket 66599
	 */
	public function test_should_prefer_a_theme_width_preset_to_a_default_preset() {
		$this->register_width_presets(
			array(
				array(
					'slug' => '50',
					'name' => 'Fifty',
					'size' => '40%',
				),
			)
		);

		$wrapper = $this->get_element(
			render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => 'var:preset|dimension|50' ) ) ), self::LINK ),
			'div'
		);

		$this->assertSame( '--wp--block-button--width: 40;', $wrapper['style'] );
		$this->assertNotContains( 'wp-block-button__width-50', $this->get_classes( $wrapper ) );
	}

	/**
	 * Tests the width as a preset that is a length.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_a_width_preset_that_is_a_length() {
		$this->register_width_presets(
			array(
				array(
					'slug' => 'wide',
					'name' => 'Wide',
					'size' => '30rem',
				),
			)
		);

		$wrapper = $this->get_element(
			render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => 'var:preset|dimension|wide' ) ) ), self::LINK ),
			'div'
		);

		$this->assertSame( array( 'has-custom-width', 'wp-block-button' ), $this->get_classes( $wrapper ) );
		$this->assertSame( 'width: var(--wp--preset--dimension--wide);', $wrapper['style'], 'A preset that is a length should use the custom property.' );
	}

	/**
	 * Tests the widths that are left out.
	 *
	 * @ticket 66599
	 *
	 * @dataProvider data_widths_that_do_nothing
	 *
	 * @param mixed $width The width.
	 */
	public function test_should_not_change_the_content_for_a_width_that_is_not_a_string_or_is_empty( $width ) {
		$this->assertSame( self::LINK, render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => $width ) ) ), self::LINK ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widths_that_do_nothing() {
		return array(
			'empty string' => array( '' ),
			'number'       => array( 50 ),
			'null'         => array( null ),
			'array'        => array( array( '50%' ) ),
		);
	}

	/**
	 * Tests that the width is applied to the wrapper of the block, not to a link.
	 *
	 * @ticket 66599
	 */
	public function test_should_leave_the_content_without_a_wrapper_as_it_is_for_a_width() {
		$content = '<a class="wp-block-button__link" href="#">Click</a>';

		$this->assertSame( $content, render_block_core_button( array( 'style' => array( 'dimensions' => array( 'width' => '50%' ) ) ), $content ) );
	}

	/**
	 * Tests the background and the width together.
	 *
	 * @ticket 66599
	 */
	public function test_should_apply_the_background_and_the_width() {
		$output = render_block_core_button(
			array(
				'style' => array(
					'background' => array( 'gradient' => 'linear-gradient(#000,#fff)' ),
					'dimensions' => array( 'width' => '50%' ),
				),
			),
			self::LINK
		);

		$this->assertContains( 'has-custom-width', $this->get_classes( $this->get_element( $output, 'div' ) ) );
		$this->assertContains( 'has-background', $this->get_classes( $this->get_element( $output, 'a' ) ) );
	}
}
