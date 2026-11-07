<?php
/**
 * Tests for the markup of the list of the Page List block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_page_list_render_nested_page_list
 */
class Tests_Blocks_PageListNestedPageList extends WP_UnitTestCase {

	/**
	 * Renders the list of pages.
	 *
	 * @param array $args {
	 *     Optional. The arguments of the function.
	 *
	 *     @type string $visibility The submenu visibility. Default 'hover'.
	 *     @type bool   $icons      Whether to show the submenu icons. Default false.
	 *     @type bool   $navigation Whether the block is a child of the Navigation block. Default false.
	 *     @type array  $pages      The nested pages. Default one page.
	 *     @type bool   $nested     Whether the list is nested in a submenu. Default false.
	 *     @type array  $ancestors  The IDs of the ancestors of the active page. Default none.
	 *     @type array  $colors     The colors. Default none.
	 * }
	 * @return string|null The markup.
	 */
	private function render( $args = array() ) {
		$args = array_merge(
			array(
				'visibility' => 'hover',
				'icons'      => false,
				'navigation' => false,
				'pages'      => array( 1 => $this->page( 1 ) ),
				'nested'     => false,
				'ancestors'  => array(),
				'colors'     => array(),
			),
			$args
		);

		return block_core_page_list_render_nested_page_list(
			$args['visibility'],
			$args['icons'],
			$args['navigation'],
			$args['pages'],
			$args['nested'],
			$args['ancestors'],
			$args['colors']
		);
	}

	/**
	 * Returns the data of a page.
	 *
	 * @param int   $id   The ID of the page.
	 * @param array $data Optional. The data that differs from the defaults.
	 * @return array The page.
	 */
	private function page( $id, $data = array() ) {
		return array_merge(
			array(
				'page_id'   => $id,
				'title'     => "Page $id",
				'link'      => "https://example.org/page-$id/",
				'is_active' => false,
			),
			$data
		);
	}

	/**
	 * Returns the elements with the tag, with their attributes.
	 *
	 * @param string $markup The markup.
	 * @param string $tag    The tag name.
	 * @return array[] The attributes of each element.
	 */
	private function get_elements( $markup, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$elements  = array();

		while ( $processor->next_tag( $tag ) ) {
			$attributes = array();

			foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = $processor->get_attribute( $name );
			}

			$elements[] = $attributes;
		}

		return $elements;
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
	 * Tests that nothing is returned without pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_nothing_without_pages() {
		$this->assertNull( $this->render( array( 'pages' => array() ) ) );
	}

	/**
	 * Tests the markup of a page.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_a_page() {
		$this->assertSame(
			'<li class="wp-block-pages-list__item"><a class="wp-block-pages-list__item__link" href="https://example.org/page-1/">Page 1</a></li>',
			$this->render()
		);
	}

	/**
	 * Tests the pages in order.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_pages_in_order() {
		$markup = $this->render(
			array(
				'pages' => array(
					3 => $this->page( 3 ),
					1 => $this->page( 1 ),
					2 => $this->page( 2 ),
				),
			)
		);

		$this->assertSame( 3, substr_count( $markup, '<li ' ) );
		$this->assertSame( array( 'Page 3', 'Page 1', 'Page 2' ), $this->get_titles( $markup ) );
	}

	/**
	 * Returns the titles of the links of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The titles.
	 */
	private function get_titles( $markup ) {
		preg_match_all( '/<a [^>]*>(.*?)<\/a>/', $markup, $matches );

		return $matches[1];
	}

	/**
	 * Tests the active page.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_active_page() {
		$markup = $this->render( array( 'pages' => array( 1 => $this->page( 1, array( 'is_active' => true ) ) ) ) );

		$this->assertSame( array( 'current-menu-item', 'wp-block-pages-list__item' ), $this->get_classes( $this->get_elements( $markup, 'li' )[0] ) );
		$this->assertSame( 'page', $this->get_elements( $markup, 'a' )[0]['aria-current'] );
	}

	/**
	 * Tests that a page that is not active has no current page attribute.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_mark_a_page_that_is_not_active() {
		$this->assertArrayNotHasKey( 'aria-current', $this->get_elements( $this->render(), 'a' )[0] );
	}

	/**
	 * Tests the ancestors of the active page.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_ancestors_of_the_active_page() {
		$markup = $this->render(
			array(
				'pages'     => array(
					1 => $this->page( 1 ),
					2 => $this->page( 2 ),
				),
				'ancestors' => array( 2 ),
			)
		);

		$items = $this->get_elements( $markup, 'li' );

		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertSame( array( 'current-menu-ancestor', 'wp-block-pages-list__item' ), $this->get_classes( $items[1] ) );
	}

	/**
	 * Tests the front page.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_front_page() {
		$front_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id );

		$markup = $this->render(
			array(
				'pages' => array(
					$front_page_id => $this->page( (string) $front_page_id ),
					999999         => $this->page( 999999 ),
				),
			)
		);

		$items = $this->get_elements( $markup, 'li' );

		$this->assertSame( array( 'menu-item-home', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $items[1] ) );
	}

	/**
	 * Tests the child pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_children_in_a_submenu() {
		$markup = $this->render(
			array(
				'pages' => array(
					1 => $this->page(
						1,
						array(
							'children' => array(
								2 => $this->page( 2 ),
							),
						)
					),
				),
			)
		);

		$this->assertSame(
			'<li class="wp-block-pages-list__item has-child"><a class="wp-block-pages-list__item__link" href="https://example.org/page-1/">Page 1</a>' .
			'<ul class="wp-block-navigation__submenu-container"><li class="wp-block-pages-list__item"><a class="wp-block-pages-list__item__link" href="https://example.org/page-2/">Page 2</a></li></ul></li>',
			$markup
		);
	}

	/**
	 * Tests the classes of the items of the Navigation block.
	 *
	 * @ticket 66588
	 *
	 * @dataProvider data_navigation_classes
	 *
	 * @param string   $visibility The submenu visibility.
	 * @param bool     $icons      Whether to show the submenu icons.
	 * @param string[] $expected   The expected classes of the item with children.
	 */
	public function test_should_add_the_classes_of_the_navigation_to_the_items( $visibility, $icons, $expected ) {
		$markup = $this->render(
			array(
				'visibility' => $visibility,
				'icons'      => $icons,
				'navigation' => true,
				'pages'      => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$items = $this->get_elements( $markup, 'li' );

		sort( $expected );

		$this->assertSame( $expected, $this->get_classes( $items[0] ) );
		$this->assertSame(
			array( 'wp-block-navigation-item', 'wp-block-pages-list__item' ),
			array_values( array_diff( $this->get_classes( $items[1] ), array( 'open-on-click', 'open-on-hover-click', 'open-always' ) ) ),
			'The child item should have the classes of the navigation.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_navigation_classes() {
		$base = array( 'has-child', 'wp-block-navigation-item', 'wp-block-pages-list__item' );

		return array(
			'hover with icons'     => array( 'hover', true, array_merge( $base, array( 'open-on-hover-click' ) ) ),
			'hover without icons'  => array( 'hover', false, $base ),
			'click with icons'     => array( 'click', true, array_merge( $base, array( 'open-on-click' ) ) ),
			'click without icons'  => array( 'click', false, array_merge( $base, array( 'open-on-click' ) ) ),
			'always with icons'    => array( 'always', true, array_merge( $base, array( 'open-always' ) ) ),
			'always without icons' => array( 'always', false, array_merge( $base, array( 'open-always' ) ) ),
		);
	}

	/**
	 * Tests that the classes of the navigation are not added outside of it.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_add_the_classes_of_the_navigation_outside_of_it() {
		$markup = $this->render(
			array(
				'visibility' => 'click',
				'icons'      => true,
				'pages'      => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$this->assertSame( array( 'has-child', 'wp-block-pages-list__item' ), $this->get_classes( $this->get_elements( $markup, 'li' )[0] ) );
		$this->assertSame( 'wp-block-pages-list__item__link', $this->get_elements( $markup, 'a' )[0]['class'] );
		$this->assertStringNotContainsString( '<button', $markup );
	}

	/**
	 * Tests the class of the links of the Navigation block.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_navigation_content_class_to_the_link() {
		$link = $this->get_elements( $this->render( array( 'navigation' => true ) ), 'a' )[0];

		$this->assertSame( 'wp-block-pages-list__item__link wp-block-navigation-item__content', $link['class'] );
	}

	/**
	 * Tests the button that opens the submenu, next to the link.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_submenu_button_with_icons() {
		$markup = $this->render(
			array(
				'icons'      => true,
				'navigation' => true,
				'pages'      => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$buttons = $this->get_elements( $markup, 'button' );

		$this->assertCount( 1, $buttons, 'Only the item with children should have a button.' );
		$this->assertSame( 'Page 1 submenu', $buttons[0]['aria-label'] );
		$this->assertSame( 'wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle', $buttons[0]['class'] );
		$this->assertSame( 'false', $buttons[0]['aria-expanded'] );
		$this->assertStringContainsString( '<svg ', $markup );
		$this->assertCount( 2, $this->get_elements( $markup, 'a' ), 'Both pages should have a link.' );
	}

	/**
	 * Tests that there is no button without icons.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_render_the_submenu_button_without_icons() {
		$markup = $this->render(
			array(
				'navigation' => true,
				'pages'      => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$this->assertStringNotContainsString( '<button', $markup );
		$this->assertStringNotContainsString( '<svg', $markup );
	}

	/**
	 * Tests that an item with children opens on click with a button in place of the link.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_a_button_in_place_of_the_link_when_opening_on_click() {
		$markup = $this->render(
			array(
				'visibility' => 'click',
				'icons'      => true,
				'navigation' => true,
				'pages'      => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$buttons = $this->get_elements( $markup, 'button' );
		$links   = $this->get_elements( $markup, 'a' );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'Page 1 submenu', $buttons[0]['aria-label'] );
		$this->assertSame( array( 'wp-block-navigation-item__content', 'wp-block-navigation-submenu__toggle' ), $this->get_classes( $buttons[0] ) );
		$this->assertSame( 'false', $buttons[0]['aria-expanded'] );
		$this->assertStringContainsString( '>Page 1</button><span class="wp-block-page-list__submenu-icon wp-block-navigation__submenu-icon"><svg ', $markup );
		$this->assertCount( 1, $links, 'Only the child should have a link.' );
		$this->assertSame( 'https://example.org/page-2/', $links[0]['href'] );
	}

	/**
	 * Tests that a button is not used for an item without children.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_a_link_for_an_item_without_children_when_opening_on_click() {
		$markup = $this->render(
			array(
				'visibility' => 'click',
				'navigation' => true,
			)
		);

		$this->assertStringNotContainsString( '<button', $markup );
		$this->assertCount( 1, $this->get_elements( $markup, 'a' ) );
	}

	/**
	 * Tests the title of a page.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_a_title_without_a_page_title() {
		$markup = $this->render( array( 'pages' => array( 1 => $this->page( 1, array( 'title' => '' ) ) ) ) );

		$this->assertStringContainsString( '>(no title)</a>', $markup );
	}

	/**
	 * Tests that the title is filtered as post content, and stripped for the label.
	 *
	 * @ticket 66588
	 */
	public function test_should_filter_the_title() {
		$markup = $this->render(
			array(
				'icons'      => true,
				'navigation' => true,
				'pages'      => array(
					1 => $this->page(
						1,
						array(
							'title'    => '<strong>Bold</strong><script>alert(1)</script>',
							'children' => array( 2 => $this->page( 2 ) ),
						)
					),
				),
			)
		);

		$this->assertStringContainsString( '><strong>Bold</strong></a>', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertSame( 'Bold submenu', $this->get_elements( $markup, 'button' )[0]['aria-label'] );
	}

	/**
	 * Tests that the link is escaped.
	 *
	 * @ticket 66588
	 */
	public function test_should_escape_the_link() {
		$markup = $this->render( array( 'pages' => array( 1 => $this->page( 1, array( 'link' => 'javascript:alert(1)' ) ) ) ) );

		$this->assertSame( '', $this->get_elements( $markup, 'a' )[0]['href'] );

		$markup = $this->render( array( 'pages' => array( 1 => $this->page( 1, array( 'link' => 'https://example.org/?a=1&b=2' ) ) ) ) );

		$this->assertStringContainsString( 'href="https://example.org/?a=1&#038;b=2"', $markup );
	}

	/**
	 * Tests the overlay colors of the items in a submenu.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_overlay_colors_to_the_items_of_a_submenu() {
		$markup = $this->render(
			array(
				'colors' => array(
					'overlay_css_classes'   => array( 'has-text-color', 'has-vivid-red-color' ),
					'overlay_inline_styles' => 'color: red;',
				),
				'pages'  => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$items = $this->get_elements( $markup, 'li' );

		$this->assertSame( array( 'has-child', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ), 'The top level should not have the overlay colors.' );
		$this->assertArrayNotHasKey( 'style', $items[0] );
		$this->assertSame( array( 'has-text-color', 'has-vivid-red-color', 'wp-block-pages-list__item' ), $this->get_classes( $items[1] ) );
		$this->assertSame( 'color: red;', $items[1]['style'] );
	}

	/**
	 * Tests the overlay colors of the items of a list that is in a submenu.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_overlay_colors_to_the_items_of_a_nested_list() {
		$markup = $this->render(
			array(
				'nested' => true,
				'colors' => array(
					'overlay_css_classes'   => array( 'has-background' ),
					'overlay_inline_styles' => 'background-color: blue;',
				),
			)
		);

		$item = $this->get_elements( $markup, 'li' )[0];

		$this->assertSame( array( 'has-background', 'wp-block-pages-list__item' ), $this->get_classes( $item ) );
		$this->assertSame( 'background-color: blue;', $item['style'] );
	}

	/**
	 * Tests that no style is added without overlay styles.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_add_a_style_without_overlay_styles() {
		$markup = $this->render(
			array(
				'nested' => true,
				'colors' => array(
					'overlay_css_classes'   => array( 'has-background' ),
					'overlay_inline_styles' => '',
				),
			)
		);

		$this->assertArrayNotHasKey( 'style', $this->get_elements( $markup, 'li' )[0] );
	}

	/**
	 * Tests that the overlay colors are left out without both of the overlay values.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_add_the_overlay_colors_without_both_values() {
		$markup = $this->render(
			array(
				'nested' => true,
				'colors' => array( 'overlay_css_classes' => array( 'has-background' ) ),
			)
		);

		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $this->get_elements( $markup, 'li' )[0] ) );
	}

	/**
	 * Tests that the overlay colors are left out with the styles alone.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_add_the_overlay_colors_with_the_styles_alone() {
		$markup = $this->render(
			array(
				'nested' => true,
				'colors' => array( 'overlay_inline_styles' => 'color: red;' ),
			)
		);

		$item = $this->get_elements( $markup, 'li' )[0];

		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $item ) );
		$this->assertArrayNotHasKey( 'style', $item );
	}

	/**
	 * Tests that the overlay classes and styles are escaped.
	 *
	 * @ticket 66588
	 */
	public function test_should_escape_the_overlay_classes_and_styles() {
		$markup = $this->render(
			array(
				'nested' => true,
				'colors' => array(
					'overlay_css_classes'   => array( 'has-"quote"' ),
					'overlay_inline_styles' => 'font-family: "Quote";',
				),
			)
		);

		$items = $this->get_elements( $markup, 'li' );

		$this->assertCount( 1, $items );
		$this->assertSame( array( 'has-"quote"', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertSame( 'font-family: "Quote";', $items[0]['style'] );
		$this->assertCount( 1, $this->get_elements( $markup, 'a' ), 'The quotes should not break out of the attributes.' );
	}

	/**
	 * Tests that the title is filtered for the button that opens on click.
	 *
	 * @ticket 66588
	 */
	public function test_should_filter_the_title_of_the_button_that_opens_on_click() {
		$markup = $this->render(
			array(
				'visibility' => 'click',
				'navigation' => true,
				'pages'      => array(
					1 => $this->page(
						1,
						array(
							'title'    => '<em>Em</em><script>alert(1)</script>',
							'children' => array( 2 => $this->page( 2 ) ),
						)
					),
				),
			)
		);

		$this->assertStringContainsString( '><em>Em</em></button>', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
	}

	/**
	 * Tests that there is no submenu button outside of the Navigation block.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_render_the_submenu_button_outside_of_the_navigation() {
		$markup = $this->render(
			array(
				'icons' => true,
				'pages' => array(
					1 => $this->page( 1, array( 'children' => array( 2 => $this->page( 2 ) ) ) ),
				),
			)
		);

		$this->assertStringNotContainsString( '<button', $markup );
	}

	/**
	 * Tests that the options and the ancestors reach the pages at every level.
	 *
	 * @ticket 66588
	 */
	public function test_should_pass_the_options_to_the_pages_of_every_level() {
		$pages = array(
			1 => $this->page(
				1,
				array(
					'children' => array(
						2 => $this->page(
							2,
							array(
								'children' => array( 3 => $this->page( 3, array( 'is_active' => true ) ) ),
							)
						),
					),
				)
			),
		);

		$hover = $this->render(
			array(
				'icons'      => true,
				'navigation' => true,
				'pages'      => $pages,
				'ancestors'  => array( 1, 2 ),
			)
		);

		$items = $this->get_elements( $hover, 'li' );

		foreach ( array( 0, 1 ) as $index ) {
			$this->assertContains( 'current-menu-ancestor', $this->get_classes( $items[ $index ] ), "Item $index should be an ancestor." );
			$this->assertContains( 'open-on-hover-click', $this->get_classes( $items[ $index ] ), "Item $index should open on hover or click." );
		}

		$this->assertContains( 'current-menu-item', $this->get_classes( $items[2] ) );
		$this->assertCount( 2, $this->get_elements( $hover, 'button' ), 'The pages with children should have a submenu button.' );

		$click = $this->render(
			array(
				'visibility' => 'click',
				'navigation' => true,
				'pages'      => $pages,
			)
		);

		$this->assertCount( 2, $this->get_elements( $click, 'button' ), 'The pages with children should open on click.' );
		$this->assertCount( 1, $this->get_elements( $click, 'a' ), 'Only the page without children should have a link.' );
	}
}
