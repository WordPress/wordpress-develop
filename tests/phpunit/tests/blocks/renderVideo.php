<?php
/**
 * Tests for the server-side rendering of the Video block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_video
 */
class Tests_Blocks_RenderVideo extends WP_UnitTestCase {

	/**
	 * Original script modules.
	 *
	 * @var WP_Script_Modules|null
	 */
	private $original_script_modules;

	/**
	 * ID of an attachment that has valid dimensions.
	 *
	 * @var int
	 */
	private static $video_id;

	/**
	 * ID of a post that is not an attachment.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$video_id = $factory->attachment->create_object(
			array(
				'file'           => 'video.mp4',
				'post_mime_type' => 'video/mp4',
			)
		);
		wp_update_attachment_metadata(
			self::$video_id,
			array(
				'width'  => 640,
				'height' => 360,
			)
		);

		self::$post_id = $factory->post->create();
		wp_update_attachment_metadata(
			self::$post_id,
			array(
				'width'  => 640,
				'height' => 360,
			)
		);
	}

	public function set_up() {
		global $wp_script_modules;

		parent::set_up();

		$this->original_script_modules = $wp_script_modules;
		$wp_script_modules             = null;
	}

	public function tear_down() {
		global $wp_script_modules;

		$wp_script_modules = $this->original_script_modules;
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Creates the attachment of a video with the metadata that is passed.
	 *
	 * @param mixed $metadata Metadata of the attachment.
	 * @return int Attachment ID.
	 */
	private function create_video_with_metadata( $metadata ) {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'other-video.mp4',
				'post_mime_type' => 'video/mp4',
			)
		);
		wp_update_attachment_metadata( $id, $metadata );

		return $id;
	}

	/**
	 * Asserts the attributes of the first video of the HTML, regardless of their order.
	 *
	 * @param array  $expected Expected attributes, keyed by name.
	 * @param string $html     HTML with a video.
	 */
	private function assert_video_attributes( $expected, $html ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( 'VIDEO' ), 'The HTML should have a video.' );

		$actual = array();
		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$actual[ $name ] = $processor->get_attribute( $name );
		}

		ksort( $expected );
		ksort( $actual );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that the content without a video tag is returned unchanged.
	 *
	 * @ticket 66515
	 *
	 * @dataProvider data_content_without_a_video
	 *
	 * @param string $content Content of the block.
	 */
	public function test_should_return_the_content_without_a_video_tag_unchanged( $content ) {
		$this->assertSame( $content, render_block_core_video( array( 'id' => self::$video_id ), $content ) );
		$this->assertSame( array(), wp_script_modules()->get_queue(), 'No script module should be enqueued.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_without_a_video() {
		return array(
			'empty content'        => array( '' ),
			'text'                 => array( 'Not a video' ),
			'another element'      => array( '<figure class="wp-block-video"><audio src="a.mp3" autoplay></audio></figure>' ),
			'a video in a comment' => array( '<figure class="wp-block-video"></figure>' ),
		);
	}

	/**
	 * Tests that the dimensions are added to the video of an attachment.
	 *
	 * @ticket 66515
	 */
	public function test_should_add_the_dimensions_of_the_attachment() {
		$content = '<figure class="wp-block-video"><video controls src="video.mp4"></video></figure>';

		$output = render_block_core_video( array( 'id' => self::$video_id ), $content );

		$this->assert_video_attributes(
			array(
				'controls' => true,
				'src'      => 'video.mp4',
				'width'    => '640',
				'height'   => '360',
				'style'    => 'aspect-ratio: 640 / 360;',
			),
			$output
		);
		$this->assertStringStartsWith( '<figure class="wp-block-video"><video ', $output, 'The markup around the video should be kept.' );
		$this->assertStringEndsWith( '></video></figure>', $output, 'The markup around the video should be kept.' );
	}

	/**
	 * Tests that an uppercase video tag is handled.
	 *
	 * @ticket 66515
	 */
	public function test_should_handle_an_uppercase_video_tag() {
		$content = '<figure><VIDEO src="video.mp4"></VIDEO></figure>';

		$this->assert_video_attributes(
			array(
				'src'    => 'video.mp4',
				'width'  => '640',
				'height' => '360',
				'style'  => 'aspect-ratio: 640 / 360;',
			),
			render_block_core_video( array( 'id' => self::$video_id ), $content )
		);
	}

	/**
	 * Tests that the dimensions replace the ones that are in the markup.
	 *
	 * @ticket 66515
	 */
	public function test_should_replace_the_dimensions_that_are_in_the_markup() {
		$content = '<video width="10" height="20" src="video.mp4"></video>';

		$this->assert_video_attributes(
			array(
				'src'    => 'video.mp4',
				'width'  => '640',
				'height' => '360',
				'style'  => 'aspect-ratio: 640 / 360;',
			),
			render_block_core_video( array( 'id' => self::$video_id ), $content )
		);
	}

	/**
	 * Tests that the aspect ratio is added before the style that is in the markup.
	 *
	 * @ticket 66515
	 */
	public function test_should_add_the_aspect_ratio_before_the_existing_style() {
		$content = '<video style="object-fit:cover;" src="video.mp4"></video>';

		$this->assert_video_attributes(
			array(
				'src'    => 'video.mp4',
				'width'  => '640',
				'height' => '360',
				'style'  => 'aspect-ratio: 640 / 360;object-fit:cover;',
			),
			render_block_core_video( array( 'id' => self::$video_id ), $content )
		);
	}

	/**
	 * Tests that only the first video of the content gets the dimensions.
	 *
	 * @ticket 66515
	 */
	public function test_should_only_add_the_dimensions_to_the_first_video() {
		$content = '<video src="one.mp4"></video><video src="two.mp4"></video>';

		$output = render_block_core_video( array( 'id' => self::$video_id ), $content );

		$this->assert_video_attributes(
			array(
				'src'    => 'one.mp4',
				'width'  => '640',
				'height' => '360',
				'style'  => 'aspect-ratio: 640 / 360;',
			),
			$output
		);
		$this->assertStringEndsWith( '</video><video src="two.mp4"></video>', $output, 'The second video should not be changed.' );
	}

	/**
	 * Tests that the content is not changed when the ID is not a positive integer.
	 *
	 * @ticket 66515
	 *
	 * @dataProvider data_attributes_without_a_valid_id
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_not_add_the_dimensions_without_a_valid_id( $attributes ) {
		$content = '<video src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( $attributes, $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_a_valid_id() {
		return array(
			'no id'         => array( array() ),
			'a null id'     => array( array( 'id' => null ) ),
			'a zero id'     => array( array( 'id' => 0 ) ),
			'a negative id' => array( array( 'id' => -5 ) ),
			'a string id'   => array( array( 'id' => '1' ) ),
			'a float id'    => array( array( 'id' => 1.0 ) ),
			'an array id'   => array( array( 'id' => array( 1 ) ) ),
		);
	}

	/**
	 * Tests that the ID of an attachment is not used when it is not an integer.
	 *
	 * @ticket 66515
	 */
	public function test_should_not_add_the_dimensions_when_the_id_of_an_attachment_is_not_an_integer() {
		$content = '<video src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array( 'id' => (string) self::$video_id ), $content ), 'A numeric string should be ignored.' );
		$this->assertSame( $content, render_block_core_video( array( 'id' => (float) self::$video_id ), $content ), 'A float should be ignored.' );
	}

	/**
	 * Tests that the current post is not used when the ID is zero.
	 *
	 * @ticket 66515
	 */
	public function test_should_not_use_the_current_post_when_the_id_is_zero() {
		$GLOBALS['post'] = get_post( self::$video_id );
		$content         = '<video src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array( 'id' => 0 ), $content ) );
	}

	/**
	 * Tests that a style attribute without a value is replaced with the aspect ratio.
	 *
	 * @ticket 66515
	 */
	public function test_should_replace_a_style_attribute_without_a_value() {
		$this->assert_video_attributes(
			array(
				'src'    => 'video.mp4',
				'width'  => '640',
				'height' => '360',
				'style'  => 'aspect-ratio: 640 / 360;',
			),
			render_block_core_video( array( 'id' => self::$video_id ), '<video style src="video.mp4"></video>' )
		);
	}

	/**
	 * Tests that the content is not changed when the ID is not the one of an attachment.
	 *
	 * @ticket 66515
	 */
	public function test_should_not_add_the_dimensions_when_the_id_is_not_an_attachment() {
		$content = '<video src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array( 'id' => self::$post_id ), $content ), 'A post should be ignored.' );
		$this->assertSame( $content, render_block_core_video( array( 'id' => self::$post_id + 100000 ), $content ), 'An ID that does not exist should be ignored.' );
	}

	/**
	 * Tests that the content is not changed when the metadata has no valid dimensions.
	 *
	 * @ticket 66515
	 *
	 * @dataProvider data_metadata_without_valid_dimensions
	 *
	 * @param mixed $metadata Metadata of the attachment.
	 */
	public function test_should_not_add_the_dimensions_without_valid_metadata( $metadata ) {
		$id      = $this->create_video_with_metadata( $metadata );
		$content = '<video src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array( 'id' => $id ), $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_metadata_without_valid_dimensions() {
		return array(
			'no metadata'       => array( array() ),
			'only a width'      => array( array( 'width' => 640 ) ),
			'only a height'     => array( array( 'height' => 360 ) ),
			'a zero width'      => array(
				array(
					'width'  => 0,
					'height' => 360,
				),
			),
			'a zero height'     => array(
				array(
					'width'  => 640,
					'height' => 0,
				),
			),
			'a negative width'  => array(
				array(
					'width'  => -640,
					'height' => 360,
				),
			),
			'a negative height' => array(
				array(
					'width'  => 640,
					'height' => -360,
				),
			),
			'a string width'    => array(
				array(
					'width'  => '640',
					'height' => 360,
				),
			),
			'a string height'   => array(
				array(
					'width'  => 640,
					'height' => '360',
				),
			),
			'a float width'     => array(
				array(
					'width'  => 640.5,
					'height' => 360,
				),
			),
			'null dimensions'   => array(
				array(
					'width'  => null,
					'height' => null,
				),
			),
		);
	}

	/**
	 * Tests that the interactivity directives are added to a video that autoplays.
	 *
	 * @ticket 66515
	 *
	 * @dataProvider data_autoplay_markup
	 *
	 * @param string $content   Content of the block.
	 * @param string $attribute Name of the autoplay attribute, as it is in the markup.
	 * @param mixed  $value     Value of the autoplay attribute.
	 */
	public function test_should_add_the_directives_to_a_video_that_autoplays( $content, $attribute, $value ) {
		$this->assert_video_attributes(
			array(
				$attribute            => $value,
				'src'                 => 'video.mp4',
				'data-wp-interactive' => 'core/video',
				'data-wp-init'        => 'callbacks.stopAutoplay',
			),
			render_block_core_video( array(), $content )
		);
		$this->assertContains( '@wordpress/block-library/video/view', wp_script_modules()->get_queue(), 'The view script module should be enqueued.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_autoplay_markup() {
		return array(
			'a boolean attribute' => array( '<figure><video autoplay src="video.mp4"></video></figure>', 'autoplay', true ),
			'an empty value'      => array( '<video autoplay="" src="video.mp4"></video>', 'autoplay', '' ),
			'an uppercase tag'    => array( '<VIDEO AUTOPLAY src="video.mp4"></VIDEO>', 'autoplay', true ),
			'a value'             => array( '<video autoplay="autoplay" src="video.mp4"></video>', 'autoplay', 'autoplay' ),
		);
	}

	/**
	 * Tests that a video that does not autoplay does not get the directives.
	 *
	 * @ticket 66515
	 */
	public function test_should_not_add_the_directives_to_a_video_that_does_not_autoplay() {
		$content = '<video controls src="video.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array(), $content ) );
		$this->assertSame( array(), wp_script_modules()->get_queue(), 'No script module should be enqueued.' );
	}

	/**
	 * Tests that only the first video is checked for the autoplay.
	 *
	 * @ticket 66515
	 */
	public function test_should_only_check_the_first_video_for_the_autoplay() {
		$content = '<video src="one.mp4"></video><video autoplay src="two.mp4"></video>';

		$this->assertSame( $content, render_block_core_video( array(), $content ) );
		$this->assertSame( array(), wp_script_modules()->get_queue() );
	}

	/**
	 * Tests that the directives and the dimensions are both added.
	 *
	 * @ticket 66515
	 */
	public function test_should_add_the_directives_and_the_dimensions() {
		$content = '<video autoplay src="video.mp4"></video>';

		$this->assert_video_attributes(
			array(
				'autoplay'            => true,
				'src'                 => 'video.mp4',
				'data-wp-interactive' => 'core/video',
				'data-wp-init'        => 'callbacks.stopAutoplay',
				'width'               => '640',
				'height'              => '360',
				'style'               => 'aspect-ratio: 640 / 360;',
			),
			render_block_core_video( array( 'id' => self::$video_id ), $content )
		);
	}
}
