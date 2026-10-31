<?php
/**
 * Unit tests covering WP_Widget_Pages functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-pages.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Pages
 */
class Tests_Widgets_wpWidgetPages extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="pages-2" class="widget widget_pages">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Page IDs keyed by title.
	 *
	 * @var int[]
	 */
	protected static $pages = array();

	/**
	 * Creates the pages shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$pages = array(
			'Charlie' => 1,
			'Alpha'   => 3,
			'Bravo'   => 2,
		);

		foreach ( $pages as $title => $menu_order ) {
			self::$pages[ $title ] = $factory->post->create(
				array(
					'post_type'  => 'page',
					'post_title' => $title,
					'menu_order' => $menu_order,
				)
			);
		}
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
	 * Returns the page titles in the order they appear in the output.
	 *
	 * @param string $output Widget output.
	 * @return string[] Page titles.
	 */
	private function get_listed_titles( $output ) {
		preg_match_all( '#<a href="[^"]*">([^<]*)</a>#', $output, $matches );

		return $matches[1];
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Pages();

		$this->assertSame( 'pages', $widget->id_base );
		$this->assertSame( 'widget_pages', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests that widget() wraps the list of pages in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_outputs_list_of_pages() {
		$widget = new WP_Widget_Pages();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );

		$this->assertStringStartsWith( '<section id="pages-2" class="widget widget_pages">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringContainsString( '<ul>', $output );
		$this->assertSameSets( array_keys( self::$pages ), $this->get_listed_titles( $output ) );
	}

	/**
	 * Tests that widget() outputs nothing when there are no pages to list.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_outputs_nothing_without_pages() {
		$widget   = new WP_Widget_Pages();
		$instance = array( 'exclude' => implode( ',', self::$pages ) );

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Tests the title shown by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 *
	 * @dataProvider data_widget_title
	 *
	 * @param array  $instance Widget instance.
	 * @param string $expected Expected title.
	 */
	public function test_widget_title( $instance, $expected ) {
		$widget = new WP_Widget_Pages();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">' . $expected . "</h2>\n", $output );
	}

	/**
	 * Data provider for test_widget_title.
	 *
	 * @return array[]
	 */
	public static function data_widget_title() {
		return array(
			'no title'     => array( array(), 'Pages' ),
			'custom title' => array( array( 'title' => 'Site Pages' ), 'Site Pages' ),
		);
	}

	/**
	 * Tests that the widget_title filter receives the title, instance and id_base.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$widget   = new WP_Widget_Pages();
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

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<h2 class="widget-title">Filtered Title</h2>', $output );
		$this->assertSame(
			array( array( 'Initial Title', $instance, 'pages' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that the title markup is omitted when the title is filtered to an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_omits_title_markup_for_empty_filtered_title() {
		$this->set_navigation_widgets_format( 'xhtml' );
		add_filter( 'widget_title', '__return_empty_string' );

		$widget = new WP_Widget_Pages();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '<ul>', $output, 'The list should still be displayed.' );
	}

	/**
	 * Tests the navigation wrapper and its aria-label in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
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

		$widget = new WP_Widget_Pages();

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
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
			'title with HTML'     => array( '<em>Site</em> Pages ', 'Site Pages' ),
			'title with only tag' => array( '<span></span>', 'Pages' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$widget = new WP_Widget_Pages();

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
	}

	/**
	 * Tests the arguments passed to the widget_pages_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 *
	 * @dataProvider data_widget_pages_args
	 *
	 * @param array  $instance    Widget instance.
	 * @param string $sort_column Expected sort_column argument.
	 * @param string $exclude     Expected exclude argument.
	 */
	public function test_widget_applies_widget_pages_args_filter( $instance, $sort_column, $exclude ) {
		$widget = new WP_Widget_Pages();

		$filter = new MockAction();
		add_filter( 'widget_pages_args', array( $filter, 'filter' ), 10, 2 );

		get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertSame(
			array(
				array(
					array(
						'title_li'    => '',
						'echo'        => 0,
						'sort_column' => $sort_column,
						'exclude'     => $exclude,
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Data provider for test_widget_applies_widget_pages_args_filter.
	 *
	 * @return array[]
	 */
	public static function data_widget_pages_args() {
		return array(
			'defaults'           => array( array(), 'menu_order, post_title', '' ),
			'ID with exclusions' => array(
				array(
					'sortby'  => 'ID',
					'exclude' => '12,34',
				),
				'ID',
				'12,34',
			),
		);
	}

	/**
	 * Tests that the sortby setting controls the order of the listed pages.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 *
	 * @dataProvider data_widget_sorts_pages
	 *
	 * @param string   $sortby   The sortby setting.
	 * @param string[] $expected Expected page titles, in order.
	 */
	public function test_widget_sorts_pages( $sortby, $expected ) {
		$widget = new WP_Widget_Pages();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array( 'sortby' => $sortby ) ) );

		$this->assertSame( $expected, $this->get_listed_titles( $output ) );
	}

	/**
	 * Data provider for test_widget_sorts_pages.
	 *
	 * @return array[]
	 */
	public static function data_widget_sorts_pages() {
		return array(
			'page order' => array( 'menu_order', array( 'Charlie', 'Bravo', 'Alpha' ) ),
			'page title' => array( 'post_title', array( 'Alpha', 'Bravo', 'Charlie' ) ),
			'page ID'    => array( 'ID', array( 'Charlie', 'Alpha', 'Bravo' ) ),
		);
	}

	/**
	 * Tests that excluded pages are not listed.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_widget_excludes_pages() {
		$widget   = new WP_Widget_Pages();
		$instance = array( 'exclude' => (string) self::$pages['Bravo'] );

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertSameSets( array( 'Alpha', 'Charlie' ), $this->get_listed_titles( $output ) );
	}

	/**
	 * Tests that update() sanitizes the settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Pages();

		$old_instance = array(
			'title'   => 'Old Title',
			'sortby'  => 'post_title',
			'exclude' => '1',
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
			'sanitized values' => array(
				array(
					'title'   => '<b>New Pages</b>',
					'sortby'  => 'ID',
					'exclude' => ' 12,34 <script>',
				),
				array(
					'title'   => 'New Pages',
					'sortby'  => 'ID',
					'exclude' => '12,34',
				),
			),
			'invalid sortby'   => array(
				array(
					'title'   => 'Pages',
					'sortby'  => 'post_date',
					'exclude' => '',
				),
				array(
					'title'   => 'Pages',
					'sortby'  => 'menu_order',
					'exclude' => '',
				),
			),
		);
	}

	/**
	 * Tests that update() keeps settings that are not part of the new instance.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::update
	 */
	public function test_update_keeps_other_old_settings() {
		$widget = new WP_Widget_Pages();

		$new_instance = array(
			'title'   => 'Pages',
			'sortby'  => 'post_title',
			'exclude' => '',
		);
		$old_instance = array( 'custom' => 'value' );

		$result = $widget->update( $new_instance, $old_instance );

		$this->assertSame( 'value', $result['custom'] );
	}

	/**
	 * Tests that form() displays the fields with the current settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::form
	 */
	public function test_form_displays_fields() {
		$widget = new WP_Widget_Pages();
		$widget->_set( 2 );

		$instance = array(
			'title'   => 'Site "Pages"',
			'sortby'  => 'ID',
			'exclude' => '12,34',
		);

		$output    = get_echo( array( $widget, 'form' ), array( $instance ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$inputs   = array();
		$selects  = array();
		$selected = array();
		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			if ( 'INPUT' === $tag ) {
				$inputs[ $processor->get_attribute( 'id' ) ] = array(
					'name'  => $processor->get_attribute( 'name' ),
					'value' => $processor->get_attribute( 'value' ),
				);
			} elseif ( 'SELECT' === $tag ) {
				$selects[ $processor->get_attribute( 'id' ) ] = $processor->get_attribute( 'name' );
			} elseif ( 'OPTION' === $tag && null !== $processor->get_attribute( 'selected' ) ) {
				$selected[] = $processor->get_attribute( 'value' );
			}
		}

		$this->assertSame(
			array(
				'widget-pages-2-title'   => array(
					'name'  => 'widget-pages[2][title]',
					'value' => 'Site "Pages"',
				),
				'widget-pages-2-exclude' => array(
					'name'  => 'widget-pages[2][exclude]',
					'value' => '12,34',
				),
			),
			$inputs,
			'The form should contain the title and exclude inputs with the saved values.'
		);
		$this->assertSame( array( 'widget-pages-2-sortby' => 'widget-pages[2][sortby]' ), $selects, 'The form should contain the sortby select.' );
		$this->assertSame( array( 'ID' ), $selected, 'Only the saved sortby option should be selected.' );
	}

	/**
	 * Tests that form() displays empty fields for a new widget.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Pages::form
	 */
	public function test_form_with_empty_instance() {
		$widget = new WP_Widget_Pages();
		$widget->_set( 2 );

		$output    = get_echo( array( $widget, 'form' ), array( array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( '', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain an exclude input.' );
		$this->assertSame( 'widget-pages-2-exclude', $processor->get_attribute( 'id' ) );
		$this->assertSame( '', $processor->get_attribute( 'value' ) );
	}
}
