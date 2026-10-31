<?php
/**
 * Unit tests covering WP_Nav_Menu_Widget functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-nav-menu-widget.php
 *
 * @group widgets
 *
 * @covers WP_Nav_Menu_Widget
 */
class Tests_Widgets_wpNavMenuWidget extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="nav_menu-2" class="widget widget_nav_menu">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * ID of the shared navigation menu.
	 *
	 * @var int
	 */
	protected static $menu_id;

	/**
	 * Creates the navigation menu and menu items shared by the tests.
	 */
	public static function wpSetUpBeforeClass() {
		self::$menu_id = wp_create_nav_menu( 'Primary Menu' );

		wp_update_nav_menu_item(
			self::$menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);

		wp_update_nav_menu_item(
			self::$menu_id,
			0,
			array(
				'menu-item-title'  => 'About',
				'menu-item-url'    => home_url( '/about/' ),
				'menu-item-status' => 'publish',
			)
		);
	}

	/**
	 * Deletes the shared navigation menu after the tests run.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_nav_menu( self::$menu_id );
	}

	/**
	 * Tears down test state after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['wp_customize'] );

		parent::tear_down();
	}

	/**
	 * Sets the format used for navigation widgets.
	 *
	 * @param string $format The navigation widgets format, either 'html5' or 'xhtml'.
	 */
	private function set_navigation_widgets_format( $format ) {
		add_filter(
			'navigation_widgets_format',
			static function () use ( $format ) {
				return $format;
			}
		);
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $instance Widget instance.
	 * @return string Widget output.
	 */
	private function render( $instance ) {
		$widget = new WP_Nav_Menu_Widget();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
	}

	/**
	 * Renders the settings form.
	 *
	 * @param array $instance Current settings.
	 * @return string Form output.
	 */
	private function render_form( $instance ) {
		$widget = new WP_Nav_Menu_Widget();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'form' ), array( $instance ) );
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::__construct
	 */
	public function test_construct() {
		$widget = new WP_Nav_Menu_Widget();

		$this->assertSame( 'nav_menu', $widget->id_base );
		$this->assertSame( 'widget_nav_menu', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests that widget() wraps the navigation menu in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_widget_outputs_navigation_menu() {
		$output = $this->render( array( 'nav_menu' => self::$menu_id ) );

		$this->assertStringStartsWith( '<section id="nav_menu-2" class="widget widget_nav_menu">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringNotContainsString( '<h2 class="widget-title">', $output, 'No title should be rendered when the title setting is empty.' );
		$this->assertStringContainsString( '>Home</a>', $output );
		$this->assertStringContainsString( '>About</a>', $output );
	}

	/**
	 * Tests that widget() outputs nothing when no valid navigation menu is configured.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 *
	 * @dataProvider data_widget_invalid_nav_menu
	 *
	 * @param array $instance Widget instance.
	 */
	public function test_widget_outputs_nothing_without_valid_menu( $instance ) {
		$this->assertSame( '', $this->render( $instance ) );
	}

	/**
	 * Data provider for test_widget_outputs_nothing_without_valid_menu.
	 *
	 * @return array[]
	 */
	public static function data_widget_invalid_nav_menu() {
		return array(
			'missing nav_menu'     => array( array() ),
			'nonexistent nav_menu' => array( array( 'nav_menu' => 999999 ) ),
		);
	}

	/**
	 * Tests that widget() outputs the custom title when set.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_widget_outputs_custom_title() {
		$output = $this->render(
			array(
				'title'    => 'Site Links',
				'nav_menu' => self::$menu_id,
			)
		);

		$this->assertStringContainsString( "<h2 class=\"widget-title\">Site Links</h2>\n", $output );
	}

	/**
	 * Tests that widget() applies the widget_title filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$instance = array(
			'title'    => 'Original Title',
			'nav_menu' => self::$menu_id,
		);

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'widget_title',
			static function () {
				return 'Filtered Title';
			},
			20
		);

		$output = $this->render( $instance );

		$this->assertStringContainsString( "<h2 class=\"widget-title\">Filtered Title</h2>\n", $output );
		$this->assertSame(
			array( array( 'Original Title', $instance, 'nav_menu' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that widget() omits the title element when the widget_title filter returns an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_widget_omits_title_when_filtered_to_empty() {
		add_filter( 'widget_title', '__return_empty_string' );

		$output = $this->render(
			array(
				'title'    => 'Hidden Title',
				'nav_menu' => self::$menu_id,
			)
		);

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '>Home</a>', $output, 'The menu items should still be rendered.' );
	}

	/**
	 * Tests that the navigation wrapper is added in HTML5 mode with the expected aria-label.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 *
	 * @dataProvider data_widget_navigation_aria_label
	 *
	 * @param string $filtered_title Title returned by the widget_title filter.
	 * @param string $expected_label Expected aria-label on the nav element.
	 */
	public function test_widget_adds_navigation_wrapper_in_html5( $filtered_title, $expected_label ) {
		$this->set_navigation_widgets_format( 'html5' );

		add_filter(
			'widget_title',
			static function () use ( $filtered_title ) {
				return $filtered_title;
			}
		);

		$output    = $this->render( array( 'nav_menu' => self::$menu_id ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'The output should contain a nav tag in html5 format.' );
		$this->assertSame( $expected_label, $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( '</nav>', $output );
	}

	/**
	 * Data provider for test_widget_adds_navigation_wrapper_in_html5.
	 *
	 * @return array[]
	 */
	public static function data_widget_navigation_aria_label() {
		return array(
			'title with HTML'     => array( '<em>Footer</em> Links ', 'Footer Links' ),
			'title with only tag' => array( '<span></span>', 'Menu' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode while the menu still renders.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$output    = $this->render( array( 'nav_menu' => self::$menu_id ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
		$this->assertTrue(
			( new WP_HTML_Tag_Processor( $output ) )->next_tag(
				array(
					'tag_name'   => 'DIV',
					'class_name' => 'menu-primary-menu-container',
				)
			),
			'The menu should be wrapped in a div container in xhtml format.'
		);
		$this->assertStringContainsString( '>Home</a>', $output, 'The menu items should still be listed.' );
	}

	/**
	 * Tests the arguments passed to the widget_nav_menu_args filter in HTML5 and XHTML modes.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 *
	 * @dataProvider data_widget_nav_menu_args
	 *
	 * @param string $format        Navigation widgets format.
	 * @param string $title         Widget title setting.
	 * @param array  $expected_keys Expected keys in $nav_menu_args.
	 */
	public function test_widget_applies_widget_nav_menu_args_filter( $format, $title, $expected_keys ) {
		$this->set_navigation_widgets_format( $format );

		$instance = array(
			'title'    => $title,
			'nav_menu' => self::$menu_id,
		);

		$filter = new MockAction();
		add_filter( 'widget_nav_menu_args', array( $filter, 'filter' ), 10, 4 );

		$this->render( $instance );

		$filter_args = $filter->get_args();
		$this->assertCount( 1, $filter_args, 'The widget_nav_menu_args filter should be applied once.' );

		list( $nav_menu_args, $nav_menu, $args, $filter_instance ) = $filter_args[0];

		$this->assertSameSets( $expected_keys, array_keys( $nav_menu_args ) );
		$this->assertSame( '', $nav_menu_args['fallback_cb'] );
		$this->assertInstanceOf( WP_Term::class, $nav_menu );
		$this->assertSame( self::$menu_id, $nav_menu->term_id );
		$this->assertSame( $nav_menu, $nav_menu_args['menu'] );
		$this->assertSame( $this->args, $args );
		$this->assertSame( $instance, $filter_instance );
	}

	/**
	 * Data provider for test_widget_applies_widget_nav_menu_args_filter.
	 *
	 * @return array[]
	 */
	public static function data_widget_nav_menu_args() {
		return array(
			'html5 format' => array(
				'html5',
				'Site Navigation',
				array( 'fallback_cb', 'menu', 'container', 'container_aria_label', 'items_wrap' ),
			),
			'xhtml format' => array(
				'xhtml',
				'Site Navigation',
				array( 'fallback_cb', 'menu' ),
			),
		);
	}

	/**
	 * Tests that update() sanitizes and casts settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected updated settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Nav_Menu_Widget();

		$old_instance = array(
			'title'    => 'Old Title',
			'nav_menu' => 5,
		);

		$this->assertSame( $expected, $widget->update( $new_instance, $old_instance ) );
	}

	/**
	 * Data provider for test_update.
	 *
	 * @return array[]
	 */
	public static function data_update() {
		return array(
			'sanitized title and integer menu ID' => array(
				array(
					'title'    => '<b>Main Menu</b>',
					'nav_menu' => '12',
				),
				array(
					'title'    => 'Main Menu',
					'nav_menu' => 12,
				),
			),
			'missing keys omitted'                => array(
				array(),
				array(),
			),
		);
	}

	/**
	 * Tests that form() displays the fields with the saved settings when menus exist.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::form
	 */
	public function test_form_displays_fields_with_saved_settings() {
		$output    = $this->render_form(
			array(
				'title'    => 'Primary Links',
				'nav_menu' => self::$menu_id,
			)
		);
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'P',
					'class_name' => 'nav-menu-widget-no-menus-message',
				)
			),
			'The no-menus message paragraph should be present.'
		);
		$this->assertSame( 'display:none', $processor->get_attribute( 'style' ), 'The no-menus message should be hidden when menus exist.' );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'DIV',
					'class_name' => 'nav-menu-widget-form-controls',
				)
			),
			'The form controls container should be present.'
		);
		$this->assertNull( $processor->get_attribute( 'style' ), 'The form controls should be visible when menus exist.' );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( 'widget-nav_menu-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-nav_menu[2][title]', $processor->get_attribute( 'name' ) );
		$this->assertSame( 'Primary Links', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'SELECT' ), 'The form should contain a menu select.' );
		$this->assertSame( 'widget-nav_menu-2-nav_menu', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-nav_menu[2][nav_menu]', $processor->get_attribute( 'name' ) );

		$options = array();
		while ( $processor->next_tag( 'OPTION' ) ) {
			$options[ $processor->get_attribute( 'value' ) ] = null !== $processor->get_attribute( 'selected' );
		}

		$this->assertSame(
			array(
				'0'                     => false,
				(string) self::$menu_id => true,
			),
			$options,
			'The saved navigation menu option should be selected.'
		);
		$this->assertStringContainsString( admin_url( 'nav-menus.php' ), $output );
		$this->assertStringNotContainsString( 'edit-selected-nav-menu', $output, 'The Customizer edit button should not be rendered outside the Customizer.' );
	}

	/**
	 * Tests that form() hides the controls and shows the no-menus message when no menus exist.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::form
	 */
	public function test_form_when_no_menus_exist() {
		add_filter( 'wp_get_nav_menus', '__return_empty_array' );

		$output    = $this->render_form( array() );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'P',
					'class_name' => 'nav-menu-widget-no-menus-message',
				)
			)
		);
		$this->assertNull( $processor->get_attribute( 'style' ), 'The no-menus message should be visible when no menus exist.' );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'DIV',
					'class_name' => 'nav-menu-widget-form-controls',
				)
			)
		);
		$this->assertSame( 'display:none', $processor->get_attribute( 'style' ), 'The form controls should be hidden when no menus exist.' );
	}

	/**
	 * Tests that form() renders the Customizer link and edit button when $wp_customize is active.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Nav_Menu_Widget::form
	 */
	public function test_form_in_customizer_context() {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();

		$output_without_menu = $this->render_form( array() );
		$processor           = new WP_HTML_Tag_Processor( $output_without_menu );

		$this->assertStringContainsString( 'javascript: wp.customize.panel( &quot;nav_menus&quot; ).focus();', $output_without_menu );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'P',
					'class_name' => 'edit-selected-nav-menu',
				)
			),
			'The edit menu button wrapper should be rendered in the Customizer.'
		);
		$this->assertSame( 'display: none;', $processor->get_attribute( 'style' ), 'The edit menu button should be hidden when no menu is selected.' );

		$output_with_menu    = $this->render_form( array( 'nav_menu' => self::$menu_id ) );
		$processor_with_menu = new WP_HTML_Tag_Processor( $output_with_menu );

		$this->assertTrue(
			$processor_with_menu->next_tag(
				array(
					'tag_name'   => 'P',
					'class_name' => 'edit-selected-nav-menu',
				)
			)
		);
		$this->assertSame( '', $processor_with_menu->get_attribute( 'style' ), 'The edit menu button should be visible when a menu is selected.' );
	}
}
