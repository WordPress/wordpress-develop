<?php
/**
 * Unit tests covering WP_Widget_Search functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-search.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Search
 */
class Tests_Widgets_wpWidgetSearch extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="search-2" class="widget widget_search">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Search();

		$this->assertSame( 'search', $widget->id_base );
		$this->assertSame( 'widget_search', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests widget() output with default arguments and an empty instance.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::widget
	 */
	public function test_widget_output_without_title() {
		$widget   = new WP_Widget_Search();
		$instance = array();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringStartsWith( '<section id="search-2" class="widget widget_search">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '<form role="search"', $output );
	}

	/**
	 * Tests that widget_title filter can modify the title output.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$widget   = new WP_Widget_Search();
		$instance = array( 'title' => 'Initial Title' );

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'widget_title',
			static function () {
				return 'Filtered Search Title';
			},
			20
		);

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Filtered Search Title</h2>', $output );
		$this->assertSame(
			array( array( 'Initial Title', $instance, 'search' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that an empty filtered title omits the before/after title wrapper.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::widget
	 */
	public function test_widget_omits_empty_filtered_title() {
		$widget   = new WP_Widget_Search();
		$instance = array( 'title' => 'Will Be Emptied' );

		add_filter( 'widget_title', '__return_empty_string' );

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringNotContainsString( 'widget-title', $output );
	}

	/**
	 * Tests update() method sanitizes the title field.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::update
	 *
	 * @dataProvider data_update_titles
	 *
	 * @param string $title    Submitted title.
	 * @param string $expected Expected sanitized title.
	 */
	public function test_update_sanitizes_title( $title, $expected ) {
		$widget = new WP_Widget_Search();

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
			'plain string'   => array( 'Search Site', 'Search Site' ),
			'with html tags' => array( 'Search <strong>Site</strong> <script>alert(1)</script>', 'Search Site' ),
		);
	}

	/**
	 * Tests form() output displays title field correctly.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Search::form
	 *
	 * @dataProvider data_form_titles
	 *
	 * @param array  $instance Widget settings instance.
	 * @param string $expected Expected value in the input field.
	 */
	public function test_form_displays_title_field( $instance, $expected ) {
		$widget = new WP_Widget_Search();
		$widget->_set( 2 );

		$output    = get_echo( array( $widget, 'form' ), array( $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The form should contain a label tag.' );
		$this->assertSame( 'widget-search-2-title', $processor->get_attribute( 'for' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain an input tag.' );
		$this->assertSame( 'widget-search-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-search[2][title]', $processor->get_attribute( 'name' ) );
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
			'with quotes'  => array( array( 'title' => 'Search "Quotes" & More' ), 'Search "Quotes" & More' ),
			'no title key' => array( array(), '' ),
		);
	}
}
