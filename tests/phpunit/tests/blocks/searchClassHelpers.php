<?php
/**
 * Tests for the functions of the Search block that build its classes and its typography styles.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_SearchClassHelpers extends WP_UnitTestCase {

	/**
	 * Tests the classes of the position of the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::classnames_for_block_core_search
	 *
	 * @dataProvider data_classnames
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected classes.
	 */
	public function test_should_build_the_classes_of_the_block( $attributes, $expected ) {
		$this->assertSame( $expected, classnames_for_block_core_search( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classnames() {
		return array(
			'no attributes'                        => array( array(), '' ),
			'an empty position'                    => array( array( 'buttonPosition' => '' ), '' ),
			'a position that is not known'         => array( array( 'buttonPosition' => 'elsewhere' ), '' ),
			'a button inside'                      => array( array( 'buttonPosition' => 'button-inside' ), 'wp-block-search__button-inside' ),
			'a button outside'                     => array( array( 'buttonPosition' => 'button-outside' ), 'wp-block-search__button-outside' ),
			'no button'                            => array( array( 'buttonPosition' => 'no-button' ), 'wp-block-search__no-button' ),
			'a button only'                        => array( array( 'buttonPosition' => 'button-only' ), 'wp-block-search__button-only wp-block-search__searchfield-hidden' ),
			'an icon without a position'           => array( array( 'buttonUseIcon' => true ), '' ),
			'a text button without a position'     => array( array( 'buttonUseIcon' => false ), '' ),
			'an icon button'                       => array(
				array(
					'buttonPosition' => 'button-inside',
					'buttonUseIcon'  => true,
				),
				'wp-block-search__button-inside wp-block-search__icon-button',
			),
			'a text button'                        => array(
				array(
					'buttonPosition' => 'button-outside',
					'buttonUseIcon'  => false,
				),
				'wp-block-search__button-outside wp-block-search__text-button',
			),
			'an icon without a button'             => array(
				array(
					'buttonPosition' => 'no-button',
					'buttonUseIcon'  => true,
				),
				'wp-block-search__no-button',
			),
			'an icon button that is only a button' => array(
				array(
					'buttonPosition' => 'button-only',
					'buttonUseIcon'  => true,
				),
				'wp-block-search__button-only wp-block-search__searchfield-hidden wp-block-search__icon-button',
			),
			'an icon that is null'                 => array(
				array(
					'buttonPosition' => 'button-inside',
					'buttonUseIcon'  => null,
				),
				'wp-block-search__button-inside',
			),
		);
	}

	/**
	 * Tests the classes of the named typography.
	 *
	 * @ticket 66534
	 *
	 * @covers ::get_typography_classes_for_block_core_search
	 *
	 * @dataProvider data_typography_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected classes.
	 */
	public function test_should_build_the_typography_classes( $attributes, $expected ) {
		$this->assertSame( $expected, get_typography_classes_for_block_core_search( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_typography_classes() {
		return array(
			'no attributes'        => array( array(), '' ),
			'empty values'         => array(
				array(
					'fontSize'   => '',
					'fontFamily' => '',
				),
				'',
			),
			'a font size'          => array( array( 'fontSize' => 'large' ), 'has-large-font-size' ),
			'a font family'        => array( array( 'fontFamily' => 'system-sans' ), 'has-system-sans-font-family' ),
			'both'                 => array(
				array(
					'fontFamily' => 'serif',
					'fontSize'   => 'small',
				),
				'has-small-font-size has-serif-font-family',
			),
			'a family with markup' => array( array( 'fontFamily' => '"><script>' ), 'has-&quot;&gt;&lt;script&gt;-font-family' ),
			'values with markup'   => array( array( 'fontSize' => '"><script>' ), 'has-&quot;&gt;&lt;script&gt;-font-size' ),
		);
	}

	/**
	 * Tests the styles of the typography.
	 *
	 * @ticket 66534
	 *
	 * @covers ::get_typography_styles_for_block_core_search
	 *
	 * @dataProvider data_typography_styles
	 *
	 * @param array  $typography Typography of the style attribute.
	 * @param string $expected   Expected styles.
	 */
	public function test_should_build_the_typography_styles( $typography, $expected ) {
		$this->assertSame( $expected, get_typography_styles_for_block_core_search( array( 'style' => array( 'typography' => $typography ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_typography_styles() {
		return array(
			'nothing'        => array( array(), '' ),
			'empty values'   => array(
				array(
					'fontSize'   => '',
					'fontFamily' => '0',
				),
				'',
			),
			'a font size'    => array( array( 'fontSize' => '20px' ), 'font-size: 20px;' ),
			'a font family'  => array( array( 'fontFamily' => 'Georgia, serif' ), 'font-family: Georgia, serif;' ),
			'letter spacing' => array( array( 'letterSpacing' => '2px' ), 'letter-spacing: 2px;' ),
			'a font weight'  => array( array( 'fontWeight' => '700' ), 'font-weight: 700;' ),
			'a font style'   => array( array( 'fontStyle' => 'italic' ), 'font-style: italic;' ),
			'a line height'  => array( array( 'lineHeight' => '1.5' ), 'line-height: 1.5;' ),
			'a transform'    => array( array( 'textTransform' => 'uppercase' ), 'text-transform: uppercase;' ),
			'all of them'    => array(
				array(
					'textTransform' => 'uppercase',
					'lineHeight'    => '1.5',
					'fontStyle'     => 'italic',
					'fontWeight'    => '700',
					'letterSpacing' => '2px',
					'fontFamily'    => 'serif',
					'fontSize'      => '20px',
				),
				'font-size: 20px;font-family: serif;letter-spacing: 2px;font-weight: 700;font-style: italic;line-height: 1.5;text-transform: uppercase;',
			),
		);
	}

	/**
	 * Tests that the text decoration is not one of the typography styles.
	 *
	 * @ticket 66534
	 *
	 * @covers ::get_typography_styles_for_block_core_search
	 */
	public function test_should_not_include_the_text_decoration() {
		$this->assertSame( '', get_typography_styles_for_block_core_search( array( 'style' => array( 'typography' => array( 'textDecoration' => 'underline' ) ) ) ) );
	}

	/**
	 * Tests the classes of the border color.
	 *
	 * @ticket 66534
	 *
	 * @covers ::get_border_color_classes_for_block_core_search
	 *
	 * @dataProvider data_border_color_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected classes.
	 */
	public function test_should_build_the_border_color_classes( $attributes, $expected ) {
		$this->assertSame( $expected, get_border_color_classes_for_block_core_search( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_border_color_classes() {
		return array(
			'no attributes'       => array( array(), '' ),
			'empty values'        => array(
				array(
					'borderColor' => '',
					'style'       => array( 'border' => array( 'color' => '' ) ),
				),
				'',
			),
			'a custom color'      => array( array( 'style' => array( 'border' => array( 'color' => '#ff0000' ) ) ), 'has-border-color' ),
			'a named color'       => array( array( 'borderColor' => 'vivid-red' ), 'has-border-color has-vivid-red-border-color' ),
			'both colors'         => array(
				array(
					'borderColor' => 'cyan',
					'style'       => array( 'border' => array( 'color' => '#ff0000' ) ),
				),
				'has-border-color has-cyan-border-color',
			),
			'a value with markup' => array( array( 'borderColor' => '"><script>' ), 'has-border-color has-&quot;&gt;&lt;script&gt;-border-color' ),
			'a color of a side'   => array( array( 'style' => array( 'border' => array( 'top' => array( 'color' => '#ff0000' ) ) ) ), '' ),
		);
	}

	/**
	 * Tests the classes of the colors.
	 *
	 * @ticket 66534
	 *
	 * @covers ::get_color_classes_for_block_core_search
	 *
	 * @dataProvider data_color_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected classes.
	 */
	public function test_should_build_the_color_classes( $attributes, $expected ) {
		$this->assertSame( $expected, get_color_classes_for_block_core_search( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_color_classes() {
		return array(
			'no attributes'                     => array( array(), '' ),
			'empty values'                      => array(
				array(
					'textColor'       => '',
					'backgroundColor' => '',
					'gradient'        => '',
				),
				'',
			),
			'a named text color'                => array( array( 'textColor' => 'vivid-red' ), 'has-text-color has-vivid-red-color' ),
			'a custom text color'               => array( array( 'style' => array( 'color' => array( 'text' => '#ff0000' ) ) ), 'has-text-color' ),
			'a named and a custom text color'   => array(
				array(
					'textColor' => 'cyan',
					'style'     => array( 'color' => array( 'text' => '#ff0000' ) ),
				),
				'has-text-color has-cyan-color',
			),
			'a named background color'          => array( array( 'backgroundColor' => 'cyan' ), 'has-background has-cyan-background-color' ),
			'a custom background color'         => array( array( 'style' => array( 'color' => array( 'background' => '#00ffff' ) ) ), 'has-background' ),
			'a named gradient'                  => array( array( 'gradient' => 'sunset' ), 'has-background has-sunset-gradient-background' ),
			'a custom gradient'                 => array( array( 'style' => array( 'color' => array( 'gradient' => 'linear-gradient(red, blue)' ) ) ), 'has-background' ),
			'a gradient of the background'      => array( array( 'style' => array( 'background' => array( 'gradient' => 'linear-gradient(red, blue)' ) ) ), 'has-background' ),
			'a named background and a gradient' => array(
				array(
					'backgroundColor' => 'cyan',
					'gradient'        => 'sunset',
				),
				'has-background has-cyan-background-color has-sunset-gradient-background',
			),
			'everything'                        => array(
				array(
					'textColor'       => 'red',
					'backgroundColor' => 'blue',
					'gradient'        => 'sunset',
				),
				'has-text-color has-red-color has-background has-blue-background-color has-sunset-gradient-background',
			),
		);
	}
}
