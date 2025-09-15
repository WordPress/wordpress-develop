<?php
/**
 * Widget API: WP_Widget_Media_Video class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.8.0
 */

/**
 * Core class that implements a video widget.
 *
 * @since 4.8.0
 *
 * @see WP_Widget_Media
 * @see WP_Widget
 */
class WP_Widget_Media_Video extends WP_Widget_Media {

	/**
	 * Constructor.
	 *
	 * @since 4.8.0
	 */
	public function __construct() {
		parent::__construct(
			'media_video',
			__( 'Video' ),
			array(
				'description' => __( 'Displays a video from the media library or from YouTube, Vimeo, or another provider.' ),
				'mime_type'   => 'video',
			)
		);

		$this->l10n = array_merge(
			$this->l10n,
			array(
				'no_media_selected'          => __( 'No video selected' ),
				'add_media'                  => _x( 'Add Video', 'label for button in the video widget' ),
				'replace_media'              => _x( 'Replace Video', 'label for button in the video widget; should preferably not be longer than ~13 characters long' ),
				'edit_media'                 => _x( 'Edit Video', 'label for button in the video widget; should preferably not be longer than ~13 characters long' ),
				'missing_attachment'         => sprintf(
					/* translators: %s: URL to media library. */
					__( 'That video cannot be found. Check your <a href="%s">media library</a> and make sure it was not deleted.' ),
					esc_url( admin_url( 'upload.php' ) )
				),
				/* translators: %d: Widget count. */
				'media_library_state_multi'  => _n_noop( 'Video Widget (%d)', 'Video Widget (%d)' ),
				'media_library_state_single' => __( 'Video Widget' ),
				/* translators: %s: A list of valid video file extensions. */
				'unsupported_file_type'      => sprintf( __( 'Sorry, the video at the supplied URL cannot be loaded. Please check that the URL is for a supported video file (%s) or stream (e.g. YouTube and Vimeo).' ), '<code>.' . implode( '</code>, <code>.', wp_get_video_extensions() ) . '</code>' ),
				'invalid_url'                => __( 'Please enter a valid video URL. Supported formats include YouTube, Vimeo, or direct video file links.' ),
				'youtube_error'              => __( 'This YouTube video cannot be displayed. It may be private, deleted, or restricted in your region.' ),
				'vimeo_error'                => __( 'This Vimeo video cannot be displayed. It may be private, deleted, or require a password.' ),
				'network_error'              => __( 'Unable to load the video due to a network error. Please check your internet connection and try again.' ),
				'file_not_found'             => __( 'The video file could not be found. Please check the URL and make sure the file exists.' ),
			)
		);
	}

	/**
	 * Get schema for properties of a widget instance (item).
	 *
	 * @since 4.8.0
	 *
	 * @see WP_REST_Controller::get_item_schema()
	 * @see WP_REST_Controller::get_additional_fields()
	 * @link https://core.trac.wordpress.org/ticket/35574
	 *
	 * @return array Schema for properties.
	 */
	public function get_instance_schema() {

		$schema = array(
			'preload' => array(
				'type'                  => 'string',
				'enum'                  => array( 'none', 'auto', 'metadata' ),
				'default'               => 'metadata',
				'description'           => __( 'Preload' ),
				'should_preview_update' => false,
			),
			'loop'    => array(
				'type'                  => 'boolean',
				'default'               => false,
				'description'           => __( 'Loop' ),
				'should_preview_update' => false,
			),
			'content' => array(
				'type'                  => 'string',
				'default'               => '',
				'sanitize_callback'     => 'wp_kses_post',
				'description'           => __( 'Tracks (subtitles, captions, descriptions, chapters, or metadata)' ),
				'should_preview_update' => false,
			),
		);

		foreach ( wp_get_video_extensions() as $video_extension ) {
			$schema[ $video_extension ] = array(
				'type'        => 'string',
				'default'     => '',
				'format'      => 'uri',
				/* translators: %s: Video extension. */
				'description' => sprintf( __( 'URL to the %s video source file' ), $video_extension ),
			);
		}

		return array_merge( $schema, parent::get_instance_schema() );
	}

	/**
	 * Render the media on the frontend.
	 *
	 * @since 4.8.0
	 *
	 * @param array $instance Widget instance props.
	 */
	public function render_media( $instance ) {
		$instance   = array_merge( wp_list_pluck( $this->get_instance_schema(), 'default' ), $instance );
		$attachment = null;

		if ( $this->is_attachment_with_mime_type( $instance['attachment_id'], $this->widget_options['mime_type'] ) ) {
			$attachment = get_post( $instance['attachment_id'] );
		}

		$src = $instance['url'];
		if ( $attachment ) {
			$src = wp_get_attachment_url( $attachment->ID );
		}

		if ( empty( $src ) ) {
			return;
		}

		if ( ! filter_var( $src, FILTER_VALIDATE_URL ) ) {
			$this->render_error_message( 'invalid_url' );
			return;
		}

		$youtube_pattern = '#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#';
		$vimeo_pattern   = '#^https?://(.+\.)?vimeo\.com/.*#';

		if ( $attachment || preg_match( $youtube_pattern, $src ) || preg_match( $vimeo_pattern, $src ) ) {
			add_filter( 'wp_video_shortcode', array( $this, 'inject_video_max_width_style' ) );

			$video_html = wp_video_shortcode(
				array_merge(
					$instance,
					compact( 'src' )
				),
				$instance['content']
			);

			remove_filter( 'wp_video_shortcode', array( $this, 'inject_video_max_width_style' ) );

			if ( empty( $video_html ) || false !== strpos( $video_html, 'Sorry, this content isn\'t available right now' ) ) {
				if ( preg_match( $youtube_pattern, $src ) ) {
					$this->render_error_message( 'youtube_error' );
				} elseif ( preg_match( $vimeo_pattern, $src ) ) {
					$this->render_error_message( 'vimeo_error' );
				} else {
					$this->render_error_message( 'file_not_found' );
				}
			} else {
				echo $video_html;
			}
		} else {
			$oembed_html = wp_oembed_get( $src );

			if ( empty( $oembed_html ) ) {
				$file_extension = pathinfo( parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION );
				if ( in_array( strtolower( $file_extension ), wp_get_video_extensions() ) ) {
					$this->render_error_message( 'file_not_found' );
				} else {
					$this->render_error_message( 'unsupported_file_type' );
				}
			} else {
				echo $this->inject_video_max_width_style( $oembed_html );
			}
		}
	}

	/**
	 * Inject max-width and remove height for videos too constrained to fit inside sidebars on frontend.
	 *
	 * @since 4.8.0
	 *
	 * @param string $html Video shortcode HTML output.
	 * @return string HTML Output.
	 */
	public function inject_video_max_width_style( $html ) {
		$html = preg_replace( '/\sheight="\d+"/', '', $html );
		$html = preg_replace( '/\swidth="\d+"/', '', $html );
		$html = preg_replace( '/(?<=width:)\s*\d+px(?=;?)/', '100%', $html );
		return $html;
	}

	/**
	 * Enqueue preview scripts.
	 *
	 * These scripts normally are enqueued just-in-time when a video shortcode is used.
	 * In the customizer, however, widgets can be dynamically added and rendered via
	 * selective refresh, and so it is important to unconditionally enqueue them in
	 * case a widget does get added.
	 *
	 * @since 4.8.0
	 */
	public function enqueue_preview_scripts() {
		/** This filter is documented in wp-includes/media.php */
		if ( 'mediaelement' === apply_filters( 'wp_video_shortcode_library', 'mediaelement' ) ) {
			wp_enqueue_style( 'wp-mediaelement' );
			wp_enqueue_script( 'mediaelement-vimeo' );
			wp_enqueue_script( 'wp-mediaelement' );
		}
	}

	/**
	 * Loads the required scripts and styles for the widget control.
	 *
	 * @since 4.8.0
	 */
	public function enqueue_admin_scripts() {
		parent::enqueue_admin_scripts();

		$handle = 'media-video-widget';
		wp_enqueue_script( $handle );

		$exported_schema = array();
		foreach ( $this->get_instance_schema() as $field => $field_schema ) {
			$exported_schema[ $field ] = wp_array_slice_assoc( $field_schema, array( 'type', 'default', 'enum', 'minimum', 'format', 'media_prop', 'should_preview_update' ) );
		}
		wp_add_inline_script(
			$handle,
			sprintf(
				'wp.mediaWidgets.modelConstructors[ %s ].prototype.schema = %s;',
				wp_json_encode( $this->id_base, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				wp_json_encode( $exported_schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			)
		);

		wp_add_inline_script(
			$handle,
			sprintf(
				'
					wp.mediaWidgets.controlConstructors[ %1$s ].prototype.mime_type = %2$s;
					wp.mediaWidgets.controlConstructors[ %1$s ].prototype.l10n = _.extend( {}, wp.mediaWidgets.controlConstructors[ %1$s ].prototype.l10n, %3$s );
				',
				wp_json_encode( $this->id_base, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				wp_json_encode( $this->widget_options['mime_type'], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				wp_json_encode( $this->l10n, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			)
		);
	}

	/**
	 * Render form template scripts.
	 *
	 * @since 4.8.0
	 */
	public function render_control_template_scripts() {
		parent::render_control_template_scripts()
		?>
		<script type="text/html" id="tmpl-wp-media-widget-video-preview">
			<# if ( data.error && 'missing_attachment' === data.error ) { #>
				<?php
				wp_admin_notice(
					$this->l10n['missing_attachment'],
					array(
						'type'               => 'error',
						'additional_classes' => array( 'notice-alt', 'notice-missing-attachment' ),
					)
				);
				?>
			<# } else if ( data.error && 'unsupported_file_type' === data.error ) { #>
				<?php
				wp_admin_notice(
					$this->l10n['unsupported_file_type'],
					array(
						'type'               => 'error',
						'additional_classes' => array( 'notice-alt', 'notice-missing-attachment' ),
					)
				);
				?>
			<# } else if ( data.error ) { #>
				<?php
				wp_admin_notice(
					__( 'Unable to preview media due to an unknown error.' ),
					array(
						'type'               => 'error',
						'additional_classes' => array( 'notice-alt' ),
					)
				);
				?>
			<# } else if ( data.is_oembed && data.model.poster ) { #>
				<a href="{{ data.model.src }}" target="_blank" class="media-widget-video-link">
					<img src="{{ data.model.poster }}" />
				</a>
			<# } else if ( data.is_oembed ) { #>
				<a href="{{ data.model.src }}" target="_blank" class="media-widget-video-link no-poster">
					<span class="dashicons dashicons-format-video"></span>
				</a>
			<# } else if ( data.model.src ) { #>
				<?php wp_underscore_video_template(); ?>
			<# } #>
		</script>
		<?php
	}
}
