<?php
/**
 * Unit tests covering WP_Widget_Categories functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-categories.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Categories
 */
class Tests_Widgets_wpWidgetCategories extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="categories-2" class="widget widget_categories">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * ID of the parent category.
	 *
	 * @var int
	 */
	protected static $parent_id;

	/**
	 * ID of the child category.
	 *
	 * @var int
	 */
	protected static $child_id;

	/**
	 * Creates the categories shared by the tests, each with one post.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_id = $factory->category->create( array( 'name' => 'Parent Cat' ) );
		self::$child_id  = $factory->category->create(
			array(
				'name'   => 'Child Cat',
				'parent' => self::$parent_id,
			)
		);

		$factory->post->create( array( 'post_category' => array( self::$parent_id ) ) );
		$factory->post->create( array( 'post_category' => array( self::$child_id ) ) );
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
	 * @param int   $number   Widget number.
	 * @return string Widget output.
	 */
	private function render( $instance, $number = 2 ) {
		$widget = new WP_Widget_Categories();
		$widget->_set( $number );

		return get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Categories();

		$this->assertSame( 'categories', $widget->id_base );
		$this->assertSame( 'widget_categories', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests that widget() wraps the list of categories in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_outputs_list_of_categories() {
		$output = $this->render( array() );

		$this->assertStringStartsWith( '<section id="categories-2" class="widget widget_categories">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringContainsString( '<ul>', $output );
		$this->assertStringContainsString( '>Parent Cat</a>', $output );
		$this->assertStringContainsString( '>Child Cat</a>', $output );
		$this->assertStringNotContainsString( '<select', $output, 'The list should not use a dropdown by default.' );
	}

	/**
	 * Tests the title shown by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 *
	 * @dataProvider data_widget_title
	 *
	 * @param array  $instance Widget instance.
	 * @param string $expected Expected title.
	 */
	public function test_widget_title( $instance, $expected ) {
		$output = $this->render( $instance );

		$this->assertStringContainsString( '<h2 class="widget-title">' . $expected . "</h2>\n", $output );
	}

	/**
	 * Data provider for test_widget_title.
	 *
	 * @return array[]
	 */
	public static function data_widget_title() {
		return array(
			'no title'     => array( array(), 'Categories' ),
			'custom title' => array( array( 'title' => 'Topics' ), 'Topics' ),
		);
	}

	/**
	 * Tests that the widget_title filter receives the title, instance and id_base.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$instance = array( 'title' => 'Initial Title' );

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

		$this->assertStringContainsString( '<h2 class="widget-title">Filtered Title</h2>', $output );
		$this->assertSame(
			array( array( 'Initial Title', $instance, 'categories' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that the title markup is omitted when the title is filtered to an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_omits_title_markup_for_empty_filtered_title() {
		$this->set_navigation_widgets_format( 'xhtml' );
		add_filter( 'widget_title', '__return_empty_string' );

		$output = $this->render( array() );

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '>Parent Cat</a>', $output, 'The list should still be displayed.' );
	}

	/**
	 * Tests the navigation wrapper and its aria-label in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 *
	 * @dataProvider data_widget_navigation_aria_label
	 *
	 * @param string $filtered_title Title returned by the widget_title filter.
	 * @param string $expected       Expected aria-label.
	 */
	public function test_widget_adds_navigation_wrapper_in_html5( $filtered_title, $expected ) {
		$this->set_navigation_widgets_format( 'html5' );
		add_filter(
			'widget_title',
			static function () use ( $filtered_title ) {
				return $filtered_title;
			}
		);

		$output    = $this->render( array() );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'The output should contain a nav tag.' );
		$this->assertSame( $expected, $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( '</nav>', $output );
	}

	/**
	 * Data provider for test_widget_adds_navigation_wrapper_in_html5.
	 *
	 * @return array[]
	 */
	public static function data_widget_navigation_aria_label() {
		return array(
			'title with HTML'     => array( '<em>Site</em> Topics ', 'Site Topics' ),
			'title with only tag' => array( '<span></span>', 'Categories' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$processor = new WP_HTML_Tag_Processor( $this->render( array() ) );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
	}

	/**
	 * Tests the arguments passed to the widget_categories_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 *
	 * @dataProvider data_widget_options
	 *
	 * @param array $instance     Widget instance.
	 * @param bool  $count        Whether post counts are expected.
	 * @param bool  $hierarchical Whether the hierarchy is expected.
	 */
	public function test_widget_applies_widget_categories_args_filter( $instance, $count, $hierarchical ) {
		$filter = new MockAction();
		add_filter( 'widget_categories_args', array( $filter, 'filter' ), 10, 2 );

		$this->render( $instance );

		$filter_args = $filter->get_args();
		$this->assertCount( 1, $filter_args, 'The filter should be applied once.' );

		list( $cat_args, $filter_instance ) = $filter_args[0];

		$this->assertIsArray( $cat_args );

		$this->assertSame( $instance, $filter_instance );
		$this->assertSameSets( array( 'orderby', 'show_count', 'hierarchical', 'title_li' ), array_keys( $cat_args ) );
		$this->assertSame( 'name', $cat_args['orderby'] );
		$this->assertSame( '', $cat_args['title_li'] );
		$this->assertSame( $count, $cat_args['show_count'], 'show_count should match the count setting.' );
		$this->assertSame( $hierarchical, $cat_args['hierarchical'], 'hierarchical should match the hierarchical setting.' );
	}

	/**
	 * Data provider for the count and hierarchical settings.
	 *
	 * @return array[]
	 */
	public static function data_widget_options() {
		return array(
			'defaults'          => array( array(), false, false ),
			'count only'        => array( array( 'count' => 1 ), true, false ),
			'hierarchical only' => array( array( 'hierarchical' => 1 ), false, true ),
			'both'              => array(
				array(
					'count'        => 1,
					'hierarchical' => 1,
				),
				true,
				true,
			),
			// Instances saved before WordPress 2.8 stored booleans.
			'booleans enabled'  => array(
				array(
					'count'        => true,
					'hierarchical' => true,
				),
				true,
				true,
			),
			'booleans disabled' => array(
				array(
					'count'        => false,
					'hierarchical' => false,
				),
				false,
				false,
			),
		);
	}

	/**
	 * Tests that the count and hierarchical settings change the list output.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 *
	 * @dataProvider data_widget_options
	 *
	 * @param array $instance     Widget instance.
	 * @param bool  $count        Whether post counts are expected.
	 * @param bool  $hierarchical Whether the hierarchy is expected.
	 */
	public function test_widget_list_output_options( $instance, $count, $hierarchical ) {
		$output = $this->render( $instance );

		if ( $count ) {
			$this->assertStringContainsString( 'Child Cat</a> (1)', $output, 'Post counts should be shown.' );
		} else {
			$this->assertStringNotContainsString( 'Child Cat</a> (1)', $output, 'Post counts should not be shown.' );
		}

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertSame(
			$hierarchical,
			$processor->next_tag(
				array(
					'tag_name'   => 'UL',
					'class_name' => 'children',
				)
			),
			'Child categories should only be nested when the hierarchy is shown.'
		);
	}

	/**
	 * Tests the dropdown output.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_outputs_dropdown() {
		// Render one dropdown first, so the ID of the next one doesn't depend on the test order.
		$this->render( array( 'dropdown' => 1 ), 2 );

		$output    = $this->render(
			array(
				'title'    => 'Pick a Topic',
				'dropdown' => 1,
			),
			3
		);
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'FORM' ), 'The dropdown should be in a form.' );
		$this->assertSame( home_url(), $processor->get_attribute( 'action' ) );
		$this->assertSame( 'get', $processor->get_attribute( 'method' ) );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The dropdown should have a label.' );
		$this->assertSame( 'categories-dropdown-3', $processor->get_attribute( 'for' ) );
		$this->assertTrue( $processor->has_class( 'screen-reader-text' ) );
		$this->assertStringContainsString( '>Pick a Topic</label>', $output );

		$this->assertTrue( $processor->next_tag( 'SELECT' ), 'The output should contain a select.' );
		$this->assertSame( 'categories-dropdown-3', $processor->get_attribute( 'id' ) );

		$this->assertStringContainsString( '>Select Category</option>', $output );
		$this->assertStringContainsString( '>Parent Cat</option>', $output );
		$this->assertStringContainsString( '"categories-dropdown-3"', $output, 'The script should target the dropdown.' );
		$this->assertStringNotContainsString( '<ul>', $output, 'The dropdown should replace the list.' );
		$this->assertStringNotContainsString( '<nav', $output, 'The dropdown should not use the navigation wrapper.' );
	}

	/**
	 * Tests the arguments passed to the widget_categories_dropdown_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_widget_applies_widget_categories_dropdown_args_filter() {
		$this->render( array( 'dropdown' => 1 ), 2 );

		$instance = array(
			'dropdown'     => 1,
			'count'        => 1,
			'hierarchical' => 1,
		);

		$filter = new MockAction();
		add_filter( 'widget_categories_dropdown_args', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'widget_categories_args', array( $filter, 'filter' ), 10, 2 );

		$this->render( $instance, 3 );

		$filter_args = $filter->get_args();
		$this->assertCount( 1, $filter_args, 'Only the dropdown filter should be applied.' );

		list( $cat_args, $filter_instance ) = $filter_args[0];

		$this->assertIsArray( $cat_args );

		$this->assertSame( $instance, $filter_instance );
		$this->assertSameSets( array( 'orderby', 'show_count', 'hierarchical', 'show_option_none', 'id' ), array_keys( $cat_args ) );
		$this->assertSame( 'name', $cat_args['orderby'] );
		$this->assertTrue( $cat_args['show_count'] );
		$this->assertTrue( $cat_args['hierarchical'] );
		$this->assertSame( 'Select Category', $cat_args['show_option_none'] );
		$this->assertSame( 'categories-dropdown-3', $cat_args['id'] );
	}

	/**
	 * Tests that update() sanitizes the title and casts the checkboxes.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Categories();

		$old_instance = array(
			'title'        => 'Old Title',
			'count'        => 1,
			'hierarchical' => 1,
			'dropdown'     => 1,
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
			'sanitized values'     => array(
				array(
					'title'        => '<b>New Topics</b>',
					'count'        => 'on',
					'hierarchical' => '1',
					'dropdown'     => 'on',
				),
				array(
					'title'        => 'New Topics',
					'count'        => 1,
					'hierarchical' => 1,
					'dropdown'     => 1,
				),
			),
			'unchecked checkboxes' => array(
				array( 'title' => 'Topics' ),
				array(
					'title'        => 'Topics',
					'count'        => 0,
					'hierarchical' => 0,
					'dropdown'     => 0,
				),
			),
			'missing title'        => array(
				array( 'count' => 'on' ),
				array(
					'title'        => '',
					'count'        => 1,
					'hierarchical' => 0,
					'dropdown'     => 0,
				),
			),
		);
	}

	/**
	 * Tests that form() displays the fields with the current settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Categories::form
	 *
	 * @dataProvider data_form
	 *
	 * @param array  $instance Current settings.
	 * @param string $title    Expected title value.
	 * @param array  $checked  Expected checked state of each checkbox, keyed by field.
	 */
	public function test_form_displays_fields( $instance, $title, $checked ) {
		$widget = new WP_Widget_Categories();
		$widget->_set( 2 );

		$processor = new WP_HTML_Tag_Processor( get_echo( array( $widget, 'form' ), array( $instance ) ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( 'widget-categories-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-categories[2][title]', $processor->get_attribute( 'name' ) );
		$this->assertSame( $title, $processor->get_attribute( 'value' ) );

		foreach ( $checked as $field => $is_checked ) {
			$this->assertTrue( $processor->next_tag( 'INPUT' ), "The form should contain a $field checkbox." );
			$this->assertSame( "widget-categories-2-$field", $processor->get_attribute( 'id' ) );
			$this->assertSame( "widget-categories[2][$field]", $processor->get_attribute( 'name' ) );
			$this->assertSame( 'checkbox', $processor->get_attribute( 'type' ) );
			$this->assertSame( $is_checked ? 'checked' : null, $processor->get_attribute( 'checked' ), "The $field checkbox has the wrong checked state." );
		}
	}

	/**
	 * Data provider for test_form_displays_fields.
	 *
	 * @return array[]
	 */
	public static function data_form() {
		return array(
			'new widget'  => array(
				array(),
				'',
				array(
					'dropdown'     => false,
					'count'        => false,
					'hierarchical' => false,
				),
			),
			'all enabled' => array(
				array(
					'title'        => 'Site "Topics"',
					'count'        => 1,
					'hierarchical' => 1,
					'dropdown'     => 1,
				),
				'Site "Topics"',
				array(
					'dropdown'     => true,
					'count'        => true,
					'hierarchical' => true,
				),
			),
			'count only'  => array(
				array(
					'title' => 'Topics',
					'count' => 1,
				),
				'Topics',
				array(
					'dropdown'     => false,
					'count'        => true,
					'hierarchical' => false,
				),
			),
		);
	}
}
