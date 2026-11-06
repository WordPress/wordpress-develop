<?php
/**
 * Tests for the helper functions of the Home Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_HomeLinkHelpers extends WP_UnitTestCase {

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
			'blockName' => 'core/home-link',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Returns the attributes of the item for the wrapper attributes.
	 *
	 * @param array $context The context of the block.
	 * @return array The class names and the style.
	 */
	private function get_wrapper( $context = array() ) {
		$processor = new WP_HTML_Tag_Processor( '<li ' . block_core_home_link_build_li_wrapper_attributes( $context ) . '>' );
		$processor->next_tag( 'li' );

		return array(
			'classes' => explode( ' ', (string) $processor->get_attribute( 'class' ) ),
			'style'   => $processor->get_attribute( 'style' ),
		);
	}

	/**
	 * Tests that there are no colors without any in the context.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_css_colors
	 */
	public function test_should_have_no_colors_for_an_empty_context() {
		$this->assertSame(
			array(
				'css_classes'   => array(),
				'inline_styles' => '',
			),
			block_core_home_link_build_css_colors( array() )
		);
	}

	/**
	 * Tests the colors of the context.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_css_colors
	 *
	 * @dataProvider data_colors
	 *
	 * @param array  $context        The context of the block.
	 * @param array  $expected_class The expected classes.
	 * @param string $expected_style The expected inline styles.
	 */
	public function test_should_build_the_colors_of_the_context( $context, $expected_class, $expected_style ) {
		$this->assertSame(
			array(
				'css_classes'   => $expected_class,
				'inline_styles' => $expected_style,
			),
			block_core_home_link_build_css_colors( $context )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_colors() {
		return array(
			'a named text color'                   => array(
				array( 'textColor' => 'red' ),
				array( 'has-text-color', 'has-red-color' ),
				'',
			),
			'a custom text color'                  => array(
				array( 'style' => array( 'color' => array( 'text' => '#ff0000' ) ) ),
				array( 'has-text-color' ),
				'color: #ff0000;',
			),
			'a named and a custom text color'      => array(
				array(
					'textColor' => 'red',
					'style'     => array( 'color' => array( 'text' => '#00ff00' ) ),
				),
				array( 'has-text-color', 'has-red-color' ),
				'',
			),
			'a named background color'             => array(
				array( 'backgroundColor' => 'blue' ),
				array( 'has-background', 'has-blue-background-color' ),
				'',
			),
			'a custom background color'            => array(
				array( 'style' => array( 'color' => array( 'background' => '#0000ff' ) ) ),
				array( 'has-background' ),
				'background-color: #0000ff;',
			),
			'a named and a custom background'      => array(
				array(
					'backgroundColor' => 'blue',
					'style'           => array( 'color' => array( 'background' => '#00ff00' ) ),
				),
				array( 'has-background', 'has-blue-background-color' ),
				'',
			),
			'custom text and background colors'    => array(
				array(
					'style' => array(
						'color' => array(
							'text'       => '#111111',
							'background' => '#eeeeee',
						),
					),
				),
				array( 'has-text-color', 'has-background' ),
				'color: #111111;background-color: #eeeeee;',
			),
			'named text and background colors'     => array(
				array(
					'textColor'       => 'red',
					'backgroundColor' => 'blue',
				),
				array( 'has-text-color', 'has-red-color', 'has-background', 'has-blue-background-color' ),
				'',
			),
			'a named text and a custom background' => array(
				array(
					'textColor' => 'red',
					'style'     => array( 'color' => array( 'background' => '#eeeeee' ) ),
				),
				array( 'has-text-color', 'has-red-color', 'has-background' ),
				'background-color: #eeeeee;',
			),
			'a style without colors'               => array(
				array( 'style' => array( 'spacing' => array( 'padding' => '1px' ) ) ),
				array(),
				'',
			),
		);
	}

	/**
	 * Tests the classes and the style of the wrapper.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_li_wrapper_attributes
	 */
	public function test_should_build_the_wrapper_attributes_with_the_colors() {
		$this->go_to( '/?p=999999' );

		$wrapper = $this->get_wrapper(
			array(
				'textColor' => 'red',
				'style'     => array( 'color' => array( 'background' => '#eeeeee' ) ),
			)
		);

		foreach ( array( 'has-text-color', 'has-red-color', 'has-background', 'wp-block-navigation-item', 'wp-block-home-link' ) as $class_name ) {
			$this->assertContains( $class_name, $wrapper['classes'] );
		}

		$this->assertNotContains( 'current-menu-item', $wrapper['classes'] );
		$this->assertStringContainsString( 'background-color: #eeeeee', $wrapper['style'] );
	}

	/**
	 * Tests the class of the page that is shown.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_li_wrapper_attributes
	 */
	public function test_should_mark_the_front_page_as_the_current_item() {
		$this->go_to( '/' );

		$this->assertContains( 'current-menu-item', $this->get_wrapper()['classes'] );
		$this->assertContains( 'wp-block-navigation-item', $this->get_wrapper()['classes'] );
	}

	/**
	 * Tests that another page is not the current item.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_li_wrapper_attributes
	 */
	public function test_should_not_mark_another_page_as_the_current_item() {
		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$this->assertNotContains( 'current-menu-item', $this->get_wrapper()['classes'] );
	}

	/**
	 * Tests the posts page when there is no static home page.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_li_wrapper_attributes
	 */
	public function test_should_mark_the_blog_as_the_current_item_without_a_static_home_page() {
		$posts_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', $posts_page );

		$this->go_to( '/' );

		$this->assertTrue( is_home() );
		$this->assertFalse( is_front_page() );
		$this->assertContains( 'current-menu-item', $this->get_wrapper()['classes'] );
	}

	/**
	 * Tests that the page of the posts is not the current item when it is a page of its own.
	 *
	 * @ticket 66581
	 *
	 * @covers ::block_core_home_link_build_li_wrapper_attributes
	 */
	public function test_should_not_mark_the_page_of_the_posts_as_the_current_item() {
		$home_page  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_page );
		update_option( 'page_for_posts', $posts_page );

		$this->go_to( get_permalink( $posts_page ) );

		$this->assertTrue( is_home() );
		$this->assertNotContains( 'current-menu-item', $this->get_wrapper()['classes'] );
	}
}
