<?php

/**
 * @group menu
 * @group walker
 */
class Tests_Menu_Walker_Nav_Menu_Edit extends WP_UnitTestCase {
	protected $_wp_nav_menu_max_depth;

	public function set_up() {
		global $_wp_nav_menu_max_depth;

		parent::set_up();

		/** Walker_Nav_Menu_Edit class */
		require_once ABSPATH . 'wp-admin/includes/class-walker-nav-menu-edit.php';

		$this->walker = new Walker_Nav_Menu_Edit();

		$this->_wp_nav_menu_max_depth = $_wp_nav_menu_max_depth;
	}

	public function tear_down() {
		global $_wp_nav_menu_max_depth;

		$_wp_nav_menu_max_depth = $this->_wp_nav_menu_max_depth;

		parent::tear_down();
	}

	/**
	 * @ticket 36729
	 */
	public function test_original_title_prefix_should_not_be_shown_if_empty() {
		$expected = '';

		$post_id = $this->factory->post->create();

		$item = array(
			'classes'          => array(),
			'description'      => '',
			'ID'               => $post_id,
			'menu_item_parent' => 0,
			'menu_order'       => 0,
			'object_id'        => $post_id,
			'object'           => 'post',
			'post_excerpt'     => get_the_excerpt( $post_id ),
			'title'            => get_the_title( $post_id ),
			'type'             => 'foobar',
			'type_label'       => 'Foo Bar',
			'target'           => '_blank',
			'url'              => '',
			'xfn'              => '',
		);

		$this->walker->start_el( $expected, (object) $item );

		$this->assertDoesNotMatchRegularExpression( '#<p class="link-to-original">\s*Original: <a href=""></a>#', $expected );
	}
}
