<?php
/**
 * Tests for the server-side rendering of the Icon block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group icons
 *
 * @covers ::render_block_core_icon
 */
class Tests_Blocks_RenderIcon extends WP_UnitTestCase {

	/**
	 * Slug of the public collection registered for the tests.
	 */
	const PUBLIC_COLLECTION = 'icon-block-public';

	/**
	 * Slug of the collection that is not public, registered for the tests.
	 */
	const PRIVATE_COLLECTION = 'icon-block-private';

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
			'blockName' => 'core/icon',
			'attrs'     => array(),
		);

		$collections = WP_Icon_Collections_Registry::get_instance();
		$collections->register( self::PUBLIC_COLLECTION, array( 'label' => 'Public' ) );
		$collections->register(
			self::PRIVATE_COLLECTION,
			array(
				'label'  => 'Private',
				'public' => false,
			)
		);

		$icons = WP_Icons_Registry::get_instance();
		$icons->register(
			self::PUBLIC_COLLECTION . '/square',
			array(
				'label'   => 'Square',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/></svg>',
			)
		);
		$icons->register(
			self::PUBLIC_COLLECTION . '/outline',
			array(
				'label'   => 'Outline',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill:none;"><path d="M4 4h16v16H4z"/></svg>',
			)
		);
		$icons->register(
			self::PRIVATE_COLLECTION . '/hidden',
			array(
				'label'   => 'Hidden',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/></svg>',
			)
		);
	}

	public function tear_down() {
		$collections = WP_Icon_Collections_Registry::get_instance();
		foreach ( array( self::PUBLIC_COLLECTION, self::PRIVATE_COLLECTION ) as $slug ) {
			if ( $collections->is_registered( $slug ) ) {
				$collections->unregister( $slug );
			}
		}

		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block. The icon is a registered icon by default.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array() ) {
		$attributes = array_merge( array( 'icon' => self::PUBLIC_COLLECTION . '/square' ), $attributes );

		return render_block_core_icon( $attributes );
	}

	/**
	 * Returns the attributes of the svg element of the output.
	 *
	 * @param string $output The output.
	 * @return array The attributes.
	 */
	private function get_svg( $output ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'svg' ), 'The output should have an svg element.' );

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the CSS classes of the svg element as a sorted list.
	 *
	 * @param array $svg The attributes of the svg element.
	 * @return string[] The classes.
	 */
	private function get_classes( $svg ) {
		$classes = preg_split( '/\s+/', $svg['class'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		sort( $classes );

		return $classes;
	}

	/**
	 * Returns the CSS classes of the first element of the output as a sorted list.
	 *
	 * @param string $output The output.
	 * @param string $tag    The tag name.
	 * @return string[] The classes.
	 */
	private function get_element_classes( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag( $tag );

		$classes = preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY );
		sort( $classes );

		return $classes;
	}

	/**
	 * Tests the icon in the wrapper.
	 *
	 * @ticket 66586
	 */
	public function test_should_render_the_icon_in_the_wrapper() {
		$output = $this->render();
		$svg    = $this->get_svg( $output );

		$this->assertStringStartsWith( '<div class="wp-block-icon"><svg ', $output );
		$this->assertStringEndsWith( '</svg></div>', $output );
		$this->assertSame( 'true', $svg['aria-hidden'] );
		$this->assertSame( 'false', $svg['focusable'] );
		$this->assertArrayNotHasKey( 'width', $svg, 'The size is left to the block styles.' );
		$this->assertArrayNotHasKey( 'height', $svg, 'The size is left to the block styles.' );
		$this->assertArrayNotHasKey( 'style', $svg );
		$this->assertArrayNotHasKey( 'class', $svg );
	}

	/**
	 * Tests that nothing is rendered without a usable icon.
	 *
	 * @ticket 66586
	 *
	 * @dataProvider data_no_icon
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_render_nothing_without_a_usable_icon( $attributes ) {
		$this->assertNull( render_block_core_icon( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_icon() {
		return array(
			'no attributes'          => array( array() ),
			'empty icon'             => array( array( 'icon' => '' ) ),
			'icon is not a string'   => array( array( 'icon' => array( 'icon-block-public/square' ) ) ),
			'icon is null'           => array( array( 'icon' => null ) ),
			'icon is not registered' => array( array( 'icon' => 'icon-block-public/missing' ) ),
		);
	}

	/**
	 * Tests that an icon of a collection that is not public is not rendered.
	 *
	 * @ticket 66586
	 */
	public function test_should_render_nothing_for_an_icon_of_a_collection_that_is_not_public() {
		$this->assertNull( render_block_core_icon( array( 'icon' => self::PRIVATE_COLLECTION . '/hidden' ) ) );
	}

	/**
	 * Tests the accessible label.
	 *
	 * @ticket 66586
	 */
	public function test_should_label_the_icon() {
		$svg = $this->get_svg( $this->render( array( 'ariaLabel' => 'A square' ) ) );

		$this->assertSame( 'img', $svg['role'] );
		$this->assertSame( 'A square', $svg['aria-label'] );
		$this->assertArrayNotHasKey( 'aria-hidden', $svg );
		$this->assertArrayNotHasKey( 'focusable', $svg );
	}

	/**
	 * Tests the colors of the text and of the background.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_preset_colors() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'textColor'       => 'vivid-red',
					'backgroundColor' => 'cyan-bluish-gray',
				)
			)
		);

		$this->assertSame(
			array( 'has-background', 'has-cyan-bluish-gray-background-color', 'has-text-color', 'has-vivid-red-color' ),
			$this->get_classes( $svg )
		);
		$this->assertStringContainsString( 'color:var(--wp--preset--color--vivid-red)', $svg['style'] );
		$this->assertStringContainsString( 'background-color:var(--wp--preset--color--cyan-bluish-gray)', $svg['style'] );
	}

	/**
	 * Tests the custom colors of the text and of the background.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_custom_colors() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'style' => array(
						'color' => array(
							'text'       => '#111111',
							'background' => '#eeeeee',
						),
					),
				)
			)
		);

		$this->assertSame( array( 'has-background', 'has-text-color' ), $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'color:#111111', $svg['style'] );
		$this->assertStringContainsString( 'background-color:#eeeeee', $svg['style'] );
	}

	/**
	 * Tests that a preset color wins over a custom color.
	 *
	 * @ticket 66586
	 */
	public function test_should_prefer_the_preset_colors_to_the_custom_colors() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'textColor'       => 'vivid-red',
					'backgroundColor' => 'cyan-bluish-gray',
					'style'           => array(
						'color' => array(
							'text'       => '#111111',
							'background' => '#eeeeee',
						),
					),
				)
			)
		);

		$this->assertContains( 'has-vivid-red-color', $this->get_classes( $svg ) );
		$this->assertContains( 'has-cyan-bluish-gray-background-color', $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'color:var(--wp--preset--color--vivid-red)', $svg['style'] );
		$this->assertStringNotContainsString( '#111111', $svg['style'] );
		$this->assertStringNotContainsString( '#eeeeee', $svg['style'] );
	}

	/**
	 * Tests the border.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_border() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'borderColor' => 'vivid-red',
					'style'       => array(
						'border' => array(
							'radius' => '9px',
							'style'  => 'dashed',
							'width'  => '2px',
						),
					),
				)
			)
		);

		$this->assertSame( array( 'has-border-color', 'has-vivid-red-border-color' ), $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'border-radius:9px', $svg['style'] );
		$this->assertStringContainsString( 'border-style:dashed', $svg['style'] );
		$this->assertStringContainsString( 'border-width:2px', $svg['style'] );
	}

	/**
	 * Tests the custom border color, and that a preset border color wins over it.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_custom_border_color() {
		$attributes = array(
			'style' => array(
				'border' => array(
					'color' => '#ff0000',
				),
			),
		);

		$svg = $this->get_svg( $this->render( $attributes ) );

		$this->assertSame( array( 'has-border-color' ), $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'border-color:#ff0000', $svg['style'] );

		$attributes['borderColor'] = 'vivid-red';

		$svg = $this->get_svg( $this->render( $attributes ) );

		$this->assertContains( 'has-vivid-red-border-color', $this->get_classes( $svg ) );
		$this->assertStringNotContainsString( '#ff0000', $svg['style'] ?? '' );
	}

	/**
	 * Tests the border of one side.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_border_of_one_side() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'style' => array(
						'border' => array(
							'top' => array(
								'color' => '#ff0000',
								'style' => 'solid',
								'width' => '3px',
							),
						),
					),
				)
			)
		);

		$this->assertStringContainsString( 'border-top-color:#ff0000', $svg['style'] );
		$this->assertStringContainsString( 'border-top-style:solid', $svg['style'] );
		$this->assertStringContainsString( 'border-top-width:3px', $svg['style'] );
		$this->assertStringNotContainsString( 'border-bottom', $svg['style'] );
	}

	/**
	 * Tests the shadow, the padding and the dimensions.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_shadow_the_padding_and_the_dimensions() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'style' => array(
						'shadow'     => 'var:preset|shadow|natural',
						'spacing'    => array(
							'padding' => '4px',
							'margin'  => '10px',
						),
						'dimensions' => array(
							'width'     => '48px',
							'minWidth'  => '24px',
							'minHeight' => '12px',
							'height'    => '100px',
						),
					),
				)
			)
		);

		$this->assertStringContainsString( 'box-shadow:var(--wp--preset--shadow--natural)', $svg['style'] );
		$this->assertStringContainsString( 'padding:4px', $svg['style'] );
		$this->assertStringContainsString( 'width:48px', $svg['style'] );
		$this->assertStringContainsString( 'min-width:24px', $svg['style'] );
		$this->assertStringContainsString( 'min-height:12px', $svg['style'] );
		$this->assertStringNotContainsString( 'margin', $svg['style'], 'The margin is not applied to the icon.' );
		$this->assertStringNotContainsString( 'height:100px', $svg['style'], 'The height is not applied to the icon.' );
	}

	/**
	 * Tests the background image, with the default size.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_the_background_image_with_the_default_size() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'style' => array(
						'background' => array(
							'backgroundImage' => array( 'url' => 'https://example.org/image.png' ),
						),
					),
				)
			)
		);

		$this->assertContains( 'has-background', $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'background-image:url(\'https://example.org/image.png\')', $svg['style'] );
		$this->assertStringContainsString( 'background-size:cover', $svg['style'] );
	}

	/**
	 * Tests the position of a background image that is contained.
	 *
	 * @ticket 66586
	 */
	public function test_should_center_a_contained_background_image() {
		$background = array(
			'backgroundImage' => array( 'url' => 'https://example.org/image.png' ),
			'backgroundSize'  => 'contain',
		);

		$svg = $this->get_svg( $this->render( array( 'style' => array( 'background' => $background ) ) ) );

		$this->assertStringContainsString( 'background-size:contain', $svg['style'] );
		$this->assertStringContainsString( 'background-position:50% 50%', $svg['style'] );

		$background['backgroundPosition'] = '10% 20%';

		$svg = $this->get_svg( $this->render( array( 'style' => array( 'background' => $background ) ) ) );

		$this->assertStringContainsString( 'background-position:10% 20%', $svg['style'] );
		$this->assertStringNotContainsString( '50% 50%', $svg['style'] );
	}

	/**
	 * Tests that a gradient is applied without the background image classes and defaults.
	 *
	 * @ticket 66586
	 */
	public function test_should_apply_a_gradient() {
		$svg = $this->get_svg(
			$this->render(
				array(
					'style' => array(
						'background' => array(
							'gradient' => 'linear-gradient(#000,#fff)',
						),
					),
				)
			)
		);

		$this->assertContains( 'has-background', $this->get_classes( $svg ) );
		$this->assertStringContainsString( 'background-image:linear-gradient(#000,#fff)', $svg['style'] );
		$this->assertStringNotContainsString( 'background-size', $svg['style'] );
	}

	/**
	 * Tests that the style of the icon is kept and the styles of the block come after it.
	 *
	 * @ticket 66586
	 */
	public function test_should_keep_the_style_of_the_icon() {
		$attributes = array( 'icon' => self::PUBLIC_COLLECTION . '/outline' );

		$this->assertSame( 'fill:none', $this->get_svg( $this->render( $attributes ) )['style'], 'The style of the icon should stay.' );

		$attributes['style'] = array( 'color' => array( 'text' => '#111111' ) );

		$this->assertSame( 'fill:none; color:#111111;', $this->get_svg( $this->render( $attributes ) )['style'] );
	}

	/**
	 * Tests the flips.
	 *
	 * @ticket 66586
	 *
	 * @dataProvider data_flips
	 *
	 * @param array    $attributes The flip attributes.
	 * @param string[] $expected   The expected classes.
	 */
	public function test_should_flip_the_icon( $attributes, $expected ) {
		$this->assertSame( $expected, $this->get_classes( $this->get_svg( $this->render( $attributes ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_flips() {
		return array(
			'horizontal' => array( array( 'flipHorizontal' => true ), array( 'is-flip-horizontal' ) ),
			'vertical'   => array( array( 'flipVertical' => true ), array( 'is-flip-vertical' ) ),
			'both'       => array(
				array(
					'flipHorizontal' => true,
					'flipVertical'   => true,
				),
				array( 'is-flip-horizontal', 'is-flip-vertical' ),
			),
			'both off'   => array(
				array(
					'flipHorizontal' => false,
					'flipVertical'   => false,
				),
				array(),
			),
		);
	}

	/**
	 * Tests the rotation.
	 *
	 * @ticket 66586
	 *
	 * @dataProvider data_rotations
	 *
	 * @param mixed       $rotation The rotation attribute.
	 * @param string|null $expected The expected style, or null for none.
	 */
	public function test_should_rotate_the_icon( $rotation, $expected ) {
		$svg = $this->get_svg( $this->render( array( 'rotation' => $rotation ) ) );

		if ( null === $expected ) {
			$this->assertArrayNotHasKey( 'style', $svg );
		} else {
			$this->assertSame( $expected, $svg['style'] );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_rotations() {
		return array(
			'degrees'          => array( 90, 'rotate: 90deg;' ),
			'negative degrees' => array( -45, 'rotate: -45deg;' ),
			'string'           => array( '45', 'rotate: 45deg;' ),
			'fraction'         => array( 45.9, 'rotate: 45deg;' ),
			'zero'             => array( 0, null ),
		);
	}

	/**
	 * Tests that the rotation is added to the existing styles.
	 *
	 * @ticket 66586
	 */
	public function test_should_add_the_rotation_to_the_existing_styles() {
		$attributes = array(
			'icon'     => self::PUBLIC_COLLECTION . '/outline',
			'rotation' => 90,
		);

		$this->assertSame( 'fill:none; rotate: 90deg;', $this->get_svg( $this->render( $attributes ) )['style'] );

		$attributes['style'] = array( 'color' => array( 'text' => '#111111' ) );

		$this->assertSame( 'fill:none; color:#111111; rotate: 90deg;', $this->get_svg( $this->render( $attributes ) )['style'] );
	}

	/**
	 * Tests the wrapper attributes of the block supports.
	 *
	 * @ticket 66586
	 */
	public function test_should_add_the_wrapper_attributes() {
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/icon',
			'attrs'     => array( 'align' => 'center' ),
		);

		$wrapper = $this->get_element_classes( $this->render(), 'div' );

		$this->assertSame( array( 'aligncenter', 'wp-block-icon' ), $wrapper );
	}
}
