<?php
/**
 * @group menu
 * @group walker
 */
class Tests_Menu_Walker_Nav_Menu extends WP_UnitTestCase {

	/**
	 * @var \Walker_Nav_Menu The instance of the walker.
	 */
	public $walker;

	/**
	 * Setup.
	 */
	public function set_up() {
		global $_wp_nav_menu_max_depth;

		parent::set_up();

		/** Walker_Nav_Menu class */
		require_once ABSPATH . 'wp-includes/class-walker-nav-menu.php';
		$this->walker = new Walker_Nav_Menu();

		$this->_wp_nav_menu_max_depth = $_wp_nav_menu_max_depth;
	}

	/**
	 * Tear down
	 */
	public function tear_down() {
		global $_wp_nav_menu_max_depth;

		$_wp_nav_menu_max_depth = $this->_wp_nav_menu_max_depth;
		parent::tear_down();
	}

	/**
	 * Tests when an item's target is _blank, that rel="noopener" is added.
	 *
	 * @ticket 43290
	 */
	public function test_noopener_no_referrer_for_target_blank() {
		$actual     = '';
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

		$this->walker->start_el( $actual, (object) $item, 0, (object) $args );

		$this->assertSame( "<li id=\"menu-item-{$post_id}\" class=\"menu-item-{$post_id}\"><a target=\"_blank\" rel=\"noopener\">{$post_title}</a>", $actual );
	}

	/**
	 * @ticket 47720
	 *
	 * @dataProvider data_start_el_with_empty_attributes
	 */
	public function test_start_el_with_empty_attributes( $value, $expected ) {
		$output     = '';
		$post_id    = $this->factory->post->create();
		$post_title = get_the_title( $post_id );

		$item = array(
			'ID'        => $post_id,
			'object_id' => $post_id,
			'title'     => $post_title,
			'target'    => '',
			'xfn'       => '',
			'current'   => false,
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		add_filter(
			'nav_menu_link_attributes',
			static function( $atts ) use ( $value ) {
				$atts['data-test'] = $value;
				return $atts;
			}
		);

		$this->walker->start_el( $output, (object) $item, 0, (object) $args );

		if ( '' !== $expected ) {
			$expected = sprintf( ' data-test="%s"', $expected );
		}

		$this->assertSame( "<li id=\"menu-item-{$post_id}\" class=\"menu-item-{$post_id}\"><a{$expected}>{$post_title}</a>", $output );
	}

	public function data_start_el_with_empty_attributes() {
		return array(
			array(
				'',
				'',
			),
			array(
				0,
				'0',
			),
			array(
				0.0,
				'0',
			),
			array(
				'0',
				'0',
			),
			array(
				null,
				'',
			),
			array(
				false,
				'',
			),
			array(
				true,
				'1',
			),
			array(
				array(),
				'',
			),
		);
	}
}
