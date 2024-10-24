<?php
/**
 * Unit tests covering WP_Widget_Media_Video functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-media-video.php
 *
 * @group widgets
 */
class Tests_Widgets_wpWidgetMediaVideo extends WP_UnitTestCase {

	/**
	 * Clean up global scope.
	 *
	 * @global WP_Scripts $wp_scripts
	 * @global WP_Styles $wp_styles
	 */
	public function clean_up_global_scope() {
		global $wp_scripts, $wp_styles;
		parent::clean_up_global_scope();
		$wp_scripts = null;
		$wp_styles  = null;
	}

	/**
	 * Test get_instance_schema method.
	 *
	 * @covers WP_Widget_Media_Video::get_instance_schema
	 */
	public function test_get_instance_schema() {
		$widget = new WP_Widget_Media_Video();
		$schema = $widget->get_instance_schema();

		$this->assertSameSets(
			array_merge(
				array(
					'attachment_id',
					'preload',
					'loop',
					'title',
					'url',
					'content',
				),
				wp_get_video_extensions()
			),
			array_keys( $schema )
		);
	}

	/**
	 * Test schema filtering.
	 *
	 * @covers WP_Widget_Media_Video::get_instance_schema
	 *
	 * @ticket 45029
	 */
	public function test_get_instance_schema_filtering() {
		$widget = new WP_Widget_Media_Video();
		$schema = $widget->get_instance_schema();

		add_filter( 'widget_media_video_instance_schema', array( $this, 'filter_instance_schema' ), 10, 2 );
		$schema = $widget->get_instance_schema();

		$this->assertTrue( $schema['loop']['default'] );
	}

	/**
	 * Filters instance schema.
	 *
	 * @since 5.2.0
	 *
	 * @param array                 $schema Schema.
	 * @param WP_Widget_Media_Video $widget Widget.
	 * @return array
	 */
	public function filter_instance_schema( $schema, $widget ) {
		// Override the default loop value (false).
		$schema['loop']['default'] = true;
		return $schema;
	}

	/**
	 * Test constructor.
	 *
	 * @covers WP_Widget_Media_Video::__construct
	 */
	public function test_constructor() {
		$widget = new WP_Widget_Media_Video();

		$this->assertArrayHasKey( 'mime_type', $widget->widget_options );
		$this->assertArrayHasKey( 'customize_selective_refresh', $widget->widget_options );
		$this->assertArrayHasKey( 'description', $widget->widget_options );
		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		$this->assertSame( 'video', $widget->widget_options['mime_type'] );
		$this->assertSameSets(
			array(
				'add_to_widget',
				'replace_media',
				'unsupported_file_type',
				'edit_media',
				'media_library_state_multi',
				'media_library_state_single',
				'missing_attachment',
				'no_media_selected',
				'add_media',
			),
			array_keys( $widget->l10n )
		);
	}

	/**
	 * Test get_instance_schema method.
	 *
	 * @covers WP_Widget_Media_Video::update
	 */
	public function test_update() {
		$widget   = new WP_Widget_Media_Video();
		$instance = array();

		// Should return valid attachment ID.
		$expected = array(
			'attachment_id' => 1,
		);
		$result   = $widget->update( $expected, $instance );
		$this->assertSame( $expected, $result );

		// Should filter invalid attachment ID.
		$result = $widget->update(
			array(
				'attachment_id' => 'media',
			),
			$instance
		);
		$this->assertSame( $result, $instance );

		// Should return valid attachment url.
		$expected = array(
			'url' => 'https://chickenandribs.org',
		);
		$result   = $widget->update( $expected, $instance );
		$this->assertSame( $expected, $result );

		// Should filter invalid attachment url.
		$result = $widget->update(
			array(
				'url' => 'not_a_url',
			),
			$instance
		);
		$this->assertNotSame( $result, $instance );
		$this->assertStringStartsWith( 'http://', $result['url'] );

		// Should return loop setting.
		$expected = array(
			'loop' => true,
		);
		$result   = $widget->update( $expected, $instance );
		$this->assertSame( $expected, $result );

		// Should filter invalid loop setting.
		$result = $widget->update(
			array(
				'loop' => 'not-boolean',
			),
			$instance
		);
		$this->assertSame( $result, $instance );

		// Should return valid attachment title.
		$expected = array(
			'title' => 'A video of goats',
		);
		$result   = $widget->update( $expected, $instance );
		$this->assertSame( $expected, $result );

		// Should filter invalid attachment title.
		$result = $widget->update(
			array(
				'title' => '<h1>Cute Baby Goats</h1>',
			),
			$instance
		);
		$this->assertNotSame( $result, $instance );

		// Should return valid preload setting.
		$expected = array(
			'preload' => 'none',
		);
		$result   = $widget->update( $expected, $instance );
		$this->assertSame( $expected, $result );

		// Should filter invalid preload setting.
		$result = $widget->update(
			array(
				'preload' => 'nope',
			),
			$instance
		);
		$this->assertSame( $result, $instance );

		// Should filter invalid key.
		$result = $widget->update(
			array(
				'h4x' => 'value',
			),
			$instance
		);
		$this->assertSame( $result, $instance );
	}

	/**
	 * Test render_media method.
	 *
	 * @covers WP_Widget_Media_Video::render_media
	 * @covers WP_Widget_Media_Video::inject_video_max_width_style
	 */
	public function test_render_media() {
		$test_movie_file = __FILE__ . '../../data/uploads/small-video.m4v';
		$widget          = new WP_Widget_Media_Video();
		$attachment_id   = self::factory()->attachment->create_object(
			array(
				'file'           => $test_movie_file,
				'post_parent'    => 0,
				'post_mime_type' => 'video/mp4',
				'post_title'     => 'Test Video',
			)
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $test_movie_file ) );

		// Should be empty when there is no attachment_id.
		ob_start();
		$widget->render_media( array() );
		$output = ob_get_clean();
		$this->assertEmpty( $output );

		// Should be empty when there is an invalid attachment_id.
		ob_start();
		$widget->render_media(
			array(
				'attachment_id' => 777,
			)
		);
		$output = ob_get_clean();
		$this->assertEmpty( $output );

		// Tests with video from library.
		ob_start();
		$widget->render_media(
			array(
				'attachment_id' => $attachment_id,
			)
		);
		$output = ob_get_clean();

		// Check default outputs.
		$this->assertStringContainsString( 'preload="metadata"', $output );
		$this->assertStringContainsString( 'class="wp-video"', $output );
		$this->assertStringContainsString( 'width:100%', $output );
		$this->assertStringNotContainsString( 'height=', $output );
		$this->assertStringNotContainsString( 'width="', $output );
		$this->assertStringContainsString( 'small-video.m4v', $output );// Auto parses dimensions.

		ob_start();
		$widget->render_media(
			array(
				'attachment_id' => $attachment_id,
				'title'         => 'Open Source Cartoon',
				'preload'       => 'metadata',
				'loop'          => true,
			)
		);
		$output = ob_get_clean();

		// Custom attributes.
		$this->assertStringContainsString( 'preload="metadata"', $output );
		$this->assertStringContainsString( 'loop', $output );

		// Externally hosted video.
		ob_start();
		$content = '<track srclang="en" label="English" kind="subtitles" src="http://example.com/wp-content/uploads/2017/04/subtitles-en.vtt">';
		$widget->render_media(
			array(
				'attachment_id' => null,
				'loop'          => false,
				'url'           => 'https://www.youtube.com/watch?v=72xdCU__XCk',
				'content'       => $content,
			)
		);
		$output = ob_get_clean();

		// Custom attributes.
		$this->assertStringContainsString( 'preload="metadata"', $output );
		$this->assertStringContainsString( 'src="https://www.youtube.com/watch?v=72xdCU__XCk', $output );
		$this->assertStringContainsString( $content, $output );
	}

	/**
	 * Test enqueue_preview_scripts method.
	 *
	 * @global WP_Scripts $wp_scripts
	 * @global WP_Styles $wp_styles
	 * @covers WP_Widget_Media_Video::enqueue_preview_scripts
	 */
	public function test_enqueue_preview_scripts() {
		global $wp_scripts, $wp_styles;
		$widget = new WP_Widget_Media_Video();

		$wp_scripts = null;
		$wp_styles  = null;
		$widget->enqueue_preview_scripts();
		$this->assertTrue( wp_script_is( 'wp-mediaelement' ) );
		$this->assertTrue( wp_style_is( 'wp-mediaelement' ) );
		$this->assertTrue( wp_script_is( 'mediaelement-vimeo' ) );

		$wp_scripts = null;
		$wp_styles  = null;
		add_filter( 'wp_video_shortcode_library', '__return_empty_string' );
		$widget->enqueue_preview_scripts();
		$this->assertFalse( wp_script_is( 'wp-mediaelement' ) );
		$this->assertFalse( wp_style_is( 'wp-mediaelement' ) );
		$this->assertFalse( wp_script_is( 'mediaelement-vimeo' ) );
	}

	/**
	 * Test enqueue_admin_scripts method.
	 *
	 * @covers WP_Widget_Media_Video::enqueue_admin_scripts
	 */
	public function test_enqueue_admin_scripts() {
		set_current_screen( 'widgets.php' );
		$widget = new WP_Widget_Media_Video();
		$widget->enqueue_admin_scripts();

		$this->assertTrue( wp_script_is( 'media-video-widget' ) );
	}

	/**
	 * Test render_control_template_scripts method.
	 *
	 * @covers WP_Widget_Media_Video::render_control_template_scripts
	 */
	public function test_render_control_template_scripts() {
		$widget = new WP_Widget_Media_Video();

		ob_start();
		$widget->render_control_template_scripts();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/html" id="tmpl-wp-media-widget-video-preview">', $output );
	}
}
