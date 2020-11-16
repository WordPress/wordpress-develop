<?php

/**
 * @group admin
 * @group adminScreen
 */
class Tests_Admin_includesScreen extends WP_UnitTestCase {
	var $core_screens = array(
		'index.php'                            => array(
			'base'            => 'dashboard',
			'id'              => 'dashboard',
			'is_block_editor' => false,
		),
		'edit.php'                             => array(
			'base'            => 'edit',
			'id'              => 'edit-post',
			'post_type'       => 'post',
			'is_block_editor' => false,
		),
		'post-new.php'                         => array(
			'action'          => 'add',
			'base'            => 'post',
			'id'              => 'post',
			'post_type'       => 'post',
			'is_block_editor' => true,
		),
		'post.php'                             => array(
			'base'            => 'post',
			'id'              => 'post',
			'post_type'       => 'post',
			'is_block_editor' => true,
		),
		'edit-tags.php'                        => array(
			'base'            => 'edit-tags',
			'id'              => 'edit-post_tag',
			'post_type'       => 'post',
			'taxonomy'        => 'post_tag',
			'is_block_editor' => false,
		),
		'edit-tags.php?taxonomy=post_tag'      => array(
			'base'            => 'edit-tags',
			'id'              => 'edit-post_tag',
			'post_type'       => 'post',
			'taxonomy'        => 'post_tag',
			'is_block_editor' => false,
		),
		'edit-tags.php?taxonomy=category'      => array(
			'base'            => 'edit-tags',
			'id'              => 'edit-category',
			'post_type'       => 'post',
			'taxonomy'        => 'category',
			'is_block_editor' => false,
		),
		'upload.php'                           => array(
			'base'            => 'upload',
			'id'              => 'upload',
			'post_type'       => 'attachment',
			'is_block_editor' => false,
		),
		'media-new.php'                        => array(
			'action'          => 'add',
			'base'            => 'media',
			'id'              => 'media',
			'is_block_editor' => false,
		),
		'edit.php?post_type=page'              => array(
			'base'            => 'edit',
			'id'              => 'edit-page',
			'post_type'       => 'page',
			'is_block_editor' => false,
		),
		'link-manager.php'                     => array(
			'base'            => 'link-manager',
			'id'              => 'link-manager',
			'is_block_editor' => false,
		),
		'link-add.php'                         => array(
			'action'          => 'add',
			'base'            => 'link',
			'id'              => 'link',
			'is_block_editor' => false,
		),
		'edit-tags.php?taxonomy=link_category' => array(
			'base'            => 'edit-tags',
			'id'              => 'edit-link_category',
			'taxonomy'        => 'link_category',
			'post_type'       => '',
			'is_block_editor' => false,
		),
		'edit-comments.php'                    => array(
			'base'            => 'edit-comments',
			'id'              => 'edit-comments',
			'is_block_editor' => false,
		),
		'themes.php'                           => array(
			'base'            => 'themes',
			'id'              => 'themes',
			'is_block_editor' => false,
		),
		'widgets.php'                          => array(
			'base'            => 'widgets',
			'id'              => 'widgets',
			'is_block_editor' => false,
		),
		'nav-menus.php'                        => array(
			'base'            => 'nav-menus',
			'id'              => 'nav-menus',
			'is_block_editor' => false,
		),
		'plugins.php'                          => array(
			'base'            => 'plugins',
			'id'              => 'plugins',
			'is_block_editor' => false,
		),
		'users.php'                            => array(
			'base'            => 'users',
			'id'              => 'users',
			'is_block_editor' => false,
		),
		'user-new.php'                         => array(
			'action'          => 'add',
			'base'            => 'user',
			'id'              => 'user',
			'is_block_editor' => false,
		),
		'profile.php'                          => array(
			'base'            => 'profile',
			'id'              => 'profile',
			'is_block_editor' => false,
		),
		'tools.php'                            => array(
			'base'            => 'tools',
			'id'              => 'tools',
			'is_block_editor' => false,
		),
		'import.php'                           => array(
			'base'            => 'import',
			'id'              => 'import',
			'is_block_editor' => false,
		),
		'export.php'                           => array(
			'base'            => 'export',
			'id'              => 'export',
			'is_block_editor' => false,
		),
		'options-general.php'                  => array(
			'base'            => 'options-general',
			'id'              => 'options-general',
			'is_block_editor' => false,
		),
		'options-writing.php'                  => array(
			'base'            => 'options-writing',
			'id'              => 'options-writing',
			'is_block_editor' => false,
		),
	);

	function setUp() {
		set_current_screen( 'front' );
		parent::setUp();
	}

	function tearDown() {
		unset( $GLOBALS['wp_taxonomies']['old-or-new'] );
		unset( $GLOBALS['screen'] );
		unset( $GLOBALS['current_screen'] );
		parent::tearDown();
	}

	function test_set_current_screen_with_hook_suffix() {
		global $current_screen;

		foreach ( $this->core_screens as $hook_name => $screen ) {
			$_GET               = array();
			$_POST              = array();
			$_REQUEST           = array();
			$GLOBALS['taxnow']  = '';
			$GLOBALS['typenow'] = '';
			$screen             = (object) $screen;
			$hook               = parse_url( $hook_name );

			if ( ! empty( $hook['query'] ) ) {
				$args = wp_parse_args( $hook['query'] );
				if ( isset( $args['taxonomy'] ) ) {
					$GLOBALS['taxnow']    = $args['taxonomy'];
					$_GET['taxonomy']     = $args['taxonomy'];
					$_POST['taxonomy']    = $args['taxonomy'];
					$_REQUEST['taxonomy'] = $args['taxonomy'];
				}
				if ( isset( $args['post_type'] ) ) {
					$GLOBALS['typenow']    = $args['post_type'];
					$_GET['post_type']     = $args['post_type'];
					$_POST['post_type']    = $args['post_type'];
					$_REQUEST['post_type'] = $args['post_type'];
				} elseif ( isset( $screen->post_type ) ) {
					$GLOBALS['typenow']    = $screen->post_type;
					$_GET['post_type']     = $screen->post_type;
					$_POST['post_type']    = $screen->post_type;
					$_REQUEST['post_type'] = $screen->post_type;
				}
			}

			$GLOBALS['hook_suffix'] = $hook['path'];
			set_current_screen();

			$this->assertSame( $screen->id, $current_screen->id, $hook_name );
			$this->assertSame( $screen->base, $current_screen->base, $hook_name );
			if ( isset( $screen->action ) ) {
				$this->assertSame( $screen->action, $current_screen->action, $hook_name );
			}
			if ( isset( $screen->post_type ) ) {
				$this->assertSame( $screen->post_type, $current_screen->post_type, $hook_name );
			} else {
				$this->assertEmpty( $current_screen->post_type, $hook_name );
			}
			if ( isset( $screen->taxonomy ) ) {
				$this->assertSame( $screen->taxonomy, $current_screen->taxonomy, $hook_name );
			}

			$this->assertTrue( $current_screen->in_admin() );
			$this->assertTrue( $current_screen->in_admin( 'site' ) );
			$this->assertFalse( $current_screen->in_admin( 'network' ) );
			$this->assertFalse( $current_screen->in_admin( 'user' ) );
			$this->assertFalse( $current_screen->in_admin( 'garbage' ) );
			$this->assertSame( $screen->is_block_editor, $current_screen->is_block_editor );

			// With convert_to_screen(), the same ID should return the exact $current_screen.
			$this->assertSame( $current_screen, convert_to_screen( $screen->id ), $hook_name );

			// With convert_to_screen(), the hook_suffix should return the exact $current_screen.
			// But, convert_to_screen() cannot figure out ?taxonomy and ?post_type.
			if ( empty( $hook['query'] ) ) {
				$this->assertSame( $current_screen, convert_to_screen( $GLOBALS['hook_suffix'] ), $hook_name );
			}
		}
	}

	function test_post_type_as_hookname() {
		$screen = convert_to_screen( 'page' );
		$this->assertSame( $screen->post_type, 'page' );
		$this->assertSame( $screen->base, 'post' );
		$this->assertSame( $screen->id, 'page' );
		$this->assertTrue( $screen->is_block_editor );
	}

	function test_post_type_with_special_suffix_as_hookname() {
		register_post_type( 'value-add' );
		$screen = convert_to_screen( 'value-add' ); // The '-add' part is key.
		$this->assertSame( $screen->post_type, 'value-add' );
		$this->assertSame( $screen->base, 'post' );
		$this->assertSame( $screen->id, 'value-add' );
		$this->assertFalse( $screen->is_block_editor ); // Post types do not support `show_in_rest` by default.

		$screen = convert_to_screen( 'edit-value-add' ); // The '-add' part is key.
		$this->assertSame( $screen->post_type, 'value-add' );
		$this->assertSame( $screen->base, 'edit' );
		$this->assertSame( $screen->id, 'edit-value-add' );
		$this->assertFalse( $screen->is_block_editor ); // Post types do not support `show_in_rest` by default.
	}

	function test_taxonomy_with_special_suffix_as_hookname() {
		register_taxonomy( 'old-or-new', 'post' );
		$screen = convert_to_screen( 'edit-old-or-new' ); // The '-new' part is key.
		$this->assertSame( $screen->taxonomy, 'old-or-new' );
		$this->assertSame( $screen->base, 'edit-tags' );
		$this->assertSame( $screen->id, 'edit-old-or-new' );
		$this->assertFalse( $screen->is_block_editor );
	}

	function test_post_type_with_edit_prefix() {
		register_post_type( 'edit-some-thing' );
		$screen = convert_to_screen( 'edit-some-thing' );
		$this->assertSame( $screen->post_type, 'edit-some-thing' );
		$this->assertSame( $screen->base, 'post' );
		$this->assertSame( $screen->id, 'edit-some-thing' );
		$this->assertFalse( $screen->is_block_editor ); // Post types do not support `show_in_rest` by default.

		$screen = convert_to_screen( 'edit-edit-some-thing' );
		$this->assertSame( $screen->post_type, 'edit-some-thing' );
		$this->assertSame( $screen->base, 'edit' );
		$this->assertSame( $screen->id, 'edit-edit-some-thing' );
		$this->assertFalse( $screen->is_block_editor ); // Post types do not support `show_in_rest` by default.
	}

	function test_post_type_edit_collisions() {
		register_post_type( 'comments' );
		register_post_type( 'tags' );

		// Sorry, core wins here.
		$screen = convert_to_screen( 'edit-comments' );
		$this->assertSame( $screen->base, 'edit-comments' );

		// The post type wins here. convert_to_screen( $post_type ) is only relevant for meta boxes anyway.
		$screen = convert_to_screen( 'comments' );
		$this->assertSame( $screen->base, 'post' );

		// Core wins.
		$screen = convert_to_screen( 'edit-tags' );
		$this->assertSame( $screen->base, 'edit-tags' );

		$screen = convert_to_screen( 'tags' );
		$this->assertSame( $screen->base, 'post' );
	}

	function test_help_tabs() {
		$tab      = __FUNCTION__;
		$tab_args = array(
			'title'    => 'Help!',
			'id'       => $tab,
			'content'  => 'Some content',
			'callback' => false,
		);

		$screen = get_current_screen();
		$screen->add_help_tab( $tab_args );
		$this->assertSame(
			$screen->get_help_tab( $tab ),
			array(
				'title'    => 'Help!',
				'id'       => $tab,
				'content'  => 'Some content',
				'callback' => false,
				'priority' => 10,
			)
		);

		$tabs = $screen->get_help_tabs();
		$this->assertArrayHasKey( $tab, $tabs );

		$screen->remove_help_tab( $tab );
		$this->assertNull( $screen->get_help_tab( $tab ) );

		$screen->remove_help_tabs();
		$this->assertSame( $screen->get_help_tabs(), array() );
	}

	/**
	 * @ticket 19828
	 */
	function test_help_tabs_priority() {
		$tab_1      = 'tab1';
		$tab_1_args = array(
			'title'    => 'Help!',
			'id'       => $tab_1,
			'content'  => 'Some content',
			'callback' => false,
			'priority' => 10,
		);

		$tab_2      = 'tab2';
		$tab_2_args = array(
			'title'    => 'Help!',
			'id'       => $tab_2,
			'content'  => 'Some content',
			'callback' => false,
			'priority' => 2,
		);
		$tab_3      = 'tab3';
		$tab_3_args = array(
			'title'    => 'help!',
			'id'       => $tab_3,
			'content'  => 'some content',
			'callback' => false,
			'priority' => 40,
		);
		$tab_4      = 'tab4';
		$tab_4_args = array(
			'title'    => 'help!',
			'id'       => $tab_4,
			'content'  => 'some content',
			'callback' => false,
			// Don't include a priority.
		);

		$screen = get_current_screen();

		// Add help tabs.

		$screen->add_help_tab( $tab_1_args );
		$this->assertSame( $screen->get_help_tab( $tab_1 ), $tab_1_args );

		$screen->add_help_tab( $tab_2_args );
		$this->assertSame( $screen->get_help_tab( $tab_2 ), $tab_2_args );

		$screen->add_help_tab( $tab_3_args );
		$this->assertSame( $screen->get_help_tab( $tab_3 ), $tab_3_args );

		$screen->add_help_tab( $tab_4_args );
		// Priority is added with the default for future calls.
		$tab_4_args['priority'] = 10;
		$this->assertSame( $screen->get_help_tab( $tab_4 ), $tab_4_args );

		$tabs = $screen->get_help_tabs();
		$this->assertSame( 4, count( $tabs ) );
		$this->assertArrayHasKey( $tab_1, $tabs );
		$this->assertArrayHasKey( $tab_2, $tabs );
		$this->assertArrayHasKey( $tab_3, $tabs );
		$this->assertArrayHasKey( $tab_4, $tabs );

		// Test priority order.

		$this->assertSame(
			array(
				$tab_2 => $tab_2_args,
				$tab_1 => $tab_1_args,
				$tab_4 => $tab_4_args,
				$tab_3 => $tab_3_args,
			),
			$tabs
		);

		$screen->remove_help_tab( $tab_1 );
		$this->assertNull( $screen->get_help_tab( $tab_1 ) );
		$this->assertSame( 3, count( $screen->get_help_tabs() ) );

		$screen->remove_help_tab( $tab_2 );
		$this->assertNull( $screen->get_help_tab( $tab_2 ) );
		$this->assertSame( 2, count( $screen->get_help_tabs() ) );

		$screen->remove_help_tab( $tab_3 );
		$this->assertNull( $screen->get_help_tab( $tab_3 ) );
		$this->assertSame( 1, count( $screen->get_help_tabs() ) );

		$screen->remove_help_tab( $tab_4 );
		$this->assertNull( $screen->get_help_tab( $tab_4 ) );
		$this->assertSame( 0, count( $screen->get_help_tabs() ) );

		$screen->remove_help_tabs();
		$this->assertSame( array(), $screen->get_help_tabs() );
	}

	/**
	 * @ticket 25799
	 */
	function test_options() {
		$option      = __FUNCTION__;
		$option_args = array(
			'label'   => 'Option',
			'default' => 10,
			'option'  => $option,
		);

		$screen = get_current_screen();

		$screen->add_option( $option, $option_args );
		$this->assertSame( $screen->get_option( $option ), $option_args );

		$options = $screen->get_options();
		$this->assertArrayHasKey( $option, $options );

		$screen->remove_option( $option );
		$this->assertNull( $screen->get_option( $option ) );

		$screen->remove_options();
		$this->assertSame( $screen->get_options(), array() );
	}

	function test_in_admin() {
		$screen = get_current_screen();

		set_current_screen( 'edit.php' );
		$this->assertTrue( get_current_screen()->in_admin() );
		$this->assertTrue( get_current_screen()->in_admin( 'site' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'network' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'user' ) );

		set_current_screen( 'dashboard-network' );
		$this->assertTrue( get_current_screen()->in_admin() );
		$this->assertFalse( get_current_screen()->in_admin( 'site' ) );
		$this->assertTrue( get_current_screen()->in_admin( 'network' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'user' ) );

		set_current_screen( 'dashboard-user' );
		$this->assertTrue( get_current_screen()->in_admin() );
		$this->assertFalse( get_current_screen()->in_admin( 'site' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'network' ) );
		$this->assertTrue( get_current_screen()->in_admin( 'user' ) );

		set_current_screen( 'front' );
		$this->assertFalse( get_current_screen()->in_admin() );
		$this->assertFalse( get_current_screen()->in_admin( 'site' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'network' ) );
		$this->assertFalse( get_current_screen()->in_admin( 'user' ) );

		$GLOBALS['current_screen'] = $screen;
	}

	/**
	 * Sets up a method for testing is_block_editor for a custom post type.
	 *
	 * @since 5.2.0
	 *
	 * @param string $hook Admin page hook.
	 * @return WP_Screen Screen object.
	 */
	public function setup_block_editor_test( $hook = 'post.php' ) {
		register_post_type( 'type_shows_in_rest', array( 'show_in_rest' => true ) );

		$GLOBALS['typenow']     = 'type_shows_in_rest';
		$_GET['post_type']      = 'type_shows_in_rest';
		$_POST['post_type']     = 'type_shows_in_rest';
		$_REQUEST['post_type']  = 'type_shows_in_rest';
		$GLOBALS['hook_suffix'] = $hook;

		if ( 'post.php' === $hook ) {
			$post_id      = $this->factory->post->create(
				array(
					'post_type' => 'type_shows_in_rest',
				)
			);
			$_GET['post'] = $post_id;
		}

		set_current_screen();

		return get_current_screen();
	}

	/**
	 * Data provider for testing is_block_editor.
	 */
	public function data_is_block_editor() {
		return array(
			array(
				// Edit post: Post type supports `show_in_rest`, no filters.
				'hook'     => 'post.php',
				'filter'   => array(),
				'expected' => true,
			),
			array(
				// Edit post: Support is disabled using post specific filter.
				'hook'     => 'post.php',
				'filter'   => array(
					'name'     => 'use_block_editor_for_post',
					'function' => '__return_false',
				),
				'expected' => false,
			),
			array(
				// Edit post: Support is disabled using post type specific filter.
				'hook'     => 'post.php',
				'filter'   => array(
					'name'     => 'use_block_editor_for_post_type',
					'function' => '__return_false',
				),
				'expected' => false,
			),
			array(
				// Edit post: Support is disabled using global replace filter.
				'hook'     => 'post.php',
				'filter'   => array(
					'name'     => 'replace_editor',
					'function' => '__return_true',
				),
				'expected' => false,
			),
			array(
				// Create post: Post type supports `show_in_rest`, no filters.
				'hook'     => 'post-new.php',
				'filter'   => array(),
				'expected' => true,
			),
			array(
				// Create post: Support is disabled using post type specific filter.
				'hook'     => 'post-new.php',
				'filter'   => array(
					'name'     => 'use_block_editor_for_post_type',
					'function' => '__return_false',
				),
				'expected' => false,
			),

			array(
				// Create post: Support is not immediately disabled using post specific filter.
				'hook'     => 'post-new.php',
				'filter'   => array(
					'name'     => 'use_block_editor_for_post',
					'function' => '__return_false',
				),
				'expected' => true,
			),

			array(
				// Create post: Support is not immediately disabled using global replace filter.
				'hook'     => 'post-new.php',
				'filter'   => array(
					'name'     => 'replace_editor',
					'function' => '__return_true',
				),
				'expected' => true,
			),
		);
	}

	/**
	 * When editing a post type with `show_in_rest` support, the is_block_editor should indicate support.
	 *
	 * @ticket 46195
	 * @dataProvider data_is_block_editor
	 *
	 * @param string $hook Admin hook.
	 * @param array  $filter {
	 *     Optional. Filter name and function to hook.
	 *
	 *     $name     string Filter name to hook a function.
	 *     $function string Function name to hook to the filter.
	 * }
	 * @param bool   $expected The expected `is_block_editor` value.
	 */
	public function test_is_block_editor( $hook, $filter, $expected ) {
		if ( ! empty( $filter['name'] ) && ! empty( $filter['function'] ) ) {
			add_filter( $filter['name'], $filter['function'] );
		}

		$screen = $this->setup_block_editor_test( $hook );

		$this->assertSame( 'post', $screen->base );
		$this->assertSame( 'type_shows_in_rest', $screen->post_type );

		if ( 'post.php' === $hook ) {
			$this->assertEmpty( $screen->action );
		} else {
			$this->assertSame( 'add', $screen->action );
		}

		$this->assertSame( $expected, $screen->is_block_editor );
	}
}
