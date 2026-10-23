<?php

/**
 * Tests for get_media_states().
 *
 * @group admin
 * @group media
 *
 * @covers ::get_media_states
 */
class Tests_Admin_Includes_Template_GetMediaStates extends WP_UnitTestCase {

	/**
	 * Original value of the `$_wp_theme_features` global.
	 *
	 * @var array
	 */
	private $orig_theme_features;

	/**
	 * Attachment used by the tests.
	 *
	 * @var WP_Post
	 */
	private $attachment;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->orig_theme_features = $GLOBALS['_wp_theme_features'];

		remove_theme_support( 'custom-header' );
		remove_theme_support( 'custom-background' );

		$this->attachment = get_post(
			self::factory()->attachment->create_object(
				'media-state.jpg',
				0,
				array( 'post_mime_type' => 'image/jpeg' )
			)
		);
	}

	public function tear_down() {
		$GLOBALS['_wp_theme_features'] = $this->orig_theme_features;

		parent::tear_down();
	}

	/**
	 * Gets the URL of the test attachment.
	 *
	 * @return string Attachment URL.
	 */
	private function attachment_url() {
		return wp_get_attachment_url( $this->attachment->ID );
	}

	/**
	 * Tests that an attachment without any role has no media states.
	 *
	 * @ticket 65819
	 */
	public function test_returns_no_states_by_default() {
		add_theme_support( 'custom-header' );
		add_theme_support( 'custom-background' );

		$this->assertSame( array(), get_media_states( $this->attachment ) );
	}

	/**
	 * Tests the header image states.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_header_image_states
	 *
	 * @param bool     $header_meta_for_theme Whether the attachment is marked as a header image of the current theme.
	 * @param bool     $is_current_header     Whether the attachment is the current header image.
	 * @param bool     $theme_supports        Whether the theme supports custom headers.
	 * @param string[] $expected              Expected media states.
	 */
	public function test_header_image_states( $header_meta_for_theme, $is_current_header, $theme_supports, $expected ) {
		if ( $theme_supports ) {
			add_theme_support( 'custom-header' );
		}

		if ( $header_meta_for_theme ) {
			update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_header', get_option( 'stylesheet' ) );
		}

		if ( $is_current_header ) {
			set_theme_mod( 'header_image', $this->attachment_url() );
		}

		$this->assertSame( $expected, get_media_states( $this->attachment ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_header_image_states() {
		return array(
			'previously used header image'          => array( true, false, true, array( 'Header Image' ) ),
			'current header image'                  => array( false, true, true, array( 'Current Header Image' ) ),
			'current header image marked as header' => array( true, true, true, array( 'Current Header Image' ) ),
			'theme without custom headers'          => array( true, true, false, array() ),
		);
	}

	/**
	 * Tests that a header image of another theme is not marked as a header image.
	 *
	 * @ticket 65819
	 */
	public function test_header_image_of_another_theme() {
		add_theme_support( 'custom-header' );

		update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_header', 'another-theme' );

		$this->assertSame( array(), get_media_states( $this->attachment ) );
	}

	/**
	 * Tests the header image state when random uploaded header images are used.
	 *
	 * Runs in a separate process because get_media_states() caches the uploaded header images in a static variable.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_header_image_state_with_random_header_images() {
		add_theme_support( 'custom-header' );

		$other = get_post( self::factory()->attachment->create_object( 'other-header.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) ) );

		update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_header', get_option( 'stylesheet' ) );
		update_post_meta( $other->ID, '_wp_attachment_is_custom_header', 'another-theme' );

		set_theme_mod( 'header_image', 'random-uploaded-image' );

		$this->assertSame( array( 'Header Image' ), get_media_states( $this->attachment ), 'An uploaded header image of the current theme should be marked.' );
		$this->assertSame( array(), get_media_states( $other ), 'A header image of another theme should not be marked.' );
	}

	/**
	 * Tests the header video state.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_header_video_states
	 *
	 * @param bool     $supports_video Whether the custom header supports video.
	 * @param bool     $is_video       Whether the attachment is the header video.
	 * @param string[] $expected       Expected media states.
	 */
	public function test_header_video_state( $supports_video, $is_video, $expected ) {
		add_theme_support( 'custom-header', array( 'video' => $supports_video ) );

		$video = get_post( self::factory()->attachment->create_object( 'header-video.mp4', 0, array( 'post_mime_type' => 'video/mp4' ) ) );
		$other = get_post( self::factory()->attachment->create_object( 'other-video.mp4', 0, array( 'post_mime_type' => 'video/mp4' ) ) );

		set_theme_mod( 'header_video', $video->ID );

		$this->assertSame( $expected, get_media_states( $is_video ? $video : $other ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_header_video_states() {
		return array(
			'current header video'         => array( true, true, array( 'Current Header Video' ) ),
			'other video'                  => array( true, false, array() ),
			'header without video support' => array( false, true, array() ),
		);
	}

	/**
	 * Tests the background image states.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_background_image_states
	 *
	 * @param string|false $background_meta  Value of the background meta, or false for none.
	 * @param bool         $is_current       Whether the attachment is the current background image.
	 * @param bool         $theme_supports   Whether the theme supports custom backgrounds.
	 * @param string[]     $expected         Expected media states.
	 */
	public function test_background_image_states( $background_meta, $is_current, $theme_supports, $expected ) {
		if ( $theme_supports ) {
			add_theme_support( 'custom-background' );
		}

		if ( false !== $background_meta ) {
			update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_background', 'current' === $background_meta ? get_option( 'stylesheet' ) : $background_meta );
		}

		if ( $is_current ) {
			set_theme_mod( 'background_image', $this->attachment_url() );
		}

		$this->assertSame( $expected, get_media_states( $this->attachment ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_background_image_states() {
		return array(
			'background image'                  => array( 'current', false, true, array( 'Background Image' ) ),
			'current background image'          => array( 'current', true, true, array( 'Background Image', 'Current Background Image' ) ),
			'background image of another theme' => array( 'another-theme', true, true, array() ),
			'current background without meta'   => array( false, true, true, array() ),
			'theme without custom backgrounds'  => array( 'current', true, false, array() ),
		);
	}

	/**
	 * Tests the site icon state.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_option_values
	 *
	 * @param bool $as_string Whether the ID is read back as a string.
	 */
	public function test_site_icon_state( $as_string ) {
		update_option( 'site_icon', $this->attachment->ID );

		if ( $as_string ) {
			// Option values loaded from the database are strings.
			add_filter( 'option_site_icon', 'strval' );
		}

		$this->assertSame( array( 'Site Icon' ), get_media_states( $this->attachment ) );
	}

	/**
	 * Tests the logo state.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_option_values
	 *
	 * @param bool $as_string Whether to store the ID as a string.
	 */
	public function test_logo_state( $as_string ) {
		set_theme_mod( 'custom_logo', $as_string ? (string) $this->attachment->ID : $this->attachment->ID );

		$this->assertSame( array( 'Logo' ), get_media_states( $this->attachment ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_option_values() {
		return array(
			'integer ID' => array( false ),
			'string ID'  => array( true ),
		);
	}

	/**
	 * Tests that the site icon and logo states are not added for another attachment.
	 *
	 * @ticket 65819
	 */
	public function test_site_icon_and_logo_of_another_attachment() {
		$other = self::factory()->attachment->create_object( 'other.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );

		update_option( 'site_icon', $other );
		set_theme_mod( 'custom_logo', $other );

		$this->assertSame( array(), get_media_states( $this->attachment ) );
	}

	/**
	 * Tests that all applicable states are returned in order.
	 *
	 * @ticket 65819
	 */
	public function test_returns_all_states_in_order() {
		add_theme_support( 'custom-header' );
		add_theme_support( 'custom-background' );

		update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_header', get_option( 'stylesheet' ) );
		update_post_meta( $this->attachment->ID, '_wp_attachment_is_custom_background', get_option( 'stylesheet' ) );
		update_option( 'site_icon', $this->attachment->ID );
		set_theme_mod( 'custom_logo', $this->attachment->ID );

		$this->assertSame( array( 'Header Image', 'Background Image', 'Site Icon', 'Logo' ), get_media_states( $this->attachment ) );
	}

	/**
	 * Tests that the media states are filterable.
	 *
	 * @ticket 65819
	 */
	public function test_display_media_states_filter() {
		update_option( 'site_icon', $this->attachment->ID );

		$filter = new MockAction();
		add_filter( 'display_media_states', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'display_media_states',
			static function ( $states ) {
				$states[] = 'Custom State';

				return $states;
			},
			20
		);

		$this->assertSame( array( 'Site Icon', 'Custom State' ), get_media_states( $this->attachment ), 'The filtered states should be returned.' );
		$this->assertSame( array( array( array( 'Site Icon' ), $this->attachment ) ), $filter->get_args(), 'The filter should receive the states and the attachment.' );
	}
}
