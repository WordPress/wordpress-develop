<?php
/**
 * Unit tests covering WP_Widget_Archives functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-archives.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Archives
 */
class Tests_Widgets_wpWidgetArchives extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="archives-2" class="widget widget_archive">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Sets up the HTML5 theme support for navigation widgets.
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
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Archives();

		$this->assertSame( 'archives', $widget->id_base );
		$this->assertSame( 'Archives', $widget->name );
		$this->assertSame( 'widget_archive', $widget->widget_options['classname'] );
		$this->assertSame( 'A monthly archive of your site&#8217;s Posts.', $widget->widget_options['description'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests widget() output with default arguments and default title.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_output_default_title() {
		$widget   = new WP_Widget_Archives();
		$instance = array();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringStartsWith( '<section id="archives-2" class="widget widget_archive">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringContainsString( '<h2 class="widget-title">Archives</h2>', $output );
		$this->assertStringContainsString( '<ul>', $output );
	}

	/**
	 * Tests widget() output with custom title.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_output_custom_title() {
		$widget   = new WP_Widget_Archives();
		$instance = array( 'title' => 'Past Articles' );

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Past Articles</h2>', $output );
	}

	/**
	 * Tests that widget_title filter modifies the title output.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$widget   = new WP_Widget_Archives();
		$instance = array( 'title' => 'Initial Title' );

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'widget_title',
			static function () {
				return 'Filtered Archives Title';
			},
			20
		);

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Filtered Archives Title</h2>', $output );
		$this->assertSame(
			array( array( 'Initial Title', $instance, 'archives' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that navigation wrappers are added in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_adds_navigation_wrapper_in_html5() {
		$this->set_navigation_widgets_format( 'html5' );

		$widget   = new WP_Widget_Archives();
		$instance = array( 'title' => 'Monthly Archives' );

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'The output should contain a nav tag.' );
		$this->assertSame( 'Monthly Archives', $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( '</nav>', $output );
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$widget   = new WP_Widget_Archives();
		$instance = array( 'title' => 'Monthly Archives' );

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
	}

	/**
	 * Tests widget() output when dropdown option is enabled.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_output_dropdown() {
		$widget = new WP_Widget_Archives();
		$widget->_set( 2 );

		$instance = array(
			'title'    => 'Select Archive',
			'dropdown' => 1,
		);

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<select id="archives-dropdown-2" name="archive-dropdown">', $output );
		$this->assertStringContainsString( '<label class="screen-reader-text" for="archives-dropdown-2">Select Archive</label>', $output );
		$this->assertStringContainsString( '<option value="">Select Month</option>', $output );
		$this->assertStringContainsString( 'archives-dropdown-2', $output );
		$this->assertStringNotContainsString( '<ul>', $output );
	}

	/**
	 * Tests that widget_archives_dropdown_args filter is applied.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_widget_applies_widget_archives_dropdown_args_filter() {
		$widget = new WP_Widget_Archives();
		$widget->_set( 2 );

		$instance = array(
			'title'    => 'Select Archive',
			'dropdown' => 1,
			'count'    => 1,
		);

		$filter = new MockAction();
		add_filter( 'widget_archives_dropdown_args', array( $filter, 'filter' ), 10, 2 );

		get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertSame(
			array(
				array(
					array(
						'type'            => 'monthly',
						'format'          => 'option',
						'show_post_count' => '1',
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Tests update() method sanitizes and casts settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::update
	 *
	 * @dataProvider data_update_settings
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected returned array.
	 */
	public function test_update_settings( $new_instance, $expected ) {
		$widget = new WP_Widget_Archives();

		$old_instance = array(
			'title'    => 'Old Title',
			'count'    => 0,
			'dropdown' => 0,
		);

		$result = $widget->update( $new_instance, $old_instance );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for test_update_settings.
	 *
	 * @return array[]
	 */
	public static function data_update_settings() {
		return array(
			'standard sanitized values' => array(
				array(
					'title'    => '<b>New Archives</b>',
					'count'    => '1',
					'dropdown' => '1',
				),
				array(
					'title'    => 'New Archives',
					'count'    => 1,
					'dropdown' => 1,
				),
			),
			'empty values'              => array(
				array(
					'title'    => '',
					'count'    => 0,
					'dropdown' => 0,
				),
				array(
					'title'    => '',
					'count'    => 0,
					'dropdown' => 0,
				),
			),
		);
	}

	/**
	 * Tests form() output displays all fields correctly.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Archives::form
	 */
	public function test_form_displays_fields() {
		$widget = new WP_Widget_Archives();
		$widget->_set( 2 );

		$instance = array(
			'title'    => 'Archives Box',
			'count'    => 1,
			'dropdown' => 1,
		);

		$output    = get_echo( array( $widget, 'form' ), array( $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name' => 'INPUT',
					'id'       => 'widget-archives-2-title',
				)
			)
		);
		$this->assertSame( 'Archives Box', $processor->get_attribute( 'value' ) );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name' => 'INPUT',
					'id'       => 'widget-archives-2-dropdown',
				)
			)
		);
		$this->assertSame( 'checkbox', $processor->get_attribute( 'type' ) );
		$this->assertTrue( $processor->get_attribute( 'checked' ) );

		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name' => 'INPUT',
					'id'       => 'widget-archives-2-count',
				)
			)
		);
		$this->assertSame( 'checkbox', $processor->get_attribute( 'type' ) );
		$this->assertTrue( $processor->get_attribute( 'checked' ) );
	}
}
