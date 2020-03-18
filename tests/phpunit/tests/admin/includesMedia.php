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

	/**
	 * @ticket 49631
	 */
	public function test_media_sideload_image() {
		$year  = gmdate( 'Y' );
		$month = gmdate( 'm' );

		$image_source = 'https://raw.githubusercontent.com/WordPress/wordpress-develop/master/tests/phpunit/data/images/canola.jpg';
		$image        = 'http://' . WP_TESTS_DOMAIN . "/wp-content/uploads/{$year}/{$month}/canola.jpg";
		$image_html   = addslashes( $image );

		$media_html = media_sideload_image( $image_source, self::$post->ID );

		$this->assertNotWPError( $media_html );
		$this->assertRegExp( "~<img src='$image_html' alt='' \/>~", $media_html );

		$image_source = 'https://raw.githubusercontent.com/WordPress/wordpress-develop/master/tests/phpunit/data/images/test-image.jpg';
		$image        = 'http://' . WP_TESTS_DOMAIN . "/wp-content/uploads/{$year}/{$month}/test-image.jpg";

		$media_src = media_sideload_image( $image_source, self::$different_post->ID, null, 'src' );

		$this->assertEquals( $image, $media_src );

		$image_source = 'https://raw.githubusercontent.com/WordPress/wordpress-develop/master/tests/phpunit/data/images/test-image-large.png';

		$media_id                   = media_sideload_image( $image_source, self::$different_post->ID, null, 'id' );
		$attachment_meta_source_url = get_post_meta( $media_id, '_source_url', true );

		$this->assertInternalType( 'numeric', $media_id );
		$this->assertEquals( $attachment_meta_source_url, $image_source );
	}

}
