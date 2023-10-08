<?php

/**
 * Tests WP_Customize_Nav_Menu_Setting.
 *
 * @group customize
 */
class Test_WP_Customize_Nav_Menu_Setting extends WP_UnitTestCase {

	/**
	 * Instance of WP_Customize_Manager which is reset for each test.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * Set up a test case.
	 *
	 * @see WP_UnitTestCase_Base::set_up()
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $wp_customize;
		$this->wp_customize = new WP_Customize_Manager();
		$wp_customize       = $this->wp_customize;
	}

	/**
	 * Delete the $wp_customize global when cleaning up scope.
	 */
	public function clean_up_global_scope() {
		global $wp_customize;
		$wp_customize = null;
		parent::clean_up_global_scope();
	}

	/**
	 * Helper for getting the nav_menu_options option.
	 *
	 * @return array
	 */
	private function get_nav_menu_items_option() {
		return get_option( 'nav_menu_options', array( 'auto_add' => array() ) );
	}

	/**
	 * Test constants and statics.
	 */
	public function test_constants() {
		do_action( 'customize_register', $this->wp_customize );
		$this->assertTrue( taxonomy_exists( WP_Customize_Nav_Menu_Setting::TAXONOMY ) );
	}

	/**
	 * Test constructor.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::__construct()
	 */
	public function test_construct() {
		do_action( 'customize_register', $this->wp_customize );

		$setting = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, 'nav_menu[123]' );
		$this->assertSame( 'nav_menu', $setting->type );
		$this->assertSame( 'postMessage', $setting->transport );
		$this->assertSame( 123, $setting->term_id );
		$this->assertNull( $setting->previous_term_id );
		$this->assertNull( $setting->update_status );
		$this->assertNull( $setting->update_error );
		$this->assertIsArray( $setting->default );
		foreach ( array( 'name', 'description', 'parent' ) as $key ) {
			$this->assertArrayHasKey( $key, $setting->default );
		}
		$this->assertSame( '', $setting->default['name'] );
		$this->assertSame( '', $setting->default['description'] );
		$this->assertSame( 0, $setting->default['parent'] );

		$exception = null;
		try {
			$bad_setting = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, 'foo_bar_baz' );
			unset( $bad_setting );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
	}

	/**
	 * Test empty constructor.
	 */
	public function test_construct_empty_menus() {
		do_action( 'customize_register', $this->wp_customize );
		$_wp_customize = $this->wp_customize;
		unset( $_wp_customize->nav_menus );

		$exception = null;
		try {
			$bad_setting = new WP_Customize_Nav_Menu_Setting( $_wp_customize, 'nav_menu_item[123]' );
			unset( $bad_setting );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
	}

	/**
	 * Test constructor for placeholder (draft) menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::__construct()
	 */
	public function test_construct_placeholder() {
		do_action( 'customize_register', $this->wp_customize );
		$default = array(
			'name'        => 'Lorem \\o/',
			'description' => 'ipsum \\o/',
			'parent'      => 123,
		);
		$setting = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, 'nav_menu[-5]', compact( 'default' ) );
		$this->assertSame( -5, $setting->term_id );
		$this->assertSame( $default, $setting->default );
	}

	/**
	 * Test value method.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::value()
	 */
	public function test_value() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_name      = 'Test 123 \\o/';
		$parent_menu_id = wp_create_nav_menu( wp_slash( "Parent $menu_name" ) );
		$description    = 'Hello my world \\o/.';
		$menu_id        = wp_update_nav_menu_object(
			0,
			wp_slash(
				array(
					'menu-name'   => $menu_name,
					'parent'      => $parent_menu_id,
					'description' => $description,
				)
			)
		);

		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );

		$value = $setting->value();
		$this->assertIsArray( $value );
		foreach ( array( 'name', 'description', 'parent' ) as $key ) {
			$this->assertArrayHasKey( $key, $value );
		}
		$this->assertSame( $menu_name, $value['name'] );
		$this->assertSame( $description, $value['description'] );
		$this->assertSame( $parent_menu_id, $value['parent'] );

		$new_menu_name = 'Foo';
		wp_update_nav_menu_object( $menu_id, wp_slash( array( 'menu-name' => $new_menu_name ) ) );
		$updated_value = $setting->value();
		$this->assertSame( $new_menu_name, $updated_value['name'] );
	}

	/**
	 * Test preview method for updated menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::preview()
	 */
	public function test_preview_updated() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_id    = wp_update_nav_menu_object(
			0,
			wp_slash(
				array(
					'menu-name'   => 'Name 1 \\o/',
					'description' => 'Description 1 \\o/',
					'parent'      => 0,
				)
			)
		);
		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertNotContains( $menu_id, $nav_menu_options['auto_add'] );

		$post_value = array(
			'name'        => 'Name 2 \\o/',
			'description' => 'Description 2 \\o/',
			'parent'      => 1,
			'auto_add'    => true,
		);
		$this->wp_customize->set_post_value( $setting_id, $post_value );

		$value = $setting->value();
		$this->assertSame( 'Name 1 \\o/', $value['name'] );
		$this->assertSame( 'Description 1 \\o/', $value['description'] );
		$this->assertSame( 0, $value['parent'] );

		$term = (array) wp_get_nav_menu_object( $menu_id );

		$this->assertSameSets(
			wp_array_slice_assoc( $value, array( 'name', 'description', 'parent' ) ),
			wp_array_slice_assoc( $term, array( 'name', 'description', 'parent' ) )
		);

		$setting->preview();
		$value = $setting->value();
		$this->assertSame( 'Name 2 \\o/', $value['name'] );
		$this->assertSame( 'Description 2 \\o/', $value['description'] );
		$this->assertSame( 1, $value['parent'] );
		$term = (array) wp_get_nav_menu_object( $menu_id );
		$this->assertSameSets( $value, wp_array_slice_assoc( $term, array_keys( $value ) ) );

		$menu_object = wp_get_nav_menu_object( $menu_id );
		$this->assertEquals( (object) $term, $menu_object );
		$this->assertSame( $post_value['name'], $menu_object->name );

		$nav_menu_options = get_option( 'nav_menu_options', array( 'auto_add' => array() ) );
		$this->assertContains( $menu_id, $nav_menu_options['auto_add'] );

		$menus     = wp_get_nav_menus();
		$menus_ids = wp_list_pluck( $menus, 'term_id' );
		$i         = array_search( $menu_id, $menus_ids, true );
		$this->assertIsInt( $i, 'Update-previewed menu does not appear in wp_get_nav_menus()' );
		$filtered_menu = $menus[ $i ];
		$this->assertSame( 'Name 2 \\o/', $filtered_menu->name );
	}

	/**
	 * Test preview method for inserted menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::preview()
	 */
	public function test_preview_inserted() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_id    = -123;
		$post_value = array(
			'name'        => 'New Menu Name 1 \\o/',
			'description' => 'New Menu Description 1 \\o/',
			'parent'      => 0,
			'auto_add'    => false,
		);
		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );

		$this->wp_customize->set_post_value( $setting->id, $post_value );
		$setting->preview();
		$value = $setting->value();
		$this->assertSame( $post_value, $value );

		$term = (array) wp_get_nav_menu_object( $menu_id );
		$this->assertNotEmpty( $term );
		$this->assertNotWPError( $term );
		$this->assertSameSets( $post_value, wp_array_slice_assoc( $term, array_keys( $value ) ) );
		$this->assertSame( $menu_id, $term['term_id'] );
		$this->assertSame( $menu_id, $term['term_taxonomy_id'] );

		$menu_object = wp_get_nav_menu_object( $menu_id );
		$this->assertEquals( (object) $term, $menu_object );
		$this->assertSame( $post_value['name'], $menu_object->name );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertNotContains( $menu_id, $nav_menu_options['auto_add'] );

		$menus     = wp_get_nav_menus();
		$menus_ids = wp_list_pluck( $menus, 'term_id' );
		$i         = array_search( $menu_id, $menus_ids, true );
		$this->assertIsInt( $i, 'Insert-previewed menu was not injected into wp_get_nav_menus()' );
		$filtered_menu = $menus[ $i ];
		$this->assertSame( 'New Menu Name 1 \\o/', $filtered_menu->name );
	}

	/**
	 * Test preview method for deleted menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::preview()
	 */
	public function test_preview_deleted() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_id                        = wp_update_nav_menu_object(
			0,
			wp_slash(
				array(
					'menu-name'   => 'Name 1 \\o/',
					'description' => 'Description 1 \\o/',
					'parent'      => 0,
				)
			)
		);
		$setting_id                     = "nav_menu[$menu_id]";
		$setting                        = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );
		$nav_menu_options               = $this->get_nav_menu_items_option();
		$nav_menu_options['auto_add'][] = $menu_id;
		update_option( 'nav_menu_options', $nav_menu_options );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertContains( $menu_id, $nav_menu_options['auto_add'] );

		$this->wp_customize->set_post_value( $setting_id, false );

		$this->assertIsArray( $setting->value() );
		$this->assertIsObject( wp_get_nav_menu_object( $menu_id ) );
		$setting->preview();
		$this->assertFalse( $setting->value() );
		$this->assertFalse( wp_get_nav_menu_object( $menu_id ) );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertNotContains( $menu_id, $nav_menu_options['auto_add'] );
	}

	/**
	 * Test sanitize method.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::sanitize()
	 */
	public function test_sanitize() {
		do_action( 'customize_register', $this->wp_customize );
		$setting = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, 'nav_menu[123]' );

		$this->assertNull( $setting->sanitize( 'not an array' ) );
		$this->assertNull( $setting->sanitize( 123 ) );

		$value     = array(
			'name'        => ' Hello \\o/ <b>world</b> ',
			'description' => "New\nline \\o/",
			'parent'      => -12,
			'auto_add'    => true,
			'extra'       => 'ignored',
		);
		$sanitized = $setting->sanitize( $value );
		$this->assertSame( 'Hello \\o/ &lt;b&gt;world&lt;/b&gt;', $sanitized['name'] );
		$this->assertSame( 'New line \\o/', $sanitized['description'] );
		$this->assertSame( 0, $sanitized['parent'] );
		$this->assertTrue( $sanitized['auto_add'] );
		$this->assertSameSets( array( 'name', 'description', 'parent', 'auto_add' ), array_keys( $sanitized ) );

		$value['name'] = '    '; // Blank spaces.
		$sanitized     = $setting->sanitize( $value );
		$this->assertSame( '(unnamed)', $sanitized['name'] );
	}

	/**
	 * Test protected update() method via the save() method, for updated menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 */
	public function test_save_updated() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_id                        = wp_update_nav_menu_object(
			0,
			wp_slash(
				array(
					'menu-name'   => 'Name 1 \\o/',
					'description' => 'Description 1 \\o/',
					'parent'      => 0,
				)
			)
		);
		$nav_menu_options               = $this->get_nav_menu_items_option();
		$nav_menu_options['auto_add'][] = $menu_id;
		update_option( 'nav_menu_options', $nav_menu_options );

		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );

		$auto_add  = false;
		$new_value = array(
			'name'        => 'Name 2 \\o/',
			'description' => 'Description 2 \\o/',
			'parent'      => 1,
			'auto_add'    => $auto_add,
		);

		$this->wp_customize->set_post_value( $setting_id, $new_value );
		$setting->save();

		$menu_object = wp_get_nav_menu_object( $menu_id );
		foreach ( array( 'name', 'description', 'parent' ) as $key ) {
			$this->assertSame( $new_value[ $key ], $menu_object->$key );
		}
		$this->assertSameSets(
			wp_array_slice_assoc( $new_value, array( 'name', 'description', 'parent' ) ),
			wp_array_slice_assoc( (array) $menu_object, array( 'name', 'description', 'parent' ) )
		);
		$this->assertSame( $new_value, $setting->value() );

		$save_response = apply_filters( 'customize_save_response', array() );
		$this->assertArrayHasKey( 'nav_menu_updates', $save_response );
		$update_result = array_shift( $save_response['nav_menu_updates'] );
		$this->assertArrayHasKey( 'term_id', $update_result );
		$this->assertArrayHasKey( 'previous_term_id', $update_result );
		$this->assertArrayHasKey( 'error', $update_result );
		$this->assertArrayHasKey( 'status', $update_result );
		$this->assertArrayHasKey( 'saved_value', $update_result );
		$this->assertSame( $new_value, $update_result['saved_value'] );

		$this->assertSame( $menu_id, $update_result['term_id'] );
		$this->assertNull( $update_result['previous_term_id'] );
		$this->assertNull( $update_result['error'] );
		$this->assertSame( 'updated', $update_result['status'] );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertNotContains( $menu_id, $nav_menu_options['auto_add'] );
	}

	/**
	 * Test protected update() method via the save() method, for inserted menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 */
	public function test_save_inserted() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_id    = -123;
		$post_value = array(
			'name'        => 'New Menu Name 1 \\o/',
			'description' => 'New Menu Description 1 \\o/',
			'parent'      => 0,
			'auto_add'    => true,
		);
		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );

		$this->wp_customize->set_post_value( $setting->id, $post_value );

		$this->assertNull( $setting->previous_term_id );
		$this->assertLessThan( 0, $setting->term_id );
		$setting->save();
		$this->assertSame( $menu_id, $setting->previous_term_id );
		$this->assertGreaterThan( 0, $setting->term_id );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertContains( $setting->term_id, $nav_menu_options['auto_add'] );

		$menu = wp_get_nav_menu_object( $setting->term_id );
		unset( $post_value['auto_add'] );
		$this->assertSameSets( $post_value, wp_array_slice_assoc( (array) $menu, array_keys( $post_value ) ) );

		$save_response = apply_filters( 'customize_save_response', array() );
		$this->assertArrayHasKey( 'nav_menu_updates', $save_response );
		$update_result = array_shift( $save_response['nav_menu_updates'] );
		$this->assertArrayHasKey( 'term_id', $update_result );
		$this->assertArrayHasKey( 'previous_term_id', $update_result );
		$this->assertArrayHasKey( 'error', $update_result );
		$this->assertArrayHasKey( 'status', $update_result );
		$this->assertArrayHasKey( 'saved_value', $update_result );
		$this->assertSame( $setting->value(), $update_result['saved_value'] );

		$this->assertSame( $menu->term_id, $update_result['term_id'] );
		$this->assertSame( $menu_id, $update_result['previous_term_id'] );
		$this->assertNull( $update_result['error'] );
		$this->assertSame( 'inserted', $update_result['status'] );
	}

	/**
	 * Test saving a new name that conflicts with an existing nav menu's name.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 */
	public function test_save_inserted_conflicted_name() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_name = 'Foo';
		wp_update_nav_menu_object( 0, wp_slash( array( 'menu-name' => $menu_name ) ) );

		$menu_id    = -123;
		$setting_id = "nav_menu[$menu_id]";
		$setting    = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );
		$this->wp_customize->set_post_value( $setting->id, array( 'name' => $menu_name ) );
		$setting->save();

		$expected_resolved_menu_name = "$menu_name (2)";
		$new_menu                    = wp_get_nav_menu_object( $setting->term_id );
		$this->assertSame( $expected_resolved_menu_name, $new_menu->name );

		$save_response = apply_filters( 'customize_save_response', array() );
		$this->assertSame( $expected_resolved_menu_name, $save_response['nav_menu_updates'][0]['saved_value']['name'] );
	}

	/**
	 * Test protected update() method via the save() method, for deleted menu.
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 */
	public function test_save_deleted() {
		do_action( 'customize_register', $this->wp_customize );

		$menu_name                      = 'Lorem Ipsum \\o/';
		$menu_id                        = wp_create_nav_menu( wp_slash( $menu_name ) );
		$setting_id                     = "nav_menu[$menu_id]";
		$setting                        = new WP_Customize_Nav_Menu_Setting( $this->wp_customize, $setting_id );
		$nav_menu_options               = $this->get_nav_menu_items_option();
		$nav_menu_options['auto_add'][] = $menu_id;
		update_option( 'nav_menu_options', $nav_menu_options );

		$menu = wp_get_nav_menu_object( $menu_id );
		$this->assertSame( $menu_name, $menu->name );

		$this->wp_customize->set_post_value( $setting_id, false );
		$setting->save();

		$this->assertFalse( wp_get_nav_menu_object( $menu_id ) );

		$save_response = apply_filters( 'customize_save_response', array() );
		$this->assertArrayHasKey( 'nav_menu_updates', $save_response );
		$update_result = array_shift( $save_response['nav_menu_updates'] );
		$this->assertArrayHasKey( 'term_id', $update_result );
		$this->assertArrayHasKey( 'previous_term_id', $update_result );
		$this->assertArrayHasKey( 'error', $update_result );
		$this->assertArrayHasKey( 'status', $update_result );
		$this->assertArrayHasKey( 'saved_value', $update_result );
		$this->assertNull( $update_result['saved_value'] );

		$this->assertSame( $menu_id, $update_result['term_id'] );
		$this->assertNull( $update_result['previous_term_id'] );
		$this->assertNull( $update_result['error'] );
		$this->assertSame( 'deleted', $update_result['status'] );

		$nav_menu_options = $this->get_nav_menu_items_option();
		$this->assertNotContains( $menu_id, $nav_menu_options['auto_add'] );
	}
}
