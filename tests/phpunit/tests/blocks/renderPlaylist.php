<?php
/**
 * Tests for the server-side rendering of the Playlist block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 *
 * @covers ::render_block_core_playlist
 */
class Tests_Blocks_RenderPlaylist extends WP_UnitTestCase {

	/**
	 * ID of the script module of the block.
	 */
	const MODULE_ID = '@wordpress/block-library/playlist/view';

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original values of the properties of the Interactivity API that the block changes.
	 *
	 * @var array
	 */
	private $original_properties = array();

	/**
	 * Whether the script module of the block was in the queue before the test.
	 *
	 * @var bool
	 */
	private $module_was_queued;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = array(
			'blockName' => 'core/playlist',
			'attrs'     => array(),
		);

		foreach ( array( 'state_data', 'derived_state_closures' ) as $name ) {
			$this->original_properties[ $name ] = $this->get_interactivity_property( $name )->getValue( wp_interactivity() );
		}

		$this->module_was_queued = in_array( self::MODULE_ID, wp_script_modules()->get_queue(), true );
	}

	public function tear_down() {
		foreach ( $this->original_properties as $name => $value ) {
			$this->get_interactivity_property( $name )->setValue( wp_interactivity(), $value );
		}

		if ( ! $this->module_was_queued ) {
			wp_dequeue_script_module( self::MODULE_ID );
		}

		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Returns a property of the Interactivity API.
	 *
	 * @param string $name The name of the property.
	 * @return ReflectionProperty The property.
	 */
	private function get_interactivity_property( $name ) {
		$property = new ReflectionProperty( WP_Interactivity_API::class, $name );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property;
	}

	/**
	 * Renders the block.
	 *
	 * @param array      $attributes The attributes of the block.
	 * @param array      $tracks     Optional. The attributes of the tracks. Default one track.
	 * @param array|null $content    Optional. The content of the block. Default a figure with a button for each track.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $tracks = null, $content = null ) {
		if ( null === $tracks ) {
			$tracks = array( array( 'src' => 'https://example.org/one.mp3' ) );
		}

		$inner_blocks = array();
		$buttons      = '';

		foreach ( $tracks as $track ) {
			$inner_blocks[] = array(
				'blockName'    => 'core/playlist-track',
				'attrs'        => $track,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);

			if ( ! empty( $track['src'] ) ) {
				$buttons .= '<li><button class="wp-block-playlist-track__button">Track</button></li>';
			}
		}

		if ( null === $content ) {
			$content = '<figure class="wp-block-playlist"><ul>' . $buttons . '</ul></figure>';
		}

		$block = new WP_Block(
			array(
				'blockName'    => 'core/playlist',
				'attrs'        => $attributes,
				'innerBlocks'  => $inner_blocks,
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		return render_block_core_playlist( $attributes, $content, $block );
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $output The output.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $output, $query ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the context of the figure of the output.
	 *
	 * @param string $output The output.
	 * @return array The context.
	 */
	private function get_context( $output ) {
		$figure = $this->get_element( $output, 'figure' );

		$this->assertIsArray( $figure, 'The output should have a figure.' );

		return json_decode( $figure['data-wp-context'], true );
	}

	/**
	 * Returns the tracks in the state of the playlist of the output.
	 *
	 * @param string $output The output.
	 * @return array The tracks, keyed by their IDs.
	 */
	private function get_tracks_state( $output ) {
		$playlist_id = $this->get_context( $output )['playlistId'];
		$state       = wp_interactivity()->state( 'core/playlist' );

		$this->assertArrayHasKey( $playlist_id, $state['playlists'], 'The state should have the playlist.' );

		return $state['playlists'][ $playlist_id ]['tracks'];
	}

	/**
	 * Tests that nothing is rendered without playable tracks.
	 *
	 * @ticket 66587
	 *
	 * @dataProvider data_no_tracks
	 *
	 * @param array $tracks The attributes of the tracks.
	 */
	public function test_should_render_nothing_without_playable_tracks( $tracks ) {
		$this->assertSame( '', $this->render( array(), $tracks ) );

		if ( ! $this->module_was_queued ) {
			$this->assertNotContains( self::MODULE_ID, wp_script_modules()->get_queue(), 'The script module should not be enqueued.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_tracks() {
		return array(
			'no tracks'            => array( array() ),
			'track without src'    => array( array( array( 'title' => 'No source' ) ) ),
			'track with empty src' => array( array( array( 'src' => '' ) ) ),
		);
	}

	/**
	 * Tests that blocks that are not tracks are ignored.
	 *
	 * @ticket 66587
	 */
	public function test_should_ignore_inner_blocks_that_are_not_tracks() {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/playlist',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array( 'src' => 'https://example.org/one.mp3' ),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertSame( '', render_block_core_playlist( array(), '<figure></figure>', $block ) );
	}

	/**
	 * Tests the interactivity context of the figure.
	 *
	 * @ticket 66587
	 */
	public function test_should_add_the_interactivity_context_to_the_figure() {
		$output  = $this->render( array(), array( array( 'src' => 'https://example.org/one.mp3' ), array( 'src' => 'https://example.org/two.mp3' ) ) );
		$figure  = $this->get_element( $output, 'figure' );
		$context = $this->get_context( $output );

		$this->assertSame( 'core/playlist', $figure['data-wp-interactive'] );
		$this->assertMatchesRegularExpression( '/^playlist-\d+$/', $context['playlistId'] );
		$this->assertSame( 'track-0', $context['currentId'] );
		$this->assertFalse( $context['isPlaying'] );
		$this->assertSame( array( 'track-0', 'track-1' ), $context['tracks'] );
		$this->assertSame( 'bars', $context['waveformStyle'] );
		$this->assertFalse( $context['showPlayButtonArtwork'] );
		$this->assertSame( 'Pause', $context['labelPauseTrack'] );
		$this->assertSame( 'Play', $context['labelSelectTrack'] );
	}

	/**
	 * Tests the style of the waveform and the artwork of the play button.
	 *
	 * @ticket 66587
	 */
	public function test_should_use_the_waveform_style_and_the_play_button_artwork_attributes() {
		$context = $this->get_context(
			$this->render(
				array(
					'waveformStyle'         => 'mirror',
					'showPlayButtonArtwork' => true,
				)
			)
		);

		$this->assertSame( 'mirror', $context['waveformStyle'] );
		$this->assertTrue( $context['showPlayButtonArtwork'] );
	}

	/**
	 * Tests that each playlist gets its own ID, in the context and in the state.
	 *
	 * @ticket 66587
	 */
	public function test_should_give_each_playlist_its_own_id() {
		$first  = $this->render( array(), array( array( 'src' => 'https://example.org/one.mp3' ) ) );
		$second = $this->render( array(), array( array( 'src' => 'https://example.org/two.mp3' ) ) );

		$this->assertNotSame( $this->get_context( $first )['playlistId'], $this->get_context( $second )['playlistId'] );
		$this->assertSame( 'https://example.org/one.mp3', $this->get_tracks_state( $first )['track-0']['url'] );
		$this->assertSame( 'https://example.org/two.mp3', $this->get_tracks_state( $second )['track-0']['url'] );
	}

	/**
	 * Tests the waveform player that follows the opening tag of the figure.
	 *
	 * @ticket 66587
	 */
	public function test_should_add_the_waveform_player_after_the_opening_tag_of_the_figure() {
		$output = $this->render();
		$player = $this->get_element( $output, array( 'class_name' => 'wp-block-playlist__waveform-player' ) );

		$this->assertMatchesRegularExpression( '/^<figure [^>]*><div class="wp-block-playlist__waveform-player"/', $output );
		$this->assertSame( 'callbacks.initWaveformPlayer', $player['data-wp-watch'] );
		$this->assertSame( 'Play', $player['data-label-play'] );
		$this->assertSame( 'Pause', $player['data-label-pause'] );
		$this->assertSame( 'Seek', $player['data-label-seek'] );
		$this->assertSame( '%1$s of %2$s', $player['data-label-seek-value'] );
		$this->assertArrayNotHasKey( 'data-waveform-player-color', $player );
		$this->assertArrayNotHasKey( 'data-waveform-player-gradient', $player );
		$this->assertArrayNotHasKey( 'data-waveform-player-background-color', $player );
		$this->assertArrayNotHasKey( 'data-waveform-player-background-gradient', $player );
	}

	/**
	 * Tests the colors and gradients of the waveform.
	 *
	 * @ticket 66587
	 *
	 * @dataProvider data_waveform_colors
	 *
	 * @param string $attribute The name of the attribute.
	 * @param string $data_name The name of the data attribute.
	 */
	public function test_should_add_the_waveform_color_to_the_player( $attribute, $data_name ) {
		$player = $this->get_element(
			$this->render( array( $attribute => 'a"b<c' ) ),
			array( 'class_name' => 'wp-block-playlist__waveform-player' )
		);

		$this->assertSame( 'a"b<c', $player[ $data_name ], 'The value should be escaped in the markup and decoded by the parser.' );
		$this->assertCount( 1, array_filter( array_keys( $player ), static fn( $name ) => str_starts_with( $name, 'data-waveform-player-' ) ), 'Only the data attribute of the set color should be added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_waveform_colors() {
		return array(
			'color'               => array( 'waveformColor', 'data-waveform-player-color' ),
			'gradient'            => array( 'waveformGradient', 'data-waveform-player-gradient' ),
			'background color'    => array( 'waveformBackgroundColor', 'data-waveform-player-background-color' ),
			'background gradient' => array( 'waveformBackgroundGradient', 'data-waveform-player-background-gradient' ),
		);
	}

	/**
	 * Tests that the colors of the waveform are escaped in the markup.
	 *
	 * @ticket 66587
	 */
	public function test_should_escape_the_waveform_color() {
		$output = $this->render( array( 'waveformColor' => '"><script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'data-waveform-player-color="&quot;&gt;&lt;script&gt;', $output );
	}

	/**
	 * Tests the IDs of the tracks in the buttons.
	 *
	 * @ticket 66587
	 */
	public function test_should_give_the_buttons_the_ids_of_the_tracks() {
		$output = $this->render(
			array(),
			array(
				array( 'src' => 'https://example.org/one.mp3' ),
				array( 'title' => 'No source' ),
				array( 'src' => 'https://example.org/two.mp3' ),
			)
		);

		$processor = new WP_HTML_Tag_Processor( $output );
		$ids       = array();

		while ( $processor->next_tag( array( 'class_name' => 'wp-block-playlist-track__button' ) ) ) {
			$ids[] = json_decode( $processor->get_attribute( 'data-wp-context' ), true );
		}

		$this->assertSame( array( array( 'trackId' => 'track-0' ), array( 'trackId' => 'track-1' ) ), $ids );
		$this->assertSame( array( 'track-0', 'track-1' ), array_keys( $this->get_tracks_state( $output ) ), 'A track without a source should not take an ID.' );
	}

	/**
	 * Tests that buttons without a track are left alone.
	 *
	 * @ticket 66587
	 */
	public function test_should_leave_the_extra_buttons_alone() {
		$content = '<figure class="wp-block-playlist"><ul>' .
			'<li><button class="wp-block-playlist-track__button">One</button></li>' .
			'<li><button class="wp-block-playlist-track__button">Two</button></li>' .
			'</ul></figure>';

		$output    = $this->render( array(), null, $content );
		$processor = new WP_HTML_Tag_Processor( $output );
		$contexts  = array();

		while ( $processor->next_tag( array( 'class_name' => 'wp-block-playlist-track__button' ) ) ) {
			$contexts[] = $processor->get_attribute( 'data-wp-context' );
		}

		$this->assertSame( array( '{"trackId":"track-0"}', null ), $contexts );
	}

	/**
	 * Tests the data of the tracks in the state.
	 *
	 * @ticket 66587
	 */
	public function test_should_add_the_tracks_to_the_state() {
		$tracks = $this->get_tracks_state(
			$this->render(
				array(),
				array(
					array(
						'src'      => 'https://example.org/one.mp3',
						'title'    => 'One',
						'artist'   => 'Artist',
						'album'    => 'Album',
						'image'    => 'https://example.org/one.jpg',
						'imageAlt' => 'Cover of one',
					),
				)
			)
		);

		$this->assertSame(
			array(
				'track-0' => array(
					'url'       => 'https://example.org/one.mp3',
					'title'     => 'One',
					'artist'    => 'Artist',
					'album'     => 'Album',
					'image'     => 'https://example.org/one.jpg',
					'imageAlt'  => 'Cover of one',
					'ariaLabel' => 'One by Artist from the album Album',
				),
			),
			$tracks
		);
	}

	/**
	 * Tests the data of a track that has only a source.
	 *
	 * @ticket 66587
	 */
	public function test_should_use_defaults_for_a_track_with_only_a_source() {
		$tracks = $this->get_tracks_state( $this->render() );

		$this->assertSame(
			array(
				'track-0' => array(
					'url'       => 'https://example.org/one.mp3',
					'title'     => 'Unknown title',
					'artist'    => '',
					'album'     => '',
					'image'     => '',
					'imageAlt'  => '',
					'ariaLabel' => 'Unknown title',
				),
			),
			$tracks
		);
	}

	/**
	 * Tests the label of a track.
	 *
	 * @ticket 66587
	 *
	 * @dataProvider data_aria_labels
	 *
	 * @param array  $track    The attributes of the track.
	 * @param string $expected The expected label.
	 */
	public function test_should_label_the_track( $track, $expected ) {
		$tracks = $this->get_tracks_state( $this->render( array(), array( array_merge( array( 'src' => 'https://example.org/one.mp3' ), $track ) ) ) );

		$this->assertSame( $expected, $tracks['track-0']['ariaLabel'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_aria_labels() {
		return array(
			'title only'       => array( array( 'title' => 'One' ), 'One' ),
			'title and artist' => array(
				array(
					'title'  => 'One',
					'artist' => 'Artist',
				),
				'One',
			),
			'title and album'  => array(
				array(
					'title' => 'One',
					'album' => 'Album',
				),
				'One',
			),
			'all three'        => array(
				array(
					'title'  => 'One',
					'artist' => 'Artist',
					'album'  => 'Album',
				),
				'One by Artist from the album Album',
			),
			'empty title'      => array( array( 'title' => '' ), 'Unknown title' ),
			'no title'         => array(
				array(
					'artist' => 'Artist',
					'album'  => 'Album',
				),
				'Unknown title by Artist from the album Album',
			),
		);
	}

	/**
	 * Tests that tags are stripped and URLs are cleaned, without double encoding.
	 *
	 * @ticket 66587
	 */
	public function test_should_clean_the_data_of_the_tracks() {
		$tracks = $this->get_tracks_state(
			$this->render(
				array(),
				array(
					array(
						'src'      => 'javascript:alert(1)',
						'title'    => '<b>One</b> & <i>Two</i>',
						'artist'   => '<script>alert(1)</script>Artist',
						'album'    => '<em>Album</em>',
						'image'    => 'javascript:alert(2)',
						'imageAlt' => '<u>Alt</u>',
					),
				)
			)
		);

		$this->assertSame( '', $tracks['track-0']['url'] );
		$this->assertSame( 'One & Two', $tracks['track-0']['title'] );
		$this->assertSame( 'Artist', $tracks['track-0']['artist'] );
		$this->assertSame( 'Album', $tracks['track-0']['album'] );
		$this->assertSame( '', $tracks['track-0']['image'] );
		$this->assertSame( 'Alt', $tracks['track-0']['imageAlt'] );
		$this->assertSame( 'One & Two by Artist from the album Album', $tracks['track-0']['ariaLabel'] );
	}

	/**
	 * Tests that the script module of the block is enqueued.
	 *
	 * @ticket 66587
	 */
	public function test_should_enqueue_the_script_module() {
		$this->render();

		$this->assertContains( self::MODULE_ID, wp_script_modules()->get_queue() );
	}

	/**
	 * Tests that content without a figure is returned as it is, and the tracks are still in the state.
	 *
	 * @ticket 66587
	 */
	public function test_should_return_the_content_without_a_figure_as_it_is() {
		$content = '<div class="wp-block-playlist"><button class="wp-block-playlist-track__button">One</button></div>';
		$output  = $this->render( array(), null, $content );

		$this->assertSame( $content, $output );
		$this->assertCount( 1, wp_interactivity()->state( 'core/playlist' )['playlists'], 'The tracks should still be added to the state.' );
	}
}
