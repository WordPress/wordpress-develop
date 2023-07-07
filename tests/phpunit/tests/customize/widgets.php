<?php

/**
 * Tests for the WP_Customize_Widgets class.
 *
 * @group customize
 */
class Tests_WP_Customize_Widgets extends WP_UnitTestCase {

	/**
	 * @var WP_Customize_Manager
	 */
	protected $manager;

	/**
	 * Stored global variable in setUp for restoration in tearDown.
	 *
	 * @see $wp_registered_sidebars
	 * @var array
	 */
	protected $backup_registered_sidebars;

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

		add_theme_support( 'customize-selective-refresh-widgets' );
		add_action( 'widgets_init', array( $this, 'remove_widgets_block_editor' ) );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		update_option(
			'widget_search',
			array(
				2              => array( 'title' => '' ),
				'_multiwidget' => 1,
			)
		);
		update_option(
			'widget_categories',
			array(
				2              => array(
					'title'        => '',
					'count'        => 0,
					'hierarchical' => 0,
					'dropdown'     => 0,
				),
				'_multiwidget' => 1,
			)
		);
		update_option(
			'sidebars_widgets',
			array(
				'wp_inactive_widgets' => array(),
				'sidebar-1'           => array( 'search-2' ),
				'sidebar-2'           => array( 'categories-2' ),
				'array_version'       => 3,
			)
		);

		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->manager           = $GLOBALS['wp_customize'];

		unset( $GLOBALS['_wp_sidebars_widgets'] ); // Clear out cache set by wp_get_sidebars_widgets().
		$sidebars_widgets = wp_get_sidebars_widgets();
		$this->assertSameSets( array( 'wp_inactive_widgets', 'sidebar-1', 'sidebar-2' ), array_keys( wp_get_sidebars_widgets() ) );
		$this->assertContains( 'search-2', $sidebars_widgets['sidebar-1'] );
		$this->assertContains( 'categories-2', $sidebars_widgets['sidebar-2'] );
		$this->assertArrayHasKey( 2, get_option( 'widget_search' ) );
		$widget_categories = get_option( 'widget_categories' );
		$this->assertArrayHasKey( 2, $widget_categories );
		$this->assertSame( '', $widget_categories[2]['title'] );

		$this->backup_registered_sidebars = $GLOBALS['wp_registered_sidebars'];

		// Reset protected static var on class.
		WP_Customize_Setting::reset_aggregated_multidimensionals();
	}

	public function clean_up_global_scope() {
		global $wp_widget_factory, $wp_registered_sidebars, $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

		$wp_registered_sidebars        = array();
		$wp_registered_widgets         = array();
		$wp_registered_widget_controls = array();
		$wp_registered_widget_updates  = array();
		$wp_widget_factory->widgets    = array();

		parent::clean_up_global_scope();
	}

	public function tear_down() {
		$this->manager = null;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		$GLOBALS['wp_registered_sidebars'] = $this->backup_registered_sidebars;
		parent::tear_down();
	}

	private function set_customized_post_data( $customized ) {
		$_POST['customized'] = wp_slash( wp_json_encode( $customized ) );
		if ( $this->manager ) {
			foreach ( $customized as $id => $value ) {
				$this->manager->set_post_value( $id, $value );
			}
		}
	}

	private function do_customize_boot_actions() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['nonce']         = wp_create_nonce( 'preview-customize_' . $this->manager->theme()->get_stylesheet() );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );
		do_action( 'init' );
		do_action( 'wp_loaded' );
		do_action( 'wp', $GLOBALS['wp'] );
	}

	public function remove_widgets_block_editor() {
		remove_theme_support( 'widgets-block-editor' );
	}

	/**
	 * Test WP_Customize_Widgets::__construct()
	 */
	public function test_construct() {
		$this->assertInstanceOf( 'WP_Customize_Widgets', $this->manager->widgets );
		$this->assertSame( $this->manager, $this->manager->widgets->manager );
	}

	/**
	 * Test registering sidebars without an extant sidebars_widgets option.
	 *
	 * @see WP_Customize_Widgets::customize_register()
	 * @see WP_Customize_Widgets::preview_sidebars_widgets()
	 * @ticket 36660
	 */
	public function test_customize_register_with_deleted_sidebars() {
		$sidebar_id = 'sidebar-1';
		delete_option( 'sidebars_widgets' );
		register_sidebar( array( 'id' => $sidebar_id ) );
		$this->manager->widgets->customize_register();
		$this->assertSame( array_fill_keys( array( 'wp_inactive_widgets', $sidebar_id ), array() ), wp_get_sidebars_widgets() );
	}

	/**
	 * Tests the label and description controls when registering sidebars with Customizer.
	 *
	 * @ticket       53487
	 * @dataProvider data_customize_register_control_label_and_description
	 * @covers       WP_Customize_Widgets::customize_register
	 */
	public function test_customize_register_control_label_and_description( $sidebars, $use_classic_widgets, $expected ) {
		if ( $use_classic_widgets ) {
			add_filter( 'use_widgets_block_editor', '__return_false' );
		}

		foreach ( $sidebars as $args ) {
			register_sidebar( $args );
		}

		$this->manager->widgets->customize_register();

		$label       = array();
		$description = array();
		foreach ( array_keys( $sidebars ) as $sidebar_id ) {
			$control_id    = "sidebars_widgets[{$sidebar_id}]";
			$control       = $this->manager->get_control( $control_id );
			$label[]       = $control->label;
			$description[] = $control->description;
		}

		$this->assertSame( $expected['label'], $label );
		$this->assertSame( $expected['description'], $description );
	}

	public function data_customize_register_control_label_and_description() {
		return array(
			'with widgets block editor' => array(
				'sidebars'            => array(
					'footer-1' => array(
						'id'          => 'footer-1',
						'name'        => 'Footer 1',
						'description' => 'This is the Footer 1 sidebar.',
					),
					'footer-2' => array(
						'id'          => 'footer-2',
						'name'        => 'Footer 2',
						'description' => 'This is the Footer 2 sidebar.',
					),
				),
				'use_classic_widgets' => false,
				'expected'            => array(
					'label'       => array( 'Footer 1', 'Footer 2' ),
					'description' => array( '', '' ),
				),
			),
			'with classic widgets'      => array(
				'sidebars'            => array(
					'classic-1' => array(
						'id'          => 'classic-1',
						'name'        => 'Classic 1',
						'description' => 'This is the Classic 1 sidebar.',
					),
					'classic-2' => array(
						'id'          => 'classic-2',
						'name'        => 'Classic 2',
						'description' => 'This is the Classic 2 sidebar.',
					),
					'classic-3' => array(
						'id'          => 'classic-3',
						'name'        => 'Classic 3',
						'description' => 'This is the Classic 3 sidebar.',
					),
				),
				'use_classic_widgets' => true,
				'expected'            => array(
					'label'       => array( '', '', '' ),
					'description' => array( '', '', '' ),
				),
			),
		);
	}

	/**
	 * Tests WP_Customize_Widgets::get_selective_refreshable_widgets().
	 *
	 * @see WP_Customize_Widgets::get_selective_refreshable_widgets()
	 */
	public function test_get_selective_refreshable_widgets_when_theme_supports() {
		global $wp_widget_factory;
		add_action( 'widgets_init', array( $this, 'override_search_widget_customize_selective_refresh' ), 90 );
		add_theme_support( 'customize-selective-refresh-widgets' );
		$this->do_customize_boot_actions();

		$selective_refreshable_widgets = $this->manager->widgets->get_selective_refreshable_widgets();
		$this->assertIsArray( $selective_refreshable_widgets );
		$this->assertSame( count( $wp_widget_factory->widgets ), count( $selective_refreshable_widgets ) );
		$this->assertArrayHasKey( 'text', $selective_refreshable_widgets );
		$this->assertTrue( $selective_refreshable_widgets['text'] );
		$this->assertArrayHasKey( 'search', $selective_refreshable_widgets );
		$this->assertFalse( $selective_refreshable_widgets['search'] );
	}

	/**
	 * Tests WP_Customize_Widgets::get_selective_refreshable_widgets().
	 *
	 * @see WP_Customize_Widgets::get_selective_refreshable_widgets()
	 */
	public function test_get_selective_refreshable_widgets_when_no_theme_supports() {
		add_action( 'widgets_init', array( $this, 'override_search_widget_customize_selective_refresh' ), 90 );
		remove_theme_support( 'customize-selective-refresh-widgets' );
		$this->do_customize_boot_actions();
		$selective_refreshable_widgets = $this->manager->widgets->get_selective_refreshable_widgets();
		$this->assertEmpty( $selective_refreshable_widgets );
	}

	/**
	 * Hook into widgets_init to override the search widget's customize_selective_refresh widget option.
	 *
	 * @see Tests_WP_Customize_Widgets::test_get_selective_refreshable_widgets_when_theme_supports()
	 * @see Tests_WP_Customize_Widgets::test_get_selective_refreshable_widgets_when_no_theme_supports()
	 */
	public function override_search_widget_customize_selective_refresh() {
		global $wp_widget_factory;
		$wp_widget_factory->widgets['WP_Widget_Search']->widget_options['customize_selective_refresh'] = false;
	}

	/**
	 * Tests WP_Customize_Widgets::is_widget_selective_refreshable().
	 *
	 * @see WP_Customize_Widgets::is_widget_selective_refreshable()
	 */
	public function test_is_widget_selective_refreshable() {
		add_action( 'widgets_init', array( $this, 'override_search_widget_customize_selective_refresh' ), 90 );
		add_theme_support( 'customize-selective-refresh-widgets' );
		$this->do_customize_boot_actions();
		$this->assertFalse( $this->manager->widgets->is_widget_selective_refreshable( 'search' ) );
		$this->assertTrue( $this->manager->widgets->is_widget_selective_refreshable( 'text' ) );
		remove_theme_support( 'customize-selective-refresh-widgets' );
		$this->assertFalse( $this->manager->widgets->is_widget_selective_refreshable( 'text' ) );
	}

	/**
	 * Test WP_Customize_Widgets::register_settings() with selective refresh enabled.
	 *
	 * @ticket 30988
	 * @ticket 36389
	 */
	public function test_register_settings() {
		add_theme_support( 'customize-selective-refresh-widgets' );

		$raw_widget_customized = array(
			'widget_categories[2]' => array(
				'title'        => 'Taxonomies Brand New Value',
				'count'        => 0,
				'hierarchical' => 0,
				'dropdown'     => 0,
			),
			'widget_search[3]'     => array(
				'title' => 'Not as good as Google!',
			),
		);
		$customized            = array();
		foreach ( $raw_widget_customized as $setting_id => $instance ) {
			$customized[ $setting_id ] = $this->manager->widgets->sanitize_widget_js_instance( $instance );
		}

		$this->set_customized_post_data( $customized );
		$this->do_customize_boot_actions();
		$this->assertTrue( is_customize_preview() );

		if ( current_theme_supports( 'customize-selective-refresh-widgets' ) ) {
			$expected_transport = 'postMessage';
			$this->assertNotEmpty( $this->manager->widgets->get_selective_refreshable_widgets() );
		} else {
			$expected_transport = 'refresh';
			$this->assertEmpty( $this->manager->widgets->get_selective_refreshable_widgets() );
		}

		$setting = $this->manager->get_setting( 'widget_categories[2]' );
		$this->assertNotEmpty( $setting, 'Expected setting for pre-existing widget category-2, being customized.' );
		$this->assertSame( $expected_transport, $setting->transport );

		$setting = $this->manager->get_setting( 'widget_search[2]' );
		$this->assertNotEmpty( $setting, 'Expected setting for pre-existing widget search-2, not being customized.' );
		$this->assertSame( $expected_transport, $setting->transport );

		$setting = $this->manager->get_setting( 'widget_search[3]' );
		$this->assertNotEmpty( $setting, 'Expected dynamic setting for non-existing widget search-3, being customized.' );
		$this->assertSame( $expected_transport, $setting->transport );

		$widget_categories = get_option( 'widget_categories' );
		$this->assertSame( $raw_widget_customized['widget_categories[2]'], $widget_categories[2], 'Expected $wp_customize->get_setting(widget_categories[2])->preview() to have been called.' );
	}

	/**
	 * Test registering settings without selective refresh enabled.
	 *
	 * @ticket 36389
	 */
	public function test_register_settings_without_selective_refresh() {
		remove_theme_support( 'customize-selective-refresh-widgets' );
		$this->test_register_settings();
	}

	/**
	 * Test registering settings with selective refresh enabled at a late after_setup_theme action.
	 *
	 * @ticket 36389
	 */
	public function test_register_settings_with_late_theme_support_added() {
		remove_theme_support( 'customize-selective-refresh-widgets' );
		add_action( 'after_setup_theme', array( $this, 'add_customize_selective_refresh_theme_support' ), 100 );
		$this->test_register_settings();
	}

	/**
	 * Add customize-selective-refresh-widgets theme support.
	 */
	public function add_customize_selective_refresh_theme_support() {
		add_theme_support( 'customize-selective-refresh-widgets' );
	}

	/**
	 * Test WP_Customize_Widgets::get_setting_args()
	 */
	public function test_get_setting_args() {
		add_theme_support( 'customize-selective-refresh-widgets' );
		$this->do_customize_boot_actions();

		add_filter( 'widget_customizer_setting_args', array( $this, 'filter_widget_customizer_setting_args' ), 10, 2 );

		$default_args = array(
			'type'       => 'option',
			'capability' => 'edit_theme_options',
			'transport'  => 'refresh',
			'default'    => array(),
		);
		$args         = $this->manager->widgets->get_setting_args( 'widget_foo[2]' );
		foreach ( $default_args as $key => $default_value ) {
			$this->assertSame( $default_value, $args[ $key ] );
		}
		$this->assertTrue( is_callable( $args['sanitize_callback'] ), 'sanitize_callback is callable' );
		$this->asserttrue( is_callable( $args['sanitize_js_callback'] ), 'sanitize_js_callback is callable' );
		$this->assertSame( 'WIDGET_FOO[2]', $args['uppercase_id_set_by_filter'] );

		$default_args = array(
			'type'       => 'option',
			'capability' => 'edit_theme_options',
			'transport'  => 'postMessage',
			'default'    => array(),
		);
		$args         = $this->manager->widgets->get_setting_args( 'widget_search[2]' );
		foreach ( $default_args as $key => $default_value ) {
			$this->assertSame( $default_value, $args[ $key ] );
		}
		$this->assertTrue( is_callable( $args['sanitize_callback'] ), 'sanitize_callback is callable' );
		$this->asserttrue( is_callable( $args['sanitize_js_callback'] ), 'sanitize_js_callback is callable' );

		remove_theme_support( 'customize-selective-refresh-widgets' );
		$args = $this->manager->widgets->get_setting_args( 'widget_search[2]' );
		$this->assertSame( 'refresh', $args['transport'] );
		add_theme_support( 'customize-selective-refresh-widgets' );

		$override_args = array(
			'type'                 => 'theme_mod',
			'capability'           => 'edit_posts',
			'transport'            => 'postMessage',
			'default'              => array( 'title' => 'asd' ),
			'sanitize_callback'    => '__return_empty_array',
			'sanitize_js_callback' => '__return_empty_array',
		);
		$args          = $this->manager->widgets->get_setting_args( 'widget_bar[3]', $override_args );
		foreach ( $override_args as $key => $override_value ) {
			$this->assertSame( $override_value, $args[ $key ] );
		}
		$this->assertSame( 'WIDGET_BAR[3]', $args['uppercase_id_set_by_filter'] );

		$default_args = array(
			'type'       => 'option',
			'capability' => 'edit_theme_options',
			'transport'  => 'postMessage',
			'default'    => array(),
		);
		$args         = $this->manager->widgets->get_setting_args( 'sidebars_widgets[sidebar-1]' );
		foreach ( $default_args as $key => $default_value ) {
			$this->assertSame( $default_value, $args[ $key ] );
		}
		$this->assertTrue( is_callable( $args['sanitize_callback'] ), 'sanitize_callback is callable' );
		$this->asserttrue( is_callable( $args['sanitize_js_callback'] ), 'sanitize_js_callback is callable' );
		$this->assertSame( 'SIDEBARS_WIDGETS[SIDEBAR-1]', $args['uppercase_id_set_by_filter'] );

		$override_args = array(
			'type'                 => 'theme_mod',
			'capability'           => 'edit_posts',
			'transport'            => 'postMessage',
			'default'              => array( 'title' => 'asd' ),
			'sanitize_callback'    => '__return_empty_array',
			'sanitize_js_callback' => '__return_empty_array',
		);
		$args          = $this->manager->widgets->get_setting_args( 'sidebars_widgets[sidebar-2]', $override_args );
		foreach ( $override_args as $key => $override_value ) {
			$this->assertSame( $override_value, $args[ $key ] );
		}
		$this->assertSame( 'SIDEBARS_WIDGETS[SIDEBAR-2]', $args['uppercase_id_set_by_filter'] );
	}

	public function filter_widget_customizer_setting_args( $args, $id ) {
		$args['uppercase_id_set_by_filter'] = strtoupper( $id );
		return $args;
	}

	/**
	 * Test WP_Customize_Widgets::sanitize_widget_js_instance() and WP_Customize_Widgets::sanitize_widget_instance()
	 */
	public function test_sanitize_widget_js_instance() {
		$this->do_customize_boot_actions();

		$new_categories_instance = array(
			'title'        => 'Taxonomies Brand New Value',
			'count'        => '1',
			'hierarchical' => '1',
			'dropdown'     => '1',
		);

		$sanitized_for_js = $this->manager->widgets->sanitize_widget_js_instance( $new_categories_instance );
		$this->assertArrayHasKey( 'encoded_serialized_instance', $sanitized_for_js );
		$this->assertTrue( is_serialized( base64_decode( $sanitized_for_js['encoded_serialized_instance'] ), true ) );
		$this->assertSame( $new_categories_instance['title'], $sanitized_for_js['title'] );
		$this->assertTrue( $sanitized_for_js['is_widget_customizer_js_value'] );
		$this->assertArrayHasKey( 'instance_hash_key', $sanitized_for_js );

		$corrupted_sanitized_for_js                                = $sanitized_for_js;
		$corrupted_sanitized_for_js['encoded_serialized_instance'] = base64_encode( serialize( array( 'title' => 'EVIL' ) ) );
		$this->assertNull( $this->manager->widgets->sanitize_widget_instance( $corrupted_sanitized_for_js ), 'Expected sanitize_widget_instance to reject corrupted data.' );

		$unsanitized_from_js = $this->manager->widgets->sanitize_widget_instance( $sanitized_for_js );
		$this->assertSame( $unsanitized_from_js, $new_categories_instance );
	}

	/**
	 * There should be a 'raw_instance' key when the block editor is enabled and
	 * the widget supports them via `show_instance_in_rest`.
	 *
	 * @ticket 53489
	 */
	public function test_sanitize_widget_instance_raw_instance() {
		remove_action( 'widgets_init', array( $this, 'remove_widgets_block_editor' ) );
		$this->do_customize_boot_actions();

		$block_instance = array(
			'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
		);

		$sanitized_for_js = $this->manager->widgets->sanitize_widget_js_instance( $block_instance, 'block' );
		$this->assertArrayHasKey( 'encoded_serialized_instance', $sanitized_for_js );
		$this->assertTrue( is_serialized( base64_decode( $sanitized_for_js['encoded_serialized_instance'] ), true ) );
		$this->assertSame( '', $sanitized_for_js['title'] );
		$this->assertTrue( $sanitized_for_js['is_widget_customizer_js_value'] );
		$this->assertArrayHasKey( 'instance_hash_key', $sanitized_for_js );
		$this->assertEquals( (object) $block_instance, $sanitized_for_js['raw_instance'] );

		$unsanitized_from_js = $this->manager->widgets->sanitize_widget_instance( $sanitized_for_js );
		$this->assertSame( $unsanitized_from_js, $block_instance );
	}

	/**
	 * There should NOT be a 'raw_instance' key when the block editor is enabled
	 * but the widget does not support them because `show_instance_in_rest` on
	 * the widget is set to false.
	 *
	 * @ticket 53489
	 */
	public function test_sanitize_widget_instance_with_no_show_instance_in_rest() {
		global $wp_widget_factory;

		remove_action( 'widgets_init', array( $this, 'remove_widgets_block_editor' ) );
		$this->do_customize_boot_actions();

		$widget_object = $wp_widget_factory->get_widget_object( 'block' );
		$widget_object->widget_options['show_instance_in_rest'] = false;

		$block_instance = array(
			'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
		);

		$sanitized_for_js = $this->manager->widgets->sanitize_widget_js_instance( $block_instance, 'block' );
		$this->assertArrayHasKey( 'encoded_serialized_instance', $sanitized_for_js );
		$this->assertTrue( is_serialized( base64_decode( $sanitized_for_js['encoded_serialized_instance'] ), true ) );
		$this->assertSame( '', $sanitized_for_js['title'] );
		$this->assertTrue( $sanitized_for_js['is_widget_customizer_js_value'] );
		$this->assertArrayHasKey( 'instance_hash_key', $sanitized_for_js );
		$this->assertArrayNotHasKey( 'raw_instance', $sanitized_for_js );

		$unsanitized_from_js = $this->manager->widgets->sanitize_widget_instance( $sanitized_for_js );
		$this->assertSame( $unsanitized_from_js, $block_instance );
	}

	/**
	 * Empty instances, seen when inserting a new widget, should be left alone
	 * when sanitized.
	 *
	 * @ticket 53479
	 */
	public function test_sanitize_widget_instance_empty_instance() {
		$this->do_customize_boot_actions();
		$this->assertSame( $this->manager->widgets->sanitize_widget_instance( array() ), array() );
	}

	/**
	 * Get the widget control args for tests.
	 *
	 * @return array
	 */
	private function get_test_widget_control_args() {
		global $wp_registered_widgets;
		require_once ABSPATH . 'wp-admin/includes/widgets.php';
		$widget_id = 'search-2';
		$widget    = $wp_registered_widgets[ $widget_id ];
		$args      = array(
			'widget_id'   => $widget['id'],
			'widget_name' => $widget['name'],
		);
		$args      = wp_list_widget_controls_dynamic_sidebar(
			array(
				0 => $args,
				1 => $widget['params'][0],
			)
		);
		return $args;
	}

	/**
	 * @see WP_Customize_Widgets::get_widget_control()
	 */
	public function test_get_widget_control() {
		$this->do_customize_boot_actions();
		$widget_control = $this->manager->widgets->get_widget_control( $this->get_test_widget_control_args() );

		$this->assertStringContainsString( '<div class="form">', $widget_control );
		$this->assertStringContainsString( '<div class="widget-content">', $widget_control );
		$this->assertStringContainsString( '<input type="hidden" name="id_base" class="id_base" value="search"', $widget_control );
		$this->assertStringContainsString( '<input class="widefat"', $widget_control );
	}

	/**
	 * @see WP_Customize_Widgets::get_widget_control_parts()
	 */
	public function test_get_widget_control_parts() {
		$this->do_customize_boot_actions();
		$widget_control_parts = $this->manager->widgets->get_widget_control_parts( $this->get_test_widget_control_args() );
		$this->assertArrayHasKey( 'content', $widget_control_parts );
		$this->assertArrayHasKey( 'control', $widget_control_parts );

		$this->assertStringContainsString( '<div class="form">', $widget_control_parts['control'] );
		$this->assertStringContainsString( '<div class="widget-content">', $widget_control_parts['control'] );
		$this->assertStringContainsString( '<input type="hidden" name="id_base" class="id_base" value="search"', $widget_control_parts['control'] );
		$this->assertStringNotContainsString( '<input class="widefat"', $widget_control_parts['control'] );
		$this->assertStringContainsString( '<input class="widefat"', $widget_control_parts['content'] );
	}

	/**
	 * @see WP_Widget_Form_Customize_Control::json()
	 */
	public function test_wp_widget_form_customize_control_json() {
		$this->do_customize_boot_actions();
		$control = $this->manager->get_control( 'widget_search[2]' );
		$params  = $control->json();

		$this->assertSame( 'widget_form', $params['type'] );
		$this->assertMatchesRegularExpression( '#^<li[^>]+>\s*</li>$#', $params['content'] );
		$this->assertMatchesRegularExpression( '#^<div[^>]*class=\'widget\'[^>]*#s', $params['widget_control'] );
		$this->assertStringContainsString( '<div class="widget-content"></div>', $params['widget_control'] );
		$this->assertStringNotContainsString( '<input class="widefat"', $params['widget_control'] );
		$this->assertStringContainsString( '<input class="widefat"', $params['widget_content'] );
		$this->assertSame( 'search-2', $params['widget_id'] );
		$this->assertSame( 'search', $params['widget_id_base'] );
		$this->assertArrayHasKey( 'sidebar_id', $params );
		$this->assertArrayHasKey( 'width', $params );
		$this->assertArrayHasKey( 'height', $params );
		$this->assertIsBool( $params['is_wide'] );
	}

	/**
	 * @see WP_Customize_Widgets::is_panel_active()
	 */
	public function test_is_panel_active() {
		global $wp_registered_sidebars;
		$this->do_customize_boot_actions();

		$this->assertNotEmpty( $wp_registered_sidebars );
		$this->assertTrue( $this->manager->widgets->is_panel_active() );
		$this->assertTrue( $this->manager->get_panel( 'widgets' )->active() );

		$wp_registered_sidebars = array();
		$this->assertFalse( $this->manager->widgets->is_panel_active() );
		$this->assertFalse( $this->manager->get_panel( 'widgets' )->active() );
	}

	/**
	 * @ticket 34738
	 * @see WP_Customize_Widgets::call_widget_update()
	 */
	public function test_call_widget_update() {

		$widget_number = 2;
		$widget_id     = "search-{$widget_number}";
		$setting_id    = "widget_search[{$widget_number}]";
		$instance      = array(
			'title' => 'Buscar',
		);

		$_POST = wp_slash(
			array(
				'action'        => 'update-widget',
				'wp_customize'  => 'on',
				'nonce'         => wp_create_nonce( 'update-widget' ),
				'theme'         => $this->manager->get_stylesheet(),
				'customized'    => '{}',
				'widget-search' => array(
					2 => $instance,
				),
				'widget-id'     => $widget_id,
				'id_base'       => 'search',
				'widget-width'  => '250',
				'widget-height' => '200',
				'widget_number' => (string) $widget_number,
				'multi_number'  => '',
				'add_new'       => '',
			)
		);

		$this->do_customize_boot_actions();

		$this->assertArrayNotHasKey( $setting_id, $this->manager->unsanitized_post_values() );
		$result = $this->manager->widgets->call_widget_update( $widget_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'instance', $result );
		$this->assertArrayHasKey( 'form', $result );
		$this->assertSame( $instance, $result['instance'] );
		$this->assertStringContainsString( sprintf( 'value="%s"', esc_attr( $instance['title'] ) ), $result['form'] );

		$post_values = $this->manager->unsanitized_post_values();
		$this->assertArrayHasKey( $setting_id, $post_values );
		$post_value = $post_values[ $setting_id ];
		$this->assertIsArray( $post_value );
		$this->assertArrayHasKey( 'title', $post_value );
		$this->assertArrayHasKey( 'encoded_serialized_instance', $post_value );
		$this->assertArrayHasKey( 'instance_hash_key', $post_value );
		$this->assertArrayHasKey( 'is_widget_customizer_js_value', $post_value );
		$this->assertSame( $post_value, $this->manager->widgets->sanitize_widget_js_instance( $instance ) );
	}

	/**
	 * Test WP_Customize_Widgets::customize_dynamic_partial_args().
	 *
	 * @see WP_Customize_Widgets::customize_dynamic_partial_args()
	 */
	public function test_customize_dynamic_partial_args() {
		do_action( 'customize_register', $this->manager );

		$args = apply_filters( 'customize_dynamic_partial_args', false, 'widget[search-2]' );
		$this->assertIsArray( $args );
		$this->assertSame( 'widget', $args['type'] );
		$this->assertSame( array( $this->manager->widgets, 'render_widget_partial' ), $args['render_callback'] );
		$this->assertTrue( $args['container_inclusive'] );

		$args = apply_filters( 'customize_dynamic_partial_args', array( 'fallback_refresh' => false ), 'widget[search-2]' );
		$this->assertIsArray( $args );
		$this->assertSame( 'widget', $args['type'] );
		$this->assertSame( array( $this->manager->widgets, 'render_widget_partial' ), $args['render_callback'] );
		$this->assertTrue( $args['container_inclusive'] );
		$this->assertFalse( $args['fallback_refresh'] );

		remove_theme_support( 'customize-selective-refresh-widgets' );
		$args = apply_filters( 'customize_dynamic_partial_args', false, 'widget[search-2]' );
		$this->assertFalse( $args );
	}

	/**
	 * Test WP_Customize_Widgets::selective_refresh_init().
	 *
	 * @see WP_Customize_Widgets::selective_refresh_init()
	 */
	public function test_selective_refresh_init_with_theme_support() {
		add_theme_support( 'customize-selective-refresh-widgets' );
		$this->manager->widgets->selective_refresh_init();
		$this->assertSame( 10, has_action( 'dynamic_sidebar_before', array( $this->manager->widgets, 'start_dynamic_sidebar' ) ) );
		$this->assertSame( 10, has_action( 'dynamic_sidebar_after', array( $this->manager->widgets, 'end_dynamic_sidebar' ) ) );
		$this->assertSame( 10, has_filter( 'dynamic_sidebar_params', array( $this->manager->widgets, 'filter_dynamic_sidebar_params' ) ) );
		$this->assertSame( 10, has_filter( 'wp_kses_allowed_html', array( $this->manager->widgets, 'filter_wp_kses_allowed_data_attributes' ) ) );
	}

	/**
	 * Test WP_Customize_Widgets::selective_refresh_init().
	 *
	 * @see WP_Customize_Widgets::selective_refresh_init()
	 */
	public function test_selective_refresh_init_without_theme_support() {
		remove_theme_support( 'customize-selective-refresh-widgets' );
		$this->manager->widgets->selective_refresh_init();
		$this->assertFalse( has_action( 'dynamic_sidebar_before', array( $this->manager->widgets, 'start_dynamic_sidebar' ) ) );
		$this->assertFalse( has_action( 'dynamic_sidebar_after', array( $this->manager->widgets, 'end_dynamic_sidebar' ) ) );
		$this->assertFalse( has_filter( 'dynamic_sidebar_params', array( $this->manager->widgets, 'filter_dynamic_sidebar_params' ) ) );
		$this->assertFalse( has_filter( 'wp_kses_allowed_html', array( $this->manager->widgets, 'filter_wp_kses_allowed_data_attributes' ) ) );
	}

	/**
	 * Test WP_Customize_Widgets::customize_preview_enqueue().
	 *
	 * @see WP_Customize_Widgets::customize_preview_enqueue()
	 */
	public function test_customize_preview_enqueue() {
		$this->manager->widgets->customize_preview_enqueue();
		$this->assertTrue( wp_script_is( 'customize-preview-widgets', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'customize-preview', 'enqueued' ) );
		$script = wp_scripts()->registered['customize-preview-widgets'];
		$this->assertContains( 'customize-selective-refresh', $script->deps );
	}

	/**
	 * Test extensions to dynamic_sidebar().
	 *
	 * @see WP_Customize_Widgets::filter_dynamic_sidebar_params()
	 * @see WP_Customize_Widgets::start_dynamic_sidebar()
	 * @see WP_Customize_Widgets::end_dynamic_sidebar()
	 */
	public function test_filter_dynamic_sidebar_params() {
		global $wp_registered_sidebars;
		register_sidebar(
			array(
				'id' => 'foo',
			)
		);

		$this->manager->widgets->selective_refresh_init();

		$params = array(
			array_merge(
				$wp_registered_sidebars['foo'],
				array(
					'widget_id' => 'search-2',
				)
			),
			array(),
		);
		$this->assertSame( $params, $this->manager->widgets->filter_dynamic_sidebar_params( $params ), 'Expected short-circuit if not called after dynamic_sidebar_before.' );

		ob_start();
		do_action( 'dynamic_sidebar_before', 'foo' );
		$output = ob_get_clean();
		$this->assertSame( '<!--dynamic_sidebar_before:foo:1-->', trim( $output ) );

		$bad_params = $params;
		unset( $bad_params[0]['id'] );
		$this->assertSame( $bad_params, $this->manager->widgets->filter_dynamic_sidebar_params( $bad_params ) );

		$bad_params          = $params;
		$bad_params[0]['id'] = 'non-existing';
		$this->assertSame( $bad_params, $this->manager->widgets->filter_dynamic_sidebar_params( $bad_params ) );

		$bad_params                     = $params;
		$bad_params[0]['before_widget'] = '   <oops>';
		$this->assertSame( $bad_params, $this->manager->widgets->filter_dynamic_sidebar_params( $bad_params ) );

		$filtered_params = $this->manager->widgets->filter_dynamic_sidebar_params( $params );
		$this->assertNotEquals( $params, $filtered_params );
		ob_start();
		do_action( 'dynamic_sidebar_after', 'foo' );
		$output = ob_get_clean();
		$this->assertSame( '<!--dynamic_sidebar_after:foo:1-->', trim( $output ) );

		$output = wp_kses_post( $filtered_params[0]['before_widget'] );
		$this->assertStringContainsString( 'data-customize-partial-id="widget[search-2]"', $output );
		$this->assertStringContainsString( 'data-customize-partial-type="widget"', $output );
	}

	/**
	 * Test WP_Customize_Widgets::render_widget_partial() method.
	 *
	 * @see WP_Customize_Widgets::render_widget_partial()
	 */
	public function test_render_widget_partial() {
		add_theme_support( 'customize-selective-refresh-widgets' );
		$this->do_customize_boot_actions();
		$this->manager->widgets->selective_refresh_init();

		$partial_id = 'widget[search-2]';
		$partials   = $this->manager->selective_refresh->add_dynamic_partials( array( $partial_id ) );
		$this->assertNotEmpty( $partials );
		$partial = array_shift( $partials );
		$this->assertSame( $partial_id, $partial->id );

		$this->assertFalse( $this->manager->widgets->render_widget_partial( $partial, array() ) );
		$this->assertFalse( $this->manager->widgets->render_widget_partial( $partial, array( 'sidebar_id' => 'non-existing' ) ) );

		$output = $this->manager->widgets->render_widget_partial( $partial, array( 'sidebar_id' => 'sidebar-1' ) );

		$this->assertSame( 1, substr_count( $output, 'data-customize-partial-id' ) );
		$this->assertSame( 1, substr_count( $output, 'data-customize-partial-type="widget"' ) );
		$this->assertStringContainsString( ' id="search-2"', $output );
	}

	/**
	 * Test deprecated methods.
	 */
	public function test_deprecated_methods() {
		$this->setExpectedDeprecated( 'WP_Customize_Widgets::setup_widget_addition_previews' );
		$this->setExpectedDeprecated( 'WP_Customize_Widgets::prepreview_added_sidebars_widgets' );
		$this->setExpectedDeprecated( 'WP_Customize_Widgets::prepreview_added_widget_instance' );
		$this->setExpectedDeprecated( 'WP_Customize_Widgets::remove_prepreview_filters' );
		$this->manager->widgets->setup_widget_addition_previews();
		$this->manager->widgets->prepreview_added_sidebars_widgets();
		$this->manager->widgets->prepreview_added_widget_instance();
		$this->manager->widgets->remove_prepreview_filters();
	}
}
