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
 */
class Tests_Widgets_wpWidgetBlock extends WP_UnitTestCase {

	/**
	 * Example shortcode content to test for wpautop corruption.
	 *
	 * @var string
	 */
	protected $example_shortcode_content = '<div class="shortcode-output">Example shortcode output.</div>';

	/**
	 * Number of times the shortcode was rendered.
	 *
	 * @var int
	 */
	protected $shortcode_render_count = 0;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		add_shortcode( 'example', array( $this, 'do_example_shortcode' ) );
	}

	/**
	 * Tears down the test fixture.
	 */
	public function tear_down() {
		remove_shortcode( 'example' );
		parent::tear_down();
	}

	/**
	 * Do example shortcode.
	 *
	 * @return string Shortcode content.
	 */
	public function do_example_shortcode() {
		++$this->shortcode_render_count;
		return $this->example_shortcode_content;
	}

	/**
	 * Tests that the `<p>` wrapper added during server-side rendering of the shortcode
	 * block is removed before do_shortcode() processes the shortcode.
	 *
	 * @ticket 62694
	 *
	 * @covers WP_Widget_Block::widget
	 */
	public function test_widget_shortcode_unautop() {
		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '',
			'after_widget'  => '',
		);

		$widget   = new WP_Widget_Block();
		$instance = array(
			'content' => "<!-- wp:shortcode -->\n[example]\n<!-- /wp:shortcode -->",
		);

		$this->shortcode_render_count = 0;
		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();

		$this->assertSame( 1, $this->shortcode_render_count, 'Shortcode was rendered once.' );
		$this->assertStringContainsString(
			$this->example_shortcode_content,
			$output,
			'Shortcode was applied without wpautop corrupting it.'
		);
		$this->assertStringNotContainsString(
			'<p>' . $this->example_shortcode_content . '</p>',
			$output,
			'Expected shortcode_unautop() to have run.'
		);
	}
}
