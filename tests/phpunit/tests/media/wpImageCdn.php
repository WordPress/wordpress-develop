<?php

/**
 * Tests for the image CDN API.
 *
 * @group media
 * @covers WP_Image_CDN_Registry
 * @covers ::wp_register_image_cdn
 * @covers ::wp_unregister_image_cdn
 * @covers ::wp_unregister_image_cdn_for_mime_type
 * @covers ::wp_get_image_cdn_for_mime_type
 * @covers ::wp_get_image_cdn_url
 * @covers ::wp_img_tag_add_image_cdn_src
 */
class Tests_Media_WpImageCdn extends WP_UnitTestCase {

	private const CDN_URL = 'https://cdn.example.net/';

	private static int $attachment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$attachment_id       = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		self::$attachment_id = is_int( $attachment_id ) ? $attachment_id : 0;
	}

	public static function tear_down_after_class(): void {
		wp_delete_attachment( self::$attachment_id, true );
		parent::tear_down_after_class();
	}

	public function tear_down(): void {
		wp_unregister_image_cdn( 'cdn-a' );
		wp_unregister_image_cdn( 'cdn-b' );
		parent::tear_down();
	}

	/**
	 * Returns an uploads URL for a file.
	 *
	 * @param string $path Path relative to the uploads directory.
	 * @return string The URL.
	 */
	private function uploads_url( string $path ): string {
		return wp_get_upload_dir()['baseurl'] . '/' . $path;
	}

	/**
	 * @ticket 66627
	 */
	public function test_register_maps_each_mime_type(): void {
		$this->assertTrue(
			wp_register_image_cdn(
				'cdn-a',
				array(
					'image/jpeg' => 'https://a.example.net',
					'image/webp' => 'https://a.example.net/webp/',
				)
			)
		);

		$this->assertSame(
			array(
				'handle'   => 'cdn-a',
				'base_url' => 'https://a.example.net/',
			),
			wp_get_image_cdn_for_mime_type( 'image/jpeg' ),
			'The base URL should be given a trailing slash.'
		);
		$this->assertSame( 'https://a.example.net/webp/', wp_get_image_cdn_for_mime_type( 'image/webp' )['base_url'] ?? null );
		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/png' ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_last_registered_cdn_wins_for_a_mime_type(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => 'https://a.example.net/' ) );
		wp_register_image_cdn( 'cdn-b', array( 'image/jpeg' => 'https://b.example.net/' ) );

		$this->assertSame( 'cdn-b', wp_get_image_cdn_for_mime_type( 'image/jpeg' )['handle'] ?? null );
	}

	/**
	 * @ticket 66627
	 */
	public function test_registering_a_handle_again_replaces_its_mappings(): void {
		wp_register_image_cdn(
			'cdn-a',
			array(
				'image/jpeg' => 'https://a.example.net/',
				'image/webp' => 'https://a.example.net/',
			)
		);
		wp_register_image_cdn( 'cdn-a', array( 'image/png' => 'https://a.example.net/' ) );

		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/jpeg' ) );
		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/webp' ) );
		$this->assertNotNull( wp_get_image_cdn_for_mime_type( 'image/png' ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_unregister_removes_every_mapping_for_a_handle(): void {
		wp_register_image_cdn(
			'cdn-a',
			array(
				'image/jpeg' => 'https://a.example.net/',
				'image/webp' => 'https://a.example.net/',
			)
		);
		wp_register_image_cdn( 'cdn-b', array( 'image/png' => 'https://b.example.net/' ) );

		$this->assertTrue( wp_unregister_image_cdn( 'cdn-a' ) );
		$this->assertFalse( wp_unregister_image_cdn( 'cdn-a' ), 'Unregistering again should report nothing removed.' );
		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/jpeg' ) );
		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/webp' ) );
		$this->assertSame( 'cdn-b', wp_get_image_cdn_for_mime_type( 'image/png' )['handle'] ?? null );
	}

	/**
	 * @ticket 66627
	 */
	public function test_unregister_for_mime_type(): void {
		wp_register_image_cdn(
			'cdn-a',
			array(
				'image/jpeg' => 'https://a.example.net/',
				'image/webp' => 'https://a.example.net/',
			)
		);

		$this->assertTrue( wp_unregister_image_cdn_for_mime_type( 'image/jpeg' ) );
		$this->assertFalse( wp_unregister_image_cdn_for_mime_type( 'image/jpeg' ) );
		$this->assertNull( wp_get_image_cdn_for_mime_type( 'image/jpeg' ) );
		$this->assertNotNull( wp_get_image_cdn_for_mime_type( 'image/webp' ) );
	}

	/**
	 * @ticket 66627
	 * @expectedIncorrectUsage WP_Image_CDN_Registry::register
	 *
	 * @dataProvider data_register_rejects_invalid_mappings
	 *
	 * @param string               $handle     CDN handle.
	 * @param array<string, string> $mime_types Mime type mappings.
	 */
	public function test_register_rejects_invalid_mappings( string $handle, array $mime_types ): void {
		$this->assertFalse( wp_register_image_cdn( $handle, $mime_types ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>}>
	 */
	public function data_register_rejects_invalid_mappings(): array {
		return array(
			'empty handle'        => array( '', array( 'image/jpeg' => 'https://a.example.net/' ) ),
			'non-image mime type' => array( 'cdn-a', array( 'video/mp4' => 'https://a.example.net/' ) ),
			'relative base URL'   => array( 'cdn-a', array( 'image/jpeg' => '/cdn/' ) ),
			'unsupported scheme'  => array( 'cdn-a', array( 'image/jpeg' => 'ftp://a.example.net/' ) ),
			'javascript base URL' => array( 'cdn-a', array( 'image/jpeg' => 'javascript:alert(1)' ) ),
		);
	}

	/**
	 * @ticket 66627
	 */
	public function test_cdn_url_replaces_uploads_base_url(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$this->assertSame( self::CDN_URL . '2026/11/photo.jpg', wp_get_image_cdn_url( $this->uploads_url( '2026/11/photo.jpg' ) ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_cdn_url_ignores_the_scheme_of_the_image_url(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$url = set_url_scheme( $this->uploads_url( '2026/11/photo.jpg' ), 'https' );

		$this->assertSame( self::CDN_URL . '2026/11/photo.jpg', wp_get_image_cdn_url( $url ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_cdn_url_uses_the_given_mime_type(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/webp' => self::CDN_URL ) );

		$url = $this->uploads_url( 'photo.jpg' );

		$this->assertSame( $url, wp_get_image_cdn_url( $url ), 'The extension should be used when no mime type is given.' );
		$this->assertSame( self::CDN_URL . 'photo.jpg', wp_get_image_cdn_url( $url, 'image/webp' ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_cdn_url_leaves_other_urls_unchanged(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$this->assertSame( 'https://elsewhere.example.org/photo.jpg', wp_get_image_cdn_url( 'https://elsewhere.example.org/photo.jpg' ), 'URLs outside the uploads directory should not change.' );
		$this->assertSame( $this->uploads_url( 'photo.png' ), wp_get_image_cdn_url( $this->uploads_url( 'photo.png' ) ), 'Mime types without a CDN should not change.' );
	}

	/**
	 * @ticket 66627
	 */
	public function test_cdn_url_is_filterable(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$args = array();
		add_filter(
			'wp_image_cdn_url',
			static function ( string $cdn_url, string $url, string $mime_type, string $handle ) use ( &$args ): string {
				$args = array( $url, $mime_type, $handle );
				return add_query_arg( 'q', '80', $cdn_url );
			},
			10,
			4
		);

		$url = $this->uploads_url( 'photo.jpg' );

		$this->assertSame( self::CDN_URL . 'photo.jpg?q=80', wp_get_image_cdn_url( $url ) );
		$this->assertSame( array( $url, 'image/jpeg', 'cdn-a' ), $args );
	}

	/**
	 * @ticket 66627
	 */
	public function test_attachment_url_uses_cdn(): void {
		$local_url = wp_get_attachment_url( self::$attachment_id );

		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$this->assertIsString( $local_url );
		$this->assertSame( wp_get_image_cdn_url( $local_url ), wp_get_attachment_url( self::$attachment_id ) );
		$this->assertStringStartsWith( self::CDN_URL, (string) wp_get_attachment_image_url( self::$attachment_id, 'medium' ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_srcset_uses_cdn(): void {
		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$srcset = wp_get_attachment_image_srcset( self::$attachment_id, 'full' );

		$this->assertIsString( $srcset );
		foreach ( explode( ', ', $srcset ) as $candidate ) {
			$this->assertStringStartsWith( self::CDN_URL, $candidate );
		}
	}

	/**
	 * @ticket 66627
	 */
	public function test_content_image_src_uses_cdn(): void {
		$local_url = (string) wp_get_attachment_image_url( self::$attachment_id, 'full' );
		$content   = sprintf( '<img src="%s" class="wp-image-%d">', esc_url( $local_url ), self::$attachment_id );

		wp_register_image_cdn( 'cdn-a', array( 'image/jpeg' => self::CDN_URL ) );

		$filtered = wp_filter_content_tags( $content );

		$processor = new WP_HTML_Tag_Processor( $filtered );
		$this->assertTrue( $processor->next_tag( 'IMG' ) );
		$this->assertSame( wp_get_image_cdn_url( $local_url ), $processor->get_attribute( 'src' ) );
		$this->assertStringStartsWith( self::CDN_URL, (string) $processor->get_attribute( 'srcset' ) );
	}

	/**
	 * @ticket 66627
	 */
	public function test_content_image_unchanged_without_cdn(): void {
		$image = '<img src="' . esc_url( $this->uploads_url( 'photo.jpg' ) ) . '">';

		$this->assertSame( $image, wp_img_tag_add_image_cdn_src( $image ) );
	}
}
