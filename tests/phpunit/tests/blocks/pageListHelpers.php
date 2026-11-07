<?php
/**
 * Tests for the helper functions of the Page List block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_PageListHelpers extends WP_UnitTestCase {

	/**
	 * Tests the visibility of the submenus.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_get_submenu_visibility
	 *
	 * @dataProvider data_submenu_visibility
	 *
	 * @param array  $context  The context of the navigation block.
	 * @param string $expected The expected visibility.
	 */
	public function test_should_get_the_submenu_visibility( $context, $expected ) {
		$this->assertSame( $expected, block_core_page_list_get_submenu_visibility( $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_submenu_visibility() {
		return array(
			'no context'                             => array( array(), 'hover' ),
			'visibility click'                       => array( array( 'submenuVisibility' => 'click' ), 'click' ),
			'visibility always'                      => array( array( 'submenuVisibility' => 'always' ), 'always' ),
			'visibility hover'                       => array( array( 'submenuVisibility' => 'hover' ), 'hover' ),
			'null visibility'                        => array( array( 'submenuVisibility' => null ), 'hover' ),
			'deprecated attribute on'                => array( array( 'openSubmenusOnClick' => true ), 'click' ),
			'deprecated attribute off'               => array( array( 'openSubmenusOnClick' => false ), 'hover' ),
			'deprecated attribute is a string'       => array( array( 'openSubmenusOnClick' => '1' ), 'click' ),
			'deprecated attribute is empty string'   => array( array( 'openSubmenusOnClick' => '' ), 'hover' ),
			'deprecated attribute is null'           => array( array( 'openSubmenusOnClick' => null ), 'hover' ),
			'deprecated attribute is null with mode' => array(
				array(
					'openSubmenusOnClick' => null,
					'submenuVisibility'   => 'always',
				),
				'always',
			),
			'deprecated attribute on wins'           => array(
				array(
					'openSubmenusOnClick' => true,
					'submenuVisibility'   => 'always',
				),
				'click',
			),
			'deprecated attribute off wins'          => array(
				array(
					'openSubmenusOnClick' => false,
					'submenuVisibility'   => 'always',
				),
				'hover',
			),
		);
	}

	/**
	 * Tests the classes and styles of the colors of the navigation block.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_build_css_colors
	 *
	 * @dataProvider data_colors
	 *
	 * @param array $context  The context of the navigation block.
	 * @param array $expected The expected values that differ from the empty result.
	 */
	public function test_should_build_the_css_colors( $context, $expected ) {
		$expected = array_merge(
			array(
				'css_classes'           => array(),
				'inline_styles'         => '',
				'overlay_css_classes'   => array(),
				'overlay_inline_styles' => '',
			),
			$expected
		);

		$this->assertSame( $expected, block_core_page_list_build_css_colors( array(), $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_colors() {
		return array(
			'no colors'                               => array( array(), array() ),
			'named text color'                        => array(
				array( 'textColor' => 'vivid-red' ),
				array( 'css_classes' => array( 'has-text-color', 'has-vivid-red-color' ) ),
			),
			'named text color is kebab cased'         => array(
				array( 'textColor' => 'vividRed' ),
				array( 'css_classes' => array( 'has-text-color', 'has-vivid-red-color' ) ),
			),
			'named background color is kebab cased'   => array(
				array( 'backgroundColor' => 'palePink' ),
				array( 'css_classes' => array( 'has-background', 'has-pale-pink-background-color' ) ),
			),
			'named overlay text color is kebab cased' => array(
				array( 'overlayTextColor' => 'luminousVividAmber' ),
				array( 'overlay_css_classes' => array( 'has-text-color', 'has-luminous-vivid-amber-color' ) ),
			),
			'named overlay background is kebab cased' => array(
				array( 'overlayBackgroundColor' => 'cyanBluishGray' ),
				array( 'overlay_css_classes' => array( 'has-background', 'has-cyan-bluish-gray-background-color' ) ),
			),
			'picked text color'                       => array(
				array( 'customTextColor' => '#112233' ),
				array(
					'css_classes'   => array( 'has-text-color' ),
					'inline_styles' => 'color: #112233;',
				),
			),
			'style text color'                        => array(
				array( 'style' => array( 'color' => array( 'text' => '#445566' ) ) ),
				array(
					'css_classes'   => array( 'has-text-color' ),
					'inline_styles' => 'color: #445566;',
				),
			),
			'named text color wins'                   => array(
				array(
					'textColor'       => 'vivid-red',
					'customTextColor' => '#112233',
					'style'           => array( 'color' => array( 'text' => '#445566' ) ),
				),
				array( 'css_classes' => array( 'has-text-color', 'has-vivid-red-color' ) ),
			),
			'picked text color wins over style'       => array(
				array(
					'customTextColor' => '#112233',
					'style'           => array( 'color' => array( 'text' => '#445566' ) ),
				),
				array(
					'css_classes'   => array( 'has-text-color' ),
					'inline_styles' => 'color: #112233;',
				),
			),
			'named background color'                  => array(
				array( 'backgroundColor' => 'pale-pink' ),
				array( 'css_classes' => array( 'has-background', 'has-pale-pink-background-color' ) ),
			),
			'picked background color'                 => array(
				array( 'customBackgroundColor' => '#778899' ),
				array(
					'css_classes'   => array( 'has-background' ),
					'inline_styles' => 'background-color: #778899;',
				),
			),
			'style background color'                  => array(
				array( 'style' => array( 'color' => array( 'background' => '#aabbcc' ) ) ),
				array(
					'css_classes'   => array( 'has-background' ),
					'inline_styles' => 'background-color: #aabbcc;',
				),
			),
			'named background color wins'             => array(
				array(
					'backgroundColor'       => 'pale-pink',
					'customBackgroundColor' => '#778899',
					'style'                 => array( 'color' => array( 'background' => '#aabbcc' ) ),
				),
				array( 'css_classes' => array( 'has-background', 'has-pale-pink-background-color' ) ),
			),
			'picked background wins over style'       => array(
				array(
					'customBackgroundColor' => '#778899',
					'style'                 => array( 'color' => array( 'background' => '#aabbcc' ) ),
				),
				array(
					'css_classes'   => array( 'has-background' ),
					'inline_styles' => 'background-color: #778899;',
				),
			),
			'text and background'                     => array(
				array(
					'textColor'             => 'vivid-red',
					'customBackgroundColor' => '#778899',
				),
				array(
					'css_classes'   => array( 'has-text-color', 'has-vivid-red-color', 'has-background' ),
					'inline_styles' => 'background-color: #778899;',
				),
			),
			'picked text and background'              => array(
				array(
					'customTextColor'       => '#112233',
					'customBackgroundColor' => '#778899',
				),
				array(
					'css_classes'   => array( 'has-text-color', 'has-background' ),
					'inline_styles' => 'color: #112233;background-color: #778899;',
				),
			),
			'named overlay text color'                => array(
				array( 'overlayTextColor' => 'luminous-vivid-amber' ),
				array( 'overlay_css_classes' => array( 'has-text-color', 'has-luminous-vivid-amber-color' ) ),
			),
			'picked overlay text color'               => array(
				array( 'customOverlayTextColor' => '#010203' ),
				array(
					'overlay_css_classes'   => array( 'has-text-color' ),
					'overlay_inline_styles' => 'color: #010203;',
				),
			),
			'named overlay text color wins'           => array(
				array(
					'overlayTextColor'       => 'luminous-vivid-amber',
					'customOverlayTextColor' => '#010203',
				),
				array( 'overlay_css_classes' => array( 'has-text-color', 'has-luminous-vivid-amber-color' ) ),
			),
			'named overlay background color'          => array(
				array( 'overlayBackgroundColor' => 'cyan-bluish-gray' ),
				array( 'overlay_css_classes' => array( 'has-background', 'has-cyan-bluish-gray-background-color' ) ),
			),
			'picked overlay background color'         => array(
				array( 'customOverlayBackgroundColor' => '#040506' ),
				array(
					'overlay_css_classes'   => array( 'has-background' ),
					'overlay_inline_styles' => 'background-color: #040506;',
				),
			),
			'named overlay background wins'           => array(
				array(
					'overlayBackgroundColor'       => 'cyan-bluish-gray',
					'customOverlayBackgroundColor' => '#040506',
				),
				array( 'overlay_css_classes' => array( 'has-background', 'has-cyan-bluish-gray-background-color' ) ),
			),
			'picked overlay text and background'      => array(
				array(
					'customOverlayTextColor'       => '#010203',
					'customOverlayBackgroundColor' => '#040506',
				),
				array(
					'overlay_css_classes'   => array( 'has-text-color', 'has-background' ),
					'overlay_inline_styles' => 'color: #010203;background-color: #040506;',
				),
			),
			'colors do not mix with the overlay'      => array(
				array(
					'textColor'        => 'vivid-red',
					'overlayTextColor' => 'luminous-vivid-amber',
				),
				array(
					'css_classes'         => array( 'has-text-color', 'has-vivid-red-color' ),
					'overlay_css_classes' => array( 'has-text-color', 'has-luminous-vivid-amber-color' ),
				),
			),
			'style colors do not set the overlay'     => array(
				array(
					'style' => array(
						'color' => array(
							'text'       => '#445566',
							'background' => '#aabbcc',
						),
					),
				),
				array(
					'css_classes'   => array( 'has-text-color', 'has-background' ),
					'inline_styles' => 'color: #445566;background-color: #aabbcc;',
				),
			),
		);
	}

	/**
	 * Tests that the attributes of the block are not used for the colors.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_build_css_colors
	 */
	public function test_should_build_the_css_colors_from_the_context_only() {
		$colors = block_core_page_list_build_css_colors( array( 'textColor' => 'vivid-red' ), array() );

		$this->assertSame( array(), $colors['css_classes'] );
		$this->assertSame( '', $colors['inline_styles'] );
	}

	/**
	 * Tests that nothing is returned without pages.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_nest_pages
	 */
	public function test_should_nest_nothing_without_pages() {
		$this->assertNull( block_core_page_list_nest_pages( array(), array( 1 => array( 2 => array( 'page_id' => 2 ) ) ) ) );
		$this->assertNull( block_core_page_list_nest_pages( null, array() ) );
	}

	/**
	 * Tests that pages without children are returned as they are.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_nest_pages
	 */
	public function test_should_leave_pages_without_children_as_they_are() {
		$level = array(
			1 => array( 'page_id' => 1 ),
			2 => array( 'page_id' => 2 ),
		);

		$this->assertSame( $level, block_core_page_list_nest_pages( $level, array() ) );
		$this->assertSame( $level, block_core_page_list_nest_pages( $level, array( 9 => array( 10 => array( 'page_id' => 10 ) ) ) ), 'Children of other pages should not matter.' );
	}

	/**
	 * Tests the children of the pages, and the children of the children.
	 *
	 * @ticket 66588
	 *
	 * @covers ::block_core_page_list_nest_pages
	 */
	public function test_should_nest_the_children_of_the_pages() {
		$top_level = array(
			1 => array( 'page_id' => 1 ),
			2 => array( 'page_id' => 2 ),
		);
		$children  = array(
			1 => array(
				3 => array( 'page_id' => 3 ),
				4 => array( 'page_id' => 4 ),
			),
			3 => array(
				5 => array( 'page_id' => 5 ),
			),
		);

		$this->assertSame(
			array(
				1 => array(
					'page_id'  => 1,
					'children' => array(
						3 => array(
							'page_id'  => 3,
							'children' => array(
								5 => array( 'page_id' => 5 ),
							),
						),
						4 => array( 'page_id' => 4 ),
					),
				),
				2 => array( 'page_id' => 2 ),
			),
			block_core_page_list_nest_pages( $top_level, $children )
		);
	}
}
