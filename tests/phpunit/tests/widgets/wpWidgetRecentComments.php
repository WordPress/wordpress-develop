<?php
/**
 * Unit tests covering WP_Widget_Recent_Comments functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-recent-comments.php
 *
 * @group widgets
 * @group comment
 *
 * @covers WP_Widget_Recent_Comments
 */
class Tests_Widgets_wpWidgetRecentComments extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="recent-comments-2" class="widget widget_recent_comments">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * ID of the published post the listed comments belong to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Comment IDs keyed by comment author.
	 *
	 * @var int[]
	 */
	protected static $comments = array();

	/**
	 * Theme features before the test, restored afterwards.
	 *
	 * @var array
	 */
	private $theme_features;

	/**
	 * Creates the posts and comments shared by the tests.
	 *
	 * Six approved comments are created on a published post, from oldest to newest,
	 * so that the widget's default limit of five comments can be tested.
	 * Comments that should never be listed are created with newer dates,
	 * so that they would be listed first if they weren't excluded.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create( array( 'post_title' => 'Commented Post' ) );
		$draft_id      = $factory->post->create(
			array(
				'post_title'  => 'Draft Post',
				'post_status' => 'draft',
			)
		);

		$comments = array(
			'Alice'    => array( self::$post_id, '1' ),
			'Bob'      => array( self::$post_id, '1' ),
			'Carol'    => array( self::$post_id, '1' ),
			'Dave'     => array( self::$post_id, '1' ),
			'Eve'      => array( self::$post_id, '1' ),
			'Frank'    => array( self::$post_id, '1' ),
			'Pending'  => array( self::$post_id, '0' ),
			'Spammer'  => array( self::$post_id, 'spam' ),
			'Drafting' => array( $draft_id, '1' ),
		);

		$minute = 0;
		foreach ( $comments as $author => list( $post_id, $approved ) ) {
			$date = sprintf( '2026-01-01 10:%02d:00', $minute++ );

			self::$comments[ $author ] = $factory->comment->create(
				array(
					'comment_post_ID'    => $post_id,
					'comment_author'     => $author,
					'comment_author_url' => '',
					'comment_approved'   => $approved,
					'comment_date'       => $date,
					'comment_date_gmt'   => $date,
				)
			);
		}
	}

	/**
	 * Backs up the theme features, as some tests change the theme support for widgets.
	 */
	public function set_up() {
		parent::set_up();

		$this->theme_features = $GLOBALS['_wp_theme_features'];
	}

	/**
	 * Restores the theme features.
	 */
	public function tear_down() {
		$GLOBALS['_wp_theme_features'] = $this->theme_features;

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
	 * Returns the comment authors in the order they appear in the output.
	 *
	 * @param string $output Widget output.
	 * @return string[] Comment authors.
	 */
	private function get_listed_authors( $output ) {
		preg_match_all( '#<span class="comment-author-link">([^<]*)</span>#', $output, $matches );

		return $matches[1];
	}

	/**
	 * Returns the inputs of the widget form, keyed by ID.
	 *
	 * @param WP_Widget_Recent_Comments $widget   Widget.
	 * @param array                     $instance Widget instance.
	 * @return array[] Arrays with the 'name' and 'value' attributes of each input.
	 */
	private function get_form_inputs( $widget, $instance ) {
		$processor = new WP_HTML_Tag_Processor( get_echo( array( $widget, 'form' ), array( $instance ) ) );

		$inputs = array();
		while ( $processor->next_tag( 'INPUT' ) ) {
			$inputs[ $processor->get_attribute( 'id' ) ] = array(
				'name'  => $processor->get_attribute( 'name' ),
				'value' => $processor->get_attribute( 'value' ),
			);
		}

		return $inputs;
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Recent_Comments();

		$this->assertSame( 'recent-comments', $widget->id_base );
		$this->assertSame( 'Recent Comments', $widget->name );
		$this->assertSame( 'widget_recent_comments', $widget->alt_option_name );
		$this->assertSame( 'widget_recent_comments', $widget->widget_options['classname'] );
		$this->assertSame( 'Your site&#8217;s most recent comments.', $widget->widget_options['description'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
	}

	/**
	 * Tests that the constructor only hooks the default styles when the widget is used.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::__construct
	 *
	 * @dataProvider data_construct_style_hook
	 *
	 * @param array    $sidebars_widgets The widgets in each sidebar.
	 * @param int|bool $expected         Expected priority of the style callback, or false if it is not hooked.
	 */
	public function test_construct_hooks_styles_only_for_active_widget( $sidebars_widgets, $expected ) {
		add_filter(
			'sidebars_widgets',
			static function () use ( $sidebars_widgets ) {
				return $sidebars_widgets;
			}
		);

		$widget = new WP_Widget_Recent_Comments();

		$this->assertSame( $expected, has_action( 'wp_head', array( $widget, 'recent_comments_style' ) ) );
	}

	/**
	 * Data provider for test_construct_hooks_styles_only_for_active_widget.
	 *
	 * @return array[]
	 */
	public static function data_construct_style_hook() {
		return array(
			'widget in a sidebar'            => array( array( 'sidebar-1' => array( 'recent-comments-2' ) ), 10 ),
			'widget in the inactive widgets' => array( array( 'wp_inactive_widgets' => array( 'recent-comments-2' ) ), false ),
			'other widget in a sidebar'      => array( array( 'sidebar-1' => array( 'recent-posts-2' ) ), false ),
			'no widgets'                     => array( array(), false ),
		);
	}

	/**
	 * Tests that recent_comments_style() outputs the default styles.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::recent_comments_style
	 */
	public function test_recent_comments_style_outputs_styles() {
		add_theme_support( 'widgets' );

		$widget = new WP_Widget_Recent_Comments();

		$this->assertSame(
			'<style>.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style>',
			get_echo( array( $widget, 'recent_comments_style' ) )
		);
	}

	/**
	 * Tests that recent_comments_style() outputs nothing when the theme doesn't support widgets.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::recent_comments_style
	 */
	public function test_recent_comments_style_requires_widgets_support() {
		// remove_theme_support() doesn't remove the internal 'widgets' registration.
		_remove_theme_support( 'widgets' );

		$widget = new WP_Widget_Recent_Comments();

		$this->assertSame( '', get_echo( array( $widget, 'recent_comments_style' ) ) );
	}

	/**
	 * Tests that the show_recent_comments_widget_style filter can disable the default styles.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::recent_comments_style
	 */
	public function test_recent_comments_style_can_be_disabled_by_filter() {
		add_theme_support( 'widgets' );

		$filter = new MockAction();
		add_filter( 'show_recent_comments_widget_style', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'show_recent_comments_widget_style', '__return_false', 20 );

		$widget = new WP_Widget_Recent_Comments();

		$this->assertSame( '', get_echo( array( $widget, 'recent_comments_style' ) ) );
		$this->assertSame(
			array( array( true, 'recent-comments' ) ),
			$filter->get_args(),
			'The show_recent_comments_widget_style filter should receive true and the id_base.'
		);
	}

	/**
	 * Tests that widget() wraps the five most recent comments in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_outputs_most_recent_comments() {
		$widget = new WP_Widget_Recent_Comments();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );

		$this->assertStringStartsWith( '<section id="recent-comments-2" class="widget widget_recent_comments">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertSame( array( 'Frank', 'Eve', 'Dave', 'Carol', 'Bob' ), $this->get_listed_authors( $output ) );
	}

	/**
	 * Tests that widget() only lists approved comments on published posts.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_lists_only_approved_comments_on_published_posts() {
		$widget = new WP_Widget_Recent_Comments();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array( 'number' => 20 ) ) );

		$this->assertSame( array( 'Frank', 'Eve', 'Dave', 'Carol', 'Bob', 'Alice' ), $this->get_listed_authors( $output ) );
	}

	/**
	 * Tests the markup of a listed comment.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_comment_markup() {
		$widget = new WP_Widget_Recent_Comments();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array( 'number' => 1 ) ) );

		$this->assertStringContainsString(
			'<li class="recentcomments"><span class="comment-author-link">Frank</span> on <a href="' . esc_url( get_comment_link( self::$comments['Frank'] ) ) . '">Commented Post</a></li>',
			$output
		);
	}

	/**
	 * Tests the number of comments listed by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 *
	 * @dataProvider data_widget_number
	 *
	 * @param array $instance Widget instance.
	 * @param int   $expected Expected number of listed comments.
	 */
	public function test_widget_number( $instance, $expected ) {
		$widget = new WP_Widget_Recent_Comments();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertCount( $expected, $this->get_listed_authors( $output ) );
	}

	/**
	 * Data provider for test_widget_number.
	 *
	 * @return array[]
	 */
	public static function data_widget_number() {
		return array(
			'no number'           => array( array(), 5 ),
			'zero'                => array( array( 'number' => 0 ), 5 ),
			'numeric string'      => array( array( 'number' => '2' ), 2 ),
			'negative number'     => array( array( 'number' => -3 ), 3 ),
			'non-numeric string'  => array( array( 'number' => 'abc' ), 5 ),
			'more than available' => array( array( 'number' => 20 ), 6 ),
		);
	}

	/**
	 * Tests the arguments passed to the widget_comments_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_applies_widget_comments_args_filter() {
		$widget   = new WP_Widget_Recent_Comments();
		$instance = array( 'number' => 3 );

		$filter = new MockAction();
		add_filter( 'widget_comments_args', array( $filter, 'filter' ), 10, 2 );

		get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertSame(
			array(
				array(
					array(
						'number'      => 3,
						'status'      => 'approve',
						'post_status' => 'publish',
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Tests that widget() outputs an empty list when there are no comments to list.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_outputs_empty_list_without_comments() {
		add_filter(
			'widget_comments_args',
			static function ( $comment_args ) {
				$comment_args['status'] = 'trash';
				return $comment_args;
			}
		);

		$widget = new WP_Widget_Recent_Comments();

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'UL' ), 'The output should contain a list.' );
		$this->assertFalse( $processor->next_tag( 'LI' ), 'The list should be empty.' );
	}

	/**
	 * Tests that a widget displayed after the first one uses the widget number in its list ID.
	 *
	 * The first widget displayed on a page uses the 'recentcomments' ID. As this is tracked
	 * in a static variable for the whole request, only the IDs used after it can be tested.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_uses_widget_number_in_list_id_after_first_instance() {
		$widget = new WP_Widget_Recent_Comments();
		$widget->_set( 3 );

		// Make sure that a widget was displayed before.
		get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'UL' ), 'The output should contain a list.' );
		$this->assertSame( 'recentcomments-3', $processor->get_attribute( 'id' ) );
	}

	/**
	 * Tests the title shown by widget().
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 *
	 * @dataProvider data_widget_title
	 *
	 * @param array  $instance Widget instance.
	 * @param string $expected Expected title.
	 */
	public function test_widget_title( $instance, $expected ) {
		$widget = new WP_Widget_Recent_Comments();

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
			'no title'     => array( array(), 'Recent Comments' ),
			'empty title'  => array( array( 'title' => '' ), 'Recent Comments' ),
			'title "0"'    => array( array( 'title' => '0' ), 'Recent Comments' ),
			'custom title' => array( array( 'title' => 'Latest Comments' ), 'Latest Comments' ),
		);
	}

	/**
	 * Tests that the widget_title filter receives the title, instance and id_base.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$widget   = new WP_Widget_Recent_Comments();
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
			array( array( 'Initial Title', $instance, 'recent-comments' ) ),
			$filter->get_args(),
			'The widget_title filter should receive the title, instance, and id_base.'
		);
	}

	/**
	 * Tests that the title markup is omitted when the title is filtered to an empty string.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_omits_title_markup_for_empty_filtered_title() {
		add_filter( 'widget_title', '__return_empty_string' );

		$widget = new WP_Widget_Recent_Comments();

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );

		$this->assertStringNotContainsString( 'widget-title', $output );
		$this->assertCount( 5, $this->get_listed_authors( $output ), 'The comments should still be listed.' );
	}

	/**
	 * Tests the navigation wrapper and its aria-label in HTML5 mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
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

		$widget = new WP_Widget_Recent_Comments();

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'The output should contain a nav tag.' );
		$this->assertSame( $expected, $processor->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( '</ul></nav>', $output );
	}

	/**
	 * Data provider for test_widget_adds_navigation_wrapper_in_html5.
	 *
	 * @return array[]
	 */
	public static function data_widget_navigation_aria_label() {
		return array(
			'plain title'         => array( 'Latest Comments', 'Latest Comments' ),
			'title with HTML'     => array( '<em>Latest</em> Comments ', 'Latest Comments' ),
			'empty title'         => array( '', 'Recent Comments' ),
			'title with only tag' => array( '<span></span>', 'Recent Comments' ),
		);
	}

	/**
	 * Tests that the navigation wrapper is omitted in XHTML mode.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_widget_omits_navigation_wrapper_in_xhtml() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$widget = new WP_Widget_Recent_Comments();

		$output    = get_echo( array( $widget, 'widget' ), array( $this->args, array() ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertFalse( $processor->next_tag( 'NAV' ), 'The output should not contain a nav tag in xhtml format.' );
		$this->assertCount( 5, $this->get_listed_authors( $output ), 'The comments should still be listed.' );
	}

	/**
	 * Tests that update() sanitizes the settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Recent_Comments();

		$old_instance = array(
			'title'  => 'Old Title',
			'number' => 4,
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
			'sanitized values'   => array(
				array(
					'title'  => '<b>New</b> Comments',
					'number' => '7',
				),
				array(
					'title'  => 'New Comments',
					'number' => 7,
				),
			),
			'negative number'    => array(
				array(
					'title'  => 'Comments',
					'number' => '-3',
				),
				array(
					'title'  => 'Comments',
					'number' => 3,
				),
			),
			'non-numeric number' => array(
				array(
					'title'  => 'Comments',
					'number' => 'abc',
				),
				array(
					'title'  => 'Comments',
					'number' => 0,
				),
			),
		);
	}

	/**
	 * Tests that update() keeps settings that are not part of the new instance.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::update
	 */
	public function test_update_keeps_other_old_settings() {
		$widget = new WP_Widget_Recent_Comments();

		$new_instance = array(
			'title'  => 'Comments',
			'number' => 5,
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
	 * @covers WP_Widget_Recent_Comments::form
	 */
	public function test_form_displays_fields() {
		$widget = new WP_Widget_Recent_Comments();
		$widget->_set( 2 );

		$instance = array(
			'title'  => 'Latest "Comments"',
			'number' => '3',
		);

		$this->assertSame(
			array(
				'widget-recent-comments-2-title'  => array(
					'name'  => 'widget-recent-comments[2][title]',
					'value' => 'Latest "Comments"',
				),
				'widget-recent-comments-2-number' => array(
					'name'  => 'widget-recent-comments[2][number]',
					'value' => '3',
				),
			),
			$this->get_form_inputs( $widget, $instance )
		);
	}

	/**
	 * Tests that form() displays the default settings for a new widget.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::form
	 */
	public function test_form_with_empty_instance() {
		$widget = new WP_Widget_Recent_Comments();
		$widget->_set( 2 );

		$inputs = $this->get_form_inputs( $widget, array() );

		$this->assertSame( '', $inputs['widget-recent-comments-2-title']['value'], 'The title should be empty.' );
		$this->assertSame( '5', $inputs['widget-recent-comments-2-number']['value'], 'The number should default to 5.' );
	}

	/**
	 * Tests that the deprecated flush_widget_cache() method triggers a deprecation notice.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Recent_Comments::flush_widget_cache
	 *
	 * @expectedDeprecated WP_Widget_Recent_Comments::flush_widget_cache
	 */
	public function test_flush_widget_cache_is_deprecated() {
		$widget = new WP_Widget_Recent_Comments();

		$widget->flush_widget_cache();
	}
}
