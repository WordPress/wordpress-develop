<?php
/**
 * Tests for the server-side rendering of the Page List block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_page_list
 */
class Tests_Blocks_RenderPageList extends WP_UnitTestCase {

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
			'blockName' => 'core/page-list',
			'attrs'     => array(),
		);

		// The pages of the other tests and of the installation should not be listed.
		$pages = get_pages( array( 'post_status' => 'any' ) );

		if ( $pages ) {
			foreach ( $pages as $page ) {
				wp_delete_post( $page->ID, true );
			}
		}
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Creates a page.
	 *
	 * @param string $title Title of the page.
	 * @param array  $args  Optional. Arguments of the page that differ from the defaults.
	 * @return int The ID of the page.
	 */
	private function create_page( $title, $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'  => 'page',
					'post_title' => $title,
				),
				$args
			)
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string|null The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'core/page-list',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_page_list( $attributes, '', $block );
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
	 * Tests that nothing is rendered without pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_nothing_without_pages() {
		$this->assertNull( $this->render() );
	}

	/**
	 * Tests that only published pages are listed.
	 *
	 * @ticket 66588
	 */
	public function test_should_list_only_published_pages() {
		$this->create_page( 'Published' );
		$this->create_page( 'Draft', array( 'post_status' => 'draft' ) );
		$this->create_page( 'Private', array( 'post_status' => 'private' ) );

		$this->assertSame( array( 'Published' ), $this->get_titles( $this->render() ) );
	}

	/**
	 * Tests the list of the pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_pages_in_a_list() {
		$page_id = $this->create_page( 'Only page' );

		$this->assertSame(
			'<ul class="wp-block-page-list"><li class="wp-block-pages-list__item"><a class="wp-block-pages-list__item__link" href="' . esc_url( get_permalink( $page_id ) ) . '">Only page</a></li></ul>',
			$this->render()
		);
	}

	/**
	 * Tests the order of the pages by the order of the menu and the title.
	 *
	 * @ticket 66588
	 */
	public function test_should_order_the_pages_by_menu_order_and_title() {
		$this->create_page( 'B second by title' );
		$this->create_page( 'A first by title' );
		$this->create_page( 'Z first by menu order', array( 'menu_order' => -5 ) );
		$this->create_page( 'C last by menu order', array( 'menu_order' => 10 ) );

		$this->assertSame(
			array( 'Z first by menu order', 'A first by title', 'B second by title', 'C last by menu order' ),
			$this->get_titles( $this->render() )
		);
	}

	/**
	 * Tests the child pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_nest_the_child_pages() {
		$parent_id = $this->create_page( 'Parent' );
		$child_id  = $this->create_page( 'Child', array( 'post_parent' => $parent_id ) );
		$this->create_page( 'Grandchild', array( 'post_parent' => $child_id ) );
		$this->create_page( 'Other' );

		$markup = $this->render();

		$this->assertSame( array( 'Other', 'Parent', 'Child', 'Grandchild' ), $this->get_titles( $markup ) );
		$this->assertSame( 2, substr_count( $markup, '<ul class="wp-block-navigation__submenu-container">' ) );
		$this->assertMatchesRegularExpression( '/Parent<\/a><ul [^>]*><li [^>]*><a [^>]*>Child<\/a><ul [^>]*><li [^>]*><a [^>]*>Grandchild<\/a>/', $markup );
	}

	/**
	 * Tests the pages that are the children of a page.
	 *
	 * @ticket 66588
	 *
	 * @dataProvider data_parent_page_ids
	 *
	 * @param callable $get_value Returns the value of the parent page ID attribute from the ID of the parent.
	 */
	public function test_should_list_only_the_children_of_the_parent_page( $get_value ) {
		$parent_id = $this->create_page( 'Parent' );
		$child_id  = $this->create_page( 'Child', array( 'post_parent' => $parent_id ) );
		$this->create_page( 'Grandchild', array( 'post_parent' => $child_id ) );
		$this->create_page( 'Other' );

		$markup = $this->render( array( 'parentPageID' => $get_value( $parent_id ) ) );

		$this->assertSame( array( 'Child', 'Grandchild' ), $this->get_titles( $markup ) );
		$this->assertStringStartsWith( '<ul class="wp-block-page-list"><li class="wp-block-pages-list__item has-child">', $markup );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_parent_page_ids() {
		return array(
			'integer' => array( static fn( $id ) => $id ),
			'string'  => array( static fn( $id ) => (string) $id ),
			'float'   => array( static fn( $id ) => (float) $id ),
		);
	}

	/**
	 * Tests that nothing is rendered for a parent page without children.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_nothing_for_a_parent_page_without_children() {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Other' );

		$this->assertNull( $this->render( array( 'parentPageID' => $parent_id ) ) );
		$this->assertNull( $this->render( array( 'parentPageID' => 999999 ) ) );
	}

	/**
	 * Tests that the parent page that is not a number lists all the pages.
	 *
	 * @ticket 66588
	 *
	 * @dataProvider data_parent_page_ids_that_are_not_used
	 *
	 * @param mixed $value The value of the parent page ID attribute.
	 */
	public function test_should_list_all_the_pages_when_the_parent_page_is_not_used( $value ) {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$this->assertSame( array( 'Parent', 'Child' ), $this->get_titles( $this->render( array( 'parentPageID' => $value ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_parent_page_ids_that_are_not_used() {
		return array(
			'zero'        => array( 0 ),
			'zero string' => array( '0' ),
			'null'        => array( null ),
			'array'       => array( array( 1 ) ),
			'object'      => array( new stdClass() ),
		);
	}

	/**
	 * Tests that the list is not wrapped in a submenu.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_wrap_the_items_of_a_list_in_a_submenu() {
		$page_id = $this->create_page( 'Page' );

		$markup = $this->render( array(), array( 'core/isInsideSubmenu' => true ) );

		$this->assertStringStartsWith( '<li class="wp-block-pages-list__item', $markup );
		$this->assertStringEndsWith( '</li>', $markup );
		$this->assertStringNotContainsString( '<ul class="wp-block-page-list', $markup );
		$this->assertStringContainsString( esc_url( get_permalink( $page_id ) ), $markup );
	}

	/**
	 * Tests the current page, and the ancestors of the current page.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_current_page_and_its_ancestors() {
		$parent_id = $this->create_page( 'Parent' );
		$child_id  = $this->create_page( 'Child', array( 'post_parent' => $parent_id ) );
		$this->create_page( 'Other' );

		$this->go_to( get_permalink( $child_id ) );

		$markup = $this->render();
		$items  = $this->get_elements( $markup, 'li' );
		$links  = $this->get_elements( $markup, 'a' );

		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ), 'Other page' );
		$this->assertSame( array( 'current-menu-ancestor', 'has-child', 'wp-block-pages-list__item' ), $this->get_classes( $items[1] ), 'Parent' );
		$this->assertSame( array( 'current-menu-item', 'wp-block-pages-list__item' ), $this->get_classes( $items[2] ), 'Child' );
		$this->assertArrayNotHasKey( 'aria-current', $links[0] );
		$this->assertArrayNotHasKey( 'aria-current', $links[1] );
		$this->assertSame( 'page', $links[2]['aria-current'] );
	}

	/**
	 * Tests the current page at the top level.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_current_page_at_the_top_level() {
		$page_id = $this->create_page( 'Current' );
		$this->create_page( 'Other' );

		$this->go_to( get_permalink( $page_id ) );

		$markup = $this->render();
		$items  = $this->get_elements( $markup, 'li' );
		$links  = $this->get_elements( $markup, 'a' );

		$this->assertSame( array( 'current-menu-item', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertSame( 'page', $links[0]['aria-current'] );
		$this->assertArrayNotHasKey( 'aria-current', $links[1] );
	}

	/**
	 * Tests the overlay colors of the items of a list that is in a submenu.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_overlay_colors_to_the_items_of_a_list_in_a_submenu() {
		$this->create_page( 'Page' );

		$items = $this->get_elements(
			$this->render(
				array(),
				array(
					'core/isInsideSubmenu' => true,
					'overlayTextColor'     => 'luminous-vivid-amber',
				)
			),
			'li'
		);

		$this->assertContains( 'has-luminous-vivid-amber-color', $this->get_classes( $items[0] ) );
	}

	/**
	 * Tests that no page is current away from the pages.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_mark_a_page_away_from_the_pages() {
		$this->create_page( 'Page' );
		$this->go_to( '/' );

		$markup = $this->render();

		$this->assertStringNotContainsString( 'current-menu', $markup );
		$this->assertStringNotContainsString( 'aria-current', $markup );
	}

	/**
	 * Tests the front page.
	 *
	 * @ticket 66588
	 */
	public function test_should_mark_the_front_page() {
		$front_page_id = $this->create_page( 'Front' );
		$this->create_page( 'Other' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id );

		$items = $this->get_elements( $this->render(), 'li' );

		$this->assertSame( array( 'menu-item-home', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertSame( array( 'wp-block-pages-list__item' ), $this->get_classes( $items[1] ) );
	}

	/**
	 * Tests the title of a page without a title.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_a_page_without_a_title() {
		$this->create_page( '' );

		$this->assertSame( array( '(no title)' ), $this->get_titles( $this->render() ) );
	}

	/**
	 * Tests the colors of the Navigation block in the wrapper.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_colors_of_the_navigation_to_the_wrapper() {
		$this->create_page( 'Page' );

		$list = $this->get_elements(
			$this->render(
				array(),
				array(
					'textColor'             => 'vivid-red',
					'customBackgroundColor' => '#112233',
				)
			),
			'ul'
		)[0];

		$this->assertSame( array( 'has-background', 'has-text-color', 'has-vivid-red-color', 'wp-block-page-list' ), $this->get_classes( $list ) );
		$this->assertSame( 'background-color: #112233', $list['style'] );
	}

	/**
	 * Tests that the wrapper has no style without colors.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_add_a_style_to_the_wrapper_without_colors() {
		$this->create_page( 'Page' );

		$list = $this->get_elements( $this->render(), 'ul' )[0];

		$this->assertSame( 'wp-block-page-list', $list['class'] );
		$this->assertArrayNotHasKey( 'style', $list );
	}

	/**
	 * Tests the overlay colors of the Navigation block in the submenus.
	 *
	 * @ticket 66588
	 */
	public function test_should_add_the_overlay_colors_to_the_submenus() {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$items = $this->get_elements(
			$this->render( array(), array( 'overlayTextColor' => 'luminous-vivid-amber' ) ),
			'li'
		);

		$this->assertNotContains( 'has-luminous-vivid-amber-color', $this->get_classes( $items[0] ), 'The top level should not have the overlay colors.' );
		$this->assertContains( 'has-luminous-vivid-amber-color', $this->get_classes( $items[1] ), 'The submenu should have the overlay colors.' );
	}

	/**
	 * Tests the items of the Navigation block.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_items_of_the_navigation() {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$markup = $this->render( array(), array( 'showSubmenuIcon' => true ) );
		$items  = $this->get_elements( $markup, 'li' );

		$this->assertSame( array( 'has-child', 'open-on-hover-click', 'wp-block-navigation-item', 'wp-block-pages-list__item' ), $this->get_classes( $items[0] ) );
		$this->assertCount( 1, $this->get_elements( $markup, 'button' ), 'The item with children should have a submenu button.' );
		$this->assertSame( 'wp-block-pages-list__item__link wp-block-navigation-item__content', $this->get_elements( $markup, 'a' )[0]['class'] );
	}

	/**
	 * Tests that the icons are left out when the Navigation block has none.
	 *
	 * @ticket 66588
	 */
	public function test_should_render_the_items_of_the_navigation_without_icons() {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$markup = $this->render( array(), array( 'showSubmenuIcon' => false ) );

		$this->assertStringNotContainsString( '<button', $markup );
		$this->assertContains( 'wp-block-navigation-item', $this->get_classes( $this->get_elements( $markup, 'li' )[0] ) );
	}

	/**
	 * Tests the visibility of the submenus of the Navigation block.
	 *
	 * @ticket 66588
	 *
	 * @dataProvider data_submenu_visibility
	 *
	 * @param array  $context  The context of the block.
	 * @param string $expected The expected class of the item with children.
	 */
	public function test_should_use_the_submenu_visibility_of_the_navigation( $context, $expected ) {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$items = $this->get_elements( $this->render( array(), array_merge( array( 'showSubmenuIcon' => true ), $context ) ), 'li' );

		$this->assertContains( $expected, $this->get_classes( $items[0] ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_submenu_visibility() {
		return array(
			'click'               => array( array( 'submenuVisibility' => 'click' ), 'open-on-click' ),
			'always'              => array( array( 'submenuVisibility' => 'always' ), 'open-always' ),
			'hover'               => array( array( 'submenuVisibility' => 'hover' ), 'open-on-hover-click' ),
			'deprecated click'    => array( array( 'openSubmenusOnClick' => true ), 'open-on-click' ),
			'deprecated no click' => array( array( 'openSubmenusOnClick' => false ), 'open-on-hover-click' ),
			'deprecated wins'     => array(
				array(
					'openSubmenusOnClick' => true,
					'submenuVisibility'   => 'always',
				),
				'open-on-click',
			),
		);
	}

	/**
	 * Tests that the submenu visibility is only used inside of the Navigation block.
	 *
	 * @ticket 66588
	 */
	public function test_should_not_use_the_submenu_visibility_outside_of_the_navigation() {
		$parent_id = $this->create_page( 'Parent' );
		$this->create_page( 'Child', array( 'post_parent' => $parent_id ) );

		$markup = $this->render( array(), array( 'submenuVisibility' => 'click' ) );

		$this->assertStringNotContainsString( 'open-on-click', $markup );
		$this->assertStringNotContainsString( '<button', $markup );
		$this->assertStringNotContainsString( 'wp-block-navigation-item', $markup );
	}
}
