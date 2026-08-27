<?php

/**
 * @group themes
 * @group widgets
 *
 * @covers Twenty_Fourteen_Ephemera_Widget::widget
 */
class Tests_Theme_TwentyFourteenEphemeraWidget extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		$widgets = WP_CONTENT_DIR . '/themes/twentyfourteen/inc/widgets.php';
		if ( ! file_exists( $widgets ) ) {
			$this->markTestSkipped( 'The Twenty Fourteen theme is not installed.' );
		}
		require_once $widgets;
	}

	/**
	 * The widget must restore the original `$more` global, which `WP_Query::setup_postdata()`
	 * zeroes out for each post the widget's secondary loop renders.
	 */
	public function test_widget_restores_more_global() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'I want <!--more--> ice cream!',
			)
		);
		$this->assertIsInt( $post_id );
		set_post_format( $post_id, 'aside' );

		// Sentinel value for the restore assertion below; it does not affect what the widget renders.
		$GLOBALS['more']          = 1;
		$GLOBALS['content_width'] = 474;

		$widget = new Twenty_Fourteen_Ephemera_Widget();

		$output = get_echo(
			array( $widget, 'widget' ),
			array(
				array(
					'before_widget' => '',
					'after_widget'  => '',
				),
				array( 'format' => 'aside' ),
			)
		);

		$this->assertNotEmpty( $output, 'The widget content.' );
		$processor       = new WP_HTML_Tag_Processor( $output );
		$more_link_count = 0;
		while ( $processor->next_tag( array( 'class_name' => 'more-link' ) ) ) {
			++$more_link_count;
		}
		$this->assertSame( 1, $more_link_count, 'Expected there to be one more link.' );
		$this->assertSame( 1, $GLOBALS['more'], 'The $more global was not restored to its original value.' ); // @phpstan-ignore method.alreadyNarrowedType (The global variable is modified by Twenty_Fourteen_Ephemera_Widget::widget().)
		$this->assertSame( 474, $GLOBALS['content_width'], 'The $content_width global was not restored to its original value.' );
	}
}
