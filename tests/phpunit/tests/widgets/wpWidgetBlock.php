<?php
/**
 * Unit tests covering WP_Widget_Block functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-block.php
 *
 * @group widgets
 *
 * @covers WP_Widget_Block
 */
class Tests_Widgets_wpWidgetBlock extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="block-2" class="widget widget_block">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * Creates a published post shared by the tests so dynamic blocks (such as core/calendar) have content.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create(
			array(
				'post_title'  => 'Sample Post',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $instance Widget instance.
	 * @return string Widget output.
	 */
	private function render( $instance ) {
		$widget = new WP_Widget_Block();
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
		$widget = new WP_Widget_Block();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'form' ), array( $instance ) );
	}

	/**
	 * Tests the constructor settings, control options, and hooked filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Block();

		$this->assertSame( 'block', $widget->id_base );
		$this->assertSame( 'widget_block', $widget->widget_options['classname'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'] );
		$this->assertSame( 400, $widget->control_options['width'] );
		$this->assertSame( 350, $widget->control_options['height'] );
		$this->assertSame(
			10,
			has_filter( 'is_wide_widget_in_customizer', array( $widget, 'set_is_wide_widget_in_customizer' ) )
		);
	}

	/**
	 * Tests that widget() outputs the rendered block content wrapped in the widget markup.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::widget
	 */
	public function test_widget_outputs_block_content() {
		$output = $this->render(
			array(
				'content' => '<!-- wp:paragraph --><p>Hello Block Widget</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertStringStartsWith( '<section id="block-2" class="widget widget_block widget_text">', $output );
		$this->assertStringEndsWith( "</section>\n", $output );
		$this->assertStringContainsString( '>Hello Block Widget</p>', $output );
	}

	/**
	 * Tests that widget() falls back to an empty content string when content is omitted.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::widget
	 */
	public function test_widget_with_empty_instance() {
		$output = $this->render( array() );

		$this->assertSame( "<section id=\"block-2\" class=\"widget widget_block\"></section>\n", $output );
	}

	/**
	 * Tests that widget() applies the widget_block_content filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::widget
	 */
	public function test_widget_applies_widget_block_content_filter() {
		$widget = new WP_Widget_Block();
		$widget->_set( 2 );

		$instance = array(
			'content' => '<!-- wp:heading --><h2>Original</h2><!-- /wp:heading -->',
		);

		$filter = new MockAction();
		add_filter( 'widget_block_content', array( $filter, 'filter' ), 1, 3 );
		add_filter(
			'widget_block_content',
			static function () {
				return '<p>Filtered Content</p>';
			},
			20
		);

		$output = get_echo( array( $widget, 'widget' ), array( $this->args, $instance ) );

		$this->assertStringContainsString( '<p>Filtered Content</p>', $output );
		$this->assertSame(
			array( array( $instance['content'], $instance, $widget ) ),
			$filter->get_args(),
			'The widget_block_content filter should receive the content, instance, and widget object.'
		);
	}

	/**
	 * Tests that get_dynamic_classname() maps blocks to their legacy widget class names.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::widget
	 * @covers WP_Widget_Block::get_dynamic_classname
	 *
	 * @dataProvider data_dynamic_classname
	 *
	 * @param string      $content            Block content.
	 * @param string      $expected_classname Expected class attribute value on the wrapper.
	 * @param string|null $expected_block     Expected block name passed to widget_block_dynamic_classname.
	 */
	public function test_widget_dynamic_classname( $content, $expected_classname, $expected_block ) {
		$filter = new MockAction();
		add_filter( 'widget_block_dynamic_classname', array( $filter, 'filter' ), 10, 2 );

		$output    = $this->render( array( 'content' => $content ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'SECTION' ) );
		$this->assertSame( 'widget ' . $expected_classname, $processor->get_attribute( 'class' ) );
		$this->assertSame(
			array( array( $expected_classname, $expected_block ) ),
			$filter->get_args(),
			'The widget_block_dynamic_classname filter should receive the computed classname and block name.'
		);
	}

	/**
	 * Data provider for test_widget_dynamic_classname.
	 *
	 * @return array[]
	 */
	public static function data_dynamic_classname() {
		return array(
			'core/paragraph'       => array(
				'<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->',
				'widget_block widget_text',
				'core/paragraph',
			),
			'core/calendar'        => array(
				'<!-- wp:calendar /-->',
				'widget_block widget_calendar',
				'core/calendar',
			),
			'core/search'          => array(
				'<!-- wp:search /-->',
				'widget_block widget_search',
				'core/search',
			),
			'core/html'            => array(
				'<!-- wp:html --><div>HTML</div><!-- /wp:html -->',
				'widget_block widget_custom_html',
				'core/html',
			),
			'core/archives'        => array(
				'<!-- wp:archives /-->',
				'widget_block widget_archive',
				'core/archives',
			),
			'core/latest-posts'    => array(
				'<!-- wp:latest-posts /-->',
				'widget_block widget_recent_entries',
				'core/latest-posts',
			),
			'core/latest-comments' => array(
				'<!-- wp:latest-comments /-->',
				'widget_block widget_recent_comments',
				'core/latest-comments',
			),
			'core/tag-cloud'       => array(
				'<!-- wp:tag-cloud /-->',
				'widget_block widget_tag_cloud',
				'core/tag-cloud',
			),
			'core/categories'      => array(
				'<!-- wp:categories /-->',
				'widget_block widget_categories',
				'core/categories',
			),
			'core/audio'           => array(
				'<!-- wp:audio /-->',
				'widget_block widget_media_audio',
				'core/audio',
			),
			'core/video'           => array(
				'<!-- wp:video /-->',
				'widget_block widget_media_video',
				'core/video',
			),
			'core/image'           => array(
				'<!-- wp:image /-->',
				'widget_block widget_media_image',
				'core/image',
			),
			'core/gallery'         => array(
				'<!-- wp:gallery /-->',
				'widget_block widget_media_gallery',
				'core/gallery',
			),
			'core/rss'             => array(
				'<!-- wp:rss /-->',
				'widget_block widget_rss',
				'core/rss',
			),
			'other block'          => array(
				'<!-- wp:heading --><h2>Heading</h2><!-- /wp:heading -->',
				'widget_block',
				'core/heading',
			),
			'empty content'        => array(
				'',
				'widget_block',
				null,
			),
		);
	}

	/**
	 * Tests that the widget_block_dynamic_classname filter can override the container classname.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::widget
	 * @covers WP_Widget_Block::get_dynamic_classname
	 */
	public function test_widget_applies_widget_block_dynamic_classname_filter() {
		add_filter(
			'widget_block_dynamic_classname',
			static function () {
				return 'widget_block custom_block_class';
			}
		);

		$output    = $this->render( array( 'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'SECTION' ) );
		$this->assertSame( 'widget widget_block custom_block_class', $processor->get_attribute( 'class' ) );
	}

	/**
	 * Tests that update() preserves unfiltered HTML when the user has the unfiltered_html capability.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::update
	 */
	public function test_update_with_unfiltered_html_cap() {
		$widget = new WP_Widget_Block();

		add_filter( 'map_meta_cap', array( $this, 'grant_unfiltered_html_cap' ), 10, 2 );
		$this->assertTrue( current_user_can( 'unfiltered_html' ) );

		$new_instance = array(
			'content' => '<!-- wp:html --><script>alert(1);</script><!-- /wp:html -->',
		);
		$old_instance = array(
			'content' => '<p>Old</p>',
			'custom'  => 'kept',
		);

		$result = $widget->update( $new_instance, $old_instance );

		$this->assertSame(
			array(
				'content' => '<!-- wp:html --><script>alert(1);</script><!-- /wp:html -->',
				'custom'  => 'kept',
			),
			$result
		);
	}

	/**
	 * Tests that update() sanitizes content with wp_kses_post() when the user lacks unfiltered_html.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::update
	 */
	public function test_update_without_unfiltered_html_cap() {
		$widget = new WP_Widget_Block();

		add_filter( 'map_meta_cap', array( $this, 'revoke_unfiltered_html_cap' ), 10, 2 );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );

		$new_instance = array(
			'content' => '<!-- wp:paragraph --><p>Safe</p><script>alert(1);</script><!-- /wp:paragraph -->',
		);

		$result = $widget->update( $new_instance, array() );

		$this->assertSame(
			array(
				'content' => wp_kses_post( $new_instance['content'] ),
			),
			$result
		);
	}

	/**
	 * Grants the unfiltered_html capability via map_meta_cap.
	 *
	 * @param string[] $caps Capabilities.
	 * @param string   $cap  Capability name.
	 * @return string[] Filtered capabilities.
	 */
	public function grant_unfiltered_html_cap( $caps, $cap ) {
		if ( 'unfiltered_html' === $cap ) {
			$caps = array( 'exist' );
		}
		return $caps;
	}

	/**
	 * Revokes the unfiltered_html capability via map_meta_cap.
	 *
	 * @param string[] $caps Capabilities.
	 * @param string   $cap  Capability name.
	 * @return string[] Filtered capabilities.
	 */
	public function revoke_unfiltered_html_cap( $caps, $cap ) {
		if ( 'unfiltered_html' === $cap ) {
			$caps = array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Tests that form() outputs the content textarea with saved and default values.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::form
	 */
	public function test_form_displays_textarea() {
		$content   = '<!-- wp:paragraph --><p>Saved & "quoted"</p><!-- /wp:paragraph -->';
		$output    = $this->render_form( array( 'content' => $content ) );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The form should contain a label.' );
		$this->assertSame( 'widget-block-2-content', $processor->get_attribute( 'for' ) );

		$this->assertTrue( $processor->next_tag( 'TEXTAREA' ), 'The form should contain a content textarea.' );
		$this->assertSame( 'widget-block-2-content', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'widget-block[2][content]', $processor->get_attribute( 'name' ) );
		$this->assertStringContainsString( esc_textarea( $content ), $output );

		$empty_output = $this->render_form( array() );
		$this->assertStringContainsString( '></textarea>', $empty_output, 'The textarea should be empty by default.' );
	}

	/**
	 * Tests that set_is_wide_widget_in_customizer() returns false only for block widgets.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Block::set_is_wide_widget_in_customizer
	 *
	 * @dataProvider data_set_is_wide_widget_in_customizer
	 *
	 * @param bool   $is_wide   Initial is_wide value.
	 * @param string $widget_id Widget ID.
	 * @param bool   $expected  Expected return value.
	 */
	public function test_set_is_wide_widget_in_customizer( $is_wide, $widget_id, $expected ) {
		$widget = new WP_Widget_Block();

		$this->assertSame( $expected, $widget->set_is_wide_widget_in_customizer( $is_wide, $widget_id ) );
	}

	/**
	 * Data provider for test_set_is_wide_widget_in_customizer.
	 *
	 * @return array[]
	 */
	public static function data_set_is_wide_widget_in_customizer() {
		return array(
			'block widget when wide is true'      => array( true, 'block-2', false ),
			'non-block widget when wide is true'  => array( true, 'text-2', true ),
			'non-block widget when wide is false' => array( false, 'custom_html-3', false ),
		);
	}
}
