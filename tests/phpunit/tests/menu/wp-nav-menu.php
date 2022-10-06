<?php

/**
 * @group menu
 *
 * @covers ::wp_nav_menu
 */
class Tests_Menu_wpNavMenu extends WP_UnitTestCase {

	protected $menu_id        = 0;
	protected $lvl0_menu_item = 0;
	protected $lvl1_menu_item = 0;
	protected $lvl2_menu_item = 0;

	public function set_up() {
		parent::set_up();

		// Create nav menu.
		$this->menu_id = wp_create_nav_menu( 'test' );

		// Create lvl0 menu item.
		$this->lvl0_menu_item = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'  => 'Root menu item',
				'menu-item-url'    => '#',
				'menu-item-status' => 'publish',
			)
		);

		// Create lvl1 menu item.
		$this->lvl1_menu_item = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'     => 'Lvl1 menu item',
				'menu-item-url'       => '#',
				'menu-item-parent-id' => $this->lvl0_menu_item,
				'menu-item-status'    => 'publish',
			)
		);

		// Create lvl2 menu item.
		$this->lvl2_menu_item = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			array(
				'menu-item-title'     => 'Lvl2 menu item',
				'menu-item-url'       => '#',
				'menu-item-parent-id' => $this->lvl1_menu_item,
				'menu-item-status'    => 'publish',
			)
		);
	}

	public function tear_down() {
		wp_delete_nav_menu( $this->menu_id );
		parent::tear_down();
	}

	/**
	 * Test all menu items containing children have the CSS class `menu-item-has-children` when displaying the menu
	 * without specifying a custom depth.
	 *
	 * @ticket 28620
	 */
	public function test_wp_nav_menu_should_have_has_children_class_without_custom_depth() {

		// Render the menu with all its hierarchy.
		$menu_html = wp_nav_menu(
			array(
				'menu' => $this->menu_id,
				'echo' => false,
			)
		);

		// Level 0 should be present in the HTML output and have the `menu-item-has-children` class.
		$this->assertStringContainsString(
			sprintf(
				'<li id="menu-item-%1$d" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-%1$d">',
				$this->lvl0_menu_item,
				'Level 0 should be present in the HTML output and have the menu-item-has-children class'
			),
			$menu_html
		);

		// Level 1 should be present in the HTML output and have the `menu-item-has-children` class.
		$this->assertStringContainsString(
			sprintf(
				'<li id="menu-item-%1$d" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-%1$d">',
				$this->lvl1_menu_item,
				'Level 1 should be present in the HTML output and have the menu-item-has-children class'
			),
			$menu_html
		);

		// Level 2 should be present in the HTML output and not have the `menu-item-has-children` class since it has no
		// children.
		$this->assertStringContainsString(
			sprintf(
				'<li id="menu-item-%1$d" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-%1$d">',
				$this->lvl2_menu_item,
				'Level 2 should be present in the HTML output and not have the `menu-item-has-children` class since it has no children'
			),
			$menu_html
		);
	}

	/**
	 * Tests that when displaying a menu with a custom depth, the last menu item doesn't have the CSS class
	 * `menu-item-has-children` even if it's the case when displaying the full menu.
	 *
	 * @ticket 28620
	 */
	public function test_wp_nav_menu_should_not_have_has_children_class_with_custom_depth() {

		// Render the menu limited to 1 level of hierarchy (Lvl0 + Lvl1).
		$menu_html = wp_nav_menu(
			array(
				'menu'  => $this->menu_id,
				'depth' => 2,
				'echo'  => false,
			)
		);

		// Level 0 should be present in the HTML output and have the `menu-item-has-children` class.
		$this->assertStringContainsString(
			sprintf(
				'<li id="menu-item-%1$d" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-%1$d">',
				$this->lvl0_menu_item,
				'Level 0 should be present in the HTML output and have the menu-item-has-children class'
			),
			$menu_html
		);

		// Level 1 should be present in the HTML output and not have the `menu-item-has-children` class since its the
		// last item to be rendered.
		$this->assertStringContainsString(
			sprintf(
				'<li id="menu-item-%1$d" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-%1$d">',
				$this->lvl1_menu_item,
				'Level 1 should be present in the HTML output and not have the `menu-item-has-children` class since its the last item to be rendered'
			),
			$menu_html
		);

		// Level 2 should not be present in the HTML output.
		$this->assertStringNotContainsString(
			sprintf(
				'<li id="menu-item-%d"',
				$this->lvl2_menu_item,
				'Level 2 should not be present in the HTML output'
			),
			$menu_html
		);
	}

}
