<?php
/**
 * Tests for the functions of the Search block that build its inline styles.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_SearchStyles extends WP_UnitTestCase {

	/**
	 * Gets the declarations of a style attribute that is returned by the function.
	 *
	 * @param array  $styles Result of the function.
	 * @param string $key    Key of the element.
	 * @return string The declarations, or an empty string.
	 */
	private function declarations( $styles, $key ) {
		if ( '' === $styles[ $key ] ) {
			return '';
		}

		$this->assertMatchesRegularExpression( '/^ style="[^"]*"$/', $styles[ $key ], "The '$key' value should be a style attribute." );

		$css = html_entity_decode( substr( $styles[ $key ], 8, -1 ) );

		// The filters of the CSS change the spaces and the last semicolon, so the declarations are formatted in the same way.
		$declarations = array_filter( array_map( 'trim', explode( ';', $css ) ), 'strlen' );

		return implode(
			' ',
			array_map(
				static function ( $declaration ) {
					return preg_replace( '/^([a-z-]+)\s*:\s*/', '$1: ', $declaration ) . ';';
				},
				$declarations
			)
		);
	}

	/**
	 * Tests that there are no styles without attributes.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_return_empty_styles_without_attributes() {
		$this->assertSame(
			array(
				'input'   => '',
				'button'  => '',
				'wrapper' => '',
				'label'   => '',
			),
			styles_for_block_core_search( array() )
		);
	}

	/**
	 * Tests the width of the wrapper.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_widths
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected styles of the wrapper.
	 */
	public function test_should_add_the_width_to_the_wrapper( $attributes, $expected ) {
		$styles = styles_for_block_core_search( $attributes );

		$this->assertSame( $expected, $this->declarations( $styles, 'wrapper' ) );
		$this->assertSame( '', $styles['input'], 'The input should have no styles.' );
		$this->assertSame( '', $styles['button'], 'The button should have no styles.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widths() {
		return array(
			'a percentage'    => array(
				array(
					'width'     => 50,
					'widthUnit' => '%',
				),
				'width: 50%;',
			),
			'pixels'          => array(
				array(
					'width'     => 300,
					'widthUnit' => 'px',
				),
				'width: 300px;',
			),
			'a width as text' => array(
				array(
					'width'     => '75',
					'widthUnit' => '%',
				),
				'width: 75%;',
			),
			'no unit'         => array( array( 'width' => 50 ), '' ),
			'an empty unit'   => array(
				array(
					'width'     => 50,
					'widthUnit' => '',
				),
				'',
			),
			'no width'        => array( array( 'widthUnit' => '%' ), '' ),
			'a width of zero' => array(
				array(
					'width'     => 0,
					'widthUnit' => '%',
				),
				'',
			),
		);
	}

	/**
	 * Tests the minimum width and height of the wrapper.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_add_the_minimum_dimensions_to_the_wrapper() {
		$styles = styles_for_block_core_search(
			array(
				'style' => array(
					'dimensions' => array(
						'minWidth'  => '200px',
						'minHeight' => '3rem',
					),
				),
			)
		);

		$this->assertSame( 'min-height: 3rem; min-width: 200px;', $this->declarations( $styles, 'wrapper' ) );
		$this->assertSame( '', $styles['input'] );
		$this->assertSame( '', $styles['button'] );
	}

	/**
	 * Tests where the borders are added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 * @covers ::apply_block_core_search_border_styles
	 * @covers ::apply_block_core_search_border_style
	 *
	 * @dataProvider data_border_positions
	 *
	 * @param string|null $position Position of the button.
	 * @param bool        $inside   Whether the borders are on the wrapper.
	 */
	public function test_should_add_the_borders_to_the_wrapper_or_to_the_input_and_the_button( $position, $inside ) {
		$attributes = array(
			'style' => array(
				'border' => array(
					'width' => '2px',
					'color' => '#ff0000',
					'style' => 'solid',
				),
			),
		);

		if ( null !== $position ) {
			$attributes['buttonPosition'] = $position;
		}

		$styles   = styles_for_block_core_search( $attributes );
		$expected = 'border-width: 2px; border-color: #ff0000; border-style: solid;';

		if ( $inside ) {
			$this->assertSame( $expected, $this->declarations( $styles, 'wrapper' ) );
			$this->assertSame( '', $styles['input'] );
			$this->assertSame( '', $styles['button'] );
		} else {
			$this->assertSame( '', $styles['wrapper'] );
			$this->assertSame( $expected, $this->declarations( $styles, 'input' ) );
			$this->assertSame( $expected, $this->declarations( $styles, 'button' ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_border_positions() {
		return array(
			'no position'    => array( null, false ),
			'button outside' => array( 'button-outside', false ),
			'no button'      => array( 'no-button', false ),
			'button only'    => array( 'button-only', false ),
			'button inside'  => array( 'button-inside', true ),
		);
	}

	/**
	 * Tests the borders of each side.
	 *
	 * @ticket 66534
	 *
	 * @covers ::apply_block_core_search_border_style
	 * @covers ::apply_block_core_search_border_styles
	 */
	public function test_should_add_the_borders_of_each_side() {
		$styles = styles_for_block_core_search(
			array(
				'style' => array(
					'border' => array(
						'top'    => array(
							'width' => '1px',
							'color' => '#111111',
							'style' => 'dotted',
						),
						'right'  => array( 'width' => '2px' ),
						'bottom' => array( 'color' => '#222222' ),
						'left'   => array( 'style' => 'dashed' ),
					),
				),
			)
		);

		$this->assertSame(
			'border-top-width: 1px; border-right-width: 2px; border-top-color: #111111; border-bottom-color: #222222; border-top-style: dotted; border-left-style: dashed;',
			$this->declarations( $styles, 'input' )
		);
		$this->assertSame( $this->declarations( $styles, 'input' ), $this->declarations( $styles, 'button' ), 'The button should have the same borders.' );
	}

	/**
	 * Tests the colors of a side that are presets.
	 *
	 * @ticket 66534
	 *
	 * @covers ::apply_block_core_search_border_style
	 *
	 * @dataProvider data_border_colors
	 *
	 * @param array  $border   Border attribute.
	 * @param string $expected Expected styles.
	 */
	public function test_should_convert_the_preset_colors_of_the_sides( $border, $expected ) {
		$styles = styles_for_block_core_search( array( 'style' => array( 'border' => $border ) ) );

		$this->assertSame( $expected, $this->declarations( $styles, 'input' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_border_colors() {
		return array(
			'a preset of a side'       => array( array( 'top' => array( 'color' => 'var:preset|color|vivid-red' ) ), 'border-top-color: var(--wp--preset--color--vivid-red);' ),
			'a color of a side'        => array( array( 'left' => array( 'color' => '#00ff00' ) ), 'border-left-color: #00ff00;' ),
			'a preset of all sides'    => array( array( 'color' => 'var:preset|color|cyan' ), 'border-color: var:preset|color|cyan;' ),
			'a width that is a preset' => array( array( 'top' => array( 'width' => 'var:preset|color|cyan' ) ), 'border-top-width: var:preset|color|cyan;' ),
		);
	}

	/**
	 * Tests that empty borders are ignored.
	 *
	 * @ticket 66534
	 *
	 * @covers ::apply_block_core_search_border_style
	 *
	 * @dataProvider data_empty_borders
	 *
	 * @param array $border Border attribute.
	 */
	public function test_should_ignore_the_empty_borders( $border ) {
		$this->assertSame(
			array(
				'input'   => '',
				'button'  => '',
				'wrapper' => '',
				'label'   => '',
			),
			styles_for_block_core_search( array( 'style' => array( 'border' => $border ) ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_borders() {
		return array(
			'empty values'           => array(
				array(
					'width' => '',
					'color' => '',
					'style' => '',
				),
			),
			'empty values of a side' => array( array( 'top' => array( 'width' => '' ) ) ),
			'zero'                   => array( array( 'width' => '0' ) ),
			'null'                   => array( array( 'color' => null ) ),
			'an empty array'         => array( array() ),
		);
	}

	/**
	 * Tests the styles of a border that are added by the function directly.
	 *
	 * @ticket 66534
	 *
	 * @covers ::apply_block_core_search_border_style
	 */
	public function test_should_add_a_border_style_to_the_lists_that_are_passed() {
		$wrapper = array();
		$button  = array();
		$input   = array();

		apply_block_core_search_border_style(
			array( 'style' => array( 'border' => array( 'width' => '3px' ) ) ),
			'width',
			null,
			$wrapper,
			$button,
			$input
		);

		$this->assertSame( array(), $wrapper );
		$this->assertSame( array( 'border-width: 3px;' ), $button );
		$this->assertSame( array( 'border-width: 3px;' ), $input );
	}

	/**
	 * Tests that the style of a border is escaped.
	 *
	 * @ticket 66534
	 *
	 * @covers ::apply_block_core_search_border_style
	 */
	public function test_should_escape_the_border_styles() {
		$wrapper = array();
		$button  = array();
		$input   = array();

		apply_block_core_search_border_style(
			array( 'style' => array( 'border' => array( 'color' => '"><script>' ) ) ),
			'color',
			null,
			$wrapper,
			$button,
			$input
		);

		$this->assertSame( array( 'border-color: &quot;&gt;&lt;script&gt;;' ), $button );
	}

	/**
	 * Tests the border radius.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_border_radius
	 *
	 * @param mixed       $radius   Border radius.
	 * @param string|null $position Position of the button.
	 * @param string      $input    Expected styles of the input and the button.
	 * @param string      $wrapper  Expected styles of the wrapper.
	 */
	public function test_should_add_the_border_radius( $radius, $position, $input, $wrapper ) {
		$attributes = array( 'style' => array( 'border' => array( 'radius' => $radius ) ) );

		if ( null !== $position ) {
			$attributes['buttonPosition'] = $position;
		}

		$styles = styles_for_block_core_search( $attributes );

		$this->assertSame( $input, $this->declarations( $styles, 'input' ), 'The input should have the radius.' );
		$this->assertSame( $input, $this->declarations( $styles, 'button' ), 'The button should have the radius.' );
		$this->assertSame( $wrapper, $this->declarations( $styles, 'wrapper' ), 'The wrapper should have the right radius.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_border_radius() {
		return array(
			'a number'                => array( 8, null, 'border-radius: 8px;', '' ),
			'a number as text'        => array( '8', null, 'border-radius: 8px;', '' ),
			'a length'                => array( '1.5em', null, 'border-radius: 1.5em;', '' ),
			'a preset'                => array( 'var:preset|border-radius|large', null, 'border-radius: var(--wp--preset--border-radius--large);', '' ),
			'a preset in camel case'  => array( 'var:preset|border-radius|extraLarge', null, 'border-radius: var(--wp--preset--border-radius--extra-large);', '' ),
			'a number inside'         => array( 8, 'button-inside', 'border-radius: 8px;', 'border-radius: calc(8px + 4px);' ),
			'a length inside'         => array( '1.5em', 'button-inside', 'border-radius: 1.5em;', 'border-radius: calc(1.5em + 4px);' ),
			'a length of zero inside' => array( '0px', 'button-inside', 'border-radius: 0px;', '' ),
			'zero inside'             => array( 0, 'button-inside', '', '' ),
			'a number outside'        => array( 8, 'button-outside', 'border-radius: 8px;', '' ),
			'a number with no button' => array( 8, 'no-button', 'border-radius: 8px;', '' ),
			'an empty value'          => array( '', null, '', '' ),
		);
	}

	/**
	 * Tests the border radius of each corner.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_corner_radius
	 *
	 * @param array       $radius   Border radius of the corners.
	 * @param string|null $position Position of the button.
	 * @param string      $input    Expected styles of the input and the button.
	 * @param string      $wrapper  Expected styles of the wrapper.
	 */
	public function test_should_add_the_border_radius_of_each_corner( $radius, $position, $input, $wrapper ) {
		$attributes = array( 'style' => array( 'border' => array( 'radius' => $radius ) ) );

		if ( null !== $position ) {
			$attributes['buttonPosition'] = $position;
		}

		$styles = styles_for_block_core_search( $attributes );

		$this->assertSame( $input, $this->declarations( $styles, 'input' ), 'The input should have the radius.' );
		$this->assertSame( $input, $this->declarations( $styles, 'button' ), 'The button should have the radius.' );
		$this->assertSame( $wrapper, $this->declarations( $styles, 'wrapper' ), 'The wrapper should have the right radius.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_corner_radius() {
		return array(
			'the corners'                   => array(
				array(
					'topLeft'     => '5px',
					'topRight'    => '6px',
					'bottomLeft'  => '7px',
					'bottomRight' => '8px',
				),
				null,
				'border-top-left-radius: 5px; border-top-right-radius: 6px; border-bottom-left-radius: 7px; border-bottom-right-radius: 8px;',
				'',
			),
			'the corners inside'            => array(
				array(
					'topLeft'     => '5px',
					'bottomRight' => '8px',
				),
				'button-inside',
				'border-top-left-radius: 5px; border-bottom-right-radius: 8px;',
				'border-top-left-radius: calc(5px + 4px); border-bottom-right-radius: calc(8px + 4px);',
			),
			'a corner of zero inside'       => array(
				array(
					'topLeft'  => '0',
					'topRight' => '6px',
				),
				'button-inside',
				'border-top-left-radius: 0; border-top-right-radius: 6px;',
				'border-top-right-radius: calc(6px + 4px);',
			),
			'a corner that is a preset'     => array(
				array( 'topLeft' => 'var:preset|border-radius|large' ),
				null,
				'border-top-left-radius: var(--wp--preset--border-radius--large);',
				'',
			),
			'a corner preset in camel case' => array(
				array( 'topLeft' => 'var:preset|border-radius|extraLarge' ),
				null,
				'border-top-left-radius: var(--wp--preset--border-radius--extra-large);',
				'',
			),
			'a preset inside'               => array(
				array( 'topLeft' => 'var:preset|border-radius|large' ),
				'button-inside',
				'border-top-left-radius: var(--wp--preset--border-radius--large);',
				'border-top-left-radius: calc(var(--wp--preset--border-radius--large) + 4px);',
			),
			'a corner that is null'         => array(
				array(
					'topLeft'  => null,
					'topRight' => '6px',
				),
				null,
				'border-top-right-radius: 6px;',
				'',
			),
		);
	}

	/**
	 * Tests the shadow.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_shadows
	 *
	 * @param mixed       $shadow   Shadow of the style.
	 * @param string|null $position Position of the button.
	 * @param string      $input    Expected styles of the input and the button.
	 * @param string      $wrapper  Expected styles of the wrapper.
	 */
	public function test_should_add_the_shadow( $shadow, $position, $input, $wrapper ) {
		$attributes = array( 'style' => array( 'shadow' => $shadow ) );

		if ( null !== $position ) {
			$attributes['buttonPosition'] = $position;
		}

		$styles = styles_for_block_core_search( $attributes );

		$this->assertSame( $input, $this->declarations( $styles, 'input' ), 'The input should have the shadow.' );
		$this->assertSame( $input, $this->declarations( $styles, 'button' ), 'The button should have the shadow.' );
		$this->assertSame( $wrapper, $this->declarations( $styles, 'wrapper' ), 'The wrapper should have the shadow.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_shadows() {
		return array(
			'a shadow'                  => array( '2px 2px 4px #000000', null, 'box-shadow: 2px 2px 4px #000000;', '' ),
			'a shadow inside'           => array( '2px 2px 4px #000000', 'button-inside', '', 'box-shadow: 2px 2px 4px #000000;' ),
			'a shadow outside'          => array( '2px 2px 4px #000000', 'button-outside', 'box-shadow: 2px 2px 4px #000000;', '' ),
			'a preset'                  => array( 'var:preset|shadow|natural', null, 'box-shadow: var(--wp--preset--shadow--natural);', '' ),
			'an empty shadow'           => array( '', null, '', '' ),
			'a shadow that is an array' => array( array( 'x' => 1 ), null, '', '' ),
			'a shadow that is a number' => array( 5, null, '', '' ),
		);
	}

	/**
	 * Tests where the colors are added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_colors
	 *
	 * @param array  $color    Color of the style attribute.
	 * @param string $expected Expected styles.
	 */
	public function test_should_add_the_colors_to_the_button_or_to_the_input( $color, $expected ) {
		$with_button = styles_for_block_core_search( array( 'style' => array( 'color' => $color ) ) );
		$no_button   = styles_for_block_core_search(
			array(
				'buttonPosition' => 'no-button',
				'style'          => array( 'color' => $color ),
			)
		);

		$this->assertSame( $expected, $this->declarations( $with_button, 'button' ), 'The button should have the colors.' );
		$this->assertSame( '', $this->declarations( $with_button, 'input' ), 'The input should not have the colors when there is a button.' );
		$this->assertSame( $expected, $this->declarations( $no_button, 'input' ), 'The input should have the colors without a button.' );
		$this->assertSame( '', $this->declarations( $no_button, 'button' ), 'The button should not have the colors without a button.' );
		$this->assertSame( '', $with_button['wrapper'] . $with_button['label'], 'The wrapper and the label should not have the colors.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_colors() {
		return array(
			'a text color'       => array( array( 'text' => '#ff0000' ), 'color: #ff0000;' ),
			'a background color' => array( array( 'background' => '#00ff00' ), 'background-color: #00ff00;' ),
			'a gradient'         => array( array( 'gradient' => 'linear-gradient(red, blue)' ), 'background: linear-gradient(red, blue);' ),
			'all the colors'     => array(
				array(
					'gradient'   => 'linear-gradient(red, blue)',
					'background' => '#00ff00',
					'text'       => '#ff0000',
				),
				'color: #ff0000; background-color: #00ff00; background: linear-gradient(red, blue);',
			),
		);
	}

	/**
	 * Tests where the gradient of the background is added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_add_the_gradient_of_the_background_to_the_button_or_to_the_input() {
		$attributes = array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(red, blue)' ) ) );

		$with_button = styles_for_block_core_search( $attributes );
		$no_button   = styles_for_block_core_search( array_merge( $attributes, array( 'buttonPosition' => 'no-button' ) ) );

		$this->assertSame( 'background-image: linear-gradient(red, blue);', $this->declarations( $with_button, 'button' ) );
		$this->assertSame( '', $with_button['input'] );
		$this->assertSame( 'background-image: linear-gradient(red, blue);', $this->declarations( $no_button, 'input' ) );
		$this->assertSame( '', $no_button['button'] );
	}

	/**
	 * Tests that the colors that are empty are ignored.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_ignore_the_empty_colors() {
		$styles = styles_for_block_core_search(
			array(
				'style' => array(
					'color'      => array(
						'text'       => '',
						'background' => '0',
						'gradient'   => null,
					),
					'background' => array( 'gradient' => '' ),
				),
			)
		);

		$this->assertSame( '', $styles['button'] . $styles['input'] . $styles['wrapper'] . $styles['label'] );
	}

	/**
	 * Tests where the typography is added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_add_the_typography_to_the_label_the_button_and_the_input() {
		$styles = styles_for_block_core_search(
			array(
				'style' => array(
					'typography' => array(
						'fontSize'   => '20px',
						'fontWeight' => '700',
					),
				),
			)
		);

		foreach ( array( 'label', 'button', 'input' ) as $key ) {
			$this->assertSame( 'font-size: 20px; font-weight: 700;', $this->declarations( $styles, $key ), "The $key should have the typography." );
		}

		$this->assertSame( '', $styles['wrapper'], 'The wrapper should not have the typography.' );
	}

	/**
	 * Tests where the text decoration is added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 *
	 * @dataProvider data_text_decorations
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $label      Expected styles of the label.
	 */
	public function test_should_add_the_text_decoration_to_the_button_and_to_the_visible_label( $attributes, $label ) {
		$styles = styles_for_block_core_search(
			array_merge(
				array( 'style' => array( 'typography' => array( 'textDecoration' => 'underline' ) ) ),
				$attributes
			)
		);

		$this->assertSame( 'text-decoration: underline;', $this->declarations( $styles, 'button' ), 'The button should have the text decoration.' );
		$this->assertSame( '', $styles['input'], 'The input should not have the text decoration.' );
		$this->assertSame( '', $styles['wrapper'], 'The wrapper should not have the text decoration.' );
		$this->assertSame( $label, $this->declarations( $styles, 'label' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_decorations() {
		return array(
			'no value for the label' => array( array(), '' ),
			'a visible label'        => array( array( 'showLabel' => true ), 'text-decoration: underline;' ),
			'a hidden label'         => array( array( 'showLabel' => false ), '' ),
			'a label that is null'   => array( array( 'showLabel' => null ), '' ),
		);
	}

	/**
	 * Tests that the text decoration and the typography are added together.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_add_the_text_decoration_after_the_typography() {
		$styles = styles_for_block_core_search(
			array(
				'showLabel' => true,
				'style'     => array(
					'typography' => array(
						'fontSize'       => '20px',
						'textDecoration' => 'underline',
					),
				),
			)
		);

		$this->assertSame( 'font-size: 20px; text-decoration: underline;', $this->declarations( $styles, 'button' ) );
		$this->assertSame( 'font-size: 20px; text-decoration: underline;', $this->declarations( $styles, 'label' ) );
		$this->assertSame( 'font-size: 20px;', $this->declarations( $styles, 'input' ) );
	}

	/**
	 * Tests that the styles of the elements are escaped and filtered.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_filter_the_styles_that_are_not_safe() {
		$styles = styles_for_block_core_search(
			array(
				'style' => array(
					'color' => array(
						'text'       => 'expression( alert( 1 ) )',
						'background' => '#00ff00',
					),
				),
			)
		);

		$this->assertSame( 'background-color: #00ff00;', $this->declarations( $styles, 'button' ), 'The value that is not safe should be removed.' );
	}

	/**
	 * Tests that the styles of the input are filtered when there is no button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_filter_the_styles_of_the_input_that_are_not_safe() {
		$styles = styles_for_block_core_search(
			array(
				'buttonPosition' => 'no-button',
				'style'          => array(
					'color' => array(
						'text'       => 'expression( alert( 1 ) )',
						'background' => '#00ff00',
					),
				),
			)
		);

		$this->assertSame( 'background-color: #00ff00;', $this->declarations( $styles, 'input' ) );
	}

	/**
	 * Tests that the wrapper gets the border, the radius and the shadow together when the button is inside.
	 *
	 * @ticket 66534
	 *
	 * @covers ::styles_for_block_core_search
	 */
	public function test_should_add_all_the_styles_of_the_wrapper_when_the_button_is_inside() {
		$styles = styles_for_block_core_search(
			array(
				'buttonPosition' => 'button-inside',
				'width'          => 60,
				'widthUnit'      => '%',
				'style'          => array(
					'border' => array(
						'width'  => '1px',
						'radius' => '10px',
					),
					'shadow' => '1px 1px 2px #000000',
				),
			)
		);

		$this->assertSame(
			'width: 60%; border-width: 1px; border-radius: calc(10px + 4px); box-shadow: 1px 1px 2px #000000;',
			$this->declarations( $styles, 'wrapper' )
		);
		$this->assertSame( 'border-radius: 10px;', $this->declarations( $styles, 'input' ) );
		$this->assertSame( 'border-radius: 10px;', $this->declarations( $styles, 'button' ) );
	}
}
