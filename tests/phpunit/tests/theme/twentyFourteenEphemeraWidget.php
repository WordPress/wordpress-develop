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
	 * The widget must restore the original `$more` global, not the zeroed value
	 * it sets for each post it renders.
	 */
	public function test_widget_restores_more_global() {
		// More than one post, so a stale `$more` from a previous iteration would be restored.
		foreach ( self::factory()->post->create_many( 2 ) as $post_id ) {
			$this->assertIsInt( $post_id );
			set_post_format( $post_id, 'aside' );
		}

		$GLOBALS['more']          = 1;
		$GLOBALS['content_width'] = 474;

		$widget = new Twenty_Fourteen_Ephemera_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
			),
			array( 'format' => 'aside' )
		);
		$output = ob_get_clean();

		$this->assertNotEmpty( $output, 'The widget rendered nothing, so nothing was restored.' );
		$this->assertSame( 1, $GLOBALS['more'], 'The $more global was not restored to its original value.' ); // @phpstan-ignore method.alreadyNarrowedType (The global variable is modified by Twenty_Fourteen_Ephemera_Widget::widget().)
		$this->assertSame( 474, $GLOBALS['content_width'], 'The $content_width global was not restored to its original value.' );
	}
}
