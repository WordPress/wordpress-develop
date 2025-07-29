<?php
/**
 * Unit tests covering widget title handling for zero values.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test widget title handling for zero values across core widgets.
 *
 * @group widgets
 */
class Tests_Widgets_wpWidgetCoreZeroValues extends WP_UnitTestCase {

	/**
	 * Clean up global scope.
	 */
	public function clean_up_global_scope() {
		parent::clean_up_global_scope();
	}

	/**
	 * Test WP_Widget_Archives handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Archives::widget
	 */
	public function test_archives_widget_title_zero_value() {
		$widget = new WP_Widget_Archives();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		// Test with title set to "0"
		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		// Should contain the title "0"
		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Archives</h2>', $output );
	}

	/**
	 * Test WP_Widget_Calendar handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Calendar::widget
	 */
	public function test_calendar_widget_title_zero_value() {
		$widget = new WP_Widget_Calendar();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
	}

	/**
	 * Test WP_Widget_Categories handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Categories::widget
	 */
	public function test_categories_widget_title_zero_value() {
		$category_id = self::factory()->category->create( array( 'name' => 'Test Category' ) );
		$post_id     = self::factory()->post->create( array( 'post_category' => array( $category_id ) ) );

		$widget = new WP_Widget_Categories();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Categories</h2>', $output );
	}



	/**
	 * Test WP_Nav_Menu_Widget handles title value of "0" correctly.
	 *
	 * @covers WP_Nav_Menu_Widget::widget
	 */
	public function test_nav_menu_widget_title_zero_value() {
		$menu_id = wp_create_nav_menu( 'Test Menu' );
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			)
		);

		$widget = new WP_Nav_Menu_Widget();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title'    => '0',
			'nav_menu' => $menu_id,
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Menu</h2>', $output );

		wp_delete_nav_menu( $menu_id );
	}



	/**
	 * Test WP_Widget_Pages handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Pages::widget
	 */
	public function test_pages_widget_title_zero_value() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Page',
				'post_status' => 'publish',
			)
		);

		$widget = new WP_Widget_Pages();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Pages</h2>', $output );
	}

	/**
	 * Test WP_Widget_Recent_Comments handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Recent_Comments::widget
	 */
	public function test_recent_comments_widget_title_zero_value() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Test comment',
				'comment_approved' => 1,
			)
		);

		$widget = new WP_Widget_Recent_Comments();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Recent Comments</h2>', $output );
	}

	/**
	 * Test WP_Widget_Recent_Posts handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Recent_Posts::widget
	 */
	public function test_recent_posts_widget_title_zero_value() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		$widget = new WP_Widget_Recent_Posts();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Recent Posts</h2>', $output );
	}



	/**
	 * Test WP_Widget_Search handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Search::widget
	 */
	public function test_search_widget_title_zero_value() {
		$widget = new WP_Widget_Search();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
	}

	/**
	 * Test WP_Widget_Meta handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Meta::widget
	 */
	public function test_meta_widget_title_zero_value() {
		$widget = new WP_Widget_Meta();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Meta</h2>', $output );
	}



	/**
	 * Test WP_Widget_Text handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Text::widget
	 */
	public function test_text_widget_title_zero_value() {
		$widget = new WP_Widget_Text();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
			'text'  => 'Sample text content',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringContainsString( 'Sample text content', $output );
	}

	/**
	 * Test WP_Widget_RSS update method handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_RSS::update
	 */
	public function test_rss_widget_update_title_zero_value() {
		$widget = new WP_Widget_RSS();

		$new_instance = array(
			'title' => '0',
			'url'   => 'https://example.com/feed.xml',
			'items' => 5,
		);

		$old_instance = array();

		$result = $widget->update( $new_instance, $old_instance );

		$this->assertSame( '0', $result['title'] );
	}

	/**
	 * Test WP_Widget_Tag_Cloud handles title value of "0" correctly.
	 *
	 * @covers WP_Widget_Tag_Cloud::widget
	 */
	public function test_tag_cloud_widget_title_zero_value() {
		$tag1 = self::factory()->tag->create( array( 'name' => 'Test Tag 1' ) );
		$tag2 = self::factory()->tag->create( array( 'name' => 'Test Tag 2' ) );

		$post1 = self::factory()->post->create( array( 'tags_input' => array( 'Test Tag 1' ) ) );
		$post2 = self::factory()->post->create( array( 'tags_input' => array( 'Test Tag 2' ) ) );

		$widget = new WP_Widget_Tag_Cloud();

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
		);

		$instance = array(
			'title' => '0',
		);

		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>0</h2>', $output );
		$this->assertStringNotContainsString( '<h2>Tags</h2>', $output );
	}
}
