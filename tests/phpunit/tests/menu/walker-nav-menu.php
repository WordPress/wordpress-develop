<?php
/**
 * @group navmenus
 * @group walker
 */
class Tests_Walker_Nav_Menu extends WP_UnitTestCase {

	/**
	 * @var \Walker_Nav_Menu The instance of the walker.
	 */
	public $walker;

	/**
	 * Setup.
	 */
	public function setUp() {
		global $_wp_nav_menu_max_depth;

		parent::setUp();

		/** Walker_Nav_Menu_Edit class */
		require_once ABSPATH . 'wp-includes/class-walker-nav-menu.php';
		$this->walker = new Walker_Nav_Menu();

		$this->_wp_nav_menu_max_depth = $_wp_nav_menu_max_depth;
		parent::setUp();
	}

	/**
	 * Tear down
	 */
	public function tearDown() {
		global $_wp_nav_menu_max_depth;

		$_wp_nav_menu_max_depth = $this->_wp_nav_menu_max_depth;
		parent::tearDown();
	}

	/**
	 * Tests when an items target it _blank, that rel="'noopener noreferrer" is added.
	 *
	 * @ticket #43290
	 */
	public function test_noopener_no_referrer_for_target_blank() {
		$expected   = '';
		$post_id    = $this->factory->post->create();
		$post_title = get_the_title( $post_id );

		$item = array(
			'ID'        => $post_id,
			'object_id' => $post_id,
			'title'     => $post_title,
			'target'    => '_blank',
			'xfn'       => '',
			'current'   => false,
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		$this->walker->start_el( $expected, (object) $item, 0, (object) $args );

		$this->assertSame( "<li id=\"menu-item-{$post_id}\" class=\"menu-item-{$post_id}\"><a target=\"_blank\" rel=\"noopener noreferrer\">{$post_title}</a>", $expected );
	}
}
