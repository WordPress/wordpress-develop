<?php
/**
 * Unit tests covering WP_Widget_Links functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-links.php
 *
 * @group widgets
 * @group bookmark
 *
 * @covers WP_Widget_Links
 */
class Tests_Widgets_wpWidgetLinks extends WP_UnitTestCase {

	/**
	 * Default widget arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section id="links-2" class="widget widget_links">',
		'after_widget'  => "</section>\n",
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => "</h2>\n",
	);

	/**
	 * ID of the first link category.
	 *
	 * @var int
	 */
	protected static $category_one_id;

	/**
	 * ID of the second link category.
	 *
	 * @var int
	 */
	protected static $category_two_id;

	/**
	 * IDs of the shared bookmarks.
	 *
	 * @var int[]
	 */
	protected static $bookmark_ids = array();

	/**
	 * Creates link categories and bookmarks shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_one_id = $factory->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Blogroll',
			)
		);

		self::$category_two_id = $factory->term->create(
			array(
				'taxonomy' => 'link_category',
				'name'     => 'Resources',
			)
		);

		self::$bookmark_ids[] = $factory->bookmark->create(
			array(
				'link_name'        => 'WordPress.org',
				'link_url'         => 'https://wordpress.org/',
				'link_description' => 'Community site',
				'link_image'       => 'https://wordpress.org/logo.png',
				'link_rating'      => 8,
				'link_category'    => array( self::$category_one_id ),
			)
		);

		self::$bookmark_ids[] = $factory->bookmark->create(
			array(
				'link_name'        => 'Documentation',
				'link_url'         => 'https://developer.wordpress.org/',
				'link_description' => 'Developer docs',
				'link_rating'      => 5,
				'link_category'    => array( self::$category_two_id ),
			)
		);
	}

	/**
	 * Deletes shared bookmarks after the test class finishes.
	 */
	public static function wpTearDownAfterClass() {
		foreach ( self::$bookmark_ids as $bookmark_id ) {
			wp_delete_link( $bookmark_id );
		}
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $instance Widget instance.
	 * @return string Widget output.
	 */
	private function render( $instance ) {
		$widget = new WP_Widget_Links();
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
		$widget = new WP_Widget_Links();
		$widget->_set( 2 );

		return get_echo( array( $widget, 'form' ), array( $instance ) );
	}

	/**
	 * Tests the constructor settings and options.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::__construct
	 */
	public function test_construct() {
		$widget = new WP_Widget_Links();

		$this->assertSame( 'links', $widget->id_base );
		$this->assertSame( 'Links', $widget->name );
		$this->assertSame( 'widget_links', $widget->widget_options['classname'] );
		$this->assertSame( 'Your blogroll', $widget->widget_options['description'] );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
	}

	/**
	 * Tests that widget() outputs the bookmark categories and links using the widget wrapper.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::widget
	 */
	public function test_widget_outputs_bookmarks() {
		$output = $this->render( array( 'name' => 1 ) );

		$this->assertStringContainsString(
			'<section id="linkcat-' . self::$category_one_id . '" class="widget widget_links">',
			$output,
			'The widget id attribute should be replaced with the link category ID.'
		);
		$this->assertStringContainsString( "<h2 class=\"widget-title\">Blogroll</h2>\n", $output );
		$this->assertStringContainsString( 'href="https://wordpress.org/"', $output );
		$this->assertStringContainsString( 'WordPress.org', $output );
		$this->assertStringContainsString( 'href="https://developer.wordpress.org/"', $output );
		$this->assertStringEndsWith( "</section>\n\n", $output );
	}

	/**
	 * Tests that widget() filters output by category when a category is specified.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::widget
	 */
	public function test_widget_filters_by_category() {
		$output = $this->render(
			array(
				'category' => self::$category_one_id,
				'name'     => 1,
			)
		);

		$this->assertStringContainsString( 'Blogroll', $output );
		$this->assertStringContainsString( 'https://wordpress.org/', $output );
		$this->assertStringNotContainsString( 'Resources', $output );
		$this->assertStringNotContainsString( 'https://developer.wordpress.org/', $output );
	}

	/**
	 * Tests the arguments passed to the widget_links_args filter.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::widget
	 *
	 * @dataProvider data_widget_links_args
	 *
	 * @param array $instance      Widget instance settings.
	 * @param array $expected_args Expected arguments passed to wp_list_bookmarks().
	 */
	public function test_widget_applies_widget_links_args_filter( $instance, $expected_args ) {
		$filter = new MockAction();
		add_filter( 'widget_links_args', array( $filter, 'filter' ), 10, 2 );

		$this->render( $instance );

		$this->assertSame(
			array(
				array(
					array_merge(
						array(
							'title_before'    => '<h2 class="widget-title">',
							'title_after'     => "</h2>\n",
							'category_before' => '<section id="%id" class="widget widget_links">',
							'category_after'  => "</section>\n",
						),
						$expected_args
					),
					$instance,
				),
			),
			$filter->get_args()
		);
	}

	/**
	 * Data provider for test_widget_applies_widget_links_args_filter.
	 *
	 * @return array[]
	 */
	public static function data_widget_links_args() {
		return array(
			'default instance'       => array(
				array(),
				array(
					'show_images'      => true,
					'show_description' => false,
					'show_name'        => false,
					'show_rating'      => false,
					'category'         => false,
					'class'            => 'linkcat widget',
					'orderby'          => 'name',
					'order'            => 'ASC',
					'limit'            => -1,
				),
			),
			'rating order uses DESC' => array(
				array(
					'images'      => 0,
					'name'        => 1,
					'description' => 1,
					'rating'      => 1,
					'category'    => 12,
					'orderby'     => 'rating',
					'limit'       => 5,
				),
				array(
					'show_images'      => 0,
					'show_description' => 1,
					'show_name'        => 1,
					'show_rating'      => 1,
					'category'         => 12,
					'class'            => 'linkcat widget',
					'orderby'          => 'rating',
					'order'            => 'DESC',
					'limit'            => 5,
				),
			),
		);
	}

	/**
	 * Tests that update() sanitizes and casts settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::update
	 *
	 * @dataProvider data_update
	 *
	 * @param array $new_instance New settings input.
	 * @param array $expected     Expected updated settings.
	 */
	public function test_update( $new_instance, $expected ) {
		$widget = new WP_Widget_Links();

		$old_instance = array(
			'images'      => 1,
			'name'        => 1,
			'description' => 1,
			'rating'      => 1,
			'category'    => 3,
			'orderby'     => 'rating',
			'limit'       => 10,
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
			'all checkboxes set and valid orderby' => array(
				array(
					'images'      => '1',
					'name'        => '1',
					'description' => '1',
					'rating'      => '1',
					'orderby'     => 'rating',
					'category'    => '7',
					'limit'       => '5',
				),
				array(
					'images'      => 1,
					'name'        => 1,
					'description' => 1,
					'rating'      => 1,
					'orderby'     => 'rating',
					'category'    => 7,
					'limit'       => 5,
				),
			),
			'unchecked checkboxes and empty limit' => array(
				array(
					'orderby'  => 'id',
					'category' => '',
					'limit'    => '',
				),
				array(
					'images'      => 0,
					'name'        => 0,
					'description' => 0,
					'rating'      => 0,
					'orderby'     => 'id',
					'category'    => 0,
					'limit'       => -1,
				),
			),
			'rand orderby and zero limit'          => array(
				array(
					'orderby'  => 'rand',
					'category' => '0',
					'limit'    => '0',
				),
				array(
					'images'      => 0,
					'name'        => 0,
					'description' => 0,
					'rating'      => 0,
					'orderby'     => 'rand',
					'category'    => 0,
					'limit'       => -1,
				),
			),
			'invalid orderby falls back to name'   => array(
				array(
					'orderby'  => 'invalid_column',
					'category' => '4',
					'limit'    => '3',
				),
				array(
					'images'      => 0,
					'name'        => 0,
					'description' => 0,
					'rating'      => 0,
					'orderby'     => 'name',
					'category'    => 4,
					'limit'       => 3,
				),
			),
		);
	}

	/**
	 * Tests that form() displays the fields with saved settings.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::form
	 */
	public function test_form_displays_fields_with_saved_settings() {
		$output    = $this->render_form(
			array(
				'images'      => 0,
				'name'        => 1,
				'description' => 1,
				'rating'      => 0,
				'category'    => self::$category_two_id,
				'orderby'     => 'rating',
				'limit'       => 5,
			)
		);
		$processor = new WP_HTML_Tag_Processor( $output );

		$selects    = array();
		$selected   = array();
		$checkboxes = array();
		$text_input = array();

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			if ( 'SELECT' === $tag ) {
				$selects[ $processor->get_attribute( 'id' ) ] = $processor->get_attribute( 'name' );
			} elseif ( 'OPTION' === $tag && null !== $processor->get_attribute( 'selected' ) ) {
				$selected[] = $processor->get_attribute( 'value' );
			} elseif ( 'INPUT' === $tag && 'checkbox' === $processor->get_attribute( 'type' ) ) {
				$checkboxes[ $processor->get_attribute( 'id' ) ] = null !== $processor->get_attribute( 'checked' );
			} elseif ( 'INPUT' === $tag && 'text' === $processor->get_attribute( 'type' ) ) {
				$text_input[ $processor->get_attribute( 'id' ) ] = $processor->get_attribute( 'value' );
			}
		}

		$this->assertSame(
			array(
				'widget-links-2-category' => 'widget-links[2][category]',
				'widget-links-2-orderby'  => 'widget-links[2][orderby]',
			),
			$selects
		);
		$this->assertSame(
			array( (string) self::$category_two_id, 'rating' ),
			$selected,
			'The saved category and orderby options should be selected.'
		);
		$this->assertSame(
			array(
				'widget-links-2-images'      => false,
				'widget-links-2-name'        => true,
				'widget-links-2-description' => true,
				'widget-links-2-rating'      => false,
			),
			$checkboxes
		);
		$this->assertSame(
			array( 'widget-links-2-limit' => '5' ),
			$text_input
		);
	}

	/**
	 * Tests that form() displays the default values when given an empty instance.
	 *
	 * @ticket 65819
	 *
	 * @covers WP_Widget_Links::form
	 */
	public function test_form_with_empty_instance() {
		$output    = $this->render_form( array() );
		$processor = new WP_HTML_Tag_Processor( $output );

		$selected   = array();
		$checkboxes = array();
		$limit_val  = null;

		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			if ( 'OPTION' === $tag && null !== $processor->get_attribute( 'selected' ) ) {
				$selected[] = $processor->get_attribute( 'value' );
			} elseif ( 'INPUT' === $tag && 'checkbox' === $processor->get_attribute( 'type' ) ) {
				$checkboxes[ $processor->get_attribute( 'id' ) ] = null !== $processor->get_attribute( 'checked' );
			} elseif ( 'INPUT' === $tag && 'widget-links-2-limit' === $processor->get_attribute( 'id' ) ) {
				$limit_val = $processor->get_attribute( 'value' );
			}
		}

		$this->assertSame( array( 'name' ), $selected, 'Only the default "name" orderby option should be selected.' );
		$this->assertSame(
			array(
				'widget-links-2-images'      => true,
				'widget-links-2-name'        => true,
				'widget-links-2-description' => false,
				'widget-links-2-rating'      => false,
			),
			$checkboxes,
			'Images and name checkboxes should be checked by default.'
		);
		$this->assertSame( '', $limit_val, 'The limit input should be empty when limit is -1.' );
	}
}
