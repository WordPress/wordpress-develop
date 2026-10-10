<?php

/**
 * Tests for the WP_Widget_Meta class.
 *
 * @group widgets
 *
 * @coversDefaultClass WP_Widget_Meta
 */
class Tests_Widgets_wpWidgetMeta extends WP_UnitTestCase {

	/**
	 * Sidebar arguments passed to the widget.
	 *
	 * @var array
	 */
	const SIDEBAR_ARGS = array(
		'before_widget' => '<section id="meta-2" class="widget widget_meta">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	);

	/**
	 * Returns the output of the widget.
	 *
	 * @param array $instance Widget instance settings.
	 * @return string Widget output.
	 */
	private function get_widget_output( array $instance = array() ) {
		$widget = new WP_Widget_Meta();

		return get_echo( array( $widget, 'widget' ), array( self::SIDEBAR_ARGS, $instance ) );
	}

	/**
	 * Forces the navigation widgets markup format.
	 *
	 * @param string $format Either 'html5' or 'xhtml'.
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
	 * Tests the widget registration settings.
	 *
	 * @ticket 65819
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_sets_widget_options() {
		$widget = new WP_Widget_Meta();

		$this->assertSame( 'meta', $widget->id_base, 'The ID base did not match.' );
		$this->assertSame( 'Meta', $widget->name, 'The name did not match.' );
		$this->assertSame( 'widget_meta', $widget->widget_options['classname'], 'The class name did not match.' );
		$this->assertSame( 'Login, RSS, &amp; WordPress.org links.', $widget->widget_options['description'], 'The description did not match.' );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'], 'Selective refresh should be supported.' );
		$this->assertTrue( $widget->widget_options['show_instance_in_rest'], 'The instance should be shown in the REST API.' );
	}

	/**
	 * Tests the title displayed by the widget.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 *
	 * @dataProvider data_widget_titles
	 *
	 * @param array  $instance Widget instance settings.
	 * @param string $expected Expected title.
	 */
	public function test_widget_displays_title( $instance, $expected ) {
		$this->set_navigation_widgets_format( 'xhtml' );

		$output = $this->get_widget_output( $instance );

		$this->assertStringStartsWith(
			'<section id="meta-2" class="widget widget_meta"><h2 class="widget-title">' . $expected . '</h2>',
			$output,
			'The widget should start with its wrapper and title.'
		);
		$this->assertStringEndsWith( '</section>', $output, 'The widget should end with its wrapper.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widget_titles() {
		return array(
			'custom title' => array( array( 'title' => 'Site links' ), 'Site links' ),
			'no title'     => array( array(), 'Meta' ),
			'empty title'  => array( array( 'title' => '' ), 'Meta' ),
			'zero title'   => array( array( 'title' => '0' ), 'Meta' ),
			'null title'   => array( array( 'title' => null ), 'Meta' ),
		);
	}

	/**
	 * Tests that the title is filtered and omitted when the filter empties it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_applies_widget_title_filter() {
		$this->set_navigation_widgets_format( 'xhtml' );

		$instance = array( 'title' => 'Site links' );

		$filter = new MockAction();
		add_filter( 'widget_title', array( $filter, 'filter' ), 5, 3 );

		$this->assertStringContainsString( '<h2 class="widget-title">Site links</h2>', $this->get_widget_output( $instance ), 'The title should be displayed.' );
		$this->assertSame( array( array( 'Site links', $instance, 'meta' ) ), $filter->get_args(), 'The filter should receive the title, the instance and the ID base.' );

		add_filter( 'widget_title', '__return_empty_string', 20 );

		$output = $this->get_widget_output( $instance );

		$this->assertStringNotContainsString( 'widget-title', $output, 'The title wrapper should be omitted without a title.' );
		$this->assertStringStartsWith( '<section id="meta-2" class="widget widget_meta">', $output, 'The widget wrapper should still be displayed.' );
	}

	/**
	 * Tests the navigation wrapper for each markup format.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 *
	 * @dataProvider data_navigation_formats
	 *
	 * @param string      $format     Navigation widgets markup format.
	 * @param array       $instance   Widget instance settings.
	 * @param string|null $aria_label Expected label of the nav element, or null when there is no nav element.
	 */
	public function test_widget_navigation_wrapper( $format, $instance, $aria_label ) {
		$this->set_navigation_widgets_format( $format );

		$processor = new WP_HTML_Tag_Processor( $this->get_widget_output( $instance ) );
		$has_nav   = $processor->next_tag( 'NAV' );

		if ( null === $aria_label ) {
			$this->assertFalse( $has_nav, 'There should be no nav element.' );
		} else {
			$this->assertTrue( $has_nav, 'There should be a nav element.' );
			$this->assertSame( $aria_label, $processor->get_attribute( 'aria-label' ), 'The nav element label did not match.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_navigation_formats() {
		return array(
			'xhtml'                        => array( 'xhtml', array( 'title' => 'Site links' ), null ),
			'unknown format'               => array( 'html', array( 'title' => 'Site links' ), null ),
			'html5 with a custom title'    => array( 'html5', array( 'title' => 'Site links' ), 'Site links' ),
			'html5 with the default title' => array( 'html5', array(), 'Meta' ),
		);
	}

	/**
	 * Tests that markup in the filtered title is removed from the nav element label.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 *
	 * @dataProvider data_filtered_titles_with_markup
	 *
	 * @param string $filtered_title Title returned by the widget_title filter.
	 * @param string $aria_label     Expected label of the nav element.
	 */
	public function test_widget_navigation_label_strips_markup( $filtered_title, $aria_label ) {
		$this->set_navigation_widgets_format( 'html5' );

		add_filter(
			'widget_title',
			static function () use ( $filtered_title ) {
				return $filtered_title;
			},
			20
		);

		$processor = new WP_HTML_Tag_Processor( $this->get_widget_output( array( 'title' => 'Site links' ) ) );

		$this->assertTrue( $processor->next_tag( 'NAV' ), 'There should be a nav element.' );
		$this->assertSame( $aria_label, $processor->get_attribute( 'aria-label' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_filtered_titles_with_markup() {
		return array(
			'markup around the text' => array( ' <em>Site</em> <strong>links</strong> ', 'Site links' ),
			'title with a quote'     => array( 'Site "links"', 'Site "links"' ),
			'title with markup only' => array( '<span></span>', 'Meta' ),
			'title with only spaces' => array( '   ', 'Meta' ),
		);
	}

	/**
	 * Tests that the nav element is closed before the widget wrapper.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_closes_navigation_wrapper() {
		$this->set_navigation_widgets_format( 'html5' );

		$this->assertStringEndsWith( '</nav></section>', $this->get_widget_output() );
	}

	/**
	 * Tests the links displayed to a logged-out visitor.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_displays_links_for_logged_out_visitor() {
		update_option( 'users_can_register', 0 );

		$output = $this->get_widget_output();

		$this->assertStringContainsString( '<li><a href="http://' . WP_TESTS_DOMAIN . '/wp-login.php">Log in</a></li>', $output, 'The log in link should be displayed.' );
		$this->assertStringContainsString( '<li><a href="http://' . WP_TESTS_DOMAIN . '/?feed=rss2">Entries feed</a></li>', $output, 'The entries feed link should be displayed.' );
		$this->assertStringContainsString( '<li><a href="http://' . WP_TESTS_DOMAIN . '/?feed=comments-rss2">Comments feed</a></li>', $output, 'The comments feed link should be displayed.' );
		$this->assertStringContainsString( '<li><a href="https://wordpress.org/">WordPress.org</a></li>', $output, 'The WordPress.org link should be displayed.' );
		$this->assertStringNotContainsString( 'Site Admin', $output, 'The site admin link should not be displayed.' );
		$this->assertStringNotContainsString( 'Register', $output, 'The register link should not be displayed.' );
	}

	/**
	 * Tests the links displayed to a logged-in user.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_displays_links_for_logged_in_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->get_widget_output();

		$this->assertStringContainsString( '<li><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/">Site Admin</a></li>', $output, 'The site admin link should be displayed.' );
		$this->assertStringContainsString( '>Log out</a></li>', $output, 'The log out link should be displayed.' );
		$this->assertStringNotContainsString( '>Log in</a>', $output, 'The log in link should not be displayed.' );
	}

	/**
	 * Tests that the WordPress.org link is filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_applies_poweredby_filter() {
		$instance = array( 'title' => 'Site links' );

		$filter = new MockAction();
		add_filter( 'widget_meta_poweredby', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'widget_meta_poweredby',
			static function () {
				return '<li class="custom-powered-by">Custom link</li>';
			},
			20
		);

		$output = $this->get_widget_output( $instance );

		$this->assertStringContainsString( '<li class="custom-powered-by">Custom link</li>', $output, 'The filtered link should be displayed.' );
		$this->assertStringNotContainsString( 'WordPress.org', $output, 'The default link should not be displayed.' );
		$this->assertSame(
			array( array( '<li><a href="https://wordpress.org/">WordPress.org</a></li>', $instance ) ),
			$filter->get_args(),
			'The filter should receive the default link and the instance.'
		);
	}

	/**
	 * Tests that the wp_meta action fires once inside the list.
	 *
	 * @ticket 65819
	 *
	 * @covers ::widget
	 */
	public function test_widget_fires_wp_meta_action() {
		$action = new MockAction();
		add_action( 'wp_meta', array( $action, 'action' ) );
		add_action(
			'wp_meta',
			static function () {
				echo '<li class="custom-meta">Custom item</li>';
			}
		);

		$output = $this->get_widget_output();

		$this->assertSame( 1, $action->get_call_count(), 'The action should fire once.' );
		$this->assertMatchesRegularExpression( '#<li class="custom-meta">Custom item</li>\s*</ul>#', $output, 'Output from the action should be the last item of the list.' );
	}

	/**
	 * Tests that the title is sanitized when the widget is saved.
	 *
	 * @ticket 65819
	 *
	 * @covers ::update
	 *
	 * @dataProvider data_update_titles
	 *
	 * @param string $title    Submitted title.
	 * @param string $expected Expected saved title.
	 */
	public function test_update_sanitizes_title( $title, $expected ) {
		$widget = new WP_Widget_Meta();

		$this->assertSame(
			array( 'title' => $expected ),
			$widget->update( array( 'title' => $title ), array( 'title' => 'Old title' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_update_titles() {
		return array(
			'plain title'            => array( 'Site links', 'Site links' ),
			'surrounding whitespace' => array( "  Site links \n", 'Site links' ),
			'markup'                 => array( '<strong>Site</strong> links<script>alert(1)</script>', 'Site links' ),
			'line breaks'            => array( "Site\nlinks", 'Site links' ),
			'empty string'           => array( '', '' ),
			'zero'                   => array( '0', '0' ),
		);
	}

	/**
	 * Tests that update() keeps the other settings of the old instance and ignores other submitted settings.
	 *
	 * @ticket 65819
	 *
	 * @covers ::update
	 */
	public function test_update_only_changes_title() {
		$widget = new WP_Widget_Meta();

		$this->assertSame(
			array(
				'title' => 'New title',
				'kept'  => 'old value',
			),
			$widget->update(
				array(
					'title'   => 'New title',
					'kept'    => 'new value',
					'ignored' => 'new value',
				),
				array(
					'title' => 'Old title',
					'kept'  => 'old value',
				)
			)
		);
	}

	/**
	 * Tests the title field of the settings form.
	 *
	 * @ticket 65819
	 *
	 * @covers ::form
	 *
	 * @dataProvider data_form_titles
	 *
	 * @param array  $instance Widget instance settings.
	 * @param string $expected Expected value of the title field.
	 */
	public function test_form_displays_title_field( $instance, $expected ) {
		$widget = new WP_Widget_Meta();
		$widget->_set( 2 );

		$processor = new WP_HTML_Tag_Processor( get_echo( array( $widget, 'form' ), array( $instance ) ) );

		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The form should contain a label.' );
		$this->assertSame( 'widget-meta-2-title', $processor->get_attribute( 'for' ), 'The label should point to the title field.' );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'The form should contain an input.' );
		$this->assertSame( 'widget-meta-2-title', $processor->get_attribute( 'id' ), 'The field ID did not match.' );
		$this->assertSame( 'widget-meta[2][title]', $processor->get_attribute( 'name' ), 'The field name did not match.' );
		$this->assertSame( 'text', $processor->get_attribute( 'type' ), 'The field should be a text input.' );
		$this->assertSame( $expected, $processor->get_attribute( 'value' ), 'The field value did not match.' );
		$this->assertFalse( $processor->next_tag( 'INPUT' ), 'The form should contain a single input.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_form_titles() {
		return array(
			'saved title'       => array( array( 'title' => 'Site links' ), 'Site links' ),
			'title with quotes' => array( array( 'title' => 'Site "links" & <more>' ), 'Site "links" & <more>' ),
			'no title'          => array( array(), '' ),
			'empty title'       => array( array( 'title' => '' ), '' ),
			'zero title'        => array( array( 'title' => '0' ), '0' ),
		);
	}
}
