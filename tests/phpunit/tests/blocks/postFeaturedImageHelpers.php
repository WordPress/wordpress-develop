<?php
/**
 * Tests for the helper functions of the Post Featured Image block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_PostFeaturedImageHelpers extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Tests the attributes of the border of the image.
	 *
	 * @ticket 66546
	 *
	 * @covers ::get_block_core_post_featured_image_border_attributes
	 *
	 * @dataProvider data_borders
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $expected   Expected attributes of the image.
	 */
	public function test_should_build_the_attributes_of_the_border( $attributes, $expected ) {
		$this->assertSame( $expected, get_block_core_post_featured_image_border_attributes( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_borders() {
		return array(
			'no border'                  => array( array(), array() ),
			'a radius'                   => array( array( 'style' => array( 'border' => array( 'radius' => '5px' ) ) ), array( 'style' => 'border-radius:5px;' ) ),
			'a width'                    => array( array( 'style' => array( 'border' => array( 'width' => '2px' ) ) ), array( 'style' => 'border-width:2px;' ) ),
			'a style'                    => array( array( 'style' => array( 'border' => array( 'style' => 'dashed' ) ) ), array( 'style' => 'border-style:dashed;' ) ),
			'a custom color'             => array(
				array( 'style' => array( 'border' => array( 'color' => '#ff0000' ) ) ),
				array(
					'class' => 'has-border-color',
					'style' => 'border-color:#ff0000;',
				),
			),
			'a named color'              => array( array( 'borderColor' => 'vivid-red' ), array( 'class' => 'has-border-color has-vivid-red-border-color' ) ),
			'a named and a custom color' => array(
				array(
					'borderColor' => 'cyan',
					'style'       => array( 'border' => array( 'color' => '#ff0000' ) ),
				),
				array( 'class' => 'has-border-color has-cyan-border-color' ),
			),
			'a side'                     => array(
				array(
					'style' => array(
						'border' => array(
							'top' => array(
								'width' => '1px',
								'color' => '#00ff00',
								'style' => 'solid',
							),
						),
					),
				),
				array( 'style' => 'border-top-color:#00ff00;border-top-style:solid;border-top-width:1px;' ),
			),
			'other styles'               => array( array( 'style' => array( 'color' => array( 'text' => '#ff0000' ) ) ), array() ),
		);
	}

	/**
	 * Tests the markup of the overlay.
	 *
	 * @ticket 66546
	 *
	 * @covers ::get_block_core_post_featured_image_overlay_element_markup
	 *
	 * @dataProvider data_overlays
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $classes    Expected classes of the overlay.
	 * @param string $style      Expected style of the overlay.
	 */
	public function test_should_build_the_markup_of_the_overlay( $attributes, $classes, $style ) {
		$this->assertSame(
			'<span class="wp-block-post-featured-image__overlay ' . $classes . '" style="' . $style . '" aria-hidden="true"></span>',
			get_block_core_post_featured_image_overlay_element_markup( $attributes )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_overlays() {
		return array(
			'a dim ratio'             => array( array( 'dimRatio' => 50 ), 'has-background-dim has-background-dim-50', '' ),
			'a named color'           => array(
				array(
					'dimRatio'     => 50,
					'overlayColor' => 'vivid-red',
				),
				'has-background-dim has-background-dim-50 has-vivid-red-background-color',
				'',
			),
			'a custom color'          => array(
				array(
					'dimRatio'           => 50,
					'customOverlayColor' => '#ff0000',
				),
				'has-background-dim has-background-dim-50',
				'background-color: #ff0000',
			),
			'a named gradient'        => array(
				array(
					'dimRatio' => 50,
					'gradient' => 'sunset',
				),
				'has-background-dim has-background-dim-50 has-background-gradient has-sunset-gradient-background',
				'',
			),
			'a custom gradient'       => array(
				array(
					'dimRatio'       => 50,
					'customGradient' => 'linear-gradient(red,blue)',
				),
				'has-background-dim has-background-dim-50 has-background-gradient',
				'background-image: linear-gradient(red,blue)',
			),
			'everything and a border' => array(
				array(
					'dimRatio'           => 30,
					'overlayColor'       => 'cyan',
					'customOverlayColor' => '#00ffff',
					'gradient'           => 'sunset',
					'customGradient'     => 'linear-gradient(red,blue)',
					'style'              => array(
						'border' => array(
							'radius' => '4px',
							'width'  => '1px',
						),
					),
				),
				'has-background-dim has-background-dim-30 has-cyan-background-color has-background-gradient has-sunset-gradient-background',
				'border-radius:4px;border-width:1px;background-image: linear-gradient(red,blue);background-color: #00ffff',
			),
		);
	}

	/**
	 * Tests that the classes of the overlay are escaped.
	 *
	 * @ticket 66546
	 *
	 * @covers ::get_block_core_post_featured_image_overlay_element_markup
	 */
	public function test_should_escape_the_classes_of_the_overlay() {
		$output = get_block_core_post_featured_image_overlay_element_markup(
			array(
				'dimRatio'     => 50,
				'overlayColor' => '"><script>',
			)
		);

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'has-&quot;&gt;&lt;script&gt;-background-color', $output );
	}

	/**
	 * Tests the border classes of the overlay.
	 *
	 * @ticket 66546
	 *
	 * @covers ::get_block_core_post_featured_image_overlay_element_markup
	 */
	public function test_should_add_the_border_classes_to_the_overlay() {
		$output = get_block_core_post_featured_image_overlay_element_markup(
			array(
				'dimRatio'    => 50,
				'borderColor' => 'cyan',
			)
		);

		$this->assertStringContainsString( 'class="wp-block-post-featured-image__overlay has-border-color has-cyan-border-color has-background-dim has-background-dim-50"', $output );
	}

	/**
	 * Tests that there is no overlay without a dim ratio.
	 *
	 * @ticket 66546
	 *
	 * @covers ::get_block_core_post_featured_image_overlay_element_markup
	 *
	 * @dataProvider data_attributes_without_an_overlay
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_not_build_an_overlay_without_a_dim_ratio( $attributes ) {
		$this->assertSame( '', get_block_core_post_featured_image_overlay_element_markup( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_an_overlay() {
		return array(
			'no attributes'              => array( array() ),
			'a dim ratio of zero'        => array( array( 'dimRatio' => 0 ) ),
			'a null dim ratio'           => array( array( 'dimRatio' => null ) ),
			'colors without a dim ratio' => array(
				array(
					'overlayColor'       => 'cyan',
					'customOverlayColor' => '#00ffff',
					'gradient'           => 'sunset',
				),
			),
		);
	}
}
