<?php
/**
 * Unit tests covering WP_Widget_Calendar functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-calendar.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Calendar
 */
class Tests_Widgets_wpWidgetCalendar extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="calendar-2" class="widget widget_calendar">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Reset the private static $instance counter on WP_Widget_Calendar.
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_calendar_widget_instance_count();
	}

	/**
	 * Reset the private static $instance counter after each test.
	 */
	public function tear_down() {
		$this->reset_calendar_widget_instance_count();
		parent::tear_down();
	}

	/**
	 * Helper to reset private static $instance count using reflection.
	 */
	private function reset_calendar_widget_instance_count() {
		$property = new ReflectionProperty( 'WP_Widget_Calendar', 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, 0 );
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Calendar();

		$this->assertSame( 'calendar', $widget->id_base );
		$this->assertSame( 'Calendar', $widget->name );
		$this->assertSame( 'widget_calendar', $widget->widget_options['classname'] );
		$this->assertSame( 'A calendar of your site’s posts.', $widget->widget_options['description'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests widget() output with default arguments and no title.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_widget_output_without_title() {
		// get_calendar() outputs nothing unless at least one post exists.
		self::factory()->post->create();

		$widget   = new WP_Widget_Calendar();
		$instance = array();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringStartsWith( '<section id="calendar-2" class="widget widget_calendar">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '<div id="calendar_wrap" class="calendar_wrap">', $output );
		$this->assertStringContainsString( '<table id="wp-calendar"', $output );
	}

	/**
	 * Tests that widget() outputs an empty calendar wrapper when there are no posts.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_widget_output_without_posts() {
		$widget   = new WP_Widget_Calendar();
		$instance = array();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<div id="calendar_wrap" class="calendar_wrap"></div>', $output );
		$this->assertStringNotContainsString( '<table id="wp-calendar"', $output );
	}

	/**
	 * Tests widget() output with a custom title.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_widget_output_with_title() {
		$widget   = new WP_Widget_Calendar();
		$instance = array( 'title' => 'Events Calendar' );

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Events Calendar</h2>', $output );
		$this->assertStringContainsString( '<div id="calendar_wrap" class="calendar_wrap">', $output );
	}

	/**
	 * Tests that only the first widget instance receives the id attribute on calendar_wrap.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_widget_instance_counter_omits_id_on_subsequent_calls() {
		$widget   = new WP_Widget_Calendar();
		$instance = array();

		$output1 = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
		$output2 = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<div id="calendar_wrap" class="calendar_wrap">', $output1 );
		$this->assertStringContainsString( '<div class="calendar_wrap">', $output2 );
		$this->assertStringNotContainsString( '<div id="calendar_wrap"', $output2 );
	}

	/**
	 * Tests that widget_title filter modifies the title.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$widget   = new WP_Widget_Calendar();
		$instance = array( 'title' => 'Initial Title' );

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'widget_title',
			static function () {
				return 'Filtered Calendar Title';
			},
			20
		);

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Filtered Calendar Title</h2>', $output );
		$this->assertSame(
			array( array( 'Initial Title', $instance, 'calendar' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests update() method sanitizes the title field.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::update
	 *
	 * @dataProvider data_update_titles
	 *
	 * @param string $title    Submitted title.
	 * @param string $expected Expected sanitized title.
	 */
	public function test_update_sanitizes_title( $title, $expected ) {
		$widget = new WP_Widget_Calendar();

		$new_instance = array( 'title' => $title );
		$old_instance = array( 'title' => 'Old Title' );

		$result = $widget->update( $new_instance, $old_instance );

		$this->assertSame( array( 'title' => $expected ), $result );
	}

	/**
	 * Data provider for test_update_sanitizes_title.
	 *
	 * @return array[]
	 */
	public static function data_update_titles() {
		return array(
			'plain string'           => array( 'My Calendar', 'My Calendar' ),
			'with html tags'         => array( 'Calendar <strong>2026</strong> <script>alert(1)</script>', 'Calendar 2026' ),
			'with line breaks'       => array( "Calendar\nYear", 'Calendar Year' ),
			'surrounding whitespace' => array( '  Calendar  ', 'Calendar' ),
			'empty string'           => array( '', '' ),
			'numeric string zero'    => array( '0', '0' ),
		);
	}

	/**
	 * Tests form() output displays title field correctly.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Calendar::form
	 *
	 * @dataProvider data_form_titles
	 *
	 * @param array  $instance Widget settings instance.
	 * @param string $expected Expected value in the input field.
	 */
	public function test_form_displays_title_field( $instance, $expected ) {
		$widget = new WP_Widget_Calendar();
		$widget->_set( 2 );

		$output    = get_echo( array( $widget, 'form' ), array( $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The form should contain a label tag.' );
		$this->assertSame( 'widget-calendar-2-title', $processor->get_attribute( 'for' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain an input tag.' );
		$this->assertSame( 'widget-calendar-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-calendar[2][title]', $processor->get_attribute( 'name' ) );
		$this->assertSame( 'text', $processor->get_attribute( 'type' ) );
		$this->assertSame( $expected, $processor->get_attribute( 'value' ) );
		$this->assertFalse( $processor->next_tag( 'INPUT' ), 'The form should contain only one input field.' );
	}

	/**
	 * Data provider for test_form_displays_title_field.
	 *
	 * @return array[]
	 */
	public static function data_form_titles() {
		return array(
			'with title'        => array( array( 'title' => 'Events Calendar' ), 'Events Calendar' ),
			'with quotes'       => array( array( 'title' => 'Calendar "2026" & Beyond' ), 'Calendar "2026" & Beyond' ),
			'empty title'       => array( array( 'title' => '' ), '' ),
			'no title key'      => array( array(), '' ),
			'zero string title' => array( array( 'title' => '0' ), '0' ),
		);
	}
}
