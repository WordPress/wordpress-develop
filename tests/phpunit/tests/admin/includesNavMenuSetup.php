<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

/**
 * Tests for the functions that set up the Menus screen.
 *
 * @group admin
 * @group menus
 */
class Tests_Admin_includesNavMenuSetup extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original meta boxes.
	 *
	 * @var array
	 */
	private $original_meta_boxes;

	/**
	 * The original value of the global of the theme locations.
	 *
	 * @var mixed
	 */
	private $original_no_menus;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_meta_boxes = $GLOBALS['wp_meta_boxes'] ?? array();
		$this->original_no_menus   = $GLOBALS['one_theme_location_no_menus'] ?? null;

		$GLOBALS['wp_meta_boxes'] = array();
	}

	public function tear_down() {
		$GLOBALS['wp_meta_boxes'] = $this->original_meta_boxes;

		if ( null === $this->original_no_menus ) {
			unset( $GLOBALS['one_theme_location_no_menus'] );
		} else {
			$GLOBALS['one_theme_location_no_menus'] = $this->original_no_menus;
		}

		unregister_post_type( 'book' );
		unregister_post_type( 'note_type' );
		unregister_taxonomy( 'genre' );
		unregister_taxonomy( 'hidden_genre' );

		parent::tear_down();
	}

	/**
	 * Returns the meta boxes of the Menus screen by ID.
	 *
	 * @return array[] The meta boxes, with the context, the priority, the title, the callback and the arguments.
	 */
	private function get_boxes() {
		$boxes = array();

		foreach ( $GLOBALS['wp_meta_boxes']['nav-menus'] ?? array() as $context => $priorities ) {
			foreach ( $priorities as $priority => $priority_boxes ) {
				foreach ( $priority_boxes as $id => $box ) {
					if ( false === $box ) {
						continue;
					}

					$boxes[ $id ] = array(
						'context'  => $context,
						'priority' => $priority,
						'title'    => $box['title'],
						'callback' => $box['callback'],
						'args'     => $box['args'],
					);
				}
			}
		}

		return $boxes;
	}

	/**
	 * Tests the meta boxes of the post types.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_post_type_meta_boxes
	 */
	public function test_post_type_meta_boxes_are_registered_for_the_post_types_shown_in_menus() {
		register_post_type(
			'book',
			array(
				'public'            => true,
				'show_in_nav_menus' => true,
				'labels'            => array( 'name' => 'Books' ),
			)
		);
		register_post_type(
			'note_type',
			array(
				'public'            => true,
				'show_in_nav_menus' => false,
			)
		);

		wp_nav_menu_post_type_meta_boxes();

		$boxes = $this->get_boxes();

		$this->assertArrayHasKey( 'add-post-type-post', $boxes );
		$this->assertArrayHasKey( 'add-post-type-page', $boxes );
		$this->assertArrayHasKey( 'add-post-type-book', $boxes );
		$this->assertArrayNotHasKey( 'add-post-type-note_type', $boxes );
		$this->assertArrayNotHasKey( 'add-post-type-attachment', $boxes );
		$this->assertSame( 'Books', $boxes['add-post-type-book']['title'] );
		$this->assertSame( 'side', $boxes['add-post-type-book']['context'] );
		$this->assertSame( 'wp_nav_menu_item_post_type_meta_box', $boxes['add-post-type-book']['callback'] );
		$this->assertSame( 'book', $boxes['add-post-type-book']['args']->name );
	}

	/**
	 * Tests that pages have a higher priority.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_post_type_meta_boxes
	 */
	public function test_post_type_meta_boxes_give_pages_a_higher_priority() {
		wp_nav_menu_post_type_meta_boxes();

		$boxes = $this->get_boxes();

		$this->assertSame( 'core', $boxes['add-post-type-page']['priority'] );
		$this->assertSame( 'default', $boxes['add-post-type-post']['priority'] );
	}

	/**
	 * Tests the filter of the meta box object, which can remove a meta box.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_post_type_meta_boxes
	 * @covers ::wp_nav_menu_taxonomy_meta_boxes
	 */
	public function test_meta_boxes_use_the_filter_of_the_object() {
		$received = array();

		add_filter(
			'nav_menu_meta_box_object',
			static function ( $box_object ) use ( &$received ) {
				$received[] = $box_object->name;

				if ( in_array( $box_object->name, array( 'post', 'category' ), true ) ) {
					return false;
				}

				return $box_object;
			}
		);

		wp_nav_menu_post_type_meta_boxes();
		wp_nav_menu_taxonomy_meta_boxes();

		$boxes = $this->get_boxes();

		$this->assertContains( 'post', $received );
		$this->assertContains( 'category', $received );
		$this->assertArrayNotHasKey( 'add-post-type-post', $boxes );
		$this->assertArrayNotHasKey( 'add-category', $boxes );
		$this->assertArrayHasKey( 'add-post-type-page', $boxes );
		$this->assertArrayHasKey( 'add-post_tag', $boxes );
	}

	/**
	 * Tests that nothing is registered without post types or taxonomies that are shown in menus.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_post_type_meta_boxes
	 * @covers ::wp_nav_menu_taxonomy_meta_boxes
	 */
	public function test_meta_boxes_are_not_registered_without_objects_shown_in_menus() {
		$changed = array();

		foreach ( get_post_types( array( 'show_in_nav_menus' => true ), 'objects' ) as $post_type_object ) {
			$post_type_object->show_in_nav_menus = false;
			$changed[]                           = $post_type_object;
		}

		foreach ( get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' ) as $taxonomy_object ) {
			$taxonomy_object->show_in_nav_menus = false;
			$changed[]                          = $taxonomy_object;
		}

		wp_nav_menu_post_type_meta_boxes();
		wp_nav_menu_taxonomy_meta_boxes();

		$boxes = $this->get_boxes();

		foreach ( $changed as $changed_object ) {
			$changed_object->show_in_nav_menus = true;
		}

		$this->assertSame( array(), $boxes );
	}

	/**
	 * Tests the meta boxes of the taxonomies.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_taxonomy_meta_boxes
	 */
	public function test_taxonomy_meta_boxes_are_registered_for_the_taxonomies_shown_in_menus() {
		register_taxonomy(
			'genre',
			'post',
			array(
				'show_in_nav_menus' => true,
				'labels'            => array( 'name' => 'Genres' ),
			)
		);
		register_taxonomy(
			'hidden_genre',
			'post',
			array( 'show_in_nav_menus' => false )
		);

		wp_nav_menu_taxonomy_meta_boxes();

		$boxes = $this->get_boxes();

		$this->assertArrayHasKey( 'add-category', $boxes );
		$this->assertArrayHasKey( 'add-post_tag', $boxes );
		$this->assertArrayHasKey( 'add-genre', $boxes );
		$this->assertArrayNotHasKey( 'add-hidden_genre', $boxes );
		$this->assertSame( 'Genres', $boxes['add-genre']['title'] );
		$this->assertSame( 'side', $boxes['add-genre']['context'] );
		$this->assertSame( 'default', $boxes['add-genre']['priority'] );
		$this->assertSame( 'wp_nav_menu_item_taxonomy_meta_box', $boxes['add-genre']['callback'] );
		$this->assertSame( 'genre', $boxes['add-genre']['args']->name );
	}

	/**
	 * Tests the default query of the meta box objects.
	 *
	 * @ticket 66608
	 *
	 * @covers ::_wp_nav_menu_meta_box_object
	 *
	 * @dataProvider data_default_queries
	 *
	 * @param string $name     The name of the object.
	 * @param array  $expected The expected default query.
	 */
	public function test_meta_box_object_gets_a_default_query( $name, $expected ) {
		$box_object = (object) array( 'name' => $name );

		$result = _wp_nav_menu_meta_box_object( $box_object );

		$this->assertSame( $box_object, $result );
		$this->assertSame( $expected, $result->_default_query );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_default_queries() {
		return array(
			'page'     => array(
				'page',
				array(
					'orderby'     => 'menu_order title',
					'post_status' => 'publish',
				),
			),
			'post'     => array( 'post', array( 'post_status' => 'publish' ) ),
			'category' => array(
				'category',
				array(
					'orderby' => 'id',
					'order'   => 'DESC',
				),
			),
			'other'    => array( 'book', array( 'post_status' => 'publish' ) ),
			'tag'      => array( 'post_tag', array( 'post_status' => 'publish' ) ),
		);
	}

	/**
	 * Tests that an object without a name is returned as it is.
	 *
	 * @ticket 66608
	 *
	 * @covers ::_wp_nav_menu_meta_box_object
	 */
	public function test_meta_box_object_without_a_name_is_returned_as_it_is() {
		$box_object = (object) array( 'label' => 'No name' );

		$this->assertSame( $box_object, _wp_nav_menu_meta_box_object( $box_object ) );
		$this->assertObjectNotHasProperty( '_default_query', $box_object );
		$this->assertNull( _wp_nav_menu_meta_box_object() );
	}

	/**
	 * Tests that the default query is added through the filter of the meta box object.
	 *
	 * @ticket 66608
	 *
	 * @covers ::_wp_nav_menu_meta_box_object
	 */
	public function test_meta_box_objects_get_the_default_query_through_the_filter() {
		$this->assertSame( 10, has_filter( 'nav_menu_meta_box_object', '_wp_nav_menu_meta_box_object' ) );

		wp_nav_menu_post_type_meta_boxes();
		wp_nav_menu_taxonomy_meta_boxes();

		$boxes = $this->get_boxes();

		$this->assertSame( 'menu_order title', $boxes['add-post-type-page']['args']->_default_query['orderby'] );
		$this->assertSame( 'DESC', $boxes['add-category']['args']->_default_query['order'] );
	}

	/**
	 * Tests the setup of the screen.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_setup
	 */
	public function test_setup_registers_the_meta_boxes_and_the_columns() {
		wp_nav_menu_setup();

		$boxes = $this->get_boxes();

		$this->assertArrayHasKey( 'add-post-type-page', $boxes );
		$this->assertArrayHasKey( 'add-category', $boxes );
		$this->assertSame( 'Custom Links', $boxes['add-custom-links']['title'] );
		$this->assertSame( 'wp_nav_menu_item_link_meta_box', $boxes['add-custom-links']['callback'] );
		$this->assertSame( 'side', $boxes['add-custom-links']['context'] );
		$this->assertSame( 'default', $boxes['add-custom-links']['priority'] );
		$this->assertSame( 10, has_filter( 'manage_nav-menus_columns', 'wp_nav_menu_manage_columns' ) );
	}

	/**
	 * Tests that the advanced properties are hidden the first time.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_setup
	 */
	public function test_setup_hides_the_advanced_properties_the_first_time() {
		delete_user_option( self::$admin_id, 'managenav-menuscolumnshidden' );
		delete_user_meta( self::$admin_id, 'managenav-menuscolumnshidden' );

		wp_nav_menu_setup();

		$this->assertSame(
			array( 'link-target', 'css-classes', 'xfn', 'description', 'title-attribute' ),
			get_user_meta( self::$admin_id, 'managenav-menuscolumnshidden', true )
		);
	}

	/**
	 * Tests that the hidden columns that were saved are kept.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_setup
	 */
	public function test_setup_keeps_the_hidden_columns_that_were_saved() {
		update_user_meta( self::$admin_id, 'managenav-menuscolumnshidden', array( 'xfn' ) );

		wp_nav_menu_setup();

		$this->assertSame( array( 'xfn' ), get_user_meta( self::$admin_id, 'managenav-menuscolumnshidden', true ) );
	}

	/**
	 * Tests the meta boxes that are hidden the first time.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_initial_nav_menu_meta_boxes
	 */
	public function test_initial_meta_boxes_hide_the_ones_that_are_not_initial() {
		delete_user_meta( self::$admin_id, 'metaboxhidden_nav-menus' );

		wp_nav_menu_setup();

		$all_ids = array_keys( $this->get_boxes() );

		wp_initial_nav_menu_meta_boxes();

		$hidden = get_user_meta( self::$admin_id, 'metaboxhidden_nav-menus', true );

		$this->assertIsArray( $hidden );
		$this->assertNotContains( 'add-post-type-page', $hidden );
		$this->assertNotContains( 'add-post-type-post', $hidden );
		$this->assertNotContains( 'add-custom-links', $hidden );
		$this->assertNotContains( 'add-category', $hidden );
		$this->assertContains( 'add-post_tag', $hidden );
		$this->assertSame( array_values( array_diff( $all_ids, array( 'add-post-type-page', 'add-post-type-post', 'add-custom-links', 'add-category' ) ) ), array_values( $hidden ) );
	}

	/**
	 * Tests that the saved setting of the meta boxes is kept.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_initial_nav_menu_meta_boxes
	 */
	public function test_initial_meta_boxes_keep_the_saved_setting() {
		update_user_option( self::$admin_id, 'metaboxhidden_nav-menus', array( 'add-category' ) );

		wp_nav_menu_setup();
		wp_initial_nav_menu_meta_boxes();

		$this->assertSame( array( 'add-category' ), get_user_option( 'metaboxhidden_nav-menus', self::$admin_id ) );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'metaboxhidden_nav-menus', true ) );
	}

	/**
	 * Tests that nothing is saved when there are no meta boxes.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_initial_nav_menu_meta_boxes
	 */
	public function test_initial_meta_boxes_do_nothing_without_meta_boxes() {
		delete_user_meta( self::$admin_id, 'metaboxhidden_nav-menus' );

		$GLOBALS['wp_meta_boxes'] = null;

		wp_initial_nav_menu_meta_boxes();

		$this->assertSame( '', get_user_meta( self::$admin_id, 'metaboxhidden_nav-menus', true ) );
	}

	/**
	 * Tests the check that disables a field.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_disabled_check
	 */
	public function test_disabled_check() {
		$GLOBALS['one_theme_location_no_menus'] = false;

		$this->assertSame( " disabled='disabled'", wp_nav_menu_disabled_check( 0, false ) );
		$this->assertSame( '', wp_nav_menu_disabled_check( 5, false ) );

		ob_start();
		$result = wp_nav_menu_disabled_check( 0 );
		$output = ob_get_clean();

		$this->assertSame( " disabled='disabled'", $result );
		$this->assertSame( " disabled='disabled'", $output, 'The attribute should be echoed by default.' );
	}

	/**
	 * Tests that the check does not disable the field when a theme location has no menus.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_disabled_check
	 */
	public function test_disabled_check_is_false_when_a_theme_location_has_no_menus() {
		$GLOBALS['one_theme_location_no_menus'] = true;

		$this->assertFalse( wp_nav_menu_disabled_check( 0, false ) );

		ob_start();
		$result = wp_nav_menu_disabled_check( 0 );

		$this->assertSame( '', ob_get_clean() );
		$this->assertFalse( $result );
	}

	/**
	 * Tests the columns of the advanced properties.
	 *
	 * @ticket 66608
	 *
	 * @covers ::wp_nav_menu_manage_columns
	 */
	public function test_manage_columns() {
		$this->assertSame(
			array(
				'_title'          => 'Show advanced menu properties',
				'cb'              => '<input type="checkbox" />',
				'link-target'     => 'Link Target',
				'title-attribute' => 'Title Attribute',
				'css-classes'     => 'CSS Classes',
				'xfn'             => 'Link Relationship (XFN)',
				'description'     => 'Description',
			),
			wp_nav_menu_manage_columns()
		);
	}
}
