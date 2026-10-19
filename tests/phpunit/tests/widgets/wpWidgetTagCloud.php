<?php
/**
 * Unit tests covering WP_Widget_Tag_Cloud functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-tag-cloud.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Tag_Cloud
 */
class Tests_Widgets_wpWidgetTagCloud extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="tag_cloud-2" class="widget widget_tag_cloud">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Creates a tag and a category, each used by one post.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$category_id = $factory->category->create( array( 'name' => 'Cat One' ) );

		$factory->post->create(
			array(
				'tags_input'    => array( 'Alpha Tag' ),
				'post_category' => array( $category_id ),
			)
		);
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
		$widget = new WP_Widget_Tag_Cloud();

		return get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
	}

	/**
	 * Renders the settings form.
	 *
	 * @param array $instance Current settings.
	 * @return string Form output.
	 */
	private function render_form( $instance ) {
		$widget = new WP_Widget_Tag_Cloud();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'form' ), array( $instance ) );
	}

	/**
	 * Turns off tag cloud support for all taxonomies except the given ones.
	 *
	 * Taxonomies are reset after each test.
	 *
	 * @param string[] $keep Taxonomies that keep tag cloud support.
	 */
	private function disable_tag_cloud_taxonomies( $keep = array() ) {
		foreach ( get_taxonomies( array( 'show_tagcloud' => true ), 'objects' ) as $taxonomy ) {
			if ( ! in_array( $taxonomy->name, $keep, true ) ) {
				$taxonomy->show_tagcloud = false;
			}
		}
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Tag_Cloud();

		$this->assertSame( 'tag_cloud', $widget->id_base );
		$this->assertSame( 'Tag Cloud', $widget->name );
		$this->assertSame( 'widget_tag_cloud', $widget->widget_options['classname'] );
		$this->assertSame( 'A cloud of your most used tags.', $widget->widget_options['description'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests that widget() wraps the tag cloud in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_widget_outputs_tag_cloud() {
		$output    = $this->render( array() );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertStringStartsWith( '<section id="tag_cloud-2" class="widget widget_tag_cloud">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'DIV',
					'class_name' => 'tagcloud',
				)
			),
			'The cloud should be wrapped in a tagcloud div.'
		);
		$this->assertStringContainsString( '>Alpha Tag</a>', $output );
		$this->assertStringNotContainsString( 'Cat One', $output, 'Only tags should be listed by default.' );
	}

	/**
	 * Tests that widget() outputs nothing when the taxonomy has no terms to show.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_widget_outputs_nothing_for_empty_cloud() {
		register_taxonomy( 'wptests_tax', 'post', array( 'show_tagcloud' => true ) );

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ) );

		$output = $this->render( array( 'taxonomy' => 'wptests_tax' ) );

		$this->assertSame( '', $output );
		$this->assertSame( 0, $filter->get_call_count(), 'The title should not be filtered when nothing is displayed.' );
	}

	/**
	 * Tests the title shown by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
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
			'no title'               => array( array(), 'Tags' ),
			'empty title'            => array( array( 'title' => '' ), 'Tags' ),
			'title "0"'              => array( array( 'title' => '0' ), 'Tags' ),
			'custom title'           => array( array( 'title' => 'Topics' ), 'Topics' ),
			'category taxonomy'      => array( array( 'taxonomy' => 'category' ), 'Categories' ),
			'invalid taxonomy'       => array( array( 'taxonomy' => 'not_a_taxonomy' ), 'Tags' ),
			'custom title, category' => array(
				array(
					'title'    => 'Sections',
					'taxonomy' => 'category',
				),
				'Sections',
			),
		);
	}

	/**
	 * Tests that the widget_title filter receives the title, instance and id_base.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
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
			array( array( 'Initial Title', $instance, 'tag_cloud' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that the title markup is omitted when the title is filtered to an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_widget_omits_title_markup_for_empty_filtered_title() {
		$this->set_navigation_widgets_format( 'xhtml' );
		add_filter( 'widget_title', '__return_empty_string' );

		$output = $this->render( array() );

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '>Alpha Tag</a>', $output, 'The cloud should still be displayed.' );
	}

	/**
	 * Tests the navigation wrapper and its aria-label in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 *
	 * @dataProvider data_widget_navigation_aria_label
	 *
	 * @param array  $instance       Widget instance.
	 * @param string $filtered_title Title returned by the widget_title filter.
	 * @param string $expected       Expected aria-label.
	 */
	public function test_widget_adds_navigation_wrapper_in_html5( $instance, $filtered_title, $expected ) {
		$this->set_navigation_widgets_format( 'html5' );
		add_filter(
			'widget_title',
			static function () use ( $filtered_title ) {
				return $filtered_title;
			}
		);

		$output    = $this->render( $instance );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'The output should contain a nav tag.' );
		$this->assertSame( $expected, $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( "</div>\n</nav>", $output );
	}

	/**
	 * Data provider for test_widget_adds_navigation_wrapper_in_html5.
	 *
	 * @return array[]
	 */
	public static function data_widget_navigation_aria_label() {
		return array(
			'plain title'                   => array( array(), 'Topics', 'Topics' ),
			'title with HTML'               => array( array(), '<em>Site</em> Topics ', 'Site Topics' ),
			'empty title'                   => array( array(), '', 'Tags' ),
			'empty title, custom title'     => array( array( 'title' => 'Sections' ), '', 'Sections' ),
			'empty title, category'         => array( array( 'taxonomy' => 'category' ), '', 'Categories' ),
			'title with only tag, category' => array( array( 'taxonomy' => 'category' ), '<span></span>', 'Categories' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$processor = new WP_HTML_Tag_Processor( $this->render( array() ) );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
	}

	/**
	 * Tests the arguments passed to the widget_tag_cloud_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 *
	 * @dataProvider data_widget_tag_cloud_args
	 *
	 * @param array  $instance   Widget instance.
	 * @param string $taxonomy   Expected taxonomy argument.
	 * @param bool   $show_count Expected show_count argument.
	 */
	public function test_widget_applies_widget_tag_cloud_args_filter( $instance, $taxonomy, $show_count ) {
		$filter = new MockAction();
		add_filter( 'widget_tag_cloud_args', array( $filter, 'filter' ), 10, 2 );

		$this->render( $instance );

		$this->assertSame(
			array(
				array(
					array(
						'taxonomy'   => $taxonomy,
						'echo'       => false,
						'show_count' => $show_count,
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Data provider for test_widget_applies_widget_tag_cloud_args_filter.
	 *
	 * @return array[]
	 */
	public static function data_widget_tag_cloud_args() {
		return array(
			'defaults'         => array( array(), 'post_tag', false ),
			'count'            => array( array( 'count' => 1 ), 'post_tag', true ),
			'category'         => array( array( 'taxonomy' => 'category' ), 'category', false ),
			'invalid taxonomy' => array(
				array(
					'taxonomy' => 'not_a_taxonomy',
					'count'    => 0,
				),
				'post_tag',
				false,
			),
		);
	}

	/**
	 * Tests the cloud output for the taxonomy and count settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_widget_outputs_selected_taxonomy_with_counts() {
		$output = $this->render(
			array(
				'taxonomy' => 'category',
				'count'    => 1,
			)
		);

		$this->assertStringContainsString( '>Cat One<', $output );
		$this->assertStringNotContainsString( 'Alpha Tag', $output, 'Only the selected taxonomy should be listed.' );
		$this->assertStringContainsString( '<span class="tag-link-count"> (1)</span>', $output, 'Term counts should be shown.' );
	}

	/**
	 * Tests _get_current_taxonomy().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::_get_current_taxonomy
	 *
	 * @dataProvider data_get_current_taxonomy
	 *
	 * @param array  $instance Widget instance.
	 * @param string $expected Expected taxonomy.
	 */
	public function test_get_current_taxonomy( $instance, $expected ) {
		$widget = new WP_Widget_Tag_Cloud();

		$this->assertSame( $expected, $widget->_get_current_taxonomy( $instance ) );
	}

	/**
	 * Data provider for test_get_current_taxonomy.
	 *
	 * @return array[]
	 */
	public static function data_get_current_taxonomy() {
		return array(
			'no taxonomy'      => array( array(), 'post_tag' ),
			'empty taxonomy'   => array( array( 'taxonomy' => '' ), 'post_tag' ),
			'invalid taxonomy' => array( array( 'taxonomy' => 'not_a_taxonomy' ), 'post_tag' ),
			'category'         => array( array( 'taxonomy' => 'category' ), 'category' ),
			'post_tag'         => array( array( 'taxonomy' => 'post_tag' ), 'post_tag' ),
		);
	}

	/**
	 * Tests that update() sanitizes the settings and doesn't keep old ones.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Tag_Cloud();

		$old_instance = array(
			'title'    => 'Old Title',
			'count'    => 1,
			'taxonomy' => 'category',
			'custom'   => 'value',
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
					'title'    => '<b>New Tags</b>',
					'count'    => 'on',
					'taxonomy' => 'post_tag',
				),
				array(
					'title'    => 'New Tags',
					'count'    => 1,
					'taxonomy' => 'post_tag',
				),
			),
			'unchecked count'  => array(
				array(
					'title'    => 'Tags',
					'taxonomy' => 'category',
				),
				array(
					'title'    => 'Tags',
					'count'    => 0,
					'taxonomy' => 'category',
				),
			),
			'slashed taxonomy' => array(
				array(
					'title'    => '',
					'count'    => '',
					'taxonomy' => 'wptests\\_tax',
				),
				array(
					'title'    => '',
					'count'    => 0,
					'taxonomy' => 'wptests_tax',
				),
			),
		);
	}

	/**
	 * Tests that form() displays a taxonomy select when several taxonomies support the tag cloud.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::form
	 */
	public function test_form_with_multiple_taxonomies() {
		$output = $this->render_form(
			array(
				'title'    => 'Site "Tags"',
				'count'    => 1,
				'taxonomy' => 'category',
			)
		);

		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( 'widget-tag_cloud-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-tag_cloud[2][title]', $processor->get_attribute( 'name' ) );
		$this->assertSame( 'Site "Tags"', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'SELECT' ), 'The form should contain a taxonomy select.' );
		$this->assertSame( 'widget-tag_cloud-2-taxonomy', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-tag_cloud[2][taxonomy]', $processor->get_attribute( 'name' ) );

		$options  = array();
		$selected = array();
		while ( $processor->next_tag( 'OPTION' ) ) {
			$options[] = $processor->get_attribute( 'value' );
			if ( null !== $processor->get_attribute( 'selected' ) ) {
				$selected[] = $processor->get_attribute( 'value' );
			}
		}

		$this->assertSameSets(
			array_keys( get_taxonomies( array( 'show_tagcloud' => true ) ) ),
			$options,
			'All tag cloud taxonomies should be listed.'
		);
		$this->assertContains( 'category', $options );
		$this->assertContains( 'post_tag', $options );
		$this->assertSame( array( 'category' ), $selected, 'The saved taxonomy should be selected.' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'INPUT',
					'class_name' => 'checkbox',
				)
			),
			'The form should contain a count checkbox.'
		);
		$this->assertSame( 'widget-tag_cloud-2-count', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'checked', $processor->get_attribute( 'checked' ) );
	}

	/**
	 * Tests that form() uses a hidden taxonomy field when only one taxonomy supports the tag cloud.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::form
	 */
	public function test_form_with_single_taxonomy() {
		$this->disable_tag_cloud_taxonomies( array( 'post_tag' ) );

		$output    = $this->render_form( array() );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( '', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a taxonomy input.' );
		$this->assertSame( 'widget-tag_cloud-2-taxonomy', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'hidden', $processor->get_attribute( 'type' ) );
		$this->assertSame( 'post_tag', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a count checkbox.' );
		$this->assertSame( 'widget-tag_cloud-2-count', $processor->get_attribute( 'id' ) );
		$this->assertNull( $processor->get_attribute( 'checked' ) );

		$this->assertStringNotContainsString( '<select', $output );
	}

	/**
	 * Tests that form() shows a notice when no taxonomy supports the tag cloud.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Tag_Cloud::form
	 */
	public function test_form_without_taxonomies() {
		$this->disable_tag_cloud_taxonomies();

		$output    = $this->render_form( array( 'count' => 1 ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a taxonomy input.' );
		$this->assertSame( 'widget-tag_cloud-2-taxonomy', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'hidden', $processor->get_attribute( 'type' ) );
		$this->assertSame( '', $processor->get_attribute( 'value' ) );

		$this->assertFalse( $processor->next_tag( 'INPUT' ), 'The count checkbox should not be shown.' );
		$this->assertStringContainsString( 'there are no taxonomies that support the tag cloud widget', $output );
	}
}
