<?php

/**
 * Tests for wp_get_attachment_id3_keys().
 *
 * @group media
 *
 * @covers ::wp_get_attachment_id3_keys
 */
class Tests_Media_WpGetAttachmentId3Keys extends WP_UnitTestCase {

	/**
	 * Attachment fixture.
	 *
	 * @var WP_Post
	 */
	private static $attachment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment = $factory->attachment->create_and_get();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_display_keys_by_default() {
		$fields = wp_get_attachment_id3_keys( self::$attachment );

		$this->assertSame(
			array(
				'artist',
				'album',
				'genre',
				'year',
				'length_formatted',
			),
			array_keys( $fields )
		);
		$this->assertSame( 'Artist', $fields['artist'] );
		$this->assertSame( 'Album', $fields['album'] );
		$this->assertSame( 'Genre', $fields['genre'] );
		$this->assertSame( 'Year', $fields['year'] );
		$this->assertSame( 'Length', $fields['length_formatted'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_base_keys_for_edit_context() {
		$fields = wp_get_attachment_id3_keys( self::$attachment, 'edit' );

		$this->assertSame(
			array(
				'artist',
				'album',
			),
			array_keys( $fields )
		);
		$this->assertSame( 'Artist', $fields['artist'] );
		$this->assertSame( 'Album', $fields['album'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_js_context_keys() {
		$fields = wp_get_attachment_id3_keys( self::$attachment, 'js' );

		$this->assertSame(
			array(
				'artist',
				'album',
				'bitrate',
				'bitrate_mode',
			),
			array_keys( $fields )
		);
		$this->assertSame( 'Bitrate', $fields['bitrate'] );
		$this->assertSame( 'Bitrate Mode', $fields['bitrate_mode'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_keys() {
		add_filter(
			'wp_get_attachment_id3_keys',
			static function ( $fields ) {
				$fields['custom'] = 'Custom';
				return $fields;
			}
		);

		$fields = wp_get_attachment_id3_keys( self::$attachment, 'edit' );

		$this->assertArrayHasKey( 'custom', $fields );
		$this->assertSame( 'Custom', $fields['custom'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_attachment_and_context() {
		$received_args = array();

		add_filter(
			'wp_get_attachment_id3_keys',
			static function ( $fields, $attachment, $context ) use ( &$received_args ) {
				$received_args = array( $attachment, $context );
				return $fields;
			},
			10,
			3
		);

		wp_get_attachment_id3_keys( self::$attachment, 'js' );

		$this->assertSame( array( self::$attachment, 'js' ), $received_args );
	}
}
