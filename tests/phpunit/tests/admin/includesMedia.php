<?php

/**
 * @group media
 * @group admin
 */
class Tests_Admin_includesMedia extends WP_UnitTestCase {
	/** @var \WP_Post */
	protected static $post;
	/** @var \WP_Post */
	protected static $different_post;

	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		self::$post           = $factory->post->create_and_get();
		self::$different_post = $factory->post->create_and_get();
	}

	public function wpTearDownAfterClass() {
		self::$post           = null;
		self::$different_post = null;
	}

	public function setUp() {
		parent::setUp();

		add_filter( 'pre_http_request', [ $this, 'internal_fake_download_url' ], 10, 3 );
	}

	public function tearDown() {
		parent::tearDown();

		$this->remove_added_uploads();
		remove_filter( 'pre_http_request', [ $this, 'internal_fake_download_url' ] );
	}

	/**
	 * Filters whether to preempt an HTTP request's return value. Mocking the
	 * response. Since ticket 49631
	 *
	 * @param false  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param array  $parsed_args HTTP request arguments.
	 * @param string $url The request URL.
	 *
	 * @return array
	 */
	public function internal_fake_download_url( $preempt, array $parsed_args, $url = '' ) {
		// Just need an image content string to fill the new image, keeping the same mime type.
		file_put_contents( $parsed_args['filename'], file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) );

		return [
			'response' => [
				'code' => 200,
			],
		];
	}

	/**
	 * @ticket 49631
	 */
	public function test_media_sideload_image() {
		$year  = gmdate( 'Y' );
		$month = gmdate( 'm' );

		$image_source = 'http://' . WP_TESTS_DOMAIN . '/external/source/image1.jpg';
		$image        = 'http://' . WP_TESTS_DOMAIN . "/wp-content/uploads/{$year}/{$month}/image1.jpg";
		$image_html   = addslashes( $image );

		$media_html = media_sideload_image( $image_source, self::$post->ID );

		$this->assertNotWPError( $media_html );
		$this->assertRegExp( "~<img src='$image_html' alt='' \/>~", $media_html );

		$image_source = 'http://' . WP_TESTS_DOMAIN . '/external/source/image2.jpg';
		$image        = 'http://' . WP_TESTS_DOMAIN . "/wp-content/uploads/{$year}/{$month}/image2.jpg";

		$media_src = media_sideload_image( $image_source, self::$different_post->ID, null, 'src' );

		$this->assertEquals( $image, $media_src );

		$image_source = 'http://' . WP_TESTS_DOMAIN . '/external/source/image3.jpg';

		$media_id                   = media_sideload_image( $image_source, self::$different_post->ID, null, 'id' );
		$attachment_meta_source_url = get_post_meta( $media_id, '_source_url', true );

		$this->assertInternalType( 'numeric', $media_id );
		$this->assertEquals( $attachment_meta_source_url, $image_source );
	}

}
