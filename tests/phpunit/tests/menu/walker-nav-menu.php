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
	 * Original nav menu max depth.
	 *
	 * @var int
	 */
	private $orig_wp_nav_menu_max_depth;

	/**
	 * Setup.
	 */
	public function set_up() {
		global $_wp_nav_menu_max_depth;

		parent::set_up();

		/** Walker_Nav_Menu class */
		require_once ABSPATH . 'wp-includes/class-walker-nav-menu.php';
		$this->walker = new Walker_Nav_Menu();

		$this->orig_wp_nav_menu_max_depth = $_wp_nav_menu_max_depth;
	}

	/**
	 * Tear down
	 */
	public function tear_down() {
		global $_wp_nav_menu_max_depth;

		$_wp_nav_menu_max_depth = $this->orig_wp_nav_menu_max_depth;
		parent::tear_down();
	}

	/**
	 * Tests when an item's target is _blank, that rel="noopener" is added.
	 *
	 * @ticket 43290
	 */
	public function test_noopener_no_referrer_for_target_blank() {
		$actual     = '';
		$post_id    = self::factory()->post->create();
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
		$post_id    = self::factory()->post->create();
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

	/**
	 * Tests that `Walker_Nav_Menu::start_el()` adds `rel="privacy-policy"`.
	 *
	 * @ticket 56345
	 *
	 * @covers Walker_Nav_Menu::start_el
	 *
	 * @dataProvider data_walker_nav_menu_start_el_should_add_rel_privacy_policy_to_privacy_policy_url
	 *
	 * @param string $expected The expected substring containing the "rel" attribute and value.
	 * @param string $xfn      Optional. The XFN value. Default empty string.
	 * @param string $target   Optional. The target value. Default empty string.
	 */
	public function test_walker_nav_menu_start_el_should_add_rel_privacy_policy_to_privacy_policy_url( $expected, $xfn = '', $target = '' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Privacy Policy',
				'post_status' => 'publish',
			)
		);

		// Set the privacy policy page.
		update_option( 'wp_page_for_privacy_policy', $post_id );
		$privacy_policy_id = (int) get_option( 'wp_page_for_privacy_policy' );

		$output = '';

		$item = array(
			'ID'        => $privacy_policy_id,
			'object_id' => $privacy_policy_id,
			'title'     => 'Privacy Policy',
			'target'    => $target,
			'xfn'       => $xfn,
			'current'   => false,
			'url'       => get_privacy_policy_url(),
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		$this->walker->start_el( $output, (object) $item, 0, (object) $args );

		$this->assertStringContainsString( $expected, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_walker_nav_menu_start_el_should_add_rel_privacy_policy_to_privacy_policy_url() {
		return array(
			'no xfn value'                          => array(
				'expected' => 'rel="privacy-policy"',
			),
			'an xfn value'                          => array(
				'expected' => 'rel="nofollow privacy-policy"',
				'xfn'      => 'nofollow',
			),
			'no xfn value and a target of "_blank"' => array(
				'expected' => 'rel="noopener privacy-policy"',
				'xfn'      => '',
				'target'   => '_blank',
			),
			'an xfn value and a target of "_blank"' => array(
				'expected' => 'rel="nofollow privacy-policy"',
				'xfn'      => 'nofollow',
				'target'   => '_blank',
			),
		);
	}

	/**
	 * Tests that `Walker_Nav_Menu::start_el()` does not add `rel="privacy-policy"` when no
	 * privacy policy page exists.
	 *
	 * @ticket 56345
	 *
	 * @covers Walker_Nav_Menu::start_el
	 */
	public function test_walker_nav_menu_start_el_should_not_add_rel_privacy_policy_when_no_privacy_policy_exists() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Privacy Policy',
				'post_status' => 'publish',
			)
		);

		// Do not set the privacy policy page.

		$output = '';

		$item = array(
			'ID'        => $post_id,
			'object_id' => $post_id,
			'title'     => 'Privacy Policy',
			'target'    => '',
			'xfn'       => '',
			'current'   => false,
			'url'       => get_the_permalink( $post_id ),
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		$this->walker->start_el( $output, (object) $item, 0, (object) $args );

		$this->assertStringNotContainsString( 'rel="privacy-policy"', $output );
	}

	/**
	 * Tests that `Walker_Nav_Menu::start_el()` does not add `rel="privacy-policy"` when no URL
	 * is passed in the menu item object.
	 *
	 * @ticket 56345
	 *
	 * @covers Walker_Nav_Menu::start_el
	 */
	public function test_walker_nav_menu_start_el_should_not_add_rel_privacy_policy_when_no_url_is_passed() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Privacy Policy',
				'post_status' => 'publish',
			)
		);

		// Set the privacy policy page.
		update_option( 'wp_page_for_privacy_policy', $post_id );
		$privacy_policy_id = (int) get_option( 'wp_page_for_privacy_policy' );

		$output = '';

		$item = array(
			'ID'        => $privacy_policy_id,
			'object_id' => $privacy_policy_id,
			'title'     => 'Privacy Policy',
			'target'    => '',
			'xfn'       => '',
			'current'   => false,
			// Do not pass URL.
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		$this->walker->start_el( $output, (object) $item, 0, (object) $args );

		$this->assertStringNotContainsString( 'rel="privacy-policy"', $output );
	}

	/**
	 * Tests that `Walker_Nav_Menu::start_el()` does not add `rel="privacy-policy"` when the
	 * menu item's ID does not match the privacy policy page, but the URL does.
	 *
	 * @ticket 56345
	 *
	 * @covers Walker_Nav_Menu::start_el
	 */
	public function test_walker_nav_menu_start_el_should_add_rel_privacy_policy_when_id_does_not_match_but_url_does() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Privacy Policy',
				'post_status' => 'publish',
			)
		);

		// Set the privacy policy page.
		update_option( 'wp_page_for_privacy_policy', $post_id );
		$privacy_policy_id = (int) get_option( 'wp_page_for_privacy_policy' );

		$output = '';

		// Ensure the ID does not match the privacy policy.
		$not_privacy_policy_id = $privacy_policy_id - 1;

		$item = array(
			'ID'        => $not_privacy_policy_id,
			'object_id' => $not_privacy_policy_id,
			'title'     => 'Privacy Policy',
			'target'    => '',
			'xfn'       => '',
			'current'   => false,
			'url'       => get_privacy_policy_url(),
		);

		$args = array(
			'before'      => '',
			'after'       => '',
			'link_before' => '',
			'link_after'  => '',
		);

		$this->walker->start_el( $output, (object) $item, 0, (object) $args );

		$this->assertStringContainsString( 'rel="privacy-policy"', $output );
	}
}
