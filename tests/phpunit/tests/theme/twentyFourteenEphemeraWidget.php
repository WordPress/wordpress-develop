<?php

/**
 * @group themes
 * @group widgets
 *
 * @covers Twenty_Fourteen_Ephemera_Widget::widget
 */
class Tests_Theme_TwentyFourteenEphemeraWidget extends WP_UnitTestCase {

	/**
	 * The names of the globals these tests overwrite.
	 *
	 * @var string[]
	 */
	private array $global_names = array( 'more', 'content_width' );

	/**
	 * The values of those globals before the current test ran, keyed by name.
	 *
	 * A name is absent when the global was not set.
	 *
	 * @var array<string, mixed>
	 */
	private array $original_globals = array();

	public function set_up() {
		parent::set_up();

		/*
		 * `backupGlobals` is disabled for the suite, so these have to be restored by hand
		 * to keep later tests in the process from inheriting them. Capture them before the
		 * skip below, which still runs `tear_down()`.
		 */
		foreach ( $this->global_names as $global_name ) {
			if ( array_key_exists( $global_name, $GLOBALS ) ) {
				$this->original_globals[ $global_name ] = $GLOBALS[ $global_name ];
			}
		}

		$widgets = WP_CONTENT_DIR . '/themes/twentyfourteen/inc/widgets.php';
		if ( ! file_exists( $widgets ) ) {
			$this->markTestSkipped( 'The Twenty Fourteen theme is not installed.' );
		}
		require_once $widgets;
	}

	public function tear_down() {
		foreach ( $this->global_names as $global_name ) {
			if ( array_key_exists( $global_name, $this->original_globals ) ) {
				$GLOBALS[ $global_name ] = $this->original_globals[ $global_name ];
			} else {
				unset( $GLOBALS[ $global_name ] );
			}
		}

		parent::tear_down();
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

	/**
	 * The `$more` global is only defined once a loop has run, so the widget must not
	 * assume it exists. It can render before any loop on a 404, an empty search, or an
	 * empty archive, as well as outside the template entirely.
	 */
	public function test_widget_when_more_global_is_undefined() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'I want <!--more--> ice cream!',
			)
		);
		$this->assertIsInt( $post_id );
		set_post_format( $post_id, 'aside' );

		unset( $GLOBALS['more'] );
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
		$this->assertNull( $GLOBALS['more'], 'The $more global was not restored to a falsey value.' ); // @phpstan-ignore offsetAccess.notFound (The global variable is set by Twenty_Fourteen_Ephemera_Widget::widget().)
	}
}
