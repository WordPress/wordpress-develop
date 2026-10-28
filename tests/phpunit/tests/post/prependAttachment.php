<?php

/**
 * Tests for the `prepend_attachment()` function.
 *
 * @group post
 * @group media
 */
class Tests_Post_PrependAttachment extends WP_UnitTestCase {

	/**
	 * Regular post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * PDF attachment ID.
	 *
	 * @var int
	 */
	private static $pdf_id;

	/**
	 * Video attachment ID.
	 *
	 * @var int
	 */
	private static $video_id;

	/**
	 * Audio attachment ID.
	 *
	 * @var int
	 */
	private static $audio_id;

	/**
	 * Image attachment ID, used as a video poster.
	 *
	 * @var int
	 */
	private static $image_id;

	/**
	 * Attributes passed to the audio or video shortcode.
	 *
	 * @var array[]
	 */
	private $shortcode_atts = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id  = $factory->post->create();
		self::$pdf_id   = $factory->attachment->create_object(
			array(
				'file'           => 'annual-report.pdf',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Annual Report',
			)
		);
		self::$video_id = $factory->attachment->create_object(
			array(
				'file'           => 'clip.mp4',
				'post_mime_type' => 'video/mp4',
			)
		);
		self::$audio_id = $factory->attachment->create_object(
			array(
				'file'           => 'song.mp3',
				'post_mime_type' => 'audio/mpeg',
			)
		);
		self::$image_id = $factory->attachment->create_object(
			array(
				'file'           => 'poster.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'wp_video_shortcode_override', array( $this, 'capture_shortcode_atts' ), 10, 2 );
		add_filter( 'wp_audio_shortcode_override', array( $this, 'capture_shortcode_atts' ), 10, 2 );
	}

	/**
	 * Records the shortcode attributes and short-circuits the shortcode output.
	 *
	 * @param string $html Empty string.
	 * @param array  $atts Attributes passed to the shortcode.
	 * @return string Stub markup.
	 */
	public function capture_shortcode_atts( $html, $atts ) {
		$this->shortcode_atts[] = $atts;

		return 'wp_video_shortcode_override' === current_filter()
			? '<div class="video-stub"></div>'
			: '<div class="audio-stub"></div>';
	}

	/**
	 * Tests that the content is unchanged outside an attachment.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 *
	 * @dataProvider data_not_an_attachment
	 *
	 * @param bool $has_post Whether a regular post is the global post.
	 */
	public function test_returns_content_unchanged_when_not_an_attachment( $has_post ) {
		$GLOBALS['post'] = $has_post ? get_post( self::$post_id ) : null;

		$this->assertSame( '<p>Content</p>', prepend_attachment( '<p>Content</p>' ) );
	}

	/**
	 * Data provider for test_returns_content_unchanged_when_not_an_attachment().
	 *
	 * @return array[]
	 */
	public static function data_not_an_attachment() {
		return array(
			'regular post' => array( true ),
			'no post'      => array( false ),
		);
	}

	/**
	 * Tests that a link to the file is prepended for attachments other than audio and video.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 */
	public function test_prepends_link_for_other_attachments() {
		$GLOBALS['post'] = get_post( self::$pdf_id );

		$this->assertSame(
			"<p class=\"attachment\"><a href='" . wp_get_upload_dir()['baseurl'] . "/annual-report.pdf'>Annual Report</a></p>\n<p>Content</p>",
			prepend_attachment( '<p>Content</p>' )
		);
		$this->assertSame( array(), $this->shortcode_atts, 'No media shortcode should be rendered.' );
	}

	/**
	 * Tests the video shortcode attributes built from the attachment metadata.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 *
	 * @dataProvider data_video_metadata
	 *
	 * @param array $metadata   Attachment metadata.
	 * @param array $dimensions Expected width and height attributes.
	 */
	public function test_prepends_video_shortcode( $metadata, $dimensions ) {
		wp_update_attachment_metadata( self::$video_id, $metadata );
		$GLOBALS['post'] = get_post( self::$video_id );

		$this->assertSame( "<div class=\"video-stub\"></div>\n<p>Content</p>", prepend_attachment( '<p>Content</p>' ) );
		$this->assertSame(
			array( array( 'src' => wp_get_upload_dir()['baseurl'] . '/clip.mp4' ) + $dimensions ),
			$this->shortcode_atts
		);
	}

	/**
	 * Data provider for test_prepends_video_shortcode().
	 *
	 * @return array[]
	 */
	public static function data_video_metadata() {
		return array(
			'width and height as strings' => array(
				array(
					'width'  => '640',
					'height' => '360',
				),
				array(
					'width'  => 640,
					'height' => 360,
				),
			),
			'width only'                  => array( array( 'width' => 640 ), array() ),
			'height only'                 => array( array( 'height' => 360 ), array() ),
			'no dimensions'               => array( array(), array() ),
		);
	}

	/**
	 * Tests that the video's featured image is used as the poster.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 */
	public function test_video_poster_from_featured_image() {
		update_post_meta( self::$video_id, '_thumbnail_id', self::$image_id );
		$GLOBALS['post'] = get_post( self::$video_id );

		prepend_attachment( '' );

		$this->assertSame(
			array(
				array(
					'src'    => wp_get_upload_dir()['baseurl'] . '/clip.mp4',
					'poster' => wp_get_upload_dir()['baseurl'] . '/poster.jpg',
				),
			),
			$this->shortcode_atts
		);
	}

	/**
	 * Tests that the audio shortcode is prepended for audio attachments.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 */
	public function test_prepends_audio_shortcode() {
		$GLOBALS['post'] = get_post( self::$audio_id );

		$this->assertSame( "<div class=\"audio-stub\"></div>\n<p>Content</p>", prepend_attachment( '<p>Content</p>' ) );
		$this->assertSame( array( array( 'src' => wp_get_upload_dir()['baseurl'] . '/song.mp3' ) ), $this->shortcode_atts );
	}

	/**
	 * Tests that the 'prepend_attachment' filter receives the markup and its result is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::prepend_attachment
	 */
	public function test_prepend_attachment_filter() {
		$GLOBALS['post'] = get_post( self::$audio_id );

		$received = array();

		add_filter(
			'prepend_attachment',
			static function ( $html ) use ( &$received ) {
				$received[] = $html;

				return $html . '-filtered';
			}
		);

		$this->assertSame( "<div class=\"audio-stub\"></div>-filtered\n<p>Content</p>", prepend_attachment( '<p>Content</p>' ) );
		$this->assertSame( array( '<div class="audio-stub"></div>' ), $received, 'The filter should run once with the attachment markup.' );
	}
}
