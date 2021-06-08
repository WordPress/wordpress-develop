<?php
/**
 * Unit tests covering WP_Widget_RSS functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-rss.php
 *
 * @group widgets
 */
class Test_WP_Widget_RSS extends WP_UnitTestCase {

	/**
	 * @ticket 53278
	 * @covers WP_Widget_RSS::widget
	 * @dataProvider data_url_unhappy_path
	 * 
	 * @param mixed $url When null, unsets 'url' arg, else, sets to given value.
	 */
	public function test_url_unhappy_path( $url ) {
		$widget  = new WP_Widget_RSS();

		$args     = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section id="widget_rss-5" class="widget widget_rss">',
			'after_widget'  => "</section>\n",
		);
		$instance = array( 
			'title' => 'Foo',
			'url'   => $url,
		);

		if ( is_null( $url ) ) {
			unset( $instance['ur'] );
		}

		$this->expectOutputString( '' ); 

		$widget->widget( $args, $instance );
	}

	public function data_url_unhappy_path() {
		return array(
			'when unset' => array( 
				'url' => null,
			),
			'when empty string' => array( 
				'url' => '',
			),
			'when boolean false' => array( 
				'url' => false,
			),
		);
	}

	/**
	 * @ticket 53278
	 * @covers WP_Widget_RSS::widget
	 * @dataProvider data_url_happy_path
	 * 
	 * @param mixed  $url      URL argument.
	 * @param string $expected Expected output.
	 */
	public function test_url_happy_path( $url, $expected ) {
		$widget  = new WP_Widget_RSS();

		$args     = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section id="widget_rss-5" class="widget widget_rss">',
			'after_widget'  => "</section>\n",
		);
		$instance = array( 
			'title' => 'Foo',
			'url'   => $url,
		);

		if ( is_null( $url ) ) {
			unset( $instance['ur'] );
		}

		ob_start();
		$widget->widget( $args, $instance );
		$actual = ob_get_clean();

		$this->assertContains( $expected,  $actual );
	}

	public function data_url_happy_path() {
		return array(
			'when url is given' => array( 
				'url' => 'http://wordpress.org/news/feed/',
				'<section id="widget_rss-5" class="widget widget_rss"><h2><a class="rsswidget" href="http://wordpress.org/news/feed/">',
			),
		);
	}	

}
