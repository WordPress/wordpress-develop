<?php
/**
 * Tests for the server-side rendering of the Playlist Track block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_playlist_track
 */
class Tests_Blocks_RenderPlaylistTrack extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/playlist-track',
			'attrs'     => array(),
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Returns the attributes of the first element with the class of the output.
	 *
	 * @param string $output     The output.
	 * @param string $class_name The class name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $class_name ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( array( 'class_name' => $class_name ) ) ) {
			return null;
		}

		$attributes = array( 'tag' => $processor->get_tag() );

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Renders the block with a block instance.
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $context    Optional. The context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName' => 'core/playlist-track',
				'attrs'     => $attributes,
			),
			$context
		);

		return render_block_core_playlist_track( $attributes, '', $block );
	}

	/**
	 * Tests that a track without a source gives an empty string.
	 *
	 * @ticket 66580
	 *
	 * @dataProvider data_sources_that_are_empty
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_return_an_empty_string_without_a_source( $attributes ) {
		$this->assertSame( '', render_block_core_playlist_track( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_sources_that_are_empty() {
		return array(
			'no source'   => array( array( 'title' => 'Song' ) ),
			'empty'       => array(
				array(
					'src'   => '',
					'title' => 'Song',
				),
			),
			'zero string' => array(
				array(
					'src'   => '0',
					'title' => 'Song',
				),
			),
			'null'        => array(
				array(
					'src'   => null,
					'title' => 'Song',
				),
			),
		);
	}

	/**
	 * Tests the markup of a track with all of its data.
	 *
	 * @ticket 66580
	 */
	public function test_should_render_a_track() {
		$output = $this->render(
			array(
				'src'      => 'https://example.org/song.mp3',
				'title'    => 'Song',
				'artist'   => 'Singer',
				'length'   => '3:25',
				'image'    => 'https://example.org/cover.jpg',
				'imageAlt' => 'Cover',
			)
		);

		$item   = $this->get_element( $output, 'wp-block-playlist-track' );
		$button = $this->get_element( $output, 'wp-block-playlist-track__button' );
		$image  = $this->get_element( $output, 'wp-block-playlist-track__image' );

		$this->assertSame( 'LI', $item['tag'] );
		$this->assertSame( 'BUTTON', $button['tag'] );
		$this->assertSame( 'actions.changeTrack', $button['data-wp-on--click'] );
		$this->assertSame( 'state.isCurrentTrack', $button['data-wp-bind--aria-current'] );
		$this->assertSame( 'https://example.org/cover.jpg', $image['src'] );
		$this->assertSame( 'Cover', $image['alt'] );
		$this->assertStringContainsString( '<span class="wp-block-playlist-track__title">Song</span>', $output );
		$this->assertStringContainsString( '<span class="wp-block-playlist-track__artist">Singer</span>', $output );
		$this->assertStringContainsString( '<span class="screen-reader-text">Duration: </span>3:25</span>', $output );
		$this->assertStringContainsString( '<span class="screen-reader-text" data-wp-text="state.trackButtonActionLabel">Play</span>', $output );
		$this->assertStringStartsWith( '<li ', $output );
		$this->assertStringEndsWith( '</li>', $output );
	}

	/**
	 * Tests that the parts without data are left out.
	 *
	 * @ticket 66580
	 */
	public function test_should_leave_out_the_parts_without_data() {
		$output = render_block_core_playlist_track( array( 'src' => 'https://example.org/song.mp3' ) );

		$this->assertStringNotContainsString( 'wp-block-playlist-track__image', $output );
		$this->assertStringNotContainsString( 'wp-block-playlist-track__artist', $output );
		$this->assertStringNotContainsString( 'wp-block-playlist-track__length', $output );
		$this->assertStringContainsString( '<span class="wp-block-playlist-track__title">Unknown title</span>', $output );
	}

	/**
	 * Tests the default title.
	 *
	 * @ticket 66580
	 *
	 * @dataProvider data_empty_titles
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_use_the_default_title( $attributes ) {
		$output = render_block_core_playlist_track( $attributes + array( 'src' => 'https://example.org/song.mp3' ) );

		$this->assertStringContainsString( '>Unknown title</span>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_titles() {
		return array(
			'not set' => array( array() ),
			'empty'   => array( array( 'title' => '' ) ),
			'null'    => array( array( 'title' => null ) ),
		);
	}

	/**
	 * Tests that the text is escaped.
	 *
	 * @ticket 66580
	 */
	public function test_should_escape_the_text_and_the_attributes() {
		$output = render_block_core_playlist_track(
			array(
				'src'      => 'https://example.org/song.mp3',
				'title'    => '<b>Song</b>',
				'artist'   => 'A & B',
				'length'   => '<i>3:25</i>',
				'image'    => 'https://example.org/cover.jpg?a=1&b=2',
				'imageAlt' => 'Say "hi"',
			)
		);

		$this->assertStringContainsString( '>&lt;b&gt;Song&lt;/b&gt;</span>', $output );
		$this->assertStringContainsString( '>A &amp; B</span>', $output );
		$this->assertStringContainsString( '&lt;i&gt;3:25&lt;/i&gt;</span>', $output );
		$this->assertStringContainsString( 'src="https://example.org/cover.jpg?a=1&#038;b=2"', $output );
		$this->assertStringContainsString( 'alt="Say &quot;hi&quot;"', $output );
	}

	/**
	 * Tests the images with the setting of the context.
	 *
	 * @ticket 66580
	 *
	 * @dataProvider data_show_images
	 *
	 * @param array $context  The context of the block.
	 * @param bool  $expected Whether the image is expected.
	 */
	public function test_should_show_the_image_by_the_context( $context, $expected ) {
		$output = $this->render(
			array(
				'src'   => 'https://example.org/song.mp3',
				'image' => 'https://example.org/cover.jpg',
			),
			$context
		);

		$this->assertSame( $expected, str_contains( $output, 'wp-block-playlist-track__image' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_show_images() {
		return array(
			'not set' => array( array(), true ),
			'shown'   => array( array( 'showImages' => true ), true ),
			'hidden'  => array( array( 'showImages' => false ), false ),
		);
	}

	/**
	 * Tests that the image is shown when there is no block instance.
	 *
	 * @ticket 66580
	 */
	public function test_should_show_the_image_without_a_block_instance() {
		$output = render_block_core_playlist_track(
			array(
				'src'   => 'https://example.org/song.mp3',
				'image' => 'https://example.org/cover.jpg',
			)
		);

		$this->assertStringContainsString( 'wp-block-playlist-track__image', $output );
	}

	/**
	 * Tests that an image that is not a string is ignored.
	 *
	 * @ticket 66580
	 *
	 * @dataProvider data_images_that_are_not_valid
	 *
	 * @param mixed $image The image.
	 */
	public function test_should_ignore_an_image_that_is_not_a_string( $image ) {
		$output = render_block_core_playlist_track(
			array(
				'src'   => 'https://example.org/song.mp3',
				'image' => $image,
			)
		);

		$this->assertStringNotContainsString( 'wp-block-playlist-track__image', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_images_that_are_not_valid() {
		return array(
			'an array' => array( array( 'url' => 'https://example.org/cover.jpg' ) ),
			'a number' => array( 12 ),
			'empty'    => array( '' ),
			'null'     => array( null ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66580
	 *
	 * @covers ::register_block_core_playlist_track
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/playlist-track' );

		if ( $original_block ) {
			unregister_block_type( 'core/playlist-track' );
		}

		try {
			register_block_core_playlist_track();
			$block_type = $registry->get_registered( 'core/playlist-track' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_playlist_track', $block_type->render_callback );
		} finally {
			if ( $registry->is_registered( 'core/playlist-track' ) ) {
				unregister_block_type( 'core/playlist-track' );
			}

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
