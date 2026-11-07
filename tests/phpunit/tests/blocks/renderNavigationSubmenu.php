<?php
/**
 * Tests for the server-side rendering of the Navigation Submenu block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_navigation_submenu
 */
class Tests_Blocks_RenderNavigationSubmenu extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original supports of the block type, which the render function changes.
	 *
	 * @var array
	 */
	private $original_supports;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/navigation-submenu',
			'attrs'     => array(),
		);

		$block_type              = WP_Block_Type_Registry::get_instance()->get_registered( 'core/navigation-submenu' );
		$this->original_supports = $block_type->supports;
	}

	public function tear_down() {
		WP_Block_Type_Registry::get_instance()->get_registered( 'core/navigation-submenu' )->supports = $this->original_supports;
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes of the block. The label is set by default.
	 * @param array    $context    Optional. The context of the block. Default none.
	 * @param string[] $children   Optional. The markup of the inner blocks. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array(), $children = array() ) {
		$attributes   = array_merge( array( 'label' => 'Label' ), $attributes );
		$inner_blocks = array();

		foreach ( $children as $markup ) {
			$inner_blocks[] = array(
				'blockName'    => 'core/html',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $markup,
				'innerContent' => array( $markup ),
			);
		}

		$block = new WP_Block(
			array(
				'blockName'    => 'core/navigation-submenu',
				'attrs'        => $attributes,
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
			),
			$context
		);

		return render_block_core_navigation_submenu( $attributes, '', $block );
	}

	/**
	 * Returns the first element with the tag, with its attributes.
	 *
	 * @param string $markup The markup.
	 * @param string $tag    The tag name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $tag ) ) {
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
	 * Tests that nothing is rendered without a label.
	 *
	 * @ticket 66589
	 *
	 * @dataProvider data_no_label
	 *
	 * @param mixed $label The label.
	 */
	public function test_should_render_nothing_without_a_label( $label ) {
		$this->assertSame( '', $this->render( array( 'label' => $label ), array(), array( '<li>Child</li>' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_label() {
		return array(
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
			'null'         => array( null ),
		);
	}

	/**
	 * Tests the item with a link.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_an_item_with_a_link() {
		$markup = $this->render( array( 'url' => 'https://example.org/page/' ) );
		$item   = $this->get_element( $markup, 'li' );
		$link   = $this->get_element( $markup, 'a' );

		$this->assertSame( array( 'wp-block-navigation-item', 'wp-block-navigation-submenu' ), $this->get_classes( $item ) );
		$this->assertSame( 'wp-block-navigation-item__content', $link['class'] );
		$this->assertSame( 'https://example.org/page/', $link['href'] );
		$this->assertArrayNotHasKey( 'aria-current', $link );
		$this->assertArrayNotHasKey( 'target', $link );
		$this->assertArrayNotHasKey( 'rel', $link );
		$this->assertArrayNotHasKey( 'title', $link );
		$this->assertStringStartsWith( '<li ', $markup );
		$this->assertStringEndsWith( '<span class="wp-block-navigation-item__label">Label</span></a></li>', $markup );
		$this->assertStringNotContainsString( '<ul', $markup );
		$this->assertStringNotContainsString( '<button', $markup );
	}

	/**
	 * Tests that the link has no href without a URL.
	 *
	 * @ticket 66589
	 */
	public function test_should_leave_out_the_href_without_a_url() {
		$this->assertArrayNotHasKey( 'href', $this->get_element( $this->render(), 'a' ) );
	}

	/**
	 * Tests that the URL is escaped.
	 *
	 * @ticket 66589
	 */
	public function test_should_escape_the_url() {
		$this->assertStringContainsString( 'href="https://example.org/?a=1&#038;b=2"', $this->render( array( 'url' => 'https://example.org/?a=1&b=2' ) ) );
		$this->assertSame( '', $this->get_element( $this->render( array( 'url' => 'javascript:alert(1)' ) ), 'a' )['href'] );
	}

	/**
	 * Tests the label, which is filtered as post content.
	 *
	 * @ticket 66589
	 */
	public function test_should_filter_the_label() {
		$markup = $this->render( array( 'label' => '<strong>Bold</strong><script>alert(1)</script>' ) );

		$this->assertStringContainsString( '<span class="wp-block-navigation-item__label"><strong>Bold</strong></span>', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
	}

	/**
	 * Tests the description.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_the_description() {
		$markup = $this->render( array( 'description' => '<em>About</em><script>alert(1)</script>' ) );

		$this->assertStringContainsString( '<span class="wp-block-navigation-item__description"><em>About</em></span></a>', $markup );
		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertStringNotContainsString( 'wp-block-navigation-item__description', $this->render( array( 'description' => '' ) ) );
	}

	/**
	 * Tests the attributes of the link.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_the_attributes_of_the_link() {
		$link = $this->get_element(
			$this->render(
				array(
					'url'           => 'https://example.org/',
					'opensInNewTab' => true,
					'rel'           => 'noopener "quoted"',
					'title'         => 'A "title"',
				)
			),
			'a'
		);

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( 'noopener "quoted"', $link['rel'] );
		$this->assertSame( 'A "title"', $link['title'] );
	}

	/**
	 * Tests that the link does not open in a new tab unless it is set to.
	 *
	 * @ticket 66589
	 *
	 * @dataProvider data_not_new_tab
	 *
	 * @param mixed $value The value of the opens in a new tab attribute.
	 */
	public function test_should_not_open_in_a_new_tab_unless_it_is_set_to( $value ) {
		$this->assertArrayNotHasKey( 'target', $this->get_element( $this->render( array( 'opensInNewTab' => $value ) ), 'a' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_not_new_tab() {
		return array(
			'false'  => array( false ),
			'string' => array( 'true' ),
			'one'    => array( 1 ),
		);
	}

	/**
	 * Tests the nofollow attribute, and that the rel attribute wins over it.
	 *
	 * @ticket 66589
	 */
	public function test_should_add_nofollow_to_the_rel_of_the_link() {
		$this->assertSame( 'nofollow', $this->get_element( $this->render( array( 'nofollow' => true ) ), 'a' )['rel'] );
		$this->assertArrayNotHasKey( 'rel', $this->get_element( $this->render( array( 'nofollow' => false ) ), 'a' ) );
		$this->assertSame(
			'external',
			$this->get_element(
				$this->render(
					array(
						'nofollow' => true,
						'rel'      => 'external',
					)
				),
				'a'
			)['rel']
		);
	}

	/**
	 * Tests that a linked post that is not published is not rendered.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_nothing_for_a_linked_post_that_is_not_published() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame(
			'',
			$this->render(
				array(
					'id'   => $draft_id,
					'kind' => 'post-type',
				)
			)
		);
		$this->assertSame(
			'',
			$this->render(
				array(
					'id'   => 999999,
					'kind' => 'post-type',
				)
			),
			'A post that does not exist.'
		);
		$this->assertSame(
			'',
			$this->render(
				array(
					'id'   => $draft_id,
					'type' => 'page',
				)
			)
		);
	}

	/**
	 * Tests a linked post that is published, and links that are not posts.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_a_linked_post_that_is_published() {
		$post_id  = self::factory()->post->create();
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertNotSame(
			'',
			$this->render(
				array(
					'id'   => $post_id,
					'kind' => 'post-type',
				)
			)
		);
		$this->assertNotSame(
			'',
			$this->render(
				array(
					'id'   => $draft_id,
					'kind' => 'taxonomy',
				)
			),
			'A link to a term is not checked.'
		);
	}

	/**
	 * Tests the filter of the post statuses.
	 *
	 * @ticket 66589
	 */
	public function test_should_use_the_filter_of_the_allowed_post_statuses() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		add_filter(
			'render_block_core_navigation_link_allowed_post_status',
			static function () {
				return array( 'draft' );
			}
		);

		$this->assertNotSame(
			'',
			$this->render(
				array(
					'id'   => $draft_id,
					'kind' => 'post-type',
				)
			)
		);
	}

	/**
	 * Tests the submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_the_inner_blocks_in_a_submenu() {
		$markup = $this->render( array(), array(), array( '<li class="child">One</li>', '<li class="child">Two</li>' ) );

		$this->assertContains( 'has-child', $this->get_classes( $this->get_element( $markup, 'li' ) ) );
		$this->assertMatchesRegularExpression( '/<ul class="[^"]*wp-block-navigation__submenu-container[^"]*"><li class="child">One<\/li><li class="child">Two<\/li><\/ul><\/li>$/', $markup );
	}

	/**
	 * Tests that there is no submenu when the inner blocks have no output.
	 *
	 * @ticket 66589
	 */
	public function test_should_not_render_a_submenu_when_the_inner_blocks_have_no_output() {
		$markup = $this->render( array(), array(), array( '', "  \n" ) );

		$this->assertNotContains( 'has-child', $this->get_classes( $this->get_element( $markup, 'li' ) ) );
		$this->assertStringNotContainsString( '<ul', $markup );
	}

	/**
	 * Tests the classes of the submenu visibility.
	 *
	 * @ticket 66589
	 *
	 * @dataProvider data_visibility_classes
	 *
	 * @param array    $context  The context of the block.
	 * @param string[] $expected The expected classes beside the common ones.
	 */
	public function test_should_add_the_classes_of_the_visibility( $context, $expected ) {
		$classes = $this->get_classes( $this->get_element( $this->render( array(), $context, array( '<li>Child</li>' ) ), 'li' ) );
		$common  = array( 'has-child', 'wp-block-navigation-item', 'wp-block-navigation-submenu' );

		sort( $expected );

		$this->assertSame( array_values( array_diff( $classes, $common ) ), $expected );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_visibility_classes() {
		return array(
			'default'               => array( array(), array() ),
			'hover'                 => array( array( 'submenuVisibility' => 'hover' ), array() ),
			'hover with icons'      => array(
				array(
					'submenuVisibility' => 'hover',
					'showSubmenuIcon'   => true,
				),
				array( 'open-on-hover-click' ),
			),
			'default with icons'    => array( array( 'showSubmenuIcon' => true ), array( 'open-on-hover-click' ) ),
			'icons off'             => array( array( 'showSubmenuIcon' => false ), array() ),
			'click'                 => array( array( 'submenuVisibility' => 'click' ), array( 'open-on-click' ) ),
			'click with icons'      => array(
				array(
					'submenuVisibility' => 'click',
					'showSubmenuIcon'   => true,
				),
				array( 'open-on-click' ),
			),
			'always'                => array( array( 'submenuVisibility' => 'always' ), array( 'open-always' ) ),
			'always with icons'     => array(
				array(
					'submenuVisibility' => 'always',
					'showSubmenuIcon'   => true,
				),
				array( 'open-always' ),
			),
			'deprecated click'      => array( array( 'openSubmenusOnClick' => true ), array( 'open-on-click' ) ),
			'deprecated hover'      => array(
				array(
					'openSubmenusOnClick' => false,
					'showSubmenuIcon'     => true,
				),
				array( 'open-on-hover-click' ),
			),
			'deprecated click wins' => array(
				array(
					'openSubmenusOnClick' => true,
					'submenuVisibility'   => 'always',
				),
				array( 'open-on-click' ),
			),
		);
	}

	/**
	 * Tests the button next to the link that opens the submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_the_submenu_button_next_to_the_link() {
		$markup = $this->render( array( 'label' => '<b>Parent</b>' ), array( 'showSubmenuIcon' => true ), array( '<li>Child</li>' ) );
		$button = $this->get_element( $markup, 'button' );

		$this->assertSame( 'Parent submenu', $button['aria-label'] );
		$this->assertSame( 'wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle', $button['class'] );
		$this->assertSame( 'false', $button['aria-expanded'] );
		$this->assertStringContainsString( '</a><button ', $markup );
		$this->assertStringContainsString( block_core_shared_navigation_render_submenu_icon() . '</button><ul ', $markup );
	}

	/**
	 * Tests that there is no button without icons or without a submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_not_render_the_submenu_button_without_icons_or_a_submenu() {
		$this->assertStringNotContainsString( '<button', $this->render( array(), array(), array( '<li>Child</li>' ) ) );
		$this->assertStringNotContainsString( '<button', $this->render( array(), array( 'showSubmenuIcon' => true ) ) );
		$this->assertStringNotContainsString( '<button', $this->render( array(), array( 'showSubmenuIcon' => false ), array( '<li>Child</li>' ) ) );
	}

	/**
	 * Tests the button in place of the link when the submenu opens on click.
	 *
	 * @ticket 66589
	 */
	public function test_should_render_a_button_in_place_of_the_link_when_opening_on_click() {
		$markup = $this->render(
			array(
				'url'         => 'https://example.org/',
				'label'       => 'Parent',
				'description' => 'About',
			),
			array( 'submenuVisibility' => 'click' ),
			array( '<li>Child</li>' )
		);

		$button = $this->get_element( $markup, 'button' );

		$this->assertNull( $this->get_element( $markup, 'a' ), 'There should be no link.' );
		$this->assertSame( 'Parent submenu', $button['aria-label'] );
		$this->assertSame( 'wp-block-navigation-item__content wp-block-navigation-submenu__toggle', $button['class'] );
		$this->assertSame( 'false', $button['aria-expanded'] );
		$this->assertStringContainsString( '<button aria-label="Parent submenu" class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false"><span class="wp-block-navigation-item__label">Parent</span><span class="wp-block-navigation-item__description">About</span></button>', $markup );
		$this->assertStringContainsString( '</button><span class="wp-block-navigation__submenu-icon">' . block_core_shared_navigation_render_submenu_icon() . '</span><ul ', $markup );
	}

	/**
	 * Tests that the icon is left out when the submenu that opens on click is empty.
	 *
	 * @ticket 66589
	 */
	public function test_should_not_render_the_icon_of_a_submenu_that_opens_on_click_without_children() {
		$markup = $this->render( array(), array( 'submenuVisibility' => 'click' ) );

		$this->assertStringContainsString( '<button ', $markup );
		$this->assertStringNotContainsString( 'wp-block-navigation__submenu-icon', $markup );
		$this->assertStringNotContainsString( '<ul', $markup );
	}

	/**
	 * Tests that the label is escaped in the label of the buttons.
	 *
	 * @ticket 66589
	 */
	public function test_should_escape_the_label_in_the_buttons() {
		$attributes = array( 'label' => 'Say "hi" <b>now</b>' );

		$beside_the_link = $this->get_element( $this->render( $attributes, array( 'showSubmenuIcon' => true ), array( '<li>Child</li>' ) ), 'button' );
		$on_click        = $this->get_element( $this->render( $attributes, array( 'submenuVisibility' => 'click' ), array( '<li>Child</li>' ) ), 'button' );

		$this->assertSame( 'Say "hi" now submenu', $beside_the_link['aria-label'] );
		$this->assertSame( 'Say "hi" now submenu', $on_click['aria-label'] );
		$this->assertSame( 'false', $beside_the_link['aria-expanded'] );
		$this->assertSame( 'false', $on_click['aria-expanded'] );
	}

	/**
	 * Tests the current page.
	 *
	 * @ticket 66589
	 */
	public function test_should_mark_the_current_page() {
		$post_id = self::factory()->post->create();
		$other   = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$markup = $this->render(
			array(
				'id'   => $post_id,
				'kind' => 'post-type',
				'url'  => get_permalink( $post_id ),
			)
		);

		$this->assertContains( 'current-menu-item', $this->get_classes( $this->get_element( $markup, 'li' ) ) );
		$this->assertSame( 'page', $this->get_element( $markup, 'a' )['aria-current'] );

		$markup = $this->render(
			array(
				'id'   => $other,
				'kind' => 'post-type',
			)
		);

		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $markup, 'li' ) ) );
		$this->assertArrayNotHasKey( 'aria-current', $this->get_element( $markup, 'a' ) );
	}

	/**
	 * Tests that the current page is not marked without the ID, or away from the pages.
	 *
	 * @ticket 66589
	 */
	public function test_should_not_mark_the_current_page_without_an_id() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $this->render(), 'li' ) ) );
		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $this->render( array( 'id' => 0 ) ), 'li' ) ) );

		$this->go_to( '/' );

		$this->assertNotContains(
			'current-menu-item',
			$this->get_classes(
				$this->get_element(
					$this->render(
						array(
							'id'   => $post_id,
							'kind' => 'post-type',
						)
					),
					'li'
				)
			)
		);
	}

	/**
	 * Tests the current category.
	 *
	 * @ticket 66589
	 */
	public function test_should_mark_the_current_term() {
		$term_id = self::factory()->category->create();

		$this->go_to( get_term_link( $term_id, 'category' ) );

		$markup = $this->render(
			array(
				'id'   => $term_id,
				'kind' => 'taxonomy',
			)
		);

		$this->assertContains( 'current-menu-item', $this->get_classes( $this->get_element( $markup, 'li' ) ) );

		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$markup = $this->render(
			array(
				'id'   => $post_id,
				'kind' => 'taxonomy',
			)
		);

		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $markup, 'li' ) ), 'A link to a term is not the current post.' );
	}

	/**
	 * Tests the archive of a post type.
	 *
	 * @ticket 66589
	 */
	public function test_should_mark_the_archive_of_the_current_post_type() {
		register_post_type(
			'book',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		self::factory()->post->create( array( 'post_type' => 'book' ) );

		$archive_link = get_post_type_archive_link( 'book' );

		$this->go_to( $archive_link );

		$this->assertContains( 'current-menu-item', $this->get_classes( $this->get_element( $this->render( array( 'url' => $archive_link ) ), 'li' ) ) );
		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $this->render( array( 'url' => 'https://example.org/other/' ) ), 'li' ) ) );
		$this->assertNotContains( 'current-menu-item', $this->get_classes( $this->get_element( $this->render(), 'li' ) ), 'An item without a URL.' );

		_unregister_post_type( 'book' );
	}

	/**
	 * Tests that the ancestor of the current page is marked.
	 *
	 * @ticket 66589
	 */
	public function test_should_mark_the_ancestor_of_the_current_page() {
		$markup = $this->render( array(), array(), array( '<li class="wp-block-navigation-item current-menu-item">Current</li>' ) );

		$this->assertContains( 'current-menu-ancestor', $this->get_classes( $this->get_element( $markup, 'li' ) ) );
		$this->assertNotContains( 'current-menu-ancestor', $this->get_classes( $this->get_element( $this->render( array(), array(), array( '<li>Other</li>' ) ), 'li' ) ) );
	}

	/**
	 * Tests the overlay colors of the Navigation block on the submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_add_the_overlay_colors_to_the_submenu() {
		$markup = $this->render(
			array(),
			array(
				'overlayTextColor'       => 'vivid-red',
				'overlayBackgroundColor' => 'pale-pink',
			),
			array( '<li>Child</li>' )
		);

		$list = $this->get_element( $markup, 'ul' );

		$this->assertSame(
			array( 'has-background', 'has-pale-pink-background-color', 'has-text-color', 'has-vivid-red-color', 'wp-block-navigation-submenu', 'wp-block-navigation__submenu-container' ),
			$this->get_classes( $list )
		);
		$this->assertNotContains( 'has-text-color', $this->get_classes( $this->get_element( $markup, 'li' ) ), 'The item should not have the colors.' );
	}

	/**
	 * Tests the custom overlay colors of the Navigation block on the submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_add_the_custom_overlay_colors_to_the_submenu() {
		$list = $this->get_element(
			$this->render(
				array(),
				array(
					'customOverlayTextColor'       => '#112233',
					'customOverlayBackgroundColor' => '#445566',
				),
				array( '<li>Child</li>' )
			),
			'ul'
		);

		$this->assertStringContainsString( 'color:#112233', $list['style'] );
		$this->assertStringContainsString( 'background-color:#445566', $list['style'] );
	}

	/**
	 * Tests the colors of the item, which the submenu keeps.
	 *
	 * @ticket 66589
	 */
	public function test_should_keep_the_colors_of_the_attributes_on_the_submenu_without_overlay_colors() {
		$list = $this->get_element(
			$this->render( array( 'textColor' => 'luminous-vivid-amber' ), array(), array( '<li>Child</li>' ) ),
			'ul'
		);

		$this->assertContains( 'has-luminous-vivid-amber-color', $this->get_classes( $list ) );
	}

	/**
	 * Tests the shadow, which goes on the submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_add_the_shadow_to_the_submenu() {
		$markup = $this->render(
			array( 'style' => array( 'shadow' => 'var:preset|shadow|natural' ) ),
			array(),
			array( '<li>Child</li>' )
		);

		$this->assertStringContainsString( 'box-shadow:var(--wp--preset--shadow--natural)', $this->get_element( $markup, 'ul' )['style'] );
		$this->assertArrayNotHasKey( 'style', $this->get_element( $markup, 'li' ), 'The shadow should not be on the item.' );
	}

	/**
	 * Tests that the colors are left off when there is no submenu.
	 *
	 * @ticket 66589
	 */
	public function test_should_not_add_colors_without_a_submenu() {
		$markup = $this->render( array(), array( 'overlayTextColor' => 'vivid-red' ) );

		$this->assertStringNotContainsString( 'has-vivid-red-color', $markup );
	}
}
