<?php
/**
 * Unit tests covering WP_Widget_Recent_Posts functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-recent-posts.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Recent_Posts
 */
class Tests_Widgets_wpWidgetRecentPosts extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="recent-posts-2" class="widget widget_recent_entries">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Published post IDs, newest first.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	/**
	 * Creates six published posts, one day apart.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		for ( $i = 1; $i <= 6; $i++ ) {
			self::$post_ids[] = $factory->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => sprintf( '2026-01-%02d 10:00:00', 7 - $i ),
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
	 * Renders the widget.
	 *
	 * @param array $instance Widget instance.
	 * @return string Widget output.
	 */
	private function render( $instance ) {
		$widget = new WP_Widget_Recent_Posts();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );
	}

	/**
	 * Returns the post titles in the order they appear in the output.
	 *
	 * @param string $output Widget output.
	 * @return string[] Post titles.
	 */
	private function get_listed_titles( $output ) {
		preg_match_all( '#<a href="[^"]*"(?: aria-current="page")?>([^<]*)</a>#', $output, $matches );

		return $matches[1];
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Recent_Posts();

		$this->assertSame( 'recent-posts', $widget->id_base );
		$this->assertSame( 'widget_recent_entries', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
		$this->assertSame( 'widget_recent_entries', $widget->alt_option_name );
	}

	/**
	 * Tests that widget() lists the five most recent posts by default.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_outputs_recent_posts() {
		$output = $this->render( array() );

		// The template adds whitespace before the widget markup.
		$this->assertStringStartsWith( '<section id="recent-posts-2" class="widget widget_recent_entries">', ltrim( $output ) );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringContainsString( '<ul>', $output );
		$this->assertSame( array( 'Post 1', 'Post 2', 'Post 3', 'Post 4', 'Post 5' ), $this->get_listed_titles( $output ) );
		$this->assertStringContainsString( 'href="' . get_permalink( self::$post_ids[0] ) . '"', $output );
		$this->assertStringNotContainsString( 'post-date', $output, 'Dates should not be shown by default.' );
	}

	/**
	 * Tests that widget() outputs nothing when there are no posts to list.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_outputs_nothing_without_posts() {
		add_filter(
			'widget_posts_args',
			static function ( $args ) {
				$args['post__in'] = array( 0 );
				return $args;
			}
		);

		$this->assertSame( '', $this->render( array() ) );
	}

	/**
	 * Tests the title shown by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
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
			'no title'     => array( array(), 'Recent Posts' ),
			'custom title' => array( array( 'title' => 'Latest News' ), 'Latest News' ),
		);
	}

	/**
	 * Tests that the widget_title filter receives the title, instance and id_base.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
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
			array( array( 'Initial Title', $instance, 'recent-posts' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that the title markup is omitted when the title is filtered to an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_omits_title_markup_for_empty_filtered_title() {
		$this->set_navigation_widgets_format( 'xhtml' );
		add_filter( 'widget_title', '__return_empty_string' );

		$output = $this->render( array() );

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertStringContainsString( '>Post 1</a>', $output, 'The list should still be displayed.' );
	}

	/**
	 * Tests the navigation wrapper and its aria-label in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
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
			'title with HTML'     => array( '<em>Latest</em> News ', 'Latest News' ),
			'title with only tag' => array( '<span></span>', 'Recent Posts' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$processor = new WP_HTML_Tag_Processor( $this->render( array() ) );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
	}

	/**
	 * Tests the arguments passed to the widget_posts_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 *
	 * @dataProvider data_widget_number
	 *
	 * @param mixed $number   The number setting.
	 * @param int   $expected Expected number of posts.
	 */
	public function test_widget_applies_widget_posts_args_filter( $number, $expected ) {
		$instance = array( 'number' => $number );

		$filter = new MockAction();
		add_filter( 'widget_posts_args', array( $filter, 'filter' ), 10, 2 );

		$this->render( $instance );

		$this->assertSame(
			array(
				array(
					array(
						'posts_per_page'      => $expected,
						'no_found_rows'       => true,
						'post_status'         => 'publish',
						'post_type'           => array( 'post' ),
						'ignore_sticky_posts' => true,
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Tests that the number setting limits the listed posts.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 *
	 * @dataProvider data_widget_number
	 *
	 * @param mixed $number   The number setting.
	 * @param int   $expected Expected number of posts.
	 */
	public function test_widget_limits_number_of_posts( $number, $expected ) {
		$output = $this->render( array( 'number' => $number ) );

		$this->assertCount( $expected, $this->get_listed_titles( $output ) );
	}

	/**
	 * Data provider for the number setting.
	 *
	 * @return array[]
	 */
	public static function data_widget_number() {
		return array(
			'zero'          => array( 0, 5 ),
			'not a number'  => array( 'abc', 5 ),
			'two'           => array( 2, 2 ),
			'string three'  => array( '3', 3 ),
			'negative four' => array( -4, 4 ),
		);
	}

	/**
	 * Tests that only published posts are listed and sticky posts are not moved to the top.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_lists_only_published_posts_in_date_order() {
		self::factory()->post->create(
			array(
				'post_title'  => 'Draft Post',
				'post_status' => 'draft',
				'post_date'   => '2026-02-01 10:00:00',
			)
		);
		stick_post( self::$post_ids[5] );

		$titles = $this->get_listed_titles( $this->render( array( 'number' => 6 ) ) );

		$this->assertSame( array( 'Post 1', 'Post 2', 'Post 3', 'Post 4', 'Post 5', 'Post 6' ), $titles );
	}

	/**
	 * Tests that a post without a title is listed as "(no title)".
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_lists_untitled_post() {
		self::factory()->post->create(
			array(
				'post_title' => '',
				'post_date'  => '2026-02-01 10:00:00',
			)
		);

		$titles = $this->get_listed_titles( $this->render( array() ) );

		$this->assertSame( '(no title)', $titles[0] );
	}

	/**
	 * Tests that the show_date setting shows the date of each post.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_shows_post_dates() {
		$output = $this->render(
			array(
				'number'    => 2,
				'show_date' => true,
			)
		);

		$processor = new WP_HTML_Tag_Processor( $output );
		$dates     = 0;
		while (
			$processor->next_tag(
				array(
					'tag_name'   => 'SPAN',
					'class_name' => 'post-date',
				)
			)
		) {
			++$dates;
		}

		$this->assertSame( 2, $dates, 'Each listed post should show its date.' );
		$this->assertStringContainsString( '<span class="post-date">' . get_the_date( '', self::$post_ids[0] ) . '</span>', $output );
		$this->assertStringContainsString( '<span class="post-date">' . get_the_date( '', self::$post_ids[1] ) . '</span>', $output );
	}

	/**
	 * Tests that the post being viewed is marked as the current page.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_marks_current_post() {
		$this->go_to( get_permalink( self::$post_ids[1] ) );

		$output = $this->render( array() );

		$this->assertSame( 1, substr_count( $output, 'aria-current="page"' ), 'Only one post should be marked as current.' );
		$this->assertStringContainsString( ' aria-current="page">Post 2</a>', $output );
	}

	/**
	 * Tests that update() sanitizes and casts the settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Recent_Posts();

		$old_instance = array(
			'title'     => 'Old Title',
			'number'    => 7,
			'show_date' => true,
			'custom'    => 'value',
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
					'title'     => '<b>Latest News</b>',
					'number'    => '3',
					'show_date' => 'on',
				),
				array(
					'title'     => 'Latest News',
					'number'    => 3,
					'show_date' => true,
					'custom'    => 'value',
					'post_type' => array( 'post' ),
				),
			),
			'unchecked date'   => array(
				array(
					'title'  => 'News',
					'number' => 10,
				),
				array(
					'title'     => 'News',
					'number'    => 10,
					'show_date' => false,
					'custom'    => 'value',
					'post_type' => array( 'post' ),
				),
			),
			'empty values'     => array(
				array(
					'title'     => '',
					'number'    => '',
					'show_date' => '',
				),
				array(
					'title'     => '',
					'number'    => 0,
					'show_date' => false,
					'custom'    => 'value',
					'post_type' => array( 'post' ),
				),
			),
		);
	}

	/**
	 * Tests that form() displays the fields with the current settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Posts::form
	 *
	 * @dataProvider data_form
	 *
	 * @param array  $instance  Current settings.
	 * @param string $title     Expected title value.
	 * @param string $number    Expected number value.
	 * @param bool   $show_date Whether the date checkbox is expected to be checked.
	 */
	public function test_form_displays_fields( $instance, $title, $number, $show_date ) {
		$widget = new WP_Widget_Recent_Posts();
		$widget->_set( 2 );

		$processor = new WP_HTML_Tag_Processor( get_echo( array( $widget, 'form' ), array( $instance ) ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a title input.' );
		$this->assertSame( 'widget-recent-posts-2-title', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-recent-posts[2][title]', $processor->get_attribute( 'name' ) );
		$this->assertSame( $title, $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a number input.' );
		$this->assertSame( 'widget-recent-posts-2-number', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-recent-posts[2][number]', $processor->get_attribute( 'name' ) );
		$this->assertSame( 'number', $processor->get_attribute( 'type' ) );
		$this->assertSame( $number, $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain a date checkbox.' );
		$this->assertSame( 'widget-recent-posts-2-show_date', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-recent-posts[2][show_date]', $processor->get_attribute( 'name' ) );
		$this->assertSame( 'checkbox', $processor->get_attribute( 'type' ) );
		$this->assertSame( $show_date ? 'checked' : null, $processor->get_attribute( 'checked' ) );
	}

	/**
	 * Data provider for test_form_displays_fields.
	 *
	 * @return array[]
	 */
	public static function data_form() {
		return array(
			'new widget'   => array( array(), '', '5', false ),
			'saved values' => array(
				array(
					'title'     => 'Latest "News"',
					'number'    => 3,
					'show_date' => true,
				),
				'Latest "News"',
				'3',
				true,
			),
			'zero number'  => array( array( 'number' => 0 ), '', '0', false ),
		);
	}

	/**
	 * Unregisters any custom post type registered by a test.
	 *
	 * @ticket 31085
	 */
	public function tear_down() {
		unregister_post_type( 'widget-cpt' );

		parent::tear_down();
	}

	/**
	 * Tests that the widget only queries the 'post' post type by default.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_defaults_to_post_type_post() {
		$filter = new MockAction();
		add_filter( 'widget_posts_args', array( $filter, 'filter' ) );

		$this->render( array() );

		$args = $filter->get_args();
		$this->assertSame( array( 'post' ), $args[0][0]['post_type'] );
	}

	/**
	 * Tests that the widget only lists posts of the selected post types.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_lists_only_selected_post_types() {
		register_post_type(
			'widget-cpt',
			array(
				'public' => true,
				'label'  => 'Widget CPTs',
			)
		);

		$cpt_post_id = self::factory()->post->create(
			array(
				'post_type'  => 'widget-cpt',
				'post_title' => 'CPT Post',
				'post_date'  => '2026-02-01 10:00:00',
			)
		);

		$titles = $this->get_listed_titles( $this->render( array( 'post_type' => array( 'widget-cpt' ) ) ) );

		$this->assertSame( array( 'CPT Post' ), $titles );

		wp_delete_post( $cpt_post_id, true );
	}

	/**
	 * Tests that selecting 'any' lists posts of every public post type.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_widget_lists_any_post_type() {
		register_post_type(
			'widget-cpt',
			array(
				'public' => true,
				'label'  => 'Widget CPTs',
			)
		);

		$cpt_post_id = self::factory()->post->create(
			array(
				'post_type'  => 'widget-cpt',
				'post_title' => 'CPT Post',
				'post_date'  => '2026-02-01 10:00:00',
			)
		);

		$titles = $this->get_listed_titles(
			$this->render(
				array(
					'post_type' => array( 'any' ),
					'number'    => 1,
				)
			)
		);

		$this->assertSame( array( 'CPT Post' ), $titles );

		wp_delete_post( $cpt_post_id, true );
	}

	/**
	 * Tests that update() discards post types that are not valid choices.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::update
	 *
	 * @dataProvider data_update_post_type
	 *
	 * @param mixed $post_type Submitted post_type setting.
	 * @param array $expected  Expected saved post_type setting.
	 */
	public function test_update_sanitizes_post_type( $post_type, $expected ) {
		$widget = new WP_Widget_Recent_Posts();

		$new_instance = array(
			'title'     => 'Title',
			'number'    => 5,
			'post_type' => $post_type,
		);

		$instance = $widget->update( $new_instance, array() );

		$this->assertSame( $expected, $instance['post_type'] );
	}

	/**
	 * Data provider for test_update_sanitizes_post_type.
	 *
	 * @return array[]
	 */
	public static function data_update_post_type() {
		return array(
			'not set'                => array( null, array( 'post' ) ),
			'unregistered post type' => array( array( 'does-not-exist' ), array( 'post' ) ),
			'post explicitly'        => array( array( 'post' ), array( 'post' ) ),
			'any'                    => array( array( 'any' ), 'any' ),
			'any alongside post'     => array( array( 'any', 'post' ), 'any' ),
		);
	}

	/**
	 * Tests that update() keeps a registered public custom post type.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::update
	 */
	public function test_update_keeps_registered_custom_post_type() {
		register_post_type(
			'widget-cpt',
			array(
				'public' => true,
				'label'  => 'Widget CPTs',
			)
		);

		$new_instance = array(
			'title'     => 'Title',
			'number'    => 5,
			'post_type' => array( 'widget-cpt' ),
		);

		$widget   = new WP_Widget_Recent_Posts();
		$instance = $widget->update( $new_instance, array() );

		$this->assertSame( array( 'widget-cpt' ), $instance['post_type'] );
	}

	/**
	 * Tests that the post types selector is hidden when there are no custom post types.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::form
	 */
	public function test_form_hides_post_type_selector_without_custom_post_types() {
		$widget = new WP_Widget_Recent_Posts();

		$output = get_echo( array( $widget, 'form' ), array( array() ) );

		$this->assertStringNotContainsString( 'Post types to show', $output );
	}

	/**
	 * Tests that the post types selector is shown when a custom post type is registered.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::form
	 */
	public function test_form_shows_post_type_selector_with_custom_post_types() {
		register_post_type(
			'widget-cpt',
			array(
				'public' => true,
				'label'  => 'Widget CPTs',
			)
		);

		$widget = new WP_Widget_Recent_Posts();
		$widget->_set( 2 );

		$output = get_echo( array( $widget, 'form' ), array( array( 'post_type' => array( 'widget-cpt' ) ) ) );

		$this->assertStringContainsString( 'Post types to show', $output );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name' => 'SELECT',
					'id'       => 'widget-recent-posts-2-post_type',
				)
			)
		);
		$this->assertSame( 'widget-recent-posts[2][post_type][]', $processor->get_attribute( 'name' ) );
	}

	/**
	 * Tests that the widget_posts_cpt_args filter changes the available custom post types.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::form
	 */
	public function test_widget_posts_cpt_args_filter() {
		register_post_type(
			'widget-cpt',
			array(
				'public' => true,
				'label'  => 'Widget CPTs',
			)
		);

		add_filter(
			'widget_posts_cpt_args',
			static function ( $args ) {
				$args['name'] = 'widget-cpt';
				return $args;
			}
		);

		$widget = new WP_Widget_Recent_Posts();
		$output = get_echo( array( $widget, 'form' ), array( array() ) );

		$this->assertStringContainsString( 'Widget CPTs', $output );
	}

	/**
	 * Tests that the widget_posts_cpt_selector filter overrides the selector's visibility.
	 *
	 * @ticket 31085
	 *
	 * @covers WP_Widget_Recent_Posts::form
	 */
	public function test_widget_posts_cpt_selector_filter() {
		add_filter( 'widget_posts_cpt_selector', '__return_true' );

		$widget = new WP_Widget_Recent_Posts();
		$output = get_echo( array( $widget, 'form' ), array( array() ) );

		$this->assertStringContainsString( 'Post types to show', $output );
	}
}
