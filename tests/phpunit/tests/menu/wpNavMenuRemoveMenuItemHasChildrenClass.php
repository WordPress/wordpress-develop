<?php

/**
 * @group menu
 */
class Tests_Menu_WpNavMenuRemoveMenuItemHasChildrenClass extends WP_UnitTestCase {

	/**
	 * Ensure calling filter in legacy ways does not throw an error.
	 *
	 * @ticket 56926
	 */
	public function test_legacy_filter_should_not_throw_an_error() {
		$classes = array( 'menu-item-has-children', 'menu-item', 'menu-item-123' );

		$menu_item = (object) array(
			'classes' => $classes,
		);

		$args = (object) array(
			'depth' => 2,
		);

		$depth = 2;

		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $menu_item ) );
		$this->assertStringContainsString( 'menu-item-has-children', $class_names, 'Class name should be retained when filter is called with two arguments.' );
		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $menu_item, $args ) );
		$this->assertStringContainsString( 'menu-item-has-children', $class_names, 'Class name should be retained when filter is called with three arguments.' );
		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $menu_item, $args, $depth ) );
		$this->assertStringNotContainsString( 'menu-item-has-children', $class_names, 'Class name should not be retained when filter is called with four arguments.' );
	}

	/**
	 * Ensure menu-item-has-children class is removed or retained as expected.
	 *
	 * @dataProvider data_menu_item_has_children_class_should_be_removed_or_retained_as_expected
	 * @ticket 56926
	 */
	public function test_menu_item_has_children_class_should_be_removed_or_retained_as_expected( $args, $depth, $should_be_retained ) {
		$classes = array( 'menu-item-has-children', 'menu-item', 'menu-item-123' );

		$menu_item = (object) array(
			'classes' => $classes,
		);

		$class_names = wp_nav_menu_remove_menu_item_has_children_class( $classes, $menu_item, $args, $depth );
		if ( $should_be_retained ) {
			$this->assertContains( 'menu-item-has-children', $class_names, 'Class name should be retained.' );
			return;
		}

		$this->assertNotContains( 'menu-item-has-children', $class_names, 'Class name should not be retained.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_menu_item_has_children_class_should_be_removed_or_retained_as_expected() {
		return array(
			'Depth not set'                          => array(
				'args'               => (object) array( 'depth' => 1 ),
				'depth'              => false,
				'should_be_retained' => true,
			),
			'Neither depth nor args set'             => array(
				'args'               => false,
				'depth'              => false,
				'should_be_retained' => true,
			),
			'Max depth is set to minus 1'            => array(
				'args'               => (object) array( 'depth' => -1 ),
				'depth'              => 1,
				'should_be_retained' => false,
			),
			'Max depth is set to zero'               => array(
				'args'               => (object) array( 'depth' => 0 ),
				'depth'              => 1,
				'should_be_retained' => true,
			),
			'Item depth exceeds max depth'           => array(
				'args'               => (object) array( 'depth' => 2 ),
				'depth'              => 3,
				'should_be_retained' => false,
			),
			'Item depth is lower than max depth'     => array(
				'args'               => (object) array( 'depth' => 5 ),
				'depth'              => 3,
				'should_be_retained' => true,
			),
			'Item depth is one lower than max depth' => array(
				'args'               => (object) array( 'depth' => 2 ),
				'depth'              => 1,
				'should_be_retained' => false, // Depth is zero-based, max depth is not.
			),
		);
	}
}
