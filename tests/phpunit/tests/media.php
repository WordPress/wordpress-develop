<?php

/**
 * @group media
 * @group shortcode
 */
class Tests_Media extends WP_UnitTestCase {

	const CAPTION           = 'A simple caption.';
	const ALTERNATE_CAPTION = 'Alternate caption.';

	const HTML_CONTENT = <<<'CAP'
A <strong class='classy'>bolded</strong> <em>caption</em> with a <a href="#">link</a>.
CAP;
	const IMG_CONTENT  = <<<'CAP'
<img src="pic.jpg" id='anId' alt="pic"/>
CAP;

	const IMG_NAME = 'image.jpg';
	const IMG_URL  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . self::IMG_NAME;
	const IMG_META = array(
		'width'  => 100,
		'height' => 100,
		'sizes'  => '',
	);

	protected static $large_id;
	protected static $_sizes;
	protected static $large_filename = 'test-image-large.jpg';
	protected static $post_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$_sizes                          = wp_get_additional_image_sizes();
		$GLOBALS['_wp_additional_image_sizes'] = array();

		$filename       = DIR_TESTDATA . '/images/' . self::$large_filename;
		self::$large_id = $factory->attachment->create_upload_object( $filename );

		$post_statuses = array( 'publish', 'future', 'draft', 'auto-draft', 'trash' );
		foreach ( $post_statuses as $post_status ) {
			$date = '';
			if ( 'future' === $post_status ) {
				date_format( date_create( '+1 year' ), 'Y-m-d H:i:s' );
			}

			self::$post_ids[ $post_status ] = $factory->post->create(
				array(
					'post_status' => 'trash' === $post_status ? 'publish' : $post_status,
					'post_date'   => $date,
					'post_name'   => "$post_status-post",
				)
			);

			// Attachments without media.
			self::$post_ids[ "$post_status-attachment" ] = $factory->attachment->create_object(
				array(
					'post_parent' => self::$post_ids[ $post_status ],
					'post_status' => 'inherit',
					'post_name'   => "$post_status-attachment",
					'post_date'   => $date,
				)
			);
		}

		// Trash the trash post.
		wp_trash_post( self::$post_ids['trash'] );
	}

	public static function wpTearDownAfterClass() {
		$GLOBALS['_wp_additional_image_sizes'] = self::$_sizes;
	}

	public static function tear_down_after_class() {
		wp_delete_attachment( self::$large_id, true );
		parent::tear_down_after_class();
	}

	/**
	 * Ensures that the static content media count, fetchpriority element flag and related filter are reset between tests.
	 */
	public function tear_down() {
		global $_wp_current_template_id, $_wp_current_template_content;
		unset( $_wp_current_template_id, $_wp_current_template_content );

		parent::tear_down();

		$this->reset_content_media_count();
		$this->reset_omit_loading_attr_filter();
		$this->reset_high_priority_element_flag();
	}

	public function test_img_caption_shortcode_added() {
		global $shortcode_tags;
		$this->assertSame( 'img_caption_shortcode', $shortcode_tags['caption'] );
		$this->assertSame( 'img_caption_shortcode', $shortcode_tags['wp_caption'] );
	}

	public function test_img_caption_shortcode_with_empty_params() {
		$result = img_caption_shortcode( array() );
		$this->assertSame( '', $result );
	}

	/**
	 * @ticket 33981
	 */
	public function test_img_caption_shortcode_with_empty_params_but_content() {
		$result = img_caption_shortcode( array(), self::CAPTION );
		$this->assertSame( self::CAPTION, $result );
	}

	/**
	 * @ticket 33981
	 */
	public function test_img_caption_shortcode_short_circuit_filter() {
		add_filter( 'img_caption_shortcode', array( $this, 'return_alt_caption' ) );

		$result = img_caption_shortcode( array(), self::CAPTION );
		$this->assertSame( self::ALTERNATE_CAPTION, $result );
	}

	/**
	 * Filter used in test_img_caption_shortcode_short_circuit_filter()
	 */
	public function return_alt_caption() {
		return self::ALTERNATE_CAPTION;
	}

	/**
	 * @ticket 33981
	 */
	public function test_img_caption_shortcode_empty_width() {
		$result = img_caption_shortcode(
			array(
				'width' => 0,
			),
			self::CAPTION
		);
		$this->assertSame( self::CAPTION, $result );
	}

	/**
	 * @ticket 33981
	 */
	public function test_img_caption_shortcode_empty_caption() {
		$result = img_caption_shortcode(
			array(
				'caption' => '',
			)
		);
		$this->assertSame( '', $result );
	}

	/**
	 * @ticket 33981
	 */
	public function test_img_caption_shortcode_empty_caption_and_content() {
		$result = img_caption_shortcode(
			array(
				'caption' => '',
			),
			self::CAPTION
		);
		$this->assertSame( self::CAPTION, $result );
	}

	public function test_img_caption_shortcode_with_old_format() {
		$result = img_caption_shortcode(
			array(
				'width'   => 20,
				'caption' => self::CAPTION,
			)
		);

		$this->assertSame( 2, substr_count( $result, 'wp-caption' ) );
		$this->assertSame( 1, substr_count( $result, 'alignnone' ) );
		$this->assertSame( 1, substr_count( $result, self::CAPTION ) );

		if ( current_theme_supports( 'html5', 'caption' ) ) {
			$this->assertSame( 1, substr_count( $result, 'width: 20' ) );
		} else {
			$this->assertSame( 1, substr_count( $result, 'width: 30' ) );
		}
	}

	public function test_img_caption_shortcode_with_old_format_id_and_align() {
		$result = img_caption_shortcode(
			array(
				'width'   => 20,
				'caption' => self::CAPTION,
				'id'      => '"myId',
				'align'   => '&myAlignment',
			)
		);
		$this->assertSame( 1, substr_count( $result, 'wp-caption &amp;myAlignment' ) );
		$this->assertSame( 1, substr_count( $result, 'id="myId"' ) );
		$this->assertSame( 1, substr_count( $result, self::CAPTION ) );
	}

	public function test_img_caption_shortcode_with_old_format_and_class() {
		$result = img_caption_shortcode(
			array(
				'width'   => 20,
				'class'   => 'some-class another-class',
				'caption' => self::CAPTION,
			)
		);
		$this->assertSame( 1, substr_count( $result, 'wp-caption alignnone some-class another-class' ) );
	}

	public function test_new_img_caption_shortcode_with_html_caption() {
		$result = img_caption_shortcode(
			array(
				'width'   => 20,
				'caption' => self::HTML_CONTENT,
			)
		);

		$this->assertSame( 1, substr_count( $result, self::HTML_CONTENT ) );
	}

	public function test_new_img_caption_shortcode_new_format() {
		$result       = img_caption_shortcode(
			array( 'width' => 20 ),
			self::IMG_CONTENT . self::HTML_CONTENT
		);
		$img_preg     = preg_quote( self::IMG_CONTENT );
		$content_preg = preg_quote( self::HTML_CONTENT );

		$this->assertSame( 1, preg_match_all( "~{$img_preg}.*wp-caption-text~", $result ) );
		$this->assertSame( 1, preg_match_all( "~wp-caption-text.*{$content_preg}~", $result ) );
	}

	public function test_new_img_caption_shortcode_new_format_and_linked_image() {
		$linked_image = "<a href='#'>" . self::IMG_CONTENT . '</a>';
		$result       = img_caption_shortcode(
			array( 'width' => 20 ),
			$linked_image . self::HTML_CONTENT
		);
		$img_preg     = preg_quote( $linked_image );
		$content_preg = preg_quote( self::HTML_CONTENT );

		$this->assertSame( 1, preg_match_all( "~{$img_preg}.*wp-caption-text~", $result ) );
		$this->assertSame( 1, preg_match_all( "~wp-caption-text.*{$content_preg}~", $result ) );
	}

	public function test_new_img_caption_shortcode_new_format_and_linked_image_with_newline() {
		$linked_image = "<a href='#'>" . self::IMG_CONTENT . '</a>';
		$result       = img_caption_shortcode(
			array( 'width' => 20 ),
			$linked_image . "\n\n" . self::HTML_CONTENT
		);
		$img_preg     = preg_quote( $linked_image );
		$content_preg = preg_quote( self::HTML_CONTENT );

		$this->assertSame( 1, preg_match_all( "~{$img_preg}.*wp-caption-text~", $result ) );
		$this->assertSame( 1, preg_match_all( "~wp-caption-text.*{$content_preg}~", $result ) );
	}

	/**
	 * @ticket 34595
	 */
	public function test_img_caption_shortcode_has_aria_describedby() {
		$result = img_caption_shortcode(
			array(
				'width' => 20,
				'id'    => 'myId',
			),
			self::IMG_CONTENT . self::HTML_CONTENT
		);

		$this->assertSame( 1, substr_count( $result, 'aria-describedby="caption-myId"' ) );
	}

	public function test_add_remove_oembed_provider() {
		wp_oembed_add_provider( 'http://foo.bar/*', 'http://foo.bar/oembed' );
		$this->assertTrue( wp_oembed_remove_provider( 'http://foo.bar/*' ) );
		$this->assertFalse( wp_oembed_remove_provider( 'http://foo.bar/*' ) );
	}

	/**
	 * @ticket 23776
	 */
	public function test_autoembed_empty() {
		global $wp_embed;

		$content = '';

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $content, $result );
	}

	/**
	 * @ticket 23776
	 *
	 * @group external-http
	 */
	public function test_autoembed_no_paragraphs_around_urls() {
		global $wp_embed;

		$content = <<<EOF
$ my command
First line.

http://example.com/1/
http://example.com/2/
Last line.

<pre>http://some.link/
http://some.other.link/</pre>
EOF;

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $content, $result );
	}

	public function data_autoembed() {
		return array(

			// Should embed.
			array(
				'https://w.org',
				'[embed]',
			),
			array(
				'test
 https://w.org
test',
				'test
 [embed]
test',
			),
			array(
				'<p class="test">https://w.org</p>',
				'<p class="test">[embed]</p>',
			),
			array(
				'<p> https://w.org </p>',
				'<p> [embed] </p>',
			),
			array(
				'<p>test
https://w.org
test</p>',
				'<p>test
[embed]
test</p>',
			),
			array(
				'<p>https://w.org
</p>',
				'<p>[embed]
</p>',
			),

			// Should NOT embed.
			array(
				'test https://w.org</p>',
			),
			array(
				'<span>https://w.org</a>',
			),
			array(
				'<pre>https://w.org
</p>',
			),
			array(
				'<a href="https://w.org">
https://w.org</a>',
			),
		);
	}

	/**
	 * @dataProvider data_autoembed
	 */
	public function test_autoembed( $content, $result = null ) {
		$wp_embed = new Test_Autoembed();

		$this->assertSame( $wp_embed->autoembed( $content ), $result ? $result : $content );
	}

	public function test_wp_prepare_attachment_for_js() {
		// Attachment without media.
		$id   = wp_insert_attachment(
			array(
				'post_status'           => 'publish',
				'post_title'            => 'Prepare',
				'post_content_filtered' => 'Prepare',
				'post_type'             => 'post',
			)
		);
		$post = get_post( $id );

		$prepped = wp_prepare_attachment_for_js( $post );
		$this->assertIsArray( $prepped );
		$this->assertSame( 0, $prepped['uploadedTo'] );
		$this->assertSame( '', $prepped['mime'] );
		$this->assertSame( '', $prepped['type'] );
		$this->assertSame( '', $prepped['subtype'] );
		// #21963, there will be a GUID always, so there will be a URL.
		$this->assertNotEquals( '', $prepped['url'] );
		$this->assertSame( site_url( 'wp-includes/images/media/default.svg' ), $prepped['icon'] );

		// Fake a mime.
		$post->post_mime_type = 'image/jpeg';
		$prepped              = wp_prepare_attachment_for_js( $post );
		$this->assertSame( 'image/jpeg', $prepped['mime'] );
		$this->assertSame( 'image', $prepped['type'] );
		$this->assertSame( 'jpeg', $prepped['subtype'] );

		// Fake a mime without a slash. See #WP22532.
		$post->post_mime_type = 'image';
		$prepped              = wp_prepare_attachment_for_js( $post );
		$this->assertSame( 'image', $prepped['mime'] );
		$this->assertSame( 'image', $prepped['type'] );
		$this->assertSame( '', $prepped['subtype'] );

		// Test that if author is not found, we return "(no author)" as `display_name`.
		// The previously used test post contains no author, so we can reuse it.
		$this->assertSame( '(no author)', $prepped['authorName'] );

		// Test that if author has HTML entities in display_name, they're decoded correctly.
		$html_entity_author = self::factory()->user->create(
			array(
				'display_name' => 'You &amp; Me',
			)
		);
		$post->post_author  = $html_entity_author;
		$prepped            = wp_prepare_attachment_for_js( $post );
		$this->assertSame( 'You & Me', $prepped['authorName'] );
	}

	/**
	 * @ticket 38965
	 */
	public function test_wp_prepare_attachment_for_js_without_image_sizes() {
		// Create the attachment post.
		$id = wp_insert_attachment(
			array(
				'post_title'     => 'Attachment Title',
				'post_type'      => 'attachment',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'guid'           => 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test-image.jpg',
			)
		);

		// Add attachment metadata without sizes.
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 50,
				'height' => 50,
				'file'   => 'test-image.jpg',
			)
		);

		$prepped = wp_prepare_attachment_for_js( get_post( $id ) );

		$this->assertArrayHasKey( 'sizes', $prepped );
	}

	/**
	 * @ticket 19067
	 * @expectedDeprecated wp_convert_bytes_to_hr
	 */
	public function test_wp_convert_bytes_to_hr() {
		$kb = 1024;
		$mb = $kb * 1024;
		$gb = $mb * 1024;
		$tb = $gb * 1024;

		// Test if boundaries are correct.
		$this->assertSame( '1TB', wp_convert_bytes_to_hr( $tb ) );
		$this->assertSame( '1GB', wp_convert_bytes_to_hr( $gb ) );
		$this->assertSame( '1MB', wp_convert_bytes_to_hr( $mb ) );
		$this->assertSame( '1KB', wp_convert_bytes_to_hr( $kb ) );

		$this->assertSame( '1 TB', size_format( $tb ) );
		$this->assertSame( '1 GB', size_format( $gb ) );
		$this->assertSame( '1 MB', size_format( $mb ) );
		$this->assertSame( '1 KB', size_format( $kb ) );

		// Now some values around.
		$hr = wp_convert_bytes_to_hr( $tb + $tb / 2 + $mb );
		$this->assertEqualsWithDelta( 1.50000095367, (float) str_replace( ',', '.', $hr ), 0.0001, 'The values should be equal' );

		$hr = wp_convert_bytes_to_hr( $tb - $mb - $kb );
		$this->assertEqualsWithDelta( 1023.99902248, (float) str_replace( ',', '.', $hr ), 0.0001, 'The values should be equal' );

		$hr = wp_convert_bytes_to_hr( $gb + $gb / 2 + $mb );
		$this->assertEqualsWithDelta( 1.5009765625, (float) str_replace( ',', '.', $hr ), 0.0001, 'The values should be equal' );

		$hr = wp_convert_bytes_to_hr( $gb - $mb - $kb );
		$this->assertEqualsWithDelta( 1022.99902344, (float) str_replace( ',', '.', $hr ), 0.0001, 'The values should be equal' );

		// Edge.
		$this->assertSame( '-1B', wp_convert_bytes_to_hr( -1 ) );
		$this->assertSame( '0B', wp_convert_bytes_to_hr( 0 ) );
	}

	/**
	 * @ticket 22960
	 */
	public function test_get_attached_images() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			self::IMG_NAME,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$images = get_attached_media( 'image', $post_id );
		$this->assertEqualSets( $images, array( $attachment_id => get_post( $attachment_id ) ) );
	}

	/**
	 * @ticket 22960
	 */
	public function test_post_galleries_images() {
		$ids1      = array();
		$ids1_srcs = array();
		foreach ( range( 1, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids1[]      = $attachment_id;
			$ids1_srcs[] = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
		}

		$ids2      = array();
		$ids2_srcs = array();
		foreach ( range( 4, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids2[]      = $attachment_id;
			$ids2_srcs[] = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
		}

		$ids1_joined = implode( ',', array_slice( $ids1, 0, 3 ) );
		$ids2_joined = implode( ',', array_slice( $ids2, 3, 3 ) );

		$blob    = <<<BLOB
[gallery ids="$ids1_joined"]

[gallery ids="$ids2_joined"]
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_galleries_images( $post_id );
		$this->assertSameSetsWithIndex( $srcs, array( array_slice( $ids1_srcs, 0, 3 ), array_slice( $ids2_srcs, 3, 3 ) ) );
	}

	/**
	 * @ticket 22960
	 */
	public function test_post_gallery_images() {
		$ids1      = array();
		$ids1_srcs = array();
		foreach ( range( 1, 3 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids1[]      = $attachment_id;
			$ids1_srcs[] = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
		}

		$ids2      = array();
		$ids2_srcs = array();
		foreach ( range( 4, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids2[]      = $attachment_id;
			$ids2_srcs[] = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
		}

		$ids1_joined = implode( ',', $ids1 );
		$ids2_joined = implode( ',', $ids2 );

		$blob    = <<<BLOB
[gallery ids="$ids1_joined"]

[gallery ids="$ids2_joined"]
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSame( $srcs, $ids1_srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_block_post_gallery_images() {
		// Similar to test_post_gallery_images but with blocks instead of shortcodes
		$ids      = array();
		$imgs     = array();
		$ids_srcs = array();
		foreach ( range( 1, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids[]      = $attachment_id;
			$url        = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
			$ids_srcs[] = $url;
			$imgs[]     = '<figure><img src="' . $url . '" data-id="' . $i . '" /></figure>';
		}

		$imgs1_joined = implode( "\n", array_slice( $imgs, 0, 3 ) );
		$imgs2_joined = implode( "\n", array_slice( $imgs, 3, 3 ) );

		$blob    = <<<BLOB
<!-- wp:gallery -->
$imgs1_joined
<!-- /wp:gallery -->
<!-- wp:gallery -->
$imgs2_joined
<!-- /wp:gallery -->
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSameSetsWithIndex( array_slice( $ids_srcs, 0, 3 ), $srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_block_post_gallery_images_json() {
		// Similar to test_block_post_gallery_images, with IDs in the json blob
		$ids      = array();
		$imgs     = array();
		$ids_srcs = array();
		foreach ( range( 1, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids[]      = $attachment_id;
			$url        = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
			$ids_srcs[] = $url;
			$imgs[]     = '<figure><img src="' . $url . '" data-id="' . $i . '" /></figure>';

		}

		$ids1_joined = implode( ',', array_slice( $ids, 0, 3 ) );
		$ids2_joined = implode( ',', array_slice( $ids, 3, 3 ) );

		$blob    = <<<BLOB
<!-- wp:gallery {"ids":[$ids1_joined]} -->
<!-- /wp:gallery -->

<!-- wp:gallery {"ids":[$ids2_joined]} -->
<!-- /wp:gallery -->
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSameSetsWithIndex( array_slice( $ids_srcs, 0, 3 ), $srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_mixed_post_gallery_images() {
		// Similar to test_post_gallery_images but with a shortcode and a block in the same post
		$ids      = array();
		$imgs     = array();
		$ids_srcs = array();
		foreach ( range( 1, 6 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids[]      = $attachment_id;
			$url        = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
			$ids_srcs[] = $url;
			$imgs[]     = '<figure><img src="' . $url . '" data-id="' . $i . '" /></figure>';
		}

		$ids1_joined  = implode( "\n", array_slice( $ids, 0, 3 ) );
		$ids2_joined  = implode( "\n", array_slice( $ids, 3, 3 ) );
		$imgs2_joined = implode( "\n", array_slice( $imgs, 3, 3 ) );

		$blob    = <<<BLOB
[gallery ids="$ids1_joined"]

[gallery ids="$ids2_joined"]
<!-- wp:gallery -->
$imgs2_joined
<!-- /wp:gallery -->
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSameSetsWithIndex( array_slice( $ids_srcs, 0, 3 ), $srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_block_inner_post_gallery_images() {
		// Make sure get_post_gallery_images() works with gallery blocks that are nested inside something else
		$ids      = array();
		$imgs     = array();
		$ids_srcs = array();
		foreach ( range( 1, 3 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids[]      = $attachment_id;
			$url        = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
			$ids_srcs[] = $url;
			$imgs[]     = '<figure><img src="' . $url . '" data-id="' . $i . '" /></figure>';

		}

		$imgs_joined = implode( "\n", $imgs );

		$blob    = <<<BLOB
<!-- wp:columns -->
<!-- wp:column -->
<!-- wp:gallery -->
$imgs_joined
<!-- /wp:gallery -->
<!-- /wp:column -->
<!-- /wp:columns -->
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSameSetsWithIndex( $ids_srcs, $srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_block_post_gallery_innerblock_images() {
		// Make sure get_post_gallery_images() works with new version of gallery block with nested image blocks.
		$ids      = array();
		$imgs     = array();
		$ids_srcs = array();
		foreach ( range( 1, 3 ) as $i ) {
			$attachment_id = self::factory()->attachment->create_object(
				"image$i.jpg",
				0,
				array(
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				)
			);
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), self::IMG_META );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$ids[]      = $attachment_id;
			$url        = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . "image$i.jpg";
			$ids_srcs[] = $url;
			$imgs[]     = '<!-- wp:image {"id":' . $attachment_id . ',"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . $url . '" /></figure><!-- /wp:image -->';

		}

		$imgs_joined = implode( "\n", $imgs );

		$blob    = <<<BLOB
<!-- wp:gallery -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped">
$imgs_joined
</figure>
<!-- /wp:gallery -->
BLOB;
		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );
		$srcs    = get_post_gallery_images( $post_id );
		$this->assertSameSetsWithIndex( $ids_srcs, $srcs );
	}

	public function test_get_media_embedded_in_content() {
		$object = <<<OBJ
<object src="this" data="that">
	<param name="value"/>
</object>
OBJ;
		$embed  = <<<EMBED
<embed src="something.mp4"/>
EMBED;
		$iframe = <<<IFRAME
<iframe src="youtube.com" width="7000" />
IFRAME;
		$audio  = <<<AUDIO
<audio preload="none">
	<source />
</audio>
AUDIO;
		$video  = <<<VIDEO
<video preload="none">
	<source />
</video>
VIDEO;

		$content = <<<CONTENT
This is a comment
$object

This is a comment
$embed

This is a comment
$iframe

This is a comment
$audio

This is a comment
$video

This is a comment
CONTENT;

		$types    = array( 'object', 'embed', 'iframe', 'audio', 'video' );
		$contents = array_values( compact( $types ) );

		$matches = get_media_embedded_in_content( $content, 'audio' );
		$this->assertSame( array( $audio ), $matches );

		$matches = get_media_embedded_in_content( $content, 'video' );
		$this->assertSame( array( $video ), $matches );

		$matches = get_media_embedded_in_content( $content, 'object' );
		$this->assertSame( array( $object ), $matches );

		$matches = get_media_embedded_in_content( $content, 'embed' );
		$this->assertSame( array( $embed ), $matches );

		$matches = get_media_embedded_in_content( $content, 'iframe' );
		$this->assertSame( array( $iframe ), $matches );

		$matches = get_media_embedded_in_content( $content, $types );
		$this->assertSame( $contents, $matches );
	}

	public function test_get_media_embedded_in_content_order() {
		$audio   = <<<AUDIO
<audio preload="none">
	<source />
</audio>
AUDIO;
		$video   = <<<VIDEO
<video preload="none">
	<source />
</video>
VIDEO;
		$content = $audio . $video;

		$matches1 = get_media_embedded_in_content( $content, array( 'audio', 'video' ) );
		$this->assertSame( array( $audio, $video ), $matches1 );

		$reversed = $video . $audio;
		$matches2 = get_media_embedded_in_content( $reversed, array( 'audio', 'video' ) );
		$this->assertSame( array( $video, $audio ), $matches2 );
	}

	/**
	 * @ticket 35367
	 */
	public function test_wp_audio_shortcode_with_empty_params() {
		$this->assertNull( wp_audio_shortcode( array() ) );
	}

	/**
	 * @ticket 35367
	 */
	public function test_wp_audio_shortcode_with_bad_attr() {
		$this->assertSame(
			'<a class="wp-embedded-audio" href="https://example.com/foo.php">https://example.com/foo.php</a>',
			wp_audio_shortcode(
				array(
					'src' => 'https://example.com/foo.php',
				)
			)
		);
	}

	/**
	 * @ticket 35367
	 */
	public function test_wp_audio_shortcode_attributes() {
		$actual = wp_audio_shortcode(
			array(
				'src' => 'https://example.com/foo.mp3',
			)
		);

		$this->assertStringContainsString( 'src="https://example.com/foo.mp3', $actual );
		$this->assertStringNotContainsString( 'loop', $actual );
		$this->assertStringNotContainsString( 'autoplay', $actual );
		$this->assertStringContainsString( 'preload="none"', $actual );
		$this->assertStringContainsString( 'class="wp-audio-shortcode"', $actual );
		$this->assertStringContainsString( 'style="width: 100%;"', $actual );

		$actual = wp_audio_shortcode(
			array(
				'src'      => 'https://example.com/foo.mp3',
				'loop'     => true,
				'autoplay' => true,
				'preload'  => true,
				'class'    => 'foobar',
				'style'    => 'padding:0;',
			)
		);

		$this->assertStringContainsString( 'src="https://example.com/foo.mp3', $actual );
		$this->assertStringContainsString( 'loop="1"', $actual );
		$this->assertStringContainsString( 'autoplay="1"', $actual );
		$this->assertStringContainsString( 'preload="1"', $actual );
		$this->assertStringContainsString( 'class="foobar"', $actual );
		$this->assertStringContainsString( 'style="padding:0;"', $actual );
	}

	/**
	 * Test [video] shortcode processing
	 */
	public function test_video_shortcode_body() {
		$width  = 720;
		$height = 480;

		$w = empty( $GLOBALS['content_width'] ) ? 640 : $GLOBALS['content_width'];
		if ( $width > $w ) {
			$width = $w;
		}

		$post_id = get_post() ? get_the_ID() : 0;

		$video = <<<VIDEO
[video width="$width" height="480" mp4="http://domain.tld/wp-content/uploads/2013/12/xyz.mp4"]
<!-- WebM/VP8 for Firefox4, Opera, and Chrome -->
<source type="video/webm" src="myvideo.webm" />
<!-- Ogg/Vorbis for older Firefox and Opera versions -->
<source type="video/ogg" src="myvideo.ogv" />
<!-- Optional: Add subtitles for each language -->
<track kind="subtitles" src="subtitles.srt" srclang="en" />
<!-- Optional: Add chapters -->
<track kind="chapters" src="chapters.srt" srclang="en" />
[/video]
VIDEO;

		$h = ceil( ( $height * $width ) / $width );

		$content = apply_filters( 'the_content', $video );

		$expected = '<div style="width: ' . $width . 'px;" class="wp-video">' .
			"<!--[if lt IE 9]><script>document.createElement('video');</script><![endif]-->\n" .
			'<video class="wp-video-shortcode" id="video-' . $post_id . '-1" width="' . $width . '" height="' . $h . '" preload="metadata" controls="controls">' .
			'<source type="video/mp4" src="http://domain.tld/wp-content/uploads/2013/12/xyz.mp4?_=1" />' .
			'<!-- WebM/VP8 for Firefox4, Opera, and Chrome --><source type="video/webm" src="myvideo.webm" />' .
			'<!-- Ogg/Vorbis for older Firefox and Opera versions --><source type="video/ogg" src="myvideo.ogv" />' .
			'<!-- Optional: Add subtitles for each language --><track kind="subtitles" src="subtitles.srt" srclang="en" />' .
			'<!-- Optional: Add chapters --><track kind="chapters" src="chapters.srt" srclang="en" />' .
			'<a href="http://domain.tld/wp-content/uploads/2013/12/xyz.mp4">' .
			"http://domain.tld/wp-content/uploads/2013/12/xyz.mp4</a></video></div>\n";

		$this->assertSame( $expected, $content );
	}

	/**
	 * @ticket 35367
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_with_empty_params() {
		$this->assertNull( wp_video_shortcode( array() ) );
	}

	/**
	 * @ticket 35367
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_with_bad_attr() {
		$this->assertSame(
			'<a class="wp-embedded-video" href="https://example.com/foo.php">https://example.com/foo.php</a>',
			wp_video_shortcode(
				array(
					'src' => 'https://example.com/foo.php',
				)
			)
		);
	}

	/**
	 * @ticket 35367
	 * @ticket 54788
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_attributes() {
		$actual = wp_video_shortcode(
			array(
				'src' => 'https://example.com/foo.mp4',
			)
		);

		$this->assertStringContainsString( 'src="https://example.com/foo.mp4', $actual );
		$this->assertStringNotContainsString( 'loop', $actual );
		$this->assertStringNotContainsString( 'autoplay', $actual );
		$this->assertStringNotContainsString( 'muted', $actual );
		$this->assertStringContainsString( 'preload="metadata"', $actual );
		$this->assertStringContainsString( 'width="640"', $actual );
		$this->assertStringContainsString( 'height="360"', $actual );
		$this->assertStringContainsString( 'class="wp-video-shortcode"', $actual );

		$actual = wp_video_shortcode(
			array(
				'src'      => 'https://example.com/foo.mp4',
				'poster'   => 'https://example.com/foo.png',
				'loop'     => true,
				'autoplay' => true,
				'muted'    => true,
				'preload'  => true,
				'width'    => 123,
				'height'   => 456,
				'class'    => 'foobar',
			)
		);

		$this->assertStringContainsString( 'src="https://example.com/foo.mp4', $actual );
		$this->assertStringContainsString( 'poster="https://example.com/foo.png', $actual );
		$this->assertStringContainsString( 'loop="1"', $actual );
		$this->assertStringContainsString( 'autoplay="1"', $actual );
		$this->assertStringContainsString( 'muted', $actual );
		$this->assertStringContainsString( 'preload="1"', $actual );
		$this->assertStringContainsString( 'width="123"', $actual );
		$this->assertStringContainsString( 'height="456"', $actual );
		$this->assertStringContainsString( 'class="foobar"', $actual );
	}

	/**
	 * @ticket 40866
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_youtube_remove_feature() {
		$actual = wp_video_shortcode(
			array(
				'src' => 'https://www.youtube.com/watch?v=72xdCU__XCk&feature=youtu.be',
			)
		);

		$this->assertStringNotContainsString( 'feature=youtu.be', $actual );
	}

	/**
	 * @ticket 40866
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_youtube_force_ssl() {
		$actual = wp_video_shortcode(
			array(
				'src' => 'http://www.youtube.com/watch?v=72xdCU__XCk',
			)
		);

		$this->assertStringContainsString( 'src="https://www.youtube.com/watch?v=72xdCU__XCk', $actual );
	}

	/**
	 * @ticket 40866
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_vimeo_force_ssl_remove_query_args() {
		$actual = wp_video_shortcode(
			array(
				'src' => 'http://vimeo.com/76979871?blah=meh',
			)
		);

		$this->assertStringContainsString( 'src="https://vimeo.com/76979871', $actual );
		$this->assertStringNotContainsString( 'blah=meh', $actual );
	}

	/**
	 * @ticket 40977
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_vimeo_adds_loop() {
		$actual = wp_video_shortcode(
			array(
				'src' => 'http://vimeo.com/76979871',
			)
		);

		$this->assertStringContainsString( 'src="https://vimeo.com/76979871?loop=0', $actual );
	}

	/**
	 * @ticket 40977
	 * @depends test_video_shortcode_body
	 */
	public function test_wp_video_shortcode_vimeo_force_adds_loop_true() {
		$actual = wp_video_shortcode(
			array(
				'src'  => 'http://vimeo.com/76979871',
				'loop' => true,
			)
		);

		$this->assertStringContainsString( 'src="https://vimeo.com/76979871?loop=1', $actual );
	}

	/**
	 * @ticket 26768
	 */
	public function test_add_image_size() {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		remove_image_size( 'test-size' );

		$this->assertArrayNotHasKey( 'test-size', $_wp_additional_image_sizes );
		add_image_size( 'test-size', 200, 600 );

		$sizes = wp_get_additional_image_sizes();

		// Clean up.
		remove_image_size( 'test-size' );

		$this->assertArrayHasKey( 'test-size', $sizes );
		$this->assertSame( 200, $sizes['test-size']['width'] );
		$this->assertSame( 600, $sizes['test-size']['height'] );
	}

	/**
	 * @ticket 26768
	 */
	public function test_remove_image_size() {
		add_image_size( 'test-size', 200, 600 );
		$this->assertTrue( has_image_size( 'test-size' ) );
		remove_image_size( 'test-size' );
		$this->assertFalse( has_image_size( 'test-size' ) );
	}

	/**
	 * @ticket 26951
	 */
	public function test_has_image_size() {
		add_image_size( 'test-size', 200, 600 );
		$this->assertTrue( has_image_size( 'test-size' ) );

		// Clean up.
		remove_image_size( 'test-size' );
	}

	/**
	 * @ticket 30346
	 */
	public function test_attachment_url_to_postid() {
		$image_path    = '2014/11/' . self::IMG_NAME;
		$attachment_id = self::factory()->attachment->create_object(
			$image_path,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_path;
		$this->assertSame( $attachment_id, attachment_url_to_postid( $image_url ) );
	}

	/**
	 * @ticket 33109
	 */
	public function test_attachment_url_to_postid_with_different_scheme() {
		$image_path    = '2014/11/' . self::IMG_NAME;
		$attachment_id = self::factory()->attachment->create_object(
			$image_path,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'https://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_path;
		$this->assertSame( $attachment_id, attachment_url_to_postid( $image_url ) );
	}

	/**
	 * @ticket 39768
	 */
	public function test_attachment_url_to_postid_should_be_case_sensitive() {
		$image_path_lower_case    = '2014/11/' . self::IMG_NAME;
		$attachment_id_lower_case = self::factory()->attachment->create_object(
			$image_path_lower_case,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_path_upper_case    = '2014/11/' . ucfirst( self::IMG_NAME );
		$attachment_id_upper_case = self::factory()->attachment->create_object(
			$image_path_upper_case,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_path_upper_case;
		$this->assertSame( $attachment_id_upper_case, attachment_url_to_postid( $image_url ) );
	}

	public function test_attachment_url_to_postid_filtered() {
		$image_path    = '2014/11/' . self::IMG_NAME;
		$attachment_id = self::factory()->attachment->create_object(
			$image_path,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		add_filter( 'upload_dir', array( $this, 'upload_dir' ) );
		$image_url = 'http://192.168.1.20.com/wp-content/uploads/' . $image_path;
		$this->assertSame( $attachment_id, attachment_url_to_postid( $image_url ) );
		remove_filter( 'upload_dir', array( $this, 'upload_dir' ) );
	}

	public function upload_dir( $dir ) {
		$dir['baseurl'] = 'http://192.168.1.20.com/wp-content/uploads';
		return $dir;
	}

	/**
	 * @ticket 31044
	 */
	public function test_attachment_url_to_postid_with_empty_url() {
		$post_id = attachment_url_to_postid( '' );
		$this->assertIsInt( $post_id );
		$this->assertSame( 0, $post_id );
	}

	/**
	 * @ticket 22768
	 */
	public function test_media_handle_upload_sets_post_excerpt() {
		$iptc_file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		// Make a copy of this file as it gets moved during the file upload.
		$tmp_name = wp_tempnam( $iptc_file );

		copy( $iptc_file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => 'test-image-iptc.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $iptc_file ),
		);

		$post_id = media_handle_upload(
			'upload',
			0,
			array(),
			array(
				'action'    => 'test_iptc_upload',
				'test_form' => false,
			)
		);

		unset( $_FILES['upload'] );

		$post = get_post( $post_id );

		// Clean up.
		wp_delete_attachment( $post_id, true );

		$this->assertSame( 'This is a comment. / Это комментарий. / Βλέπετε ένα σχόλιο.', $post->post_excerpt );
	}

	/**
	 * @ticket 37989
	 */
	public function test_media_handle_upload_expected_titles() {
		$test_file = DIR_TESTDATA . '/images/test-image.jpg';

		// Make a copy of this file as it gets moved during the file upload.
		$tmp_name = wp_tempnam( $test_file );

		copy( $test_file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => 'This is a test.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $test_file ),
		);

		$post_id = media_handle_upload(
			'upload',
			0,
			array(),
			array(
				'action'    => 'test_upload_titles',
				'test_form' => false,
			)
		);

		unset( $_FILES['upload'] );

		$post = get_post( $post_id );

		// Clean up.
		wp_delete_attachment( $post_id, true );

		$this->assertSame( 'This is a test', $post->post_title );
	}

	/**
	 * @ticket 33016
	 */
	public function test_multiline_cdata() {
		global $wp_embed;

		$content = <<<EOF
<script>// <![CDATA[
_my_function('data');
// ]]>
</script>
EOF;

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $content, $result );
	}

	/**
	 * @ticket 33016
	 */
	public function test_multiline_comment() {
		global $wp_embed;

		$content = <<<EOF
<script><!--
my_function();
// --> </script>
EOF;

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $content, $result );
	}


	/**
	 * @ticket 33016
	 *
	 * @group external-http
	 */
	public function test_multiline_comment_with_embeds() {
		$content = <<<EOF
Start.
[embed]http://www.youtube.com/embed/TEST01YRHA0[/embed]
<script><!--
my_function();
// --> </script>
http://www.youtube.com/embed/TEST02YRHA0
[embed]http://www.example.com/embed/TEST03YRHA0[/embed]
http://www.example.com/embed/TEST04YRHA0
Stop.
EOF;

		$expected = <<<EOF
<p>Start.<br />
https://youtube.com/watch?v=TEST01YRHA0<br />
<script><!--
my_function();
// --> </script><br />
https://youtube.com/watch?v=TEST02YRHA0<br />
<a href="http://www.example.com/embed/TEST03YRHA0">http://www.example.com/embed/TEST03YRHA0</a><br />
http://www.example.com/embed/TEST04YRHA0<br />
Stop.</p>

EOF;

		$result = apply_filters( 'the_content', $content );
		$this->assertSameIgnoreEOL( $expected, $result );
	}

	/**
	 * @ticket 33016
	 */
	public function filter_wp_embed_shortcode_custom( $content, $url ) {
		if ( 'https://www.example.com/?video=1' === $url ) {
			$content = '@embed URL was replaced@';
		}
		return $content;
	}

	/**
	 * @ticket 33016
	 *
	 * @group external-http
	 */
	public function test_oembed_explicit_media_link() {
		global $wp_embed;
		add_filter( 'embed_maybe_make_link', array( $this, 'filter_wp_embed_shortcode_custom' ), 10, 2 );

		$content = <<<EOF
https://www.example.com/?video=1
EOF;

		$expected = <<<EOF
@embed URL was replaced@
EOF;

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $expected, $result );

		$content = <<<EOF
<a href="https://www.example.com/?video=1">https://www.example.com/?video=1</a>
<script>// <![CDATA[
_my_function('data');
myvar = 'Hello world
https://www.example.com/?video=1
do not break this';
// ]]>
</script>
EOF;

		$result = $wp_embed->autoembed( $content );
		$this->assertSame( $content, $result );

		remove_filter( 'embed_maybe_make_link', array( $this, 'filter_wp_embed_shortcode_custom' ), 10 );
	}

	/**
	 * Tests the default output of `wp_get_attachment_image()`.
	 *
	 * @ticket 34635
	 */
	public function test_wp_get_attachment_image_defaults() {
		$image    = image_downsize( self::$large_id, 'thumbnail' );
		$expected = sprintf(
			'<img width="%1$d" height="%2$d" src="%3$s" class="attachment-thumbnail size-thumbnail" alt="" decoding="async" loading="lazy" />',
			$image[1],
			$image[2],
			$image[0]
		);

		$this->assertSame( $expected, wp_get_attachment_image( self::$large_id ) );
	}

	/**
	 * @ticket 50801
	 */
	public function test_wp_get_attachment_image_filter_output() {
		$image    = image_downsize( self::$large_id, 'thumbnail' );
		$expected = 'Override wp_get_attachment_image';

		add_filter( 'wp_get_attachment_image', array( $this, 'filter_wp_get_attachment_image' ) );
		$output = wp_get_attachment_image( self::$large_id );
		remove_filter( 'wp_get_attachment_image', array( $this, 'filter_wp_get_attachment_image' ) );

		$this->assertSame( $expected, $output );
	}

	public function filter_wp_get_attachment_image() {
		return 'Override wp_get_attachment_image';
	}

	/**
	 * Test that `wp_get_attachment_image()` returns a proper alt value.
	 *
	 * @ticket 34635
	 */
	public function test_wp_get_attachment_image_with_alt() {
		// Add test alt metadata.
		update_post_meta( self::$large_id, '_wp_attachment_image_alt', 'Some very clever alt text', true );

		$image    = image_downsize( self::$large_id, 'thumbnail' );
		$expected = sprintf(
			'<img width="%1$d" height="%2$d" src="%3$s" class="attachment-thumbnail size-thumbnail" alt="Some very clever alt text" decoding="async" loading="lazy" />',
			$image[1],
			$image[2],
			$image[0]
		);

		$this->assertSame( $expected, wp_get_attachment_image( self::$large_id ) );

		// Cleanup.
		update_post_meta( self::$large_id, '_wp_attachment_image_alt', '', true );
	}

	/**
	 * @ticket 33878
	 */
	public function test_wp_get_attachment_image_url() {
		$this->assertFalse( wp_get_attachment_image_url( 0 ) );

		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			self::IMG_NAME,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image = wp_get_attachment_image_src( $attachment_id, 'thumbnail', false );

		$this->assertSame( $image[0], wp_get_attachment_image_url( $attachment_id ) );
	}

	/**
	 * @ticket 12235
	 */
	public function test_wp_get_attachment_caption() {
		$this->assertFalse( wp_get_attachment_caption( 0 ) );

		$caption = 'This is a caption.';

		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			self::IMG_NAME,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_excerpt'   => $caption,
			)
		);

		$this->assertFalse( wp_get_attachment_caption( $post_id ) );

		$this->assertSame( $caption, wp_get_attachment_caption( $attachment_id ) );
	}

	/**
	 * @ticket 12235
	 */
	public function test_wp_get_attachment_caption_empty() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			self::IMG_NAME,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_excerpt'   => '',
			)
		);

		$this->assertSame( '', wp_get_attachment_caption( $attachment_id ) );
	}

	/**
	 * Helper function to get image size array from size "name".
	 */
	private function get_image_size_array_from_meta( $image_meta, $size_name ) {
		$array = false;

		if ( is_array( $image_meta ) ) {
			if ( 'full' === $size_name && isset( $image_meta['width'] ) && isset( $image_meta['height'] ) ) {
				$array = array( $image_meta['width'], $image_meta['height'] );
			} elseif ( isset( $image_meta['sizes'][ $size_name ]['width'] ) && isset( $image_meta['sizes'][ $size_name ]['height'] ) ) {
				$array = array( $image_meta['sizes'][ $size_name ]['width'], $image_meta['sizes'][ $size_name ]['height'] );
			}
		}

		if ( ! $array ) {
			$this->fail( sprintf( "Could not retrieve image metadata for size '%s'.", $size_name ) );
		}

		return $array;
	}

	/**
	 * Helper function to move the src image to the first position in the expected srcset string.
	 */
	private function src_first( $srcset, $src_url, $src_width ) {
		$src_string    = $src_url . ' ' . $src_width . 'w';
		$src_not_first = ', ' . $src_string;

		if ( strpos( $srcset, $src_not_first ) ) {
			$srcset = str_replace( $src_not_first, '', $srcset );
			$srcset = $src_string . ', ' . $srcset;
		}

		return $srcset;
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset() {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		$year_month      = gmdate( 'Y/m' );
		$image_meta      = wp_get_attachment_metadata( self::$large_id );
		$uploads_dir_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/';

		// Set up test cases for all expected size names.
		$intermediates = array( 'medium', 'medium_large', 'large', 'full' );

		// Add any soft crop intermediate sizes.
		foreach ( $_wp_additional_image_sizes as $name => $additional_size ) {
			if ( ! $_wp_additional_image_sizes[ $name ]['crop'] || 0 === $_wp_additional_image_sizes[ $name ]['height'] ) {
				$intermediates[] = $name;
			}
		}

		$expected = '';

		foreach ( $image_meta['sizes'] as $name => $size ) {
			// Allow the sizes that should be included so we pick up 'medium_large' in 4.4.
			if ( in_array( $name, $intermediates, true ) ) {
				$expected .= $uploads_dir_url . $year_month . '/' . $size['file'] . ' ' . $size['width'] . 'w, ';
			}
		}

		$expected = trim( $expected, ' ,' );

		foreach ( $intermediates as $int_size ) {
			$image_url  = wp_get_attachment_image_url( self::$large_id, $int_size );
			$size_array = $this->get_image_size_array_from_meta( $image_meta, $int_size );

			if ( 'full' === $int_size ) {
				// Add the full size image. Expected to be in the srcset when the full size image is used as src.
				$_expected = $uploads_dir_url . $image_meta['file'] . ' ' . $image_meta['width'] . 'w, ' . $expected;
			} else {
				$_expected = $expected;
			}

			$expected_srcset = $this->src_first( $_expected, $image_url, $size_array[0] );
			$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_url, $image_meta ) );
		}
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset_no_date_uploads() {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		// Disable date organized uploads.
		add_filter( 'upload_dir', '_upload_dir_no_subdir' );

		// Make an image.
		$filename = DIR_TESTDATA . '/images/' . self::$large_filename;
		$id       = self::factory()->attachment->create_upload_object( $filename );

		$image_meta      = wp_get_attachment_metadata( $id );
		$uploads_dir_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/';

		// Set up test cases for all expected size names.
		$intermediates = array( 'medium', 'medium_large', 'large', 'full' );

		foreach ( $_wp_additional_image_sizes as $name => $additional_size ) {
			if ( ! $_wp_additional_image_sizes[ $name ]['crop'] || 0 === $_wp_additional_image_sizes[ $name ]['height'] ) {
				$intermediates[] = $name;
			}
		}

		$expected = '';

		foreach ( $image_meta['sizes'] as $name => $size ) {
			// Allow the sizes that should be included so we pick up 'medium_large' in 4.4.
			if ( in_array( $name, $intermediates, true ) ) {
				$expected .= $uploads_dir_url . $size['file'] . ' ' . $size['width'] . 'w, ';
			}
		}

		$expected = trim( $expected, ' ,' );

		foreach ( $intermediates as $int_size ) {
			$image_urls[ $int_size ] = wp_get_attachment_image_url( $id, $int_size );
		}

		// Remove the attachment.
		wp_delete_attachment( $id, true );
		remove_filter( 'upload_dir', '_upload_dir_no_subdir' );

		foreach ( $intermediates as $int_size ) {
			$size_array = $this->get_image_size_array_from_meta( $image_meta, $int_size );
			$image_url  = $image_urls[ $int_size ];

			if ( 'full' === $int_size ) {
				// Add the full size image. Expected to be in the srcset when the full size image is used as src.
				$_expected = $uploads_dir_url . $image_meta['file'] . ' ' . $image_meta['width'] . 'w, ' . $expected;
			} else {
				$_expected = $expected;
			}

			$expected_srcset = $this->src_first( $_expected, $image_url, $size_array[0] );
			$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_url, $image_meta ) );
		}
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset_with_edits() {
		// For this test we're going to mock metadata changes from an edit.
		// Start by getting the attachment metadata.
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$image_url  = wp_get_attachment_image_url( self::$large_id, 'medium' );
		$size_array = $this->get_image_size_array_from_meta( $image_meta, 'medium' );

		// Copy hash generation method used in wp_save_image().
		$hash = 'e' . time() . rand( 100, 999 );

		$filename_base = wp_basename( self::$large_filename, '.jpg' );
		$filename_hash = "{$filename_base}-{$hash}";

		// Add the hash to the image URL.
		$image_url = str_replace( $filename_base, $filename_hash, $image_url );

		// Replace file paths for full and medium sizes with hashed versions.
		$image_meta['sizes']['medium']['file']       = str_replace( $filename_base, $filename_hash, $image_meta['sizes']['medium']['file'] );
		$image_meta['sizes']['medium_large']['file'] = str_replace( $filename_base, $filename_hash, $image_meta['sizes']['medium_large']['file'] );
		$image_meta['sizes']['large']['file']        = str_replace( $filename_base, $filename_hash, $image_meta['sizes']['large']['file'] );

		// Calculate a srcset array.
		$sizes = explode( ', ', wp_calculate_image_srcset( $size_array, $image_url, $image_meta ) );

		// Test to confirm all sources in the array include the same edit hash.
		foreach ( $sizes as $size ) {
			$this->assertStringContainsString( $hash, $size );
		}
	}

	/**
	 * @ticket 35106
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset_with_absolute_path_in_meta() {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		$year_month      = gmdate( 'Y/m' );
		$image_meta      = wp_get_attachment_metadata( self::$large_id );
		$uploads_dir_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/';

		// Set up test cases for all expected size names.
		$intermediates = array( 'medium', 'medium_large', 'large', 'full' );

		// Add any soft crop intermediate sizes.
		foreach ( $_wp_additional_image_sizes as $name => $additional_size ) {
			if ( ! $_wp_additional_image_sizes[ $name ]['crop'] || 0 === $_wp_additional_image_sizes[ $name ]['height'] ) {
				$intermediates[] = $name;
			}
		}

		$expected = '';

		foreach ( $image_meta['sizes'] as $name => $size ) {
			// Allow the sizes that should be included so we pick up 'medium_large' in 4.4.
			if ( in_array( $name, $intermediates, true ) ) {
				$expected .= $uploads_dir_url . $year_month . '/' . $size['file'] . ' ' . $size['width'] . 'w, ';
			}
		}

		$expected       = trim( $expected, ' ,' );
		$full_size_file = $image_meta['file'];

		// Prepend an absolute path to simulate a pre-2.7 upload.
		$image_meta['file'] = 'H:\home\wordpress\trunk/wp-content/uploads/' . $image_meta['file'];

		foreach ( $intermediates as $int_size ) {
			$image_url  = wp_get_attachment_image_url( self::$large_id, $int_size );
			$size_array = $this->get_image_size_array_from_meta( $image_meta, $int_size );

			if ( 'full' === $int_size ) {
				// Add the full size image. Expected to be in the srcset when the full size image is used as src.
				$_expected = $uploads_dir_url . $full_size_file . ' ' . $image_meta['width'] . 'w, ' . $expected;
			} else {
				$_expected = $expected;
			}

			$expected_srcset = $this->src_first( $_expected, $image_url, $size_array[0] );
			$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_url, $image_meta ) );
		}
	}

	/**
	 * @ticket 61690
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset_with_relative_content_url() {
		$_SERVER['HTTPS'] = 'on';

		add_filter(
			'upload_dir',
			static function ( $upload_dir ) {
				$upload_dir['baseurl'] = '/wp-content/uploads';
				return $upload_dir;
			}
		);

		$image_url  = wp_get_attachment_image_url( self::$large_id, 'medium' );
		$image_meta = wp_get_attachment_metadata( self::$large_id );

		$size_array = array( 300, 225 );

		$srcset = wp_calculate_image_srcset( $size_array, $image_url, $image_meta );

		$this->assertStringStartsWith( '/wp-content/uploads', $srcset );
	}

	/**
	 * @ticket 33641
	 */
	public function test_wp_calculate_image_srcset_false() {
		$sizes = wp_calculate_image_srcset( array( 400, 300 ), 'file.png', array() );

		// For canola.jpg we should return.
		$this->assertFalse( $sizes );
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_srcset_no_width() {
		$image_url  = wp_get_attachment_image_url( self::$large_id, 'medium' );
		$image_meta = wp_get_attachment_metadata( self::$large_id );

		$size_array = array( 0, 0 );

		$srcset = wp_calculate_image_srcset( $size_array, $image_url, $image_meta );

		// The srcset should be false.
		$this->assertFalse( $srcset );
	}

	/**
	 * @ticket 34955
	 * @ticket 33641
	 */
	public function test_wp_calculate_image_srcset_ratio_variance() {
		// Mock data for this test.
		$size_array = array( 218, 300 );
		$image_src  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test-768x1055-218x300.png';
		$image_meta = array(
			'width'  => 768,
			'height' => 1055,
			'file'   => '2015/12/test-768x1055.png',
			'sizes'  => array(
				'thumbnail'      => array(
					'file'      => 'test-768x1055-150x150.png',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium'         => array(
					'file'      => 'test-768x1055-218x300.png',
					'width'     => 218,
					'height'    => 300,
					'mime-type' => 'image/png',
				),
				'custom-600'     => array(
					'file'      => 'test-768x1055-600x824.png',
					'width'     => 600,
					'height'    => 824,
					'mime-type' => 'image/png',
				),
				'post-thumbnail' => array(
					'file'      => 'test-768x1055-768x510.png',
					'width'     => 768,
					'height'    => 510,
					'mime-type' => 'image/png',
				),
			),
		);

		$uploads_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/';

		$expected_srcset = $uploads_url . 'test-768x1055-218x300.png 218w, ' .
			$uploads_url . 'test-768x1055-600x824.png 600w, ' .
			$uploads_url . 'test-768x1055.png 768w';

		$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_src, $image_meta ) );
	}

	/**
	 * @ticket 35108
	 * @ticket 33641
	 */
	public function test_wp_calculate_image_srcset_include_src() {
		// Mock data for this test.
		$size_array = array( 2000, 1000 );
		$image_src  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test.png';
		$image_meta = array(
			'width'  => 2000,
			'height' => 1000,
			'file'   => '2015/12/test.png',
			'sizes'  => array(
				'thumbnail'    => array(
					'file'      => 'test-150x150.png',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium'       => array(
					'file'      => 'test-300x150.png',
					'width'     => 300,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium_large' => array(
					'file'      => 'test-768x384.png',
					'width'     => 768,
					'height'    => 384,
					'mime-type' => 'image/png',
				),
				'large'        => array(
					'file'      => 'test-1024x512.png',
					'width'     => 1024,
					'height'    => 512,
					'mime-type' => 'image/png',
				),
			),
		);

		$uploads_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/';

		$expected_srcset = $uploads_url . 'test.png 2000w, ' .
			$uploads_url . 'test-300x150.png 300w, ' .
			$uploads_url . 'test-768x384.png 768w, ' .
			$uploads_url . 'test-1024x512.png 1024w';

		$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_src, $image_meta ) );
	}

	/**
	 * @ticket 35480
	 */
	public function test_wp_calculate_image_srcset_corrupted_image_meta() {
		$size_array = array( 300, 150 );
		$image_src  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test-300x150.png';
		$image_meta = array(
			'width'  => 1600,
			'height' => 800,
			'file'   => '2015/12/test.png',
			'sizes'  => array(
				'thumbnail'    => array(
					'file'      => 'test-150x150.png',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium'       => array(
					'file'      => 'test-300x150.png',
					'width'     => 300,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium_large' => array(
					'file'      => 'test-768x384.png',
					'width'     => 768,
					'height'    => 384,
					'mime-type' => 'image/png',
				),
				'large'        => array(
					'file'      => 'test-1024x512.png',
					'width'     => 1024,
					'height'    => 512,
					'mime-type' => 'image/png',
				),
			),
		);

		$srcset = array(
			300  => 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test-300x150.png 300w',
			768  => 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test-768x384.png 768w',
			1024 => 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test-1024x512.png 1024w',
			1600 => 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test.png 1600w',
		);

		// No sizes array.
		$image_meta1 = $image_meta;
		unset( $image_meta1['sizes'] );
		$this->assertFalse( wp_calculate_image_srcset( $size_array, $image_src, $image_meta1 ) );

		// Sizes is string instead of array; only full size available means no srcset.
		$image_meta2          = $image_meta;
		$image_meta2['sizes'] = '';
		$this->assertFalse( wp_calculate_image_srcset( $size_array, $image_src, $image_meta2 ) );

		// File name is incorrect.
		$image_meta3         = $image_meta;
		$image_meta3['file'] = '/';
		$this->assertFalse( wp_calculate_image_srcset( $size_array, $image_src, $image_meta3 ) );

		// File name is incorrect.
		$image_meta4 = $image_meta;
		unset( $image_meta4['file'] );
		$this->assertFalse( wp_calculate_image_srcset( $size_array, $image_src, $image_meta4 ) );

		// Intermediate size is string instead of array.
		$image_meta5                          = $image_meta;
		$image_meta5['sizes']['medium_large'] = '';
		unset( $srcset[768] );
		$expected_srcset = implode( ', ', $srcset );
		$this->assertSame( $expected_srcset, wp_calculate_image_srcset( $size_array, $image_src, $image_meta5 ) );
	}

	/**
	 * @ticket 36549
	 * @ticket 33641
	 */
	public function test_wp_calculate_image_srcset_with_spaces_in_filenames() {
		// Mock data for this test.
		$image_src  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/test image-300x150.png';
		$image_meta = array(
			'width'  => 3000,
			'height' => 1500,
			'file'   => '2015/12/test image.png',
			'sizes'  => array(
				'thumbnail'    => array(
					'file'      => 'test image-150x150.png',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium'       => array(
					'file'      => 'test image-300x150.png',
					'width'     => 300,
					'height'    => 150,
					'mime-type' => 'image/png',
				),
				'medium_large' => array(
					'file'      => 'test image-768x384.png',
					'width'     => 768,
					'height'    => 384,
					'mime-type' => 'image/png',
				),
				'large'        => array(
					'file'      => 'test image-1024x512.png',
					'width'     => 1024,
					'height'    => 512,
					'mime-type' => 'image/png',
				),
			),
		);

		$uploads_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/2015/12/';

		$expected_srcset = $uploads_url . 'test%20image-300x150.png 300w, ' .
			$uploads_url . 'test%20image-768x384.png 768w, ' .
			$uploads_url . 'test%20image-1024x512.png 1024w';

		$this->assertSame( $expected_srcset, wp_calculate_image_srcset( array( 300, 150 ), $image_src, $image_meta ) );
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_get_attachment_image_srcset() {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = array( $image_meta['width'], $image_meta['height'] ); // Full size.

		$srcset = wp_get_attachment_image_srcset( self::$large_id, $size_array, $image_meta );

		$year_month  = gmdate( 'Y/m' );
		$uploads_dir = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/';

		// Set up test cases for all expected size names.
		$intermediates = array( 'medium', 'medium_large', 'large', 'full' );

		foreach ( $_wp_additional_image_sizes as $name => $additional_size ) {
			if ( ! $_wp_additional_image_sizes[ $name ]['crop'] || 0 === $_wp_additional_image_sizes[ $name ]['height'] ) {
				$intermediates[] = $name;
			}
		}

		$expected = '';

		foreach ( $image_meta['sizes'] as $name => $size ) {
			// Allow the sizes that should be included so we pick up 'medium_large' in 4.4.
			if ( in_array( $name, $intermediates, true ) ) {
				$expected .= $uploads_dir . $year_month . '/' . $size['file'] . ' ' . $size['width'] . 'w, ';
			}
		}

		$expected .= $uploads_dir . $image_meta['file'] . ' ' . $image_meta['width'] . 'w';

		$expected_srcset = $this->src_first( $expected, $uploads_dir . $image_meta['file'], $size_array[0] );

		$this->assertSame( $expected_srcset, $srcset );
	}

	/**
	 * @ticket 33641
	 */
	public function test_wp_get_attachment_image_srcset_single_srcset() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = array( 150, 150 );
		/*
		 * In our tests, thumbnails will only return a single srcset candidate,
		 * so we shouldn't return a srcset value in order to avoid unneeded markup.
		 */
		$sizes = wp_get_attachment_image_srcset( self::$large_id, $size_array, $image_meta );

		$this->assertFalse( $sizes );
	}

	/**
	 * @ticket 33641
	 */
	public function test_wp_get_attachment_image_srcset_invalidsize() {
		$image_meta    = wp_get_attachment_metadata( self::$large_id );
		$invalid_size  = 'nailthumb';
		$original_size = array( 1600, 1200 );

		$srcset = wp_get_attachment_image_srcset( self::$large_id, $invalid_size, $image_meta );

		// Expect a srcset for the original full size image to be returned.
		$expected = wp_get_attachment_image_srcset( self::$large_id, $original_size, $image_meta );

		$this->assertSame( $expected, $srcset );
	}

	/**
	 * @ticket 33641
	 */
	public function test_wp_get_attachment_image_sizes() {
		// Test sizes against the default WP sizes.
		$intermediates = array( 'thumbnail', 'medium', 'medium_large', 'large' );

		// Make sure themes aren't filtering the sizes array.
		remove_all_filters( 'wp_calculate_image_sizes' );

		foreach ( $intermediates as $int_size ) {
			$image = wp_get_attachment_image_src( self::$large_id, $int_size );

			$expected = '(max-width: ' . $image[1] . 'px) 100vw, ' . $image[1] . 'px';
			$sizes    = wp_get_attachment_image_sizes( self::$large_id, $int_size );

			$this->assertSame( $expected, $sizes );
		}
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_calculate_image_sizes() {
		// Test sizes against the default WP sizes.
		$intermediates = array( 'thumbnail', 'medium', 'medium_large', 'large' );
		$image_meta    = wp_get_attachment_metadata( self::$large_id );

		// Make sure themes aren't filtering the sizes array.
		remove_all_filters( 'wp_calculate_image_sizes' );

		foreach ( $intermediates as $int_size ) {
			$size_array             = $this->get_image_size_array_from_meta( $image_meta, $int_size );
			$image_src              = $image_meta['sizes'][ $int_size ]['file'];
			list( $width, $height ) = $size_array;

			$expected = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
			$sizes    = wp_calculate_image_sizes( $size_array, $image_src, $image_meta );

			$this->assertSame( $expected, $sizes );
		}
	}

	/**
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_filter_content_tags_srcset_sizes() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = $this->get_image_size_array_from_meta( $image_meta, 'medium' );

		$srcset = sprintf( 'srcset="%s"', wp_get_attachment_image_srcset( self::$large_id, $size_array, $image_meta ) );
		$sizes  = sprintf( 'sizes="%s"', wp_get_attachment_image_sizes( self::$large_id, $size_array, $image_meta ) );

		// Function used to build HTML for the editor.
		$img                  = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img_no_size_in_class = str_replace( 'size-', '', $img );
		$img_no_width_height  = str_replace( ' width="' . $size_array[0] . '"', '', $img );
		$img_no_width_height  = str_replace( ' height="' . $size_array[1] . '"', '', $img_no_width_height );
		$img_no_size_id       = str_replace( 'wp-image-', 'id-', $img );
		$img_with_sizes_attr  = str_replace( '<img ', '<img sizes="99vw" ', $img );
		$img_xhtml            = str_replace( ' />', '/>', $img );
		$img_html5            = str_replace( ' />', '>', $img );

		// Manually add srcset and sizes to the markup from get_image_tag().
		$respimg                  = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img );
		$respimg_no_size_in_class = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_no_size_in_class );
		$respimg_no_width_height  = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_no_width_height );
		$respimg_with_sizes_attr  = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' />', $img_with_sizes_attr );
		$respimg_xhtml            = preg_replace( '|<img ([^>]+)/>|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_xhtml );
		$respimg_html5            = preg_replace( '|<img ([^>]+)>|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_html5 );

		$content = '
			<p>Image, standard. Should have srcset and sizes.</p>
			%1$s

			<p>Image, no size class. Should have srcset and sizes.</p>
			%2$s

			<p>Image, no width and height attributes. Should have srcset and sizes (from matching the file name).</p>
			%3$s

			<p>Image, no attachment ID class. Should NOT have srcset and sizes.</p>
			%4$s

			<p>Image, with sizes attribute. Should NOT have two sizes attributes.</p>
			%5$s

			<p>Image, XHTML 1.0 style (no space before the closing slash). Should have srcset and sizes.</p>
			%6$s

			<p>Image, HTML 5.0 style. Should have srcset and sizes.</p>
			%7$s';

		$content_unfiltered = sprintf(
			$content,
			$img,
			$img_no_size_in_class,
			$img_no_width_height,
			$img_no_size_id,
			$img_with_sizes_attr,
			$img_xhtml,
			$img_html5
		);

		$content_filtered = sprintf(
			$content,
			$respimg,
			$respimg_no_size_in_class,
			$respimg_no_width_height,
			$img_no_size_id,
			$respimg_with_sizes_attr,
			$respimg_xhtml,
			$respimg_html5
		);

		// Do not add width, height, and loading.
		add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		$this->assertSame( $content_filtered, wp_filter_content_tags( $content_unfiltered ) );

		remove_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
	}

	/**
	 * When rendering attributes for responsive images,
	 * we rely on the 'wp-image-*' class to find the image by ID.
	 * The class name may not be consistent with attachment IDs in DB when
	 * working with imported content or when a user has edited
	 * the 'src' attribute manually. To avoid incorrect images
	 * being displayed, ensure we don't add attributes in this case.
	 *
	 * @ticket 34898
	 * @ticket 33641
	 */
	public function test_wp_filter_content_tags_srcset_sizes_wrong() {
		$img = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );

		// Replace the src URL.
		$image_wrong_src = preg_replace( '|src="[^"]+"|', 'src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/foo.jpg"', $img );

		$this->assertSame( $image_wrong_src, wp_filter_content_tags( $image_wrong_src ) );
	}

	/**
	 * @ticket 33641
	 */
	public function test_wp_filter_content_tags_srcset_sizes_with_preexisting_srcset() {
		// Generate HTML and add a dummy srcset attribute.
		$img = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );
		$img = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . 'srcset="image2x.jpg 2x" />', $img );

		// The content filter should return the image unchanged.
		$this->assertSame( $img, wp_filter_content_tags( $img ) );
	}

	/**
	 * @ticket 55347
	 */
	public function test_wp_filter_content_tags_has_filter() {
		$filter = new MockAction();
		add_filter( 'wp_content_img_tag', array( &$filter, 'filter' ) );
		$img_tag_1 = get_image_tag( self::$large_id, '', '', '', 'medium' );

		wp_filter_content_tags( $img_tag_1 );
		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 55510
	 * @covers ::wp_filter_content_tags
	 */
	public function test_wp_filter_content_tags_handles_duplicate_img_and_iframe_tags_once() {
		$img     = get_image_tag( self::$large_id, '', '', '', 'large' );
		$iframe  = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$content = "$img\n$img\n$iframe\n$iframe";

		// Record how often one of the available img and iframe filters is run.
		// Both images and iframes support lazy-loading, so that's why this is used here.
		$img_filter = new MockAction();
		add_filter( 'wp_img_tag_add_loading_attr', array( &$img_filter, 'filter' ) );
		$iframe_filter = new MockAction();
		add_filter( 'wp_iframe_tag_add_loading_attr', array( &$iframe_filter, 'filter' ) );

		// Ensure the img and iframe filters only ran once because the content is a single duplicated img tag and a
		// single duplicate iframe tag.
		wp_filter_content_tags( $content );
		$this->assertSame( 1, $img_filter->get_call_count() );
		$this->assertSame( 1, $iframe_filter->get_call_count() );
	}

	/**
	 * @ticket 55510
	 * @covers ::wp_filter_content_tags
	 */
	public function test_wp_filter_content_tags_filter_with_identical_image_tags_custom_attributes() {
		$img     = get_image_tag( self::$large_id, '', '', '', 'large' );
		$img     = str_replace( '<img ', '<img srcset="custom" sizes="custom" loading="custom" decoding="custom"', $img );
		$content = "$img\n$img";

		add_filter(
			'wp_content_img_tag',
			static function ( $filtered_image ) {
				return "<span>$filtered_image</span>";
			}
		);

		// Ensure there is no duplicate <span> wrapping the image.
		$this->assertStringNotContainsString( '<span><span><img ', wp_filter_content_tags( $content ) );
	}

	/**
	 * @ticket 55510
	 * @covers ::wp_filter_content_tags
	 */
	public function test_wp_filter_content_tags_filter_with_identical_image_tags_disabled_core_filters() {
		$img     = get_image_tag( self::$large_id, '', '', '', 'large' );
		$content = "$img\n$img";

		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		add_filter(
			'wp_content_img_tag',
			static function ( $filtered_image ) {
				return "<span>$filtered_image</span>";
			}
		);

		// Ensure the output has both instances of the image wrapped with a single <span>.
		$this->assertSame( "<span>$img</span>\n<span>$img</span>", wp_filter_content_tags( $content ) );
	}

	/**
	 * @ticket 33641
	 * @ticket 34528
	 */
	public function test_wp_calculate_image_srcset_animated_gifs() {
		// Mock meta for an animated gif.
		$image_meta = array(
			'width'  => 1200,
			'height' => 600,
			'file'   => 'animated.gif',
			'sizes'  => array(
				'thumbnail' => array(
					'file'      => 'animated-150x150.gif',
					'width'     => 150,
					'height'    => 150,
					'mime-type' => 'image/gif',
				),
				'medium'    => array(
					'file'      => 'animated-300x150.gif',
					'width'     => 300,
					'height'    => 150,
					'mime-type' => 'image/gif',
				),
				'large'     => array(
					'file'      => 'animated-1024x512.gif',
					'width'     => 1024,
					'height'    => 512,
					'mime-type' => 'image/gif',
				),
			),
		);

		$full_src  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_meta['file'];
		$large_src = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_meta['sizes']['large']['file'];

		// Test with soft resized size array.
		$size_array = array( 900, 450 );

		// Full size GIFs should not return a srcset.
		$this->assertFalse( wp_calculate_image_srcset( $size_array, $full_src, $image_meta ) );
		// Intermediate sized GIFs should not include the full size in the srcset.
		$this->assertStringNotContainsString( $full_src, wp_calculate_image_srcset( $size_array, $large_src, $image_meta ) );
	}

	/**
	 * @ticket 35045
	 * @ticket 33641
	 * @requires function imagejpeg
	 */
	public function test_wp_filter_content_tags_schemes() {
		// Disable lazy loading attribute to not add the 'auto' keyword to the `sizes` attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );

		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = $this->get_image_size_array_from_meta( $image_meta, 'medium' );

		$srcset = sprintf( 'srcset="%s"', wp_get_attachment_image_srcset( self::$large_id, $size_array, $image_meta ) );
		$sizes  = sprintf( 'sizes="%s"', wp_get_attachment_image_sizes( self::$large_id, $size_array, $image_meta ) );

		// Build HTML for the editor.
		$img          = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img          = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );
		$img_https    = str_replace( 'http://', 'https://', $img );
		$img_relative = str_replace( 'http://', '//', $img );

		// Manually add srcset and sizes to the markup from get_image_tag().
		$respimg          = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img );
		$respimg_https    = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_https );
		$respimg_relative = preg_replace( '|<img ([^>]+) />|', '<img $1 ' . $srcset . ' ' . $sizes . ' />', $img_relative );

		$content = '
			<p>Image, http: protocol. Should have srcset and sizes.</p>
			%1$s

			<p>Image, https: protocol. Should have srcset and sizes.</p>
			%2$s

			<p>Image, protocol-relative. Should have srcset and sizes.</p>
			%3$s';

		$unfiltered = sprintf(
			$content,
			$img,
			$img_https,
			$img_relative
		);

		$expected = sprintf(
			$content,
			$respimg,
			$respimg_https,
			$respimg_relative
		);

		$actual = wp_filter_content_tags( $unfiltered );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 34945
	 * @ticket 33641
	 */
	public function test_wp_get_attachment_image_with_https_on() {
		// Mock meta for the image.
		$image_meta = array(
			'width'  => 1200,
			'height' => 600,
			'file'   => 'test.jpg',
			'sizes'  => array(
				'thumbnail' => array(
					'file'   => 'test-150x150.jpg',
					'width'  => 150,
					'height' => 150,
				),
				'medium'    => array(
					'file'   => 'test-300x150.jpg',
					'width'  => 300,
					'height' => 150,
				),
				'large'     => array(
					'file'   => 'test-1024x512.jpg',
					'width'  => 1024,
					'height' => 512,
				),
			),
		);

		// Test using the large file size.
		$size_array = array( 1024, 512 );
		$image_url  = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $image_meta['sizes']['large']['file'];

		$_SERVER['HTTPS'] = 'on';

		$uploads_url = 'https://' . WP_TESTS_DOMAIN . '/wp-content/uploads/';

		$expected = $uploads_url . 'test-1024x512.jpg 1024w, ' .
			$uploads_url . 'test-300x150.jpg 300w, ' .
			$uploads_url . 'test.jpg 1200w';

		$actual = wp_calculate_image_srcset( $size_array, $image_url, $image_meta );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 36084
	 */
	public function test_get_image_send_to_editor_defaults() {
		$id      = self::$large_id;
		$caption = '';
		$title   = 'A test title value.';
		$align   = 'left';

		// Calculate attachment data (default is medium).
		$attachment = wp_get_attachment_image_src( $id, 'medium' );

		$html     = '<img src="%1$s" alt="" width="%2$d" height="%3$d" class="align%4$s size-medium wp-image-%5$d" />';
		$expected = sprintf(
			$html,
			$attachment[0],
			$attachment[1],
			$attachment[2],
			$align,
			$id
		);

		$this->assertSame( $expected, get_image_send_to_editor( $id, $caption, $title, $align ) );

		$this->assertSame( $expected, get_image_send_to_editor( $id, $caption, $title, $align ) );
	}

	/**
	 * @ticket 36084
	 */
	public function test_get_image_send_to_editor_defaults_with_optional_params() {
		$id      = self::$large_id;
		$caption = 'A test caption.';
		$title   = 'A test title value.';
		$align   = 'left';
		$url     = get_permalink( $id );
		$rel     = true;
		$size    = 'thumbnail';
		$alt     = 'An example alt value.';

		// Calculate attachment data.
		$attachment = wp_get_attachment_image_src( $id, $size );

		$html = '<a href="%1$s" rel="%2$s"><img src="%3$s" alt="%4$s" width="%5$d" height="%6$d" class="size-%8$s wp-image-%9$d" /></a>';
		$html = '[caption id="attachment_%9$d" align="align%7$s" width="%5$d"]' . $html . ' %10$s[/caption]';

		$expected = sprintf(
			$html,
			$url,
			'attachment wp-att-' . $id,
			$attachment[0],
			$alt,
			$attachment[1],
			$attachment[2],
			$align,
			$size,
			$id,
			$caption
		);

		$this->assertSame( $expected, get_image_send_to_editor( $id, $caption, $title, $align, $url, $rel, $size, $alt ) );
	}

	/**
	 * @ticket 36084
	 */
	public function test_get_image_send_to_editor_defaults_no_caption_no_rel() {
		$id      = self::$large_id;
		$caption = '';
		$title   = 'A test title value.';
		$align   = 'left';
		$url     = get_permalink( $id );
		$rel     = '';
		$size    = 'thumbnail';
		$alt     = 'An example alt value.';

		// Calculate attachment data.
		$attachment = wp_get_attachment_image_src( $id, $size );

		$html = '<a href="%1$s"><img src="%2$s" alt="%3$s" width="%4$d" height="%5$d" class="align%6$s size-%7$s wp-image-%8$d" /></a>';

		$expected = sprintf(
			$html,
			$url,
			$attachment[0],
			$alt,
			$attachment[1],
			$attachment[2],
			$align,
			$size,
			$id
		);

		$this->assertSame( $expected, get_image_send_to_editor( $id, $caption, $title, $align, $url, $rel, $size, $alt ) );
	}

	/**
	 * Tests if wp_get_attachment_image() uses wp_get_attachment_metadata().
	 *
	 * In this way, the meta data can be filtered using the filter
	 * `wp_get_attachment_metadata`.
	 *
	 * The test checks if the image size that is added in the filter is
	 * used in the output of `wp_get_attachment_image()`.
	 *
	 * @ticket 36246
	 * @requires function imagejpeg
	 */
	public function test_wp_get_attachment_image_should_use_wp_get_attachment_metadata() {
		add_filter( 'wp_get_attachment_metadata', array( $this, 'filter_36246' ), 10, 2 );

		remove_all_filters( 'wp_calculate_image_sizes' );

		$basename    = wp_basename( self::$large_filename, '.jpg' );
		$year_month  = gmdate( 'Y/m' );
		$uploads_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . $year_month . '/';

		$expected = '<img width="999" height="999" ' .
			'src="' . $uploads_url . 'test-image-testsize-999x999.jpg" ' .
			'class="attachment-testsize size-testsize" alt="" decoding="async" loading="lazy" ' .
			'srcset="' . $uploads_url . 'test-image-testsize-999x999.jpg 999w, ' . $uploads_url . $basename . '-150x150.jpg 150w" ' .
			'sizes="auto, (max-width: 999px) 100vw, 999px" />';

		$actual = wp_get_attachment_image( self::$large_id, 'testsize' );

		remove_filter( 'wp_get_attachment_metadata', array( $this, 'filter_36246' ) );

		$this->assertSame( $expected, $actual );
	}

	public function filter_36246( $data, $attachment_id ) {
		$data['sizes']['testsize'] = array(
			'file'      => 'test-image-testsize-999x999.jpg',
			'width'     => 999,
			'height'    => 999,
			'mime-type' => 'image/jpg',
		);
		return $data;
	}

	/**
	 * @ticket 50679
	 */
	public function test_wp_get_attachment_metadata_should_return_false_if_no_attachment() {
		$post_id = self::factory()->post->create();
		$data    = wp_get_attachment_metadata( $post_id );
		$this->assertFalse( $data );
	}

	/**
	 * @ticket 37813
	 */
	public function test_return_type_when_inserting_attachment_with_error_in_data() {
		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'Attachment content',
			'post_title'   => 'Attachment Title',
			'post_date'    => '2012-02-30 00:00:00',
		);

		$attachment_id = wp_insert_attachment( $data, '', 0, true );
		$this->assertWPError( $attachment_id );
		$this->assertSame( 'invalid_date', $attachment_id->get_error_code() );

		$attachment_id = wp_insert_attachment( $data, '', 0 );
		$this->assertSame( 0, $attachment_id );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_get_media_creation_timestamp_video_asf() {
		$metadata = array(
			'fileformat' => 'asf',
			'asf'        => array(
				'file_properties_object' => array(
					'creation_date_unix' => 123,
				),
			),
		);

		$this->assertSame( 123, wp_get_media_creation_timestamp( $metadata ) );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_get_media_creation_timestamp_video_matroska() {
		$metadata = array(
			'fileformat' => 'matroska',
			'matroska'   => array(
				'comments' => array(
					'creation_time' => array(
						'2015-12-24T17:40:09Z',
					),
				),
			),
		);

		$this->assertSame( 1450978809, wp_get_media_creation_timestamp( $metadata ) );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_get_media_creation_timestamp_video_quicktime() {
		$metadata = array(
			'fileformat' => 'quicktime',
			'quicktime'  => array(
				'moov' => array(
					'subatoms' => array(
						array(
							'creation_time_unix' => 1450978805,
						),
					),
				),
			),
		);

		$this->assertSame( 1450978805, wp_get_media_creation_timestamp( $metadata ) );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_get_media_creation_timestamp_video_webm() {
		$metadata = array(
			'fileformat' => 'webm',
			'matroska'   => array(
				'info' => array(
					array(
						'DateUTC_unix' => 1265680539,
					),
				),
			),
		);

		$this->assertSame( 1265680539, wp_get_media_creation_timestamp( $metadata ) );
	}

	/**
	 * Test created timestamp is properly read from an MP4 file.
	 *
	 * This MP4 video file has an AAC audio track, so it can be used to test
	 *`wp_read_audio_metadata()`.
	 *
	 * @ticket 42017
	 */
	public function test_wp_read_audio_metadata_adds_creation_date_with_mp4() {
		$video    = DIR_TESTDATA . '/uploads/small-video.mp4';
		$metadata = wp_read_audio_metadata( $video );

		$this->assertSame( 1269120551, $metadata['created_timestamp'] );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_read_video_metadata_adds_creation_date_with_quicktime() {
		$video    = DIR_TESTDATA . '/uploads/small-video.mov';
		$metadata = wp_read_video_metadata( $video );

		$this->assertSame( 1269120551, $metadata['created_timestamp'] );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_read_video_metadata_adds_creation_date_with_mp4() {
		$video    = DIR_TESTDATA . '/uploads/small-video.mp4';
		$metadata = wp_read_video_metadata( $video );

		$this->assertSame( 1269120551, $metadata['created_timestamp'] );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_read_video_metadata_adds_creation_date_with_mkv() {
		$video    = DIR_TESTDATA . '/uploads/small-video.mkv';
		$metadata = wp_read_video_metadata( $video );

		$this->assertSame( 1269120551, $metadata['created_timestamp'] );
	}

	/**
	 * @ticket 35218
	 */
	public function test_wp_read_video_metadata_adds_creation_date_with_webm() {
		$video    = DIR_TESTDATA . '/uploads/small-video.webm';
		$metadata = wp_read_video_metadata( $video );

		$this->assertSame( 1269120551, $metadata['created_timestamp'] );
	}

	/**
	 * @ticket 10752
	 */
	public function test_media_handle_upload_uses_post_parent_for_directory_date() {
		$iptc_file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		// Make a copy of this file as it gets moved during the file upload.
		$tmp_name = wp_tempnam( $iptc_file );

		copy( $iptc_file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => 'test-image-iptc.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $iptc_file ),
		);

		$parent_id = self::factory()->post->create( array( 'post_date' => '2010-01-01' ) );

		$post_id = media_handle_upload(
			'upload',
			$parent_id,
			array(),
			array(
				'action'    => 'test_iptc_upload',
				'test_form' => false,
			)
		);

		unset( $_FILES['upload'] );

		$url = wp_get_attachment_url( $post_id );

		$uploads_dir = wp_upload_dir( '2010/01' );

		$expected = $uploads_dir['url'] . '/test-image-iptc.jpg';

		// Clean up.
		wp_delete_attachment( $post_id, true );
		wp_delete_post( $parent_id, true );

		$this->assertSame( $expected, $url );
	}

	/**
	 * @ticket 10752
	 */
	public function test_media_handle_upload_ignores_page_parent_for_directory_date() {
		$iptc_file = DIR_TESTDATA . '/images/test-image-iptc.jpg';

		// Make a copy of this file as it gets moved during the file upload.
		$tmp_name = wp_tempnam( $iptc_file );

		copy( $iptc_file, $tmp_name );

		$_FILES['upload'] = array(
			'tmp_name' => $tmp_name,
			'name'     => 'test-image-iptc.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $iptc_file ),
		);

		$parent_id = self::factory()->post->create(
			array(
				'post_date' => '2010-01-01',
				'post_type' => 'page',
			)
		);
		$parent    = get_post( $parent_id );

		$post_id = media_handle_upload(
			'upload',
			$parent_id,
			array(),
			array(
				'action'    => 'test_iptc_upload',
				'test_form' => false,
			)
		);

		unset( $_FILES['upload'] );

		$url = wp_get_attachment_url( $post_id );

		$uploads_dir = wp_upload_dir( current_time( 'mysql' ) );

		$expected = $uploads_dir['url'] . '/test-image-iptc.jpg';

		// Clean up.
		wp_delete_attachment( $post_id, true );
		wp_delete_post( $parent_id, true );

		$this->assertSame( $expected, $url );
	}

	/**
	 * @ticket 50367
	 * @requires function imagejpeg
	 */
	public function test_wp_filter_content_tags_width_height() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = $this->get_image_size_array_from_meta( $image_meta, 'medium' );

		$img                 = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img_no_width_height = str_replace( ' width="' . $size_array[0] . '"', '', $img );
		$img_no_width_height = str_replace( ' height="' . $size_array[1] . '"', '', $img_no_width_height );
		$img_no_width        = str_replace( ' width="' . $size_array[0] . '"', '', $img );
		$img_no_height       = str_replace( ' height="' . $size_array[1] . '"', '', $img );

		$hwstring = image_hwstring( $size_array[0], $size_array[1] );

		// Manually add width and height to the markup from get_image_tag().
		$respimg_no_width_height = str_replace( '<img ', '<img ' . $hwstring, $img_no_width_height );

		$content = '
			<p>Image, with width and height. Should NOT be modified.</p>
			%1$s

			<p>Image, no width and height attributes. Should have width, height, srcset and sizes (from matching the file name).</p>
			%2$s

			<p>Image, no width but height attribute. Should NOT be modified.</p>
			%3$s

			<p>Image, no height but width attribute. Should NOT be modified.</p>
			%4$s';

		$content_unfiltered = sprintf(
			$content,
			$img,
			$img_no_width_height,
			$img_no_width,
			$img_no_height
		);

		$content_filtered = sprintf(
			$content,
			$img,
			$respimg_no_width_height,
			$img_no_width,
			$img_no_height
		);

		// Do not add loading, srcset, and sizes.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		$this->assertSame( $content_filtered, wp_filter_content_tags( $content_unfiltered ) );

		remove_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 * @ticket 50756
	 * @ticket 58235
	 * @requires function imagejpeg
	 */
	public function test_wp_filter_content_tags_loading_lazy() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$size_array = $this->get_image_size_array_from_meta( $image_meta, 'medium' );

		$img                    = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img_xhtml              = str_replace( ' />', '/>', $img );
		$img_html5              = str_replace( ' />', '>', $img );
		$img_no_width_height    = str_replace( ' width="' . $size_array[0] . '"', '', $img );
		$img_no_width_height    = str_replace( ' height="' . $size_array[1] . '"', '', $img_no_width_height );
		$iframe                 = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$iframe_no_width_height = '<iframe src="https://www.example.com"></iframe>';

		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		$lazy_img       = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );
		$lazy_img_xhtml = wp_img_tag_add_loading_optimization_attrs( $img_xhtml, 'test' );
		$lazy_img_html5 = wp_img_tag_add_loading_optimization_attrs( $img_html5, 'test' );
		$lazy_iframe    = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		// The following should not be modified because there already is a 'loading' attribute.
		$img_eager    = str_replace( ' />', ' loading="eager" fetchpriority="high" />', $img );
		$iframe_eager = str_replace( '">', '" loading="eager">', $iframe );

		$content = '
			<p>Image, standard.</p>
			%1$s
			<p>Image, XHTML 1.0 style (no space before the closing slash).</p>
			%2$s
			<p>Image, HTML 5.0 style.</p>
			%3$s
			<p>Image, with pre-existing "loading" attribute. Should not be modified.</p>
			%4$s
			<p>Image, without dimension attributes. Should not be modified.</p>
			%5$s
			<p>Iframe, standard.</p>
			%6$s
			<p>Iframe, with pre-existing "loading" attribute. Should not be modified.</p>
			%7$s
			<p>Iframe, without dimension attributes. Should not be modified.</p>
			%8$s';

		$content_unfiltered = sprintf(
			$content,
			$img,
			$img_xhtml,
			$img_html5,
			$img_eager,
			$img_no_width_height,
			$iframe,
			$iframe_eager,
			$iframe_no_width_height
		);

		$content_filtered = sprintf(
			$content,
			$lazy_img,
			$lazy_img_xhtml,
			$lazy_img_html5,
			$img_eager,
			$img_no_width_height,
			$lazy_iframe,
			$iframe_eager,
			$iframe_no_width_height
		);

		// Do not add width, height, srcset, and sizes.
		add_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );

		$this->assertSame( $content_filtered, wp_filter_content_tags( $content_unfiltered ) );

		remove_filter( 'wp_img_tag_add_width_and_height_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
	}

	/**
	 * @ticket 44427
	 * @ticket 50756
	 * @ticket 58235
	 */
	public function test_wp_filter_content_tags_loading_lazy_opted_in() {
		$img         = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$lazy_img    = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );
		$iframe      = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$lazy_iframe = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$content = '
			<p>Image, standard.</p>
			%1$s
			<p>Iframe, standard.</p>
			%2$s';

		$content_unfiltered = sprintf( $content, $img, $iframe );
		$content_filtered   = sprintf( $content, $lazy_img, $lazy_iframe );

		// Do not add srcset and sizes while testing.
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );

		// Enable globally for all tags.
		add_filter( 'wp_lazy_loading_enabled', '__return_true' );

		$this->assertSame( $content_filtered, wp_filter_content_tags( $content_unfiltered ) );
		remove_filter( 'wp_lazy_loading_enabled', '__return_true' );
		remove_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
	}

	/**
	 * @ticket 44427
	 * @ticket 50756
	 */
	public function test_wp_filter_content_tags_loading_lazy_opted_out() {
		$img    = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$iframe = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';

		$content = '
			<p>Image, standard.</p>
			%1$s
			<p>Iframe, standard.</p>
			%2$s';
		$content = sprintf( $content, $img, $iframe );

		// Do not add srcset and sizes while testing.
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );

		// Disable globally for all tags.
		add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		$this->assertSame( $content, wp_filter_content_tags( $content ) );
		remove_filter( 'wp_lazy_loading_enabled', '__return_false' );
		remove_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		remove_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 *
	 * @expectedDeprecated wp_img_tag_add_loading_attr
	 * @expectedDeprecated wp_get_loading_attr_default
	 */
	public function test_wp_img_tag_add_loading_attr() {
		$img = '<img src="example.png" alt=" width="300" height="225" />';
		$img = wp_img_tag_add_loading_attr( $img, 'test' );

		$this->assertStringContainsString( ' loading="lazy"', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 *
	 * @expectedDeprecated wp_img_tag_add_loading_attr
	 * @expectedDeprecated wp_get_loading_attr_default
	 */
	public function test_wp_img_tag_add_loading_attr_without_src() {
		$img = '<img alt=" width="300" height="225" />';
		$img = wp_img_tag_add_loading_attr( $img, 'test' );

		$this->assertStringNotContainsString( ' loading=', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 *
	 * @expectedDeprecated wp_img_tag_add_loading_attr
	 * @expectedDeprecated wp_get_loading_attr_default
	 */
	public function test_wp_img_tag_add_loading_attr_with_single_quotes() {
		$img = "<img src='example.png' alt=' width='300' height='225' />";
		$img = wp_img_tag_add_loading_attr( $img, 'test' );

		$this->assertStringNotContainsString( ' loading=', $img );

		// Test specifically that the attribute is not there with double-quotes,
		// to avoid regressions.
		$this->assertStringNotContainsString( ' loading="lazy"', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50425
	 */
	public function test_wp_img_tag_add_loading_attr_opt_out() {
		$img = '<img src="example.png" alt=" width="300" height="225" />';
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );

		$this->assertStringNotContainsString( ' loading=', $img );
	}

	/**
	 * Test that decoding="async" is not applied to img tags with single quotes.
	 *
	 * @ticket 56969
	 *
	 * @expectedDeprecated wp_img_tag_add_decoding_attr
	 */
	public function test_wp_img_tag_add_decoding_attr_with_single_quotes() {
		$img = "<img src='example.png' alt='' width='300' height='225' />";
		$img = wp_img_tag_add_decoding_attr( $img, 'test' );
		$this->assertStringNotContainsString( ' decoding="async"', $img );
	}

	/**
	 * Test that decoding="async" is not applied to img tags inside JSON.
	 *
	 * @ticket 56969
	 */
	public function test_decoding_async_not_applied_to_json() {
		$content = '{"image": "<img src=\"example.png\" alt=\"\" width=\"300\" height=\"225\" />"}';
		$content = wp_filter_content_tags( $content );
		$this->assertStringNotContainsString( ' decoding="async"', $content );
	}

	/**
	 * @ticket 50756
	 */
	public function test_wp_iframe_tag_add_loading_attr() {
		$iframe = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$iframe = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$this->assertStringContainsString( ' loading="lazy"', $iframe );
	}

	/**
	 * @ticket 50756
	 */
	public function test_wp_iframe_tag_add_loading_attr_without_src() {
		$iframe = '<iframe width="640" height="360"></iframe>';
		$iframe = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$this->assertStringNotContainsString( ' loading=', $iframe );
	}

	/**
	 * @ticket 50756
	 */
	public function test_wp_iframe_tag_add_loading_attr_with_single_quotes() {
		$iframe = "<iframe src='https://www.example.com' width='640' height='360'></iframe>";
		$iframe = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$this->assertStringNotContainsString( ' loading=', $iframe );

		// Test specifically that the attribute is not there with double-quotes,
		// to avoid regressions.
		$this->assertStringNotContainsString( ' loading="lazy"', $iframe );
	}

	/**
	 * @ticket 50756
	 */
	public function test_wp_iframe_tag_add_loading_attr_opt_out() {
		$iframe = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		add_filter( 'wp_iframe_tag_add_loading_attr', '__return_false' );
		$iframe = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$this->assertStringNotContainsString( ' loading=', $iframe );
	}

	/**
	 * @ticket 52768
	 * @ticket 58773
	 */
	public function test_wp_iframe_tag_add_loading_attr_include_wp_embed() {
		$iframe   = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$fallback = '<blockquote>Fallback content.</blockquote>';
		$iframe   = wp_filter_oembed_result( $fallback . $iframe, (object) array( 'type' => 'rich' ), 'https://www.example.com' );
		$iframe   = wp_iframe_tag_add_loading_attr( $iframe, 'test' );

		$this->assertStringContainsString( ' loading="lazy"', $iframe );
	}

	/**
	 * @ticket 44427
	 * @ticket 50425
	 */
	public function test_wp_get_attachment_image_loading() {
		$img = wp_get_attachment_image( self::$large_id );

		$this->assertStringContainsString( ' loading="lazy"', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50425
	 */
	public function test_wp_get_attachment_image_loading_opt_out() {
		add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		$img = wp_get_attachment_image( self::$large_id );

		// There should not be any loading attribute in this case.
		$this->assertStringNotContainsString( ' loading=', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50425
	 */
	public function test_wp_get_attachment_image_loading_opt_out_individual() {
		// The default is already tested above, the filter below ensures that
		// lazy-loading is definitely enabled globally for images.
		add_filter( 'wp_lazy_loading_enabled', '__return_true' );

		$img = wp_get_attachment_image( self::$large_id, 'thumbnail', false, array( 'loading' => false ) );

		// There should not be any loading attribute in this case.
		$this->assertStringNotContainsString( ' loading=', $img );
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_attachment_image
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_attachment_image_fetchpriority_not_present_by_default() {
		$img = wp_get_attachment_image( self::$large_id );

		$this->assertStringNotContainsString( ' fetchpriority="high"', $img );
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_attachment_image
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_attachment_image_fetchpriority_high_when_not_lazy_loaded() {
		$img = wp_get_attachment_image( self::$large_id, 'large', false, array( 'loading' => false ) );

		$this->assertStringContainsString( ' fetchpriority="high"', $img );
	}

	/**
	 * @ticket 58235
	 *
	 * @dataProvider data_provider_fetchpriority_values
	 *
	 * @covers ::wp_get_attachment_image
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_attachment_image_fetchpriority_original_value_respected( $value ) {
		$img = wp_get_attachment_image(
			self::$large_id,
			'large',
			false,
			array(
				'loading'       => false,
				'fetchpriority' => $value,
			)
		);

		$this->assertStringContainsString( ' fetchpriority="' . $value . '"', $img );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_provider_fetchpriority_values() {
		return self::text_array_to_dataprovider( array( 'high', 'low', 'auto' ) );
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_attachment_image
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_attachment_image_fetchpriority_stripped_when_false() {
		$img = wp_get_attachment_image(
			self::$large_id,
			'large',
			false,
			array(
				'loading'       => false,
				'fetchpriority' => false,
			)
		);

		$this->assertStringNotContainsString( ' fetchpriority=', $img );
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_attachment_image
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_attachment_image_fetchpriority_high_prevents_lazy_loading() {
		$img = wp_get_attachment_image( self::$large_id, 'large', false, array( 'fetchpriority' => 'high' ) );

		$this->assertStringNotContainsString( ' loading="lazy"', $img );
	}

	/**
	 * @ticket 57086
	 *
	 * @dataProvider data_wp_get_attachment_image_decoding_attr
	 *
	 * @covers ::wp_get_attachment_image
	 */
	public function test_wp_get_attachment_image_decoding_attr( $decoding, $expected ) {
		if ( 'no value' === $decoding ) {
			$image = wp_get_attachment_image( self::$large_id, 'thumbnail', false, array() );
		} else {
			$image = wp_get_attachment_image( self::$large_id, 'thumbnail', false, array( 'decoding' => $decoding ) );
		}

		if ( 'no value' === $expected ) {
			$this->assertStringNotContainsString( ' decoding=', $image );
		} else {
			$this->assertStringContainsString( ' decoding="' . esc_attr( $expected ) . '"', $image );
		}
	}

	/**
	 * Data provider for test_wp_get_attachment_image_decoding_attr().
	 *
	 * @return array[]
	 */
	public function data_wp_get_attachment_image_decoding_attr() {
		return array(
			'default'     => array(
				'decoding' => 'no value',
				'expected' => 'async',
			),
			'async'       => array(
				'decoding' => 'async',
				'expected' => 'async',
			),
			'sync'        => array(
				'decoding' => 'sync',
				'expected' => 'sync',
			),
			'auto'        => array(
				'decoding' => 'auto',
				'expected' => 'auto',
			),
			'empty'       => array(
				'decoding' => '',
				'expected' => 'no value',
			),
			'false'       => array(
				'decoding' => false,
				'expected' => 'no value',
			),
			'zero'        => array(
				'decoding' => 0,
				'expected' => 'no value',
			),
			'zero string' => array(
				'decoding' => '0',
				'expected' => 'no value',
			),
			'zero float'  => array(
				'decoding' => 0.0,
				'expected' => 'no value',
			),
			'invalid'     => array(
				'decoding' => 'invalid',
				'expected' => 'no value',
			),
		);
	}

	/**
	 * @ticket 44427
	 * @ticket 50425
	 * @ticket 50756
	 * @dataProvider data_wp_lazy_loading_enabled_tag_name_defaults
	 *
	 * @param string $tag_name Tag name.
	 * @param bool   $expected Expected return value.
	 */
	public function test_wp_lazy_loading_enabled_tag_name_defaults( $tag_name, $expected ) {
		if ( $expected ) {
			$this->assertTrue( wp_lazy_loading_enabled( $tag_name, 'the_content' ) );
		} else {
			$this->assertFalse( wp_lazy_loading_enabled( $tag_name, 'the_content' ) );
		}
	}

	public function data_wp_lazy_loading_enabled_tag_name_defaults() {
		return array(
			'img => true'            => array( 'img', true ),
			'iframe => true'         => array( 'iframe', true ),
			'arbitrary tag => false' => array( 'blink', false ),
		);
	}

	/**
	 * @ticket 50425
	 * @ticket 53463
	 * @ticket 53675
	 * @dataProvider data_wp_lazy_loading_enabled_context_defaults
	 *
	 * @param string $context  Function context.
	 * @param bool   $expected Expected return value.
	 */
	public function test_wp_lazy_loading_enabled_context_defaults( $context, $expected ) {
		if ( $expected ) {
			$this->assertTrue( wp_lazy_loading_enabled( 'img', $context ) );
		} else {
			$this->assertFalse( wp_lazy_loading_enabled( 'img', $context ) );
		}
	}

	public function data_wp_lazy_loading_enabled_context_defaults() {
		return array(
			'wp_get_attachment_image => true' => array( 'wp_get_attachment_image', true ),
			'the_content => true'             => array( 'the_content', true ),
			'the_excerpt => true'             => array( 'the_excerpt', true ),
			'widget_text_content => true'     => array( 'widget_text_content', true ),
			'widget_block_content => true'    => array( 'widget_block_content', true ),
			'get_avatar => true'              => array( 'get_avatar', true ),
			'arbitrary context => true'       => array( 'something_completely_arbitrary', true ),
			'the_post_thumbnail => true'      => array( 'the_post_thumbnail', true ),
		);
	}

	/**
	 * @ticket 50543
	 */
	public function test_wp_image_file_matches_image_meta() {
		$image_meta       = wp_get_attachment_metadata( self::$large_id );
		$image_src_full   = wp_get_attachment_image_url( self::$large_id, 'full' );
		$image_src_medium = wp_get_attachment_image_url( self::$large_id, 'medium' );

		$this->assertTrue( wp_image_file_matches_image_meta( $image_src_full, $image_meta ) );
		$this->assertTrue( wp_image_file_matches_image_meta( $image_src_medium, $image_meta ) );
	}

	/**
	 * @ticket 50543
	 */
	public function test_wp_image_file_matches_image_meta_no_subsizes() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$image_src  = wp_get_attachment_image_url( self::$large_id, 'full' );

		$image_meta['sizes'] = array();

		$this->assertTrue( wp_image_file_matches_image_meta( $image_src, $image_meta ) );
	}

	/**
	 * @ticket 50543
	 */
	public function test_wp_image_file_matches_image_meta_invalid_meta() {
		$image_meta = ''; // Attachment is not an image.
		$image_src  = self::IMG_URL;

		$this->assertFalse( wp_image_file_matches_image_meta( $image_src, $image_meta ) );
	}

	/**
	 * @ticket 50543
	 */
	public function test_wp_image_file_matches_image_meta_different_meta() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$image_src  = self::IMG_URL; // Different image.

		$this->assertFalse( wp_image_file_matches_image_meta( $image_src, $image_meta ) );
	}

	/**
	 * @ticket 50543
	 */
	public function test_wp_image_file_matches_image_meta_original_image() {
		$image_meta = wp_get_attachment_metadata( self::$large_id );
		$image_src  = wp_get_original_image_url( self::$large_id );

		$this->assertTrue( wp_image_file_matches_image_meta( $image_src, $image_meta ) );
	}

	/**
	 * @ticket 22101
	 */
	public function test_gallery_shortcode_when_is_feed_true() {

		$this->go_to( '/?feed=rss2' );

		// Default: Links to image attachment page URL.
		$actual = gallery_shortcode(
			array(
				'ids' => self::$large_id,
			)
		);
		$this->assertStringContainsString( '?attachment_id=', $actual );

		// File: Links to image file URL.
		$actual = gallery_shortcode(
			array(
				'ids'  => self::$large_id,
				'link' => 'file',
			)
		);
		$this->assertSame( 2, substr_count( $actual, '.jpg' ) );

		// None: Does not link.
		$actual = gallery_shortcode(
			array(
				'ids'  => self::$large_id,
				'link' => 'none',
			)
		);
		$this->assertStringNotContainsString( '<a ', $actual );
	}

	/**
	 * Test attachment permalinks based on parent post status.
	 *
	 * @dataProvider data_attachment_permalinks_based_on_parent_status
	 * @ticket 51776
	 *
	 * @param string $post_key     Post as keyed in the shared fixture array.
	 * @param string $expected_url Expected permalink.
	 * @param bool   $expected_404 Whether the page is expected to return a 404 result.
	 *
	 */
	public function test_attachment_permalinks_based_on_parent_status( $post_key, $expected_url, $expected_404 ) {
		$this->set_permalink_structure( '/%postname%' );
		$post = get_post( self::$post_ids[ $post_key ] );

		/*
		 * The dataProvider runs before the fixures are set up, therefore the
		 * post object IDs are placeholders that needs to be replaced.
		 */
		$expected_url = home_url( str_replace( '%ID%', $post->ID, $expected_url ) );

		$this->go_to( get_permalink( $post ) );
		$this->assertSame( $expected_url, get_permalink( $post ) );
		if ( $expected_404 ) {
			$this->assertQueryTrue( 'is_404' );
		} else {
			$this->assertQueryTrue( 'is_attachment', 'is_single', 'is_singular' );
		}
		$this->assertSame( 'attachment', $post->post_type );
	}

	/**
	 * Data provider for test_attachment_permalinks_based_on_parent_status().
	 *
	 * @return array[] {
	 *     @type string $post_key     Post as keyed in the shared fixture array.
	 *     @type string $expected_url Expected permalink.
	 *     $type bool   $expected_404 Whether the page is expected to return a 404 result.
	 * }
	 */
	public function data_attachment_permalinks_based_on_parent_status() {
		return array(
			array( 'draft-attachment', '/?attachment_id=%ID%', true ),
			array( 'publish-attachment', '/publish-post/publish-attachment', false ),
			array( 'future-attachment', '/future-post/future-attachment', false ),
			array( 'auto-draft-attachment', '/?attachment_id=%ID%', true ),
			array( 'trash-attachment', '/?attachment_id=%ID%', false ),
		);
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value.
	 *
	 * @ticket 53675
	 * @ticket 56930
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default
	 *
	 * @param string $context
	 */
	public function test_wp_get_loading_attr_default( $context ) {
		// Return 'lazy' by default.
		$this->assertSame( 'lazy', wp_get_loading_attr_default( 'test' ) );
		$this->assertSame( 'lazy', wp_get_loading_attr_default( 'wp_get_attachment_image' ) );

		// Return 'lazy' if not in the loop or the main query.
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );

		$query = $this->get_new_wp_query_for_published_post();

		while ( have_posts() ) {
			the_post();

			// Return 'lazy' if in the loop but not in the main query.
			$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );

			// Set as main query.
			$this->set_main_query( $query );

			/*
			 * For contexts other than for the main content, still return 'lazy' even in the loop
			 * and in the main query, and do not increase the content media count.
			 */
			$this->assertSame( 'lazy', wp_get_loading_attr_default( 'wp_get_attachment_image' ) );

			// Return `false` in the main query for first three element.
			$this->assertFalse( wp_get_loading_attr_default( $context ), 'Expected first image to not be lazy-loaded.' );
			$this->assertFalse( wp_get_loading_attr_default( $context ), 'Expected second image to not be lazy-loaded.' );
			$this->assertFalse( wp_get_loading_attr_default( $context ), 'Expected third image to not be lazy-loaded.' );

			// Return 'lazy' if in the loop and in the main query for any subsequent elements.
			$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );

			// Yes, for all subsequent elements.
			$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
		}

		// Exceptions: In the following contexts, images shouldn't be lazy-loaded by default.
		$this->assertFalse( wp_get_loading_attr_default( 'template' ), 'Images run through the overall block template filter should not be lazy-loaded.' );
		$this->assertFalse( wp_get_loading_attr_default( 'template_part_' . WP_TEMPLATE_PART_AREA_HEADER ), 'Images in the footer block template part should not be lazy-loaded.' );
	}

	public function data_wp_get_loading_attr_default() {
		return array(
			array( 'the_content' ),
			array( 'the_post_thumbnail' ),
		);
	}

	/**
	 * @ticket 53675
	 * @ticket 58235
	 * @ticket 58892
	 */
	public function test_wp_omit_loading_attr_threshold_filter() {
		// Using a smaller image here.
		$attr = array(
			'width'  => 100,
			'height' => 100,
		);

		$query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $query );

		// Use the filter to alter the threshold for not lazy-loading to the first five elements.
		$this->force_omit_loading_attr_threshold( 5 );

		while ( have_posts() ) {
			the_post();

			// Due to the filter, now the first five elements should not be lazy-loaded, i.e. return `false`.
			for ( $i = 0; $i < 5; $i++ ) {
				$this->assertSameSetsWithIndex(
					array(
						'decoding' => 'async',
					),
					wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
					'Expected second image to not be lazy-loaded.'
				);
			}

			// For following elements, lazy-load them again.
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' )
			);
		}
	}

	/**
	 * @ticket 53675
	 * @ticket 58235
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_img_tag_add_loading_optimization_attrs
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_with_loading_optimization_attrs() {
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
		$img1         = get_image_tag( self::$large_id, '', '', '', 'large' );
		$iframe1      = '<iframe src="https://www.example.com" width="640" height="360"></iframe>';
		$img2         = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$img3         = get_image_tag( self::$large_id, '', '', '', 'thumbnail' );
		$iframe2      = '<iframe src="https://wordpress.org" width="640" height="360"></iframe>';
		$prio_img1    = str_replace( ' src=', ' fetchpriority="high" src=', $img1 );
		$lazy_img2    = wp_img_tag_add_loading_optimization_attrs( $img2, 'the_content' );
		$lazy_img3    = wp_img_tag_add_loading_optimization_attrs( $img3, 'the_content' );
		$lazy_iframe2 = wp_iframe_tag_add_loading_attr( $iframe2, 'the_content' );

		// Use a threshold of 2.
		$this->force_omit_loading_attr_threshold( 2 );

		// Following the threshold of 2, the first two content media elements should not be lazy-loaded.
		$content_unfiltered = $img1 . $iframe1 . $img2 . $img3 . $iframe2;
		$content_expected   = $prio_img1 . $iframe1 . $lazy_img2 . $lazy_img3 . $lazy_iframe2;

		$query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $query );

		while ( have_posts() ) {
			the_post();

			add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
			$content_filtered = wp_filter_content_tags( $content_unfiltered, 'the_content' );
			remove_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		}
		remove_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		// After filtering, the first image should not be lazy-loaded while the other ones should be.
		$this->assertSame( $content_expected, $content_filtered );
	}

	/**
	 * @ticket 53675
	 */
	public function test_wp_omit_loading_attr_threshold() {
		$this->reset_omit_loading_attr_filter();

		// Apply filter, ensure default value of 3.
		$omit_threshold = wp_omit_loading_attr_threshold();
		$this->assertSame( 3, $omit_threshold );

		// Add a filter that changes the value to 1. However, the filter is not applied a subsequent time in a single
		// page load by default, so the value is still 3.
		$this->force_omit_loading_attr_threshold( 1 );

		$omit_threshold = wp_omit_loading_attr_threshold();
		$this->assertSame( 3, $omit_threshold );

		// Only by enforcing a fresh check, the filter gets re-applied.
		$omit_threshold = wp_omit_loading_attr_threshold( true );
		$this->assertSame( 1, $omit_threshold );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value before loop but after get_header if not main query.
	 *
	 * @ticket 58211
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_before_loop_if_not_main_query( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();

		do_action( 'get_header' );

		// Lazy if not main query.
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value before loop but after get_header in main query but header was not called.
	 *
	 * @ticket 58211
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_before_loop_in_main_query_but_header_not_called( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		// Lazy if header not called.
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value before loop but after get_header for main query.
	 *
	 * @ticket 58211
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_before_loop_if_main_query( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		do_action( 'get_header' );
		$this->assertFalse( wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value after get_header and after loop.
	 *
	 * @ticket 58211
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_after_loop( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		do_action( 'get_header' );

		while ( have_posts() ) {
			the_post();
		}
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute if no loop.
	 *
	 * @ticket 58211
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_no_loop( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		// Ensure header and footer is called.
		do_action( 'get_header' );
		do_action( 'get_footer' );

		// Load lazy if the there is no loop and footer was called.
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_loading_attr_default_before_and_no_loop() {
		return array(
			array( 'wp_get_attachment_image' ),
			array( 'the_post_thumbnail' ),
		);
	}

	/**
	 * Tests that wp_filter_content_tags() does not add loading="lazy" to the first
	 * image in the loop when using a block theme.
	 *
	 * @ticket 56930
	 * @ticket 58548
	 * @ticket 58235
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_img_tag_add_loading_optimization_attrs
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_does_not_lazy_load_first_image_in_block_theme() {
		global $_wp_current_template_id, $_wp_current_template_content, $wp_query, $wp_the_query, $post;

		// Do not add srcset, sizes, or decoding attributes as they are irrelevant for this test.
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
		$this->force_omit_loading_attr_threshold( 1 );

		$img1      = get_image_tag( self::$large_id, '', '', '', 'large' );
		$img2      = get_image_tag( self::$large_id, '', '', '', 'medium' );
		$prio_img1 = str_replace( ' src=', ' fetchpriority="high" src=', $img1 );
		$lazy_img2 = wp_img_tag_add_loading_optimization_attrs( $img2, 'the_content' );

		// Only the second image should be lazy-loaded.
		$post_content     = $img1 . $img2;
		$expected_content = wpautop( $prio_img1 . $lazy_img2 );

		// Update the post to test with so that it has the above post content.
		wp_update_post(
			array(
				'ID'                    => self::$post_ids['publish'],
				'post_content'          => $post_content,
				'post_content_filtered' => $post_content,
			)
		);

		$wp_query     = new WP_Query( array( 'p' => self::$post_ids['publish'] ) );
		$wp_the_query = $wp_query;
		$post         = get_post( self::$post_ids['publish'] );

		// Force a template ID that is for the current stylesheet.
		$_wp_current_template_id      = get_stylesheet() . '//single';
		$_wp_current_template_content = '<!-- wp:post-content /-->';

		$html = get_the_block_template_html();
		$this->assertSame( '<div class="wp-site-blocks"><div class="entry-content wp-block-post-content is-layout-flow wp-block-post-content-is-layout-flow">' . $expected_content . '</div></div>', $html );
	}

	/**
	 * Tests that wp_filter_content_tags() does not add loading="lazy"
	 * to the featured image when using a block theme.
	 *
	 * @ticket 56930
	 * @ticket 58548
	 * @ticket 58235
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_img_tag_add_loading_optimization_attrs
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_does_not_lazy_load_first_featured_image_in_block_theme() {
		global $_wp_current_template_id, $_wp_current_template_content, $wp_query, $wp_the_query, $post;

		// Do not add srcset, sizes, or decoding attributes as they are irrelevant for this test.
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );
		add_filter(
			'wp_get_attachment_image_attributes',
			static function ( $attr ) {
				unset( $attr['srcset'], $attr['sizes'], $attr['decoding'] );
				return $attr;
			}
		);
		$this->force_omit_loading_attr_threshold( 1 );

		$content_img      = get_image_tag( self::$large_id, '', '', '', 'large' );
		$lazy_content_img = wp_img_tag_add_loading_optimization_attrs( $content_img, 'the_content' );

		// The featured image should not be lazy-loaded as it is the first image.
		$featured_image_id = self::$large_id;
		update_post_meta( self::$post_ids['publish'], '_thumbnail_id', $featured_image_id );
		$expected_featured_image = '<figure class="wp-block-post-featured-image">' . get_the_post_thumbnail(
			self::$post_ids['publish'],
			'post-thumbnail',
			array(
				'loading'       => false,
				'style'         => 'object-fit:cover;',
				'fetchpriority' => 'high',
			)
		) . '</figure>';

		// Reset high priority flag as the forced `fetchpriority="high"` above already modified it.
		$this->reset_high_priority_element_flag();

		// The post content image should be lazy-loaded since the featured image appears above.
		$post_content     = $content_img;
		$expected_content = wpautop( $lazy_content_img );

		// Update the post to test with so that it has the above post content.
		wp_update_post(
			array(
				'ID'                    => self::$post_ids['publish'],
				'post_content'          => $post_content,
				'post_content_filtered' => $post_content,
			)
		);
		$wp_query     = new WP_Query( array( 'p' => self::$post_ids['publish'] ) );
		$wp_the_query = $wp_query;
		$post         = get_post( self::$post_ids['publish'] );

		// Force a template ID that is for the current stylesheet.
		$_wp_current_template_id      = get_stylesheet() . '//single';
		$_wp_current_template_content = '<!-- wp:post-featured-image /--> <!-- wp:post-content /-->';

		$html = get_the_block_template_html();
		$this->assertSame( '<div class="wp-site-blocks">' . $expected_featured_image . ' <div class="entry-content wp-block-post-content is-layout-flow wp-block-post-content-is-layout-flow">' . $expected_content . '</div></div>', $html );
	}

	/**
	 * Tests that wp_filter_content_tags() does not add loading="lazy" to images
	 * in a "Header" template part.
	 *
	 * @ticket 56930
	 * @ticket 58235
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_img_tag_add_loading_optimization_attrs
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_does_not_lazy_load_images_in_header() {
		global $_wp_current_template_id, $_wp_current_template_content;

		// Do not add srcset, sizes, or decoding attributes as they are irrelevant for this test.
		add_filter( 'wp_img_tag_add_srcset_and_sizes_attr', '__return_false' );
		add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

		// Use a single image for each header and footer template parts.
		$header_img = get_image_tag( self::$large_id, '', '', '', 'large' );
		// Since header_img is qualified candidate for LCP, fetchpriority high is applied to it.
		$header_img = str_replace( '<img', '<img fetchpriority="high"', $header_img );

		$footer_img = get_image_tag( self::$large_id, '', '', '', 'medium' );

		// Create header and footer template parts.
		$header_post_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'header',
				'post_content' => $header_img,
			)
		);
		wp_set_post_terms( $header_post_id, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( $header_post_id, get_stylesheet(), 'wp_theme' );
		$footer_post_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'footer',
				'post_content' => $footer_img,
			)
		);
		wp_set_post_terms( $footer_post_id, WP_TEMPLATE_PART_AREA_FOOTER, 'wp_template_part_area' );
		wp_set_post_terms( $footer_post_id, get_stylesheet(), 'wp_theme' );

		// Force a template ID that is for the current stylesheet.
		$_wp_current_template_id      = get_stylesheet() . '//single';
		$_wp_current_template_content = '<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '","tagName":"header"} /--><!-- wp:template-part {"slug":"footer","theme":"' . get_stylesheet() . '","tagName":"footer"} /-->';

		// Header image should not be lazy-loaded, footer image should be lazy-loaded.
		$expected_template_content  = '<header class="wp-block-template-part">' . $header_img . '</header>';
		$expected_template_content .= '<footer class="wp-block-template-part">' . wp_img_tag_add_loading_optimization_attrs( $footer_img, 'force-lazy' ) . '</footer>';

		$html = get_the_block_template_html();
		$this->assertSame( '<div class="wp-site-blocks">' . $expected_template_content . '</div>', $html );
	}

	/**
	 * @ticket 58089
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_does_not_apply_loading_optimization_to_special_images_within_the_content() {
		global $wp_query, $wp_the_query;

		// Force no lazy-loading or fetchpriority on the image tag expected in the content.
		$expected_image = wp_get_attachment_image(
			self::$large_id,
			'large',
			false,
			array(
				'loading'       => false,
				'fetchpriority' => false,
				'decoding'      => false,
			)
		);

		// Reset high priority flag as the forced `fetchpriority="high"` above already modified it.
		$this->reset_high_priority_element_flag();

		$image_within_content = '';

		// Overwrite post content with an image.
		add_filter(
			'the_content',
			static function () use ( &$image_within_content ) {
				// Replace content with an image tag, i.e. the 'wp_get_attachment_image' context is used while running 'the_content' filter.
				$image_within_content = wp_get_attachment_image( self::$large_id, 'large', false );
				return $image_within_content;
			},
			9 // Run before wp_filter_content_tags().
		);

		/*
		 * We have to run a main query loop so that the first 'the_content' context image is not
		 * lazy-loaded.
		 * Without the fix from 58089, the image would still be lazy-loaded since the check for the
		 * separately invoked 'wp_get_attachment_image' context would lead to that.
		 */
		$wp_query     = new WP_Query( array( 'post__in' => array( self::$post_ids['publish'] ) ) );
		$wp_the_query = $wp_query;

		$content = '';
		while ( have_posts() ) {
			the_post();
			$content = get_echo( 'the_content' );
		}

		// Ensure that parsed image within content does not receive any loading optimization attributes.
		$this->assertSame( $expected_image, $image_within_content, 'Image with wp_get_attachment_image context within post content should not receive loading optimization attributes' );

		// Ensure that parsed content has the image with fetchpriority as it is the first large image.
		$expected_content = wpautop( str_replace( '<img ', '<img fetchpriority="high" decoding="async" ', $expected_image ) );
		$this->assertSame( $expected_content, $content, 'Post content with programmatically injected image is missing loading optimization attributes' );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns 'lazy' for special contexts when they're used outside of 'the_content' filter.
	 *
	 * @ticket 58089
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @dataProvider data_special_contexts_for_the_content_wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_should_return_lazy_for_special_contexts_outside_of_the_content( $context ) {
		$this->assertSame( 'lazy', wp_get_loading_attr_default( $context ) );
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns false for special contexts when they're used within 'the_content' filter.
	 *
	 * @ticket 58089
	 *
	 * @covers ::wp_get_loading_attr_default
	 *
	 * @expectedDeprecated wp_get_loading_attr_default
	 *
	 * @dataProvider data_special_contexts_for_the_content_wp_get_loading_attr_default
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_attr_default_should_return_false_for_special_contexts_within_the_content( $context ) {
		remove_all_filters( 'the_content' );

		$result = null;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$result, $context ) {
				$result = wp_get_loading_attr_default( $context );
				return $content;
			}
		);
		apply_filters( 'the_content', '' );
		$this->assertFalse( $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_special_contexts_for_the_content() {
		return array(
			'widget_media_image'      => array( 'context' => 'widget_media_image' ),
			'the_post_thumbnail'      => array( 'context' => 'the_post_thumbnail' ),
			'wp_get_attachment_image' => array( 'context' => 'wp_get_attachment_image' ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_special_contexts_for_the_content_wp_get_loading_attr_default() {
		return array(
			'the_post_thumbnail'      => array( 'context' => 'the_post_thumbnail' ),
			'wp_get_attachment_image' => array( 'context' => 'wp_get_attachment_image' ),
		);
	}

	/**
	 * Tests that wp_get_loading_attr_default() returns the expected loading attribute value.
	 *
	 * @ticket 53675
	 * @ticket 56930
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default
	 *
	 * @param string $context
	 */
	public function test_wp_get_loading_optimization_attributes( $context ) {
		$attr = $this->get_width_height_for_high_priority();

		// Return 'lazy' by default.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'test' )
		);
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'wp_get_attachment_image' )
		);

		// Return 'lazy' if not in the loop or the main query.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);

		$query = $this->get_new_wp_query_for_published_post();

		while ( have_posts() ) {
			the_post();

			// Return 'lazy' if in the loop but not in the main query.
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context )
			);

			// Set as main query.
			$this->set_main_query( $query );

			// First three element are not lazy loaded. However, first image is loaded with fetchpriority high.
			$this->assertSameSetsWithIndex(
				array(
					'decoding'      => 'async',
					'fetchpriority' => 'high',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				"Expected first image to not be lazy-loaded. First large image get's high fetchpriority."
			);
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				'Expected second image to not be lazy-loaded.'
			);
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				'Expected third image to not be lazy-loaded.'
			);

			// Return 'lazy' if in the loop and in the main query for any subsequent elements.
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context )
			);

			// Yes, for all subsequent elements.
			$this->assertSameSetsWithIndex(
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context )
			);
		}
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns fetchpriority=high and increases the count for arbitrary contexts in the main loop.
	 *
	 * @ticket 58894
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_optimization_attributes_arbitrary_contexts
	 *
	 * @param string $context Context for the element for which the loading optimization attribute is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_with_arbitrary_contexts_in_main_loop( $context ) {
		$attr = $this->get_width_height_for_high_priority();

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context ),
			'The "loading" attribute should be "lazy" when not in the loop or the main query.'
		);

		$query = $this->get_new_wp_query_for_published_post();

		// Set as main query.
		$this->set_main_query( $query );

		while ( have_posts() ) {
			the_post();

			$this->assertSameSetsWithIndex(
				array(
					'decoding'      => 'async',
					'fetchpriority' => 'high',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				'The "fetchpriority" attribute should be "high" while in the loop and the main query.'
			);

			// Images with a certain minimum size in the arbitrary contexts of the page are also counted towards the threshold.
			$this->assertSame( 1, wp_increase_content_media_count( 0 ), 'The content media count should be 1.' );
		}
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() does not return lazy loading attributes when arbitrary contexts are used before the main query loop.
	 *
	 * @ticket 58894
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_optimization_attributes_arbitrary_contexts
	 *
	 * @param string $context Context for the element for which the loading optimization attribute is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_with_arbitrary_contexts_before_main_query_loop( $context ) {
		$attr = $this->get_width_height_for_high_priority();

		$query = $this->get_new_wp_query_for_published_post();

		// Set as main query.
		$this->set_main_query( $query );

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context ),
			'The "loading" attribute should be "lazy" before the main query loop.'
		);

		while ( have_posts() ) {
			the_post();

			$this->assertSameSetsWithIndex(
				array(
					'decoding'      => 'async',
					'fetchpriority' => 'high',
				),
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				'The "fetchpriority" attribute should be "high" while in the loop and the main query.'
			);

			$this->assertArrayNotHasKey(
				'loading',
				wp_get_loading_optimization_attributes( 'img', $attr, $context ),
				'No "loading" attribute should be present on the second image in the main query loop.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_loading_optimization_attributes_arbitrary_contexts() {
		return array(
			array( 'wp_get_attachment_image' ),
			array( 'something_completely_arbitrary' ),
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns empty array for arbitrary context.
	 *
	 * @ticket 58894
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_should_return_empty_array_for_any_arbitrary_context() {
		remove_all_filters( 'the_content' );

		$result = null;
		add_filter(
			'the_content',
			function ( $content ) use ( &$result ) {
				$attr   = $this->get_width_height_for_high_priority();
				$result = wp_get_loading_optimization_attributes( 'img', $attr, 'something_completely_arbitrary' );
				return $content;
			}
		);
		apply_filters( 'the_content', '' );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 58894
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_optimization_attributes_header_context
	 *
	 * @param string $context The context for the header.
	 */
	public function test_wp_get_loading_optimization_attributes_header_contexts( $context ) {
		$attr = $this->get_width_height_for_high_priority();

		$this->assertArrayNotHasKey(
			'loading',
			wp_get_loading_optimization_attributes( 'img', $attr, $context ),
			'Images in the header context should not be lazy-loaded.'
		);

		add_filter( 'wp_loading_optimization_force_header_contexts', '__return_empty_array' );

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context ),
			'Images in the header context should get lazy-loaded after the wp_loading_optimization_force_header_contexts filter.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_loading_optimization_attributes_header_context() {
		return array(
			array( 'template_part_' . WP_TEMPLATE_PART_AREA_HEADER ),
			array( 'get_header_image_tag' ),
		);
	}

	/**
	 * @ticket 58894
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_loading_optimization_force_header_contexts_filter() {
		$attr = $this->get_width_height_for_high_priority();

		add_filter(
			'wp_loading_optimization_force_header_contexts',
			function ( $context ) {
				$contexts['something_completely_arbitrary'] = true;
				return $contexts;
			}
		);

		$this->assertSameSetsWithIndex(
			array(
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'something_completely_arbitrary' )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns the expected loading attribute value before loop but after get_header if not main query.
	 *
	 * @ticket 58211
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_before_loop_if_not_main_query( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();

		do_action( 'get_header' );

		$attr = $this->get_width_height_for_high_priority();

		// Lazy if not main query.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns the expected loading attribute value before loop but after get_header in main query but header was not called.
	 *
	 * @ticket 58211
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_before_loop_in_main_query_but_header_not_called( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		$attr = $this->get_width_height_for_high_priority();

		// Lazy if header not called.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns the expected loading attribute value before loop but after get_header for main query.
	 *
	 * @ticket 58211
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_before_loop_if_main_query( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );
		do_action( 'get_header' );

		$attr = $this->get_width_height_for_high_priority();

		// First image is loaded with high fetchpriority.
		$this->assertSameSetsWithIndex(
			array(
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context ),
			'Expected first image to not be lazy-loaded. First large image is loaded with high fetchpriority.'
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns the expected loading attribute value after get_header and after loop.
	 *
	 * @ticket 58211
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_after_loop( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		do_action( 'get_header' );

		while ( have_posts() ) {
			the_post();
		}

		$attr = $this->get_width_height_for_high_priority();
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns the expected loading attribute if no loop.
	 *
	 * @ticket 58211
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_no_loop( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );

		// Ensure header and footer is called.
		do_action( 'get_header' );
		do_action( 'get_footer' );

		$attr = $this->get_width_height_for_high_priority();

		// Load lazy if the there is no loop and footer was called.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() returns 'lazy' for special contexts when they're used outside of 'the_content' filter.
	 *
	 * @ticket 58089
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_special_contexts_for_the_content
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_should_return_lazy_for_special_contexts_outside_of_the_content( $context ) {
		$attr = $this->get_width_height_for_high_priority();
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, $context )
		);
	}

	/**
	 * Tests that wp_get_loading_optimization_attributes() does not modify any attributes for special contexts when they're used within 'the_content' filter.
	 *
	 * @ticket 58089
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_special_contexts_for_the_content
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_should_not_modify_images_for_special_contexts_within_the_content( $context ) {
		remove_all_filters( 'the_content' );

		$result = null;
		add_filter(
			'the_content',
			function ( $content ) use ( &$result, $context ) {
				$attr   = $this->get_width_height_for_high_priority();
				$result = wp_get_loading_optimization_attributes( 'img', $attr, $context );
				return $content;
			}
		);
		apply_filters( 'the_content', '' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Tests to cover the decoding attribute within wp_get_loading_optimization_attributes().
	 *
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_decoding_attribute() {

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
			),
			wp_get_loading_optimization_attributes( 'img', array(), 'the_content' ),
			'Expected decoding attribute to be async.'
		);

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'auto',
			),
			wp_get_loading_optimization_attributes( 'img', array( 'decoding' => 'auto' ), 'the_content' ),
			'Expected decoding attribute to be auto.'
		);

		$result = null;
		add_filter(
			'the_content',
			static function ( $content ) use ( &$result ) {
				$result = wp_get_loading_optimization_attributes( 'img', array(), 'something_completely_arbitrary' );
				return $content;
			}
		);
		apply_filters( 'the_content', '' );

		$this->assertSameSetsWithIndex(
			array(),
			$result,
			'Expected decoding attribute to be empty for img on arbitrary context, while running the_content.'
		);

		$this->assertSameSetsWithIndex(
			array(),
			wp_get_loading_optimization_attributes( 'iframe', array(), 'the_content' ),
			'Expected decoding attribute to be empty for iframe.'
		);
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 * @ticket 58235
	 */
	public function test_wp_img_tag_add_loading_optimization_attrs() {
		$img = '<img src="example.png" alt=" width="300" height="225" />';
		$img = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );

		$this->assertStringContainsString( ' loading="lazy"', $img );
	}

	/**
	 * @ticket 44427
	 * @ticket 50367
	 * @ticket 58235
	 */
	public function test_wp_img_tag_add_loading_optimization_attrs_without_src() {
		$img = '<img alt="" width="300" height="225" />';
		$img = wp_img_tag_add_loading_optimization_attrs( $img, 'test' );

		$this->assertStringNotContainsString( ' loading=', $img );
	}

	/**
	 * Tests that the content media count is not affected by `the_excerpt()` calls for posts that contain images.
	 *
	 * @ticket 56588
	 *
	 * @covers ::wp_trim_excerpt
	 */
	public function test_the_excerpt_does_not_affect_content_media_count() {
		global $wp_query, $wp_the_query;

		/*
		 * Use the filter to alter the threshold for not lazy-loading to the first 2 elements,
		 * then use a post that contains exactly 2 images.
		 */
		$this->force_omit_loading_attr_threshold( 2 );
		$post_content  = '<img src="example.jpg" width="800" height="600">';
		$post_content .= '<p>Some text.</p>';
		$post_content .= '<img src="example2.jpg" width="800" height="600">';

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_excerpt' => '',
			)
		);

		$wp_query     = new WP_Query( array( 'post__in' => array( $post_id ) ) );
		$wp_the_query = $wp_query;

		while ( have_posts() ) {
			the_post();

			// Call `the_excerpt()` without generating output.
			get_echo( 'the_excerpt' );
		}

		// The only way to access the value is by calling this function without increasing the value.
		$content_media_count = wp_increase_content_media_count( 0 );

		// Assert that the media count was not increased even though there are 3 images in the post's content.
		$this->assertSame( 0, $content_media_count );
	}

	/**
	 * Tests that the lazy-loading result is not affected by `the_excerpt()` calls for posts that
	 * contain images.
	 *
	 * Printing the excerpt for a post that contains images in its content prior to its featured image should result in
	 * that featured image not being lazy-loaded, since the images in the post content aren't displayed in the excerpt.
	 *
	 * @ticket 56588
	 * @ticket 58235
	 *
	 * @covers ::wp_trim_excerpt
	 */
	public function test_the_excerpt_does_not_affect_omit_lazy_loading_logic() {
		global $wp_query, $wp_the_query;

		/*
		 * Use the filter to alter the threshold for not lazy-loading to the first 2 elements,
		 * then use a post that contains exactly 2 images.
		 */
		$this->force_omit_loading_attr_threshold( 2 );

		$post_content  = '<img src="example.jpg" width="800" height="600">';
		$post_content .= '<p>Some text.</p>';
		$post_content .= '<img src="example2.jpg" width="800" height="600">';

		$post_id           = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_excerpt' => '',
			)
		);
		$featured_image_id = self::$large_id;
		update_post_meta( $post_id, '_thumbnail_id', $featured_image_id );

		$expected_image_tag = get_the_post_thumbnail(
			$post_id,
			'post-thumbnail',
			array(
				'loading'       => false,
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			)
		);

		// Reset high priority flag as the forced `fetchpriority="high"` above already modified it.
		$this->reset_high_priority_element_flag();

		$wp_query     = new WP_Query( array( 'post__in' => array( $post_id ) ) );
		$wp_the_query = $wp_query;

		$output = '';
		while ( have_posts() ) {
			the_post();

			// Print excerpt first, then the featured image.
			$output .= get_echo( 'the_excerpt' );
			$output .= get_echo( 'the_post_thumbnail' );
		}

		$this->assertStringContainsString( $expected_image_tag, $output );
	}

	/**
	 * Tests that wp_filter_content_tags() and more specifically wp_get_loading_optimization_attributes() correctly
	 * handle shortcodes images together with the content that it is part of.
	 *
	 * Images within shortcodes as part of the content should be ignored by wp_get_loading_optimization_attributes() to
	 * avoid double processing. They should instead only be processed together with any other images as part of the
	 * content, to correctly count the original sequencing of those images.
	 *
	 * @ticket 58853
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_handles_shortcode_image_together_with_the_content() {
		global $wp_query, $wp_the_query;

		// Add shortcode that prints a large image, and a block type that wraps it.
		add_shortcode(
			'full_image',
			static function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'id' => 0,
					),
					$atts,
					'full_image'
				);
				return wp_get_attachment_image( (int) $atts['id'], 'full' );
			}
		);

		/*
		 * Even though `do_shortcode()` runs before `wp_filter_content_tags()`, the image from the shortcode should not
		 * receive any loading optimization attributes because it needs to be considered together with the rest of the
		 * post content, within `wp_filter_content_tags()`.
		 * Since the hard-coded image appears before the shortcode image, it should receive `fetchpriority="high"`,
		 * despite the shortcode image being parsed before it.
		 */
		$post_content  = '<img src="example.jpg" width="800" height="600">' . "\n";
		$post_content .= '[full_image id="' . self::$large_id . '"]';
		$post_content  = wpautop( $post_content );

		/*
		 * Prepare the expected output:
		 * 1. On the first image (hard-coded in the content), expect `fetchpriority="high"`.
		 * 2. Replace the shortcode with its expected output, i.e. the full image. Expect neither
		 * `fetchpriority="high"` nor `loading="lazy"`.
		 */
		$expected_content = $post_content;
		$expected_content = str_replace(
			'<img src="example.jpg"',
			'<img fetchpriority="high" decoding="async" src="example.jpg"',
			$expected_content
		);
		$expected_content = str_replace(
			'[full_image id="' . self::$large_id . '"]',
			str_replace(
				'<img ',
				'<img decoding="async" ',
				wp_get_attachment_image(
					self::$large_id,
					'full',
					false,
					array(
						'decoding'      => false,
						'fetchpriority' => false,
						'loading'       => false,
					)
				)
			),
			$expected_content
		);

		// Create post with the content.
		$post_id = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_excerpt' => '',
			)
		);

		// We have to run a main query loop so that the first 'the_content' context images are not lazy-loaded.
		$wp_query     = new WP_Query( array( 'post__in' => array( $post_id ) ) );
		$wp_the_query = $wp_query;

		$content = '';
		while ( have_posts() ) {
			the_post();
			$content = get_echo( 'the_content' );
		}

		// Cleanup.
		remove_shortcode( 'full_image' );

		$this->assertSame( $expected_content, $content );
	}

	/**
	 * Tests that wp_filter_content_tags() and more specifically wp_get_loading_optimization_attributes() correctly
	 * handle shortcodes images within the content, including within a block.
	 *
	 * Images within shortcodes as part of the content should be ignored by wp_get_loading_optimization_attributes() to
	 * avoid double processing. They should instead only be processed together with any other images as part of the
	 * content, to correctly count the original sequencing of those images.
	 *
	 * @ticket 58853
	 *
	 * @covers ::wp_filter_content_tags
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_filter_content_tags_handles_shortcode_images_also_in_blocks_within_the_content() {
		global $wp_query, $wp_the_query;

		// Disable addition of `decoding="async"` as it is irrelevant for this test.
		add_filter(
			'wp_get_loading_optimization_attributes',
			static function ( $loading_attrs ) {
				if ( isset( $loading_attrs['decoding'] ) ) {
					unset( $loading_attrs['decoding'] );
				}
				return $loading_attrs;
			}
		);

		// Do not calculate sizes attribute as it is irrelevant for this test.
		add_filter( 'wp_calculate_image_sizes', '__return_false' );

		// Add shortcode that prints a large image, and a block type that wraps it.
		add_shortcode(
			'full_image',
			static function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'id' => 0,
					),
					$atts,
					'full_image'
				);
				return wp_get_attachment_image( (int) $atts['id'], 'full' );
			}
		);
		register_block_type(
			'core/full-image-shortcode',
			array(
				'render_callback' => static function ( $atts ) {
					if ( empty( $atts['id'] ) ) {
						return '';
					}
					return do_shortcode( '[full_image id="' . $atts['id'] . '"]' );
				},
			)
		);

		/*
		 * Include the following images:
		 * 1. Using gallery shortcode. Expected `fetchpriority="high"`.
		 * 2. Regular hard-coded image.
		 * 3. Using custom shortcode within block.
		 * 4. Regular hard-coded image. Expected `loading="lazy"`.
		 *
		 * The first image is expected to be prioritized because it is the first (large enough) content image.
		 * The first three images are expected to not have lazy-loading because that is the default threshold for
		 * omitting the attribute.
		 * The fourth image is expected to be lazy-loaded as it is past the default threshold.
		 *
		 * The results will only be correct if all images are considered together. For example:
		 * * If the image within the shortcode would only be parsed after the rest of the content, it would miss the
		 * `fetchpriority="high"` attribute and instead incorrectly receive `loading="lazy"`. The second image would as
		 * a result incorrectly receive `fetchpriority="high"`.
		 * * If the image within the block would be parsed before the rest of the content, it would incorrectly receive
		 * the `fetchpriority="high"` attribute. Then the first image would no longer receive the attribute.
		 *
		 * To ensure that this works:
		 * * `wp_filter_content_tags()` must run after `do_blocks()` and `do_shortcode()`.
		 * * `wp_get_loading_optimization_attributes()` must bail early if any images from the content blob are being
		 * considered under a different context name than 'the_content'.
		 */
		$post_content  = '[gallery ids="' . self::$large_id . '" size="large"]' . "\n";
		$post_content .= '<img src="example.jpg" width="800" height="600">' . "\n";
		$post_content .= '<p>Some text.</p>' . "\n";
		$post_content .= '<!-- wp:core/full-image-shortcode {"id":' . self::$large_id . '} --><!-- /wp:core/full-image-shortcode -->' . "\n";
		$post_content .= '<img src="example2.jpg" width="800" height="600">';

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_excerpt' => '',
			)
		);

		/*
		 * Prepare the expected output:
		 * 1. Replace the shortcode with its expected output (ID increased by 1 because of static variable within
		 * the gallery_shortcode() function). Expect `fetchpriority="high"`, but not `loading="lazy"`.
		 * 2. Do not modify the second image as it is hard-coded in the content and expected to be unchanged.
		 * 3. Replace the block with its expected output, i.e. the full image from the shortcode within. Expect neither
		 * `fetchpriority="high"` nor `loading="lazy"`.
		 * 4. On the fourth image (hard-coded in the content), expect `loading="lazy"`.
		 */
		$expected_content = $post_content;
		$expected_content = str_replace(
			'[gallery ids="' . self::$large_id . '" size="large"]',
			str_replace(
				array( ' loading="lazy"', '<img ' ),
				array( '', '<img fetchpriority="high" ' ),
				preg_replace_callback(
					'/gallery-(\d+)/',
					static function ( $matches ) {
						return 'gallery-' . ( (int) $matches[1] + 1 );
					},
					do_shortcode( '[gallery ids="' . self::$large_id . '" size="large" id="' . $post_id . '"]' )
				)
			),
			$expected_content
		);
		$expected_content = str_replace(
			'<!-- wp:core/full-image-shortcode {"id":' . self::$large_id . '} --><!-- /wp:core/full-image-shortcode -->',
			wp_get_attachment_image(
				self::$large_id,
				'full',
				false,
				array(
					'fetchpriority' => false,
					'loading'       => false,
				)
			),
			$expected_content
		);
		$expected_content = str_replace(
			'<img src="example2.jpg"',
			'<img loading="lazy" src="example2.jpg"',
			$expected_content
		);

		// We have to run a main query loop so that the first 'the_content' context images are not lazy-loaded.
		$wp_query     = new WP_Query( array( 'post__in' => array( $post_id ) ) );
		$wp_the_query = $wp_query;

		$content = '';
		while ( have_posts() ) {
			the_post();
			$content = get_echo( 'the_content' );
		}

		// Cleanup.
		remove_shortcode( 'full_image' );
		unregister_block_type( 'core/full-image-shortcode' );

		$this->assertSame( $expected_content, $content );
	}

	private function reset_content_media_count() {
		// Get current value without increasing.
		$content_media_count = wp_increase_content_media_count( 0 );

		// Decrease it by its current value to "reset" it back to 0.
		wp_increase_content_media_count( - $content_media_count );
	}

	private function reset_omit_loading_attr_filter() {
		// Add filter to "reset" omit threshold back to null (unset).
		add_filter( 'wp_omit_loading_attr_threshold', '__return_null', 100 );

		// Force filter application to re-run.
		wp_omit_loading_attr_threshold( true );

		// Clean up the above filter.
		remove_filter( 'wp_omit_loading_attr_threshold', '__return_null', 100 );
	}

	private function reset_high_priority_element_flag() {
		wp_high_priority_element_flag( true );
	}

	/**
	 * Test that generated files with the `image_editor_output_format` applied use the correct
	 * quality level based on their mime type.
	 *
	 * @ticket 56442
	 */
	public function test_quality_with_image_conversion_file_sizes() {
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_jpeg' ) );
		$temp_dir = get_temp_dir();
		$file     = $temp_dir . '/33772.jpg';
		copy( DIR_TESTDATA . '/images/33772.jpg', $file );

		// Set JPEG output quality very low and WebP quality very high, this should force all generated WebP images to
		// be larger than the the matching generated JPEGs.
		add_filter( 'wp_editor_set_quality', array( $this, 'image_editor_change_quality_low_jpeg' ), 10, 2 );

		$editor = wp_get_image_editor( $file );

		// Verify that the selected editor supports WebP output.
		if ( ! $editor->supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( 'WebP is not supported by the selected image editor.' );
		}

		$attachment_id = self::factory()->attachment->create_object(
			array(
				'post_mime_type' => 'image/jpeg',
				'file'           => $file,
			)
		);

		add_filter( 'big_image_size_threshold', array( $this, 'add_big_image_size_threshold' ) );

		// Generate all sizes as JPEGs.
		$jpeg_sizes = wp_generate_attachment_metadata( $attachment_id, $file );
		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_jpeg' ) );

		// Generate all sizes as WebP.
		add_filter( 'image_editor_output_format', array( $this, 'image_editor_output_webp' ) );
		$webp_sizes = wp_generate_attachment_metadata( $attachment_id, $file );
		remove_filter( 'image_editor_output_format', array( $this, 'image_editor_output_webp' ) );

		// The main (scaled) image: the JPEG should be smaller than the WebP.
		$this->assertLessThan( $webp_sizes['filesize'], $jpeg_sizes['filesize'], 'The JPEG should be smaller than the WebP.' );

		// Sub-sizes: for each size, the JPEGs should be smaller than the WebP.
		$sizes_to_compare = array_intersect_key( $jpeg_sizes['sizes'], $webp_sizes['sizes'] );
		foreach ( $sizes_to_compare as $size => $size_data ) {
			$this->assertLessThan( $webp_sizes['sizes'][ $size ]['filesize'], $jpeg_sizes['sizes'][ $size ]['filesize'] );
		}
	}

	/**
	 * Test that an image size isn't generated if it matches the original image size.
	 *
	 * @ticket 57370
	 */
	public function test_wp_generate_attachment_metadata_doesnt_generate_sizes_for_150_square_image() {
		$temp_dir = get_temp_dir();
		$file     = $temp_dir . '/test-square-150.jpg';
		copy( DIR_TESTDATA . '/images/test-square-150.jpg', $file );

		$attachment_id = self::factory()->attachment->create_object(
			array(
				'post_mime_type' => 'image/jpeg',
				'file'           => $file,
			)
		);

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		$this->assertSame(
			array(),
			$metadata['sizes'],
			'The sizes should be an empty array'
		);
		$this->assertSame(
			'test-square-150.jpg',
			basename( $metadata['file'] ),
			'The file basename should match the given filename'
		);
		$this->assertSame(
			150,
			$metadata['width'],
			'The width should be 150 (integer)'
		);
		$this->assertSame(
			150,
			$metadata['height'],
			'The height should be 150 (integer)'
		);
	}

	/**
	 * Tests that `wp_get_attachment_image()` uses the correct default context.
	 *
	 * @ticket 58212
	 *
	 * @covers ::wp_get_attachment_image
	 */
	public function test_wp_get_attachment_image_context_filter_default() {
		$last_context = '';
		$this->track_last_attachment_image_context( $last_context );

		wp_get_attachment_image( self::$large_id );
		$this->assertSame( 'wp_get_attachment_image', $last_context );
	}

	/**
	 * Tests that `wp_get_attachment_image()` allows overriding the context via filter.
	 *
	 * @ticket 58212
	 *
	 * @covers ::wp_get_attachment_image
	 */
	public function test_wp_get_attachment_image_context_filter_value_is_passed_correctly() {
		$last_context = '';
		$this->track_last_attachment_image_context( $last_context );

		// Add a filter that modifies the context.
		add_filter(
			'wp_get_attachment_image_context',
			static function () {
				return 'my_custom_context';
			}
		);

		wp_get_attachment_image( self::$large_id );
		$this->assertSame( 'my_custom_context', $last_context );
	}

	/**
	 * Tests tag restriction for `wp_get_loading_optimization_attributes()`.
	 *
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_optimization_attributes_min_required_attrs
	 *
	 * @param string $tag_name The tag name.
	 * @param string $attr Element attributes.
	 * @param array  $expected Expected return value.
	 * @param string $message Message to display if the test fails.
	 */
	public function test_wp_get_loading_optimization_attributes_min_required_attrs( $tag_name, $attr, $expected, $message ) {
		$context = 'the_post_thumbnail';
		$this->assertSame( wp_get_loading_optimization_attributes( $tag_name, $attr, $context ), $expected, $message );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_loading_optimization_attributes_min_required_attrs() {
		return array(
			'img_with_min_attrs' => array(
				'img',
				array(
					'width'  => 100,
					'height' => 100,
				),
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				'Expected default `decoding="async"` and `loading="lazy"`.',
			),
			'img_without_height' => array(
				'img',
				array( 'width' => 100 ),
				array(
					'decoding' => 'async',
				),
				'Only `decoding` is set as height is required for `loading` attribute.',
			),
			'img_without_width'  => array(
				'img',
				array( 'height' => 100 ),
				array(
					'decoding' => 'async',
				),
				'Only `decoding` is set as width is required for `loading` attribute.',
			),
		);
	}

	/**
	 * Tests tag restriction for `wp_get_loading_optimization_attributes()`.
	 *
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_optimization_attributes_check_allowed_tags
	 *
	 * @param string $tag_name The tag name.
	 * @param array  $expected Expected return value.
	 * @param string $message Message to display if the test fails.
	 */
	public function test_wp_get_loading_optimization_attributes_check_allowed_tags( $tag_name, $expected, $message ) {
		$attr    = $this->get_width_height_for_high_priority();
		$context = 'the_post_thumbnail';
		$this->assertSame( wp_get_loading_optimization_attributes( $tag_name, $attr, $context ), $expected, $message );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_loading_optimization_attributes_check_allowed_tags() {
		return array(
			'img'    => array(
				'img',
				array(
					'decoding' => 'async',
					'loading'  => 'lazy',
				),
				'Expected `decoding="async"` and `loading="lazy"` and `decoding="async"` for the img.',
			),
			'iframe' => array(
				'iframe',
				array(
					'loading' => 'lazy',
				),
				'Expected `loading="lazy"` for the iframe.',
			),
			'video'  =>
			array(
				'video',
				array(),
				'Function should return empty array as video tag is not supported.',
			),
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_skip_for_block_template() {
		$attr = $this->get_width_height_for_high_priority();

		// Skip logic if context is `template`.
		$this->assertSame(
			array(),
			wp_get_loading_optimization_attributes( 'img', $attr, 'template' ),
			'Skip logic and return blank array for block template.'
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_header_block_template() {
		$attr = $this->get_width_height_for_high_priority();

		// Skip logic if context is `template`.
		$this->assertSameSetsWithIndex(
			array(
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'template_part_' . WP_TEMPLATE_PART_AREA_HEADER ),
			'Images in the header block template part should not be lazy-loaded and first large image is set high fetchpriority.'
		);
	}

	/**
	 * @ticket 58235
	 * @ticket 58892
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 * @expectedIncorrectUsage wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_incorrect_loading_attrs() {
		$attr                  = $this->get_width_height_for_high_priority();
		$attr['loading']       = 'lazy';
		$attr['fetchpriority'] = 'high';

		$this->assertEqualSetsWithIndex(
			array(
				'decoding'      => 'async',
				'loading'       => 'lazy',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'test' ),
			'This should return both lazy-loading and high fetchpriority, but with doing_it_wrong message.'
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_if_loading_attr_present() {
		$attr            = $this->get_width_height_for_high_priority();
		$attr['loading'] = 'eager';

		// Check fetchpriority high logic if loading attribute is present.
		$this->assertSameSetsWithIndex(
			array(
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'test' ),
			'fetchpriority should be set to high.'
		);
	}

	/**
	 * Tests that wp_img_tag_add_loading_optimization_attrs() passes the 'src' attribute to wp_get_loading_optimization_attributes().
	 *
	 * @ticket 61436
	 *
	 * @covers ::wp_img_tag_add_loading_optimization_attrs
	 */
	public function test_wp_img_tag_add_loading_optimization_attrs_passes_src() {
		add_filter(
			'wp_get_loading_optimization_attributes',
			static function ( $loading_attrs, $tag_name, $attr ) {
				if (
					'img' === $tag_name &&
					isset( $attr['src'] ) &&
					'https://example.org/a-specific-image.jpg' === $attr['src']
				) {
					$loading_attrs['fetchpriority'] = 'low';
					$loading_attrs['loading']       = 'eager';
				}
				return $loading_attrs;
			},
			10,
			3
		);

		$image    = '<img src="https://example.org/a-specific-image.jpg" width="1280" height="720">';
		$expected = '<img fetchpriority="low" loading="eager" decoding="async" src="https://example.org/a-specific-image.jpg" width="1280" height="720">';

		// Ensure attributes are modified because image src was matched.
		$this->assertSame(
			$expected,
			wp_img_tag_add_loading_optimization_attrs( $image, 'the_content' ),
			'fetchpriority should be low when src is matched.'
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_low_res_image() {
		$attr = array(
			'width'   => 100,
			'height'  => 100,
			'loading' => 'eager',
		);

		// fetchpriority not set as image is of lower resolution.
		$this->assertSame(
			array(
				'decoding' => 'async',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'test' ),
			'loading optimization attr array should be empty.'
		);
	}

	/**
	 * Tests that the `do_shortcode` context results in a lazy-loaded image by default.
	 *
	 * @ticket 58681
	 * @ticket 58853
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_in_shortcodes() {
		$attr = $this->get_width_height_for_high_priority();

		// Shortcodes processed outside of content blobs like 'the_content' always get `loading="lazy"`.
		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'do_shortcode' ),
			'Lazy-loading not applied to shortcodes outside the loop.'
		);
	}

	/**
	 * Tests that the `do_shortcode` context does not result in loading optimization changes when used within a content
	 * blob.
	 *
	 * @ticket 58853
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_get_filters_with_do_shortcode_callback
	 *
	 * @param string $filter_name The name of the filter to hook.
	 */
	public function test_wp_get_loading_optimization_attributes_in_shortcodes_within_content_blob( $filter_name ) {
		$result = null;

		remove_all_filters( $filter_name );
		add_filter(
			$filter_name,
			function ( $content ) use ( &$result ) {
				$attr   = $this->get_width_height_for_high_priority();
				$result = wp_get_loading_optimization_attributes( 'img', $attr, 'do_shortcode' );
				return $content;
			}
		);
		apply_filters( $filter_name, '' );

		// Shortcodes processed within content blobs like 'the_content' should never get any loading optimization attributes.
		$this->assertSame(
			array(),
			$result,
			'Loading optimization unexpectedly applied to shortcodes within content blob.'
		);
	}

	/**
	 * Gets filters for content blobs that by default have a `do_shortcode()` callback.
	 *
	 * @return array[]
	 */
	public function data_get_filters_with_do_shortcode_callback() {
		return self::text_array_to_dataprovider(
			array(
				'the_content',
				'widget_text_content',
				'widget_block_content',
			)
		);
	}

	/**
	 * @ticket 58681
	 */
	public function test_content_rendering_with_shortcodes() {
		// The gallery shortcode will dynamically create image markup that should be optimized.
		$content = "[gallery ids='" . self::$large_id . "' size='large']";
		$actual  = apply_filters( 'the_content', $content );

		$this->assertStringContainsString(
			// Since the main query and loop isn't set, this should be lazily loaded.
			'loading="lazy"',
			$actual,
			'Could not confirm shortcodes get optimizations applied.'
		);
	}

	/**
	 * @ticket 58681
	 */
	public function test_content_rendering_with_shortcodes_nested() {
		global $wp_query;

		// Set WP_Query to be in the loop and the main query.
		$wp_query->in_the_loop = true;
		$this->set_main_query( $wp_query );

		add_shortcode(
			'div',
			function ( $atts, $content = null ) {
				$parsed_atts = shortcode_atts(
					array(
						'class' => '',
					),
					$atts
				);

				$class = ! empty( $parsed_atts['class'] ) ? sprintf( ' class="%s"', $parsed_atts['class'] ) : null;

				return sprintf( '<div %s>%s</div>', $class, do_shortcode( $content ) );
			}
		);

		// The gallery shortcode will dynamically create image markup that should be optimized.
		$content = "[div][gallery ids='" . self::$large_id . "' size='large'][div]";
		$actual  = apply_filters( 'the_content', $content );

		$this->assertStringContainsString(
			// Since this is in the loop, it should have a high fetchpriority.
			'fetchpriority="high"',
			$actual,
			'Could not confirm shortcodes get optimizations applied.'
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_maybe_add_fetchpriority_high_attr
	 *
	 * @dataProvider data_wp_maybe_add_fetchpriority_high_attr
	 */
	public function test_wp_maybe_add_fetchpriority_high_attr( $loading_attrs, $tag_name, $attr, $expected_fetchpriority ) {
		$loading_attrs = wp_maybe_add_fetchpriority_high_attr( $loading_attrs, $tag_name, $attr );

		if ( $expected_fetchpriority ) {
			$this->assertArrayHasKey( 'fetchpriority', $loading_attrs, 'fetchpriority attribute should be present' );
			$this->assertSame( $expected_fetchpriority, $loading_attrs['fetchpriority'], 'fetchpriority attribute has incorrect value' );
		} else {
			$this->assertArrayNotHasKey( 'fetchpriority', $loading_attrs, 'fetchpriority attribute should not be present' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_maybe_add_fetchpriority_high_attr() {
		return array(
			'small image'                   => array(
				array(),
				'img',
				$this->get_insufficient_width_height_for_high_priority(),
				false,
			),
			'large image'                   => array(
				array(),
				'img',
				$this->get_width_height_for_high_priority(),
				'high',
			),
			'image with loading=lazy'       => array(
				array(
					'loading'  => 'lazy',
					'decoding' => 'async',
				),
				'img',
				$this->get_width_height_for_high_priority(),
				false,
			),
			'image with loading=eager'      => array(
				array( 'loading' => 'eager' ),
				'img',
				$this->get_width_height_for_high_priority(),
				'high',
			),
			'image with fetchpriority=high' => array(
				array(),
				'img',
				array_merge(
					$this->get_insufficient_width_height_for_high_priority(),
					array( 'fetchpriority' => 'high' )
				),
				'high',
			),
			'image with fetchpriority=low'  => array(
				array(),
				'img',
				array_merge(
					$this->get_insufficient_width_height_for_high_priority(),
					array( 'fetchpriority' => 'low' )
				),
				false,
			),
			'non-image element'             => array(
				array(),
				'video',
				$this->get_width_height_for_high_priority(),
				false,
			),
		);
	}

	/**
	 * @ticket 58235
	 *
	 * @covers ::wp_maybe_add_fetchpriority_high_attr
	 */
	public function test_wp_maybe_add_fetchpriority_high_attr_min_priority_filter() {
		$attr = array(
			'width'  => 50,
			'height' => 50,
		);

		add_filter(
			'wp_min_priority_img_pixels',
			static function ( $res ) {
				return 2500; // 50*50=2500
			}
		);

		// fetchpriority set to high as resolution is equal to (or greater than) 2500.
		$this->assertSame(
			array(
				'fetchpriority' => 'high',
			),
			wp_maybe_add_fetchpriority_high_attr( array(), 'img', $attr )
		);
	}

	/**
	 * @ticket 58635
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_header_block_template_increase_media_count() {
		$attr = $this->get_width_height_for_high_priority();
		wp_get_loading_optimization_attributes( 'img', $attr, 'template_part_' . WP_TEMPLATE_PART_AREA_HEADER );

		// Images with a certain minimum size in the header of the page are also counted towards the threshold.
		$this->assertSame( 1, wp_increase_content_media_count( 0 ) );
	}

	/**
	 * @ticket 58635
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 */
	public function test_wp_get_loading_optimization_attributes_header_image_tag_increase_media_count() {
		$attr = $this->get_width_height_for_high_priority();
		wp_get_loading_optimization_attributes( 'img', $attr, 'get_header_image_tag' );

		// Images with a certain minimum size in the header of the page are also counted towards the threshold.
		$this->assertSame( 1, wp_increase_content_media_count( 0 ) );
	}

	/**
	 * @ticket 58635
	 *
	 * @covers ::wp_get_loading_optimization_attributes
	 *
	 * @dataProvider data_wp_get_loading_attr_default_before_and_no_loop
	 *
	 * @param string $context Context for the element for which the `loading` attribute value is requested.
	 */
	public function test_wp_get_loading_optimization_attributes_image_before_loop_increase_media_count( $context ) {
		global $wp_query;

		$wp_query = $this->get_new_wp_query_for_published_post();
		$this->set_main_query( $wp_query );
		do_action( 'get_header' );

		$attr = $this->get_width_height_for_high_priority();
		wp_get_loading_optimization_attributes( 'img', $attr, $context );

		// Images with a certain minimum size in the header of the page are also counted towards the threshold.
		$this->assertSame( 1, wp_increase_content_media_count( 0 ) );
	}

	/**
	 * Tests for pre_wp_get_loading_optimization_attributes filter.
	 *
	 * @ticket 58893
	 */
	public function test_pre_wp_get_loading_optimization_attributes_filter() {
		add_filter(
			'pre_wp_get_loading_optimization_attributes',
			static function ( $loading_attrs ) {
				if ( false === $loading_attrs ) {
					// Initialize as an empty array.
					$loading_attrs = array();
				}
				$loading_attrs['fetchpriority'] = 'high';

				return $loading_attrs;
			},
			10,
			1
		);

		$attr = $this->get_width_height_for_high_priority();

		$this->assertSameSetsWithIndex(
			array( 'fetchpriority' => 'high' ),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'The filter did not return early fetchpriority attribute'
		);

		// Clean up the filter.
		add_filter( 'pre_wp_get_loading_optimization_attributes', '__return_false' );

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'The filter did not return the default attributes.'
		);

		// Return no loading attributes.
		add_filter( 'pre_wp_get_loading_optimization_attributes', '__return_empty_array' );

		$this->assertSameSetsWithIndex(
			array(),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'The filter did not clean up all attributes.'
		);

		// Modify the loading attributes with any custom attributes.
		add_filter(
			'pre_wp_get_loading_optimization_attributes',
			static function ( $loading_attrs ) {
				if ( false === $loading_attrs ) {
					// Initialize as an empty array.
					$loading_attrs = array();
				}
				$loading_attrs['custom_attr'] = 'custom_value';

				return $loading_attrs;
			},
			10,
			1
		);

		$this->assertSameSetsWithIndex(
			array( 'custom_attr' => 'custom_value' ),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'The filter did not return custom attributes.'
		);
	}

	/**
	 * Tests for wp_get_loading_optimization_attributes filter.
	 *
	 * @ticket 58893
	 */
	public function test_wp_get_loading_optimization_attributes_filter() {
		$attr = $this->get_width_height_for_high_priority();

		$this->assertSameSetsWithIndex(
			array(
				'decoding' => 'async',
				'loading'  => 'lazy',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'Before the filter it will not return the loading attribute.'
		);

		add_filter(
			'wp_get_loading_optimization_attributes',
			static function ( $loading_attrs ) {
				unset( $loading_attrs['loading'] );
				$loading_attrs['fetchpriority'] = 'high';

				return $loading_attrs;
			},
			10,
			1
		);

		$this->assertSameSetsWithIndex(
			array(
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			),
			wp_get_loading_optimization_attributes( 'img', $attr, 'the_content' ),
			'After the filter it will not return the fetchpriority attribute.'
		);
	}

	/**
	 * Test generated markup for an image with lazy loading gets auto-sizes.
	 *
	 * @ticket 61847
	 */
	public function test_image_with_lazy_loading_has_auto_sizes() {
		$this->assertStringContainsString(
			'sizes="auto, ',
			wp_get_attachment_image( self::$large_id, 'large', false, array( 'loading' => 'lazy' ) ),
			'Failed asserting that the sizes attribute for a lazy-loaded image includes "auto".'
		);
	}

	/**
	 * Test generated markup for an image without lazy loading does not get auto-sizes.
	 *
	 * @ticket 61847
	 */
	public function test_image_without_lazy_loading_does_not_have_auto_sizes() {
		$this->assertStringNotContainsString(
			'sizes="auto, ',
			wp_get_attachment_image( self::$large_id, 'large', false, array( 'loading' => false ) ),
			'Failed asserting that the sizes attribute for an image without lazy loading does not include "auto".'
		);
	}

	/**
	 * Test content filtered markup with lazy loading gets auto-sizes.
	 *
	 * @ticket 61847
	 *
	 * @covers ::wp_img_tag_add_auto_sizes
	 */
	public function test_content_image_with_lazy_loading_has_auto_sizes() {
		// Force lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_true' );

		$this->assertStringContainsString(
			'sizes="auto, (max-width: 1024px) 100vw, 1024px"',
			wp_filter_content_tags( get_image_tag( self::$large_id, '', '', '', 'large' ) ),
			'Failed asserting that the sizes attribute for a content image with lazy loading includes "auto" with the expected sizes.'
		);
	}

	/**
	 * Test content filtered markup without lazy loading does not get auto-sizes.
	 *
	 * @ticket 61847
	 *
	 * @covers ::wp_img_tag_add_auto_sizes
	 */
	public function test_content_image_without_lazy_loading_does_not_have_auto_sizes() {
		// Disable lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );

		$this->assertStringNotContainsString(
			'sizes="auto, ',
			wp_filter_content_tags( get_image_tag( self::$large_id, '', '', '', 'large' ) ),
			'Failed asserting that the sizes attribute for a content image without lazy loading does not include "auto" with the expected sizes.'
		);
	}

	/**
	 * Test generated markup for an image with 'auto' keyword already present in sizes does not receive it again.
	 *
	 * @ticket 61847
	 *
	 * @covers ::wp_img_tag_add_auto_sizes
	 * @covers ::wp_sizes_attribute_includes_valid_auto
	 *
	 * @dataProvider data_image_with_existing_auto_sizes
	 *
	 * @param string $initial_sizes      The initial sizes attribute to test.
	 * @param bool   $expected_processed Whether the auto sizes should be processed or not.
	 */
	public function test_image_with_existing_auto_sizes_is_not_processed_again( string $initial_sizes, bool $expected_processed ) {
		$image_tag = wp_get_attachment_image(
			self::$large_id,
			'large',
			false,
			array(
				// Force pre-existing 'sizes' attribute and lazy-loading.
				'sizes'   => $initial_sizes,
				'loading' => 'lazy',
			)
		);
		if ( $expected_processed ) {
			$this->assertStringContainsString(
				'sizes="auto, ' . $initial_sizes . '"',
				$image_tag,
				'Failed asserting that "auto" keyword is not added to sizes attribute when it already exists.'
			);
		} else {
			$this->assertStringContainsString(
				'sizes="' . $initial_sizes . '"',
				$image_tag,
				'Failed asserting that "auto" keyword is not added to sizes attribute when it already exists.'
			);
		}
	}

	/**
	 * Test content filtered markup with 'auto' keyword already present in sizes does not receive it again.
	 *
	 * @ticket 61847
	 *
	 * @covers ::wp_img_tag_add_auto_sizes
	 * @covers ::wp_sizes_attribute_includes_valid_auto
	 *
	 * @dataProvider data_image_with_existing_auto_sizes
	 *
	 * @param string $initial_sizes      The initial sizes attribute to test.
	 * @param bool   $expected_processed Whether the auto sizes should be processed or not.
	 */
	public function test_content_image_with_existing_auto_sizes_is_not_processed_again( string $initial_sizes, bool $expected_processed ) {
		// Force lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_true' );

		add_filter(
			'get_image_tag',
			static function ( $html ) use ( $initial_sizes ) {
				return str_replace(
					'" />',
					'" sizes="' . $initial_sizes . '" />',
					$html
				);
			}
		);

		$image_content = wp_filter_content_tags( get_image_tag( self::$large_id, '', '', '', 'large' ) );
		if ( $expected_processed ) {
			$this->assertStringContainsString(
				'sizes="auto, ' . $initial_sizes . '"',
				$image_content,
				'Failed asserting that "auto" keyword is not added to sizes attribute in filtered content when it already exists.'
			);
		} else {
			$this->assertStringContainsString(
				'sizes="' . $initial_sizes . '"',
				$image_content,
				'Failed asserting that "auto" keyword is not added to sizes attribute in filtered content when it already exists.'
			);
		}
	}

	/**
	 * Returns data for the above test methods to assert correct behavior with a pre-existing sizes attribute.
	 *
	 * @return array<string, mixed[]> Arguments for the test scenarios.
	 */
	public function data_image_with_existing_auto_sizes() {
		return array(
			'not present'                 => array(
				'(max-width: 1024px) 100vw, 1024px',
				true,
			),
			'in beginning, without space' => array(
				'auto,(max-width: 1024px) 100vw, 1024px',
				false,
			),
			'in beginning, with space'    => array(
				'auto, (max-width: 1024px) 100vw, 1024px',
				false,
			),
			'sole keyword'                => array(
				'auto',
				false,
			),
			'with space before'           => array(
				' auto, (max-width: 1024px) 100vw, 1024px',
				false,
			),
			'with uppercase'              => array(
				'AUTO, (max-width: 1024px) 100vw, 1024px',
				false,
			),

			/*
			 * The following scenarios technically include the 'auto' keyword,
			 * but it is in the wrong place, as per the HTML spec it must be
			 * the first entry in the list.
			 * Therefore in these invalid cases the 'auto' keyword should still
			 * be added to the beginning of the list.
			 */
			'within, without space'       => array(
				'(max-width: 1024px) 100vw, auto,1024px',
				true,
			),
			'within, with space'          => array(
				'(max-width: 1024px) 100vw, auto, 1024px',
				true,
			),
			'at the end, without space'   => array(
				'(max-width: 1024px) 100vw,auto',
				true,
			),
			'at the end, with space'      => array(
				'(max-width: 1024px) 100vw, auto',
				true,
			),
		);
	}

	/**
	 * Data provider for test_wp_img_tag_add_auto_sizes().
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_wp_img_tag_add_auto_sizes() {
		return array(
			'expected_with_single_quoted_attributes'       => array(
				'input'    => "<img src='https://example.com/foo-300x225.jpg' srcset='https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w' sizes='(max-width: 650px) 100vw, 650px' loading='lazy'>",
				'expected' => "<img src='https://example.com/foo-300x225.jpg' srcset='https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w' sizes=\"auto, (max-width: 650px) 100vw, 650px\" loading='lazy'>",
			),
			'expected_with_data_sizes_attribute'           => array(
				'input'    => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading="lazy">',
				'expected' => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading="lazy">',
			),
			'expected_with_data_sizes_attribute_already_present' => array(
				'input'    => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="AUTO, (max-width: 650px) 100vw, 650px" loading="lazy">',
				'expected' => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="AUTO, (max-width: 650px) 100vw, 650px" loading="lazy">',
			),
			'not_expected_with_loading_lazy_in_attr_value' => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" alt=\'This is the LCP image and it should not get loading="lazy"!\'>',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" alt=\'This is the LCP image and it should not get loading="lazy"!\'>',
			),
			'not_expected_with_data_loading_attribute_present' => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" data-removed-loading="lazy">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" data-removed-loading="lazy">',
			),
			'expected_when_attributes_have_spaces_after_them' => array(
				'input'    => '<img src = "https://example.com/foo-300x225.jpg" srcset = "https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes = "(max-width: 650px) 100vw, 650px" loading = "lazy">',
				'expected' => '<img src = "https://example.com/foo-300x225.jpg" srcset = "https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading = "lazy">',
			),
			'expected_when_attributes_are_upper_case'      => array(
				'input'    => '<IMG SRC="https://example.com/foo-300x225.jpg" SRCSET="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" SIZES="(max-width: 650px) 100vw, 650px" LOADING="LAZY">',
				'expected' => '<IMG SRC="https://example.com/foo-300x225.jpg" SRCSET="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" LOADING="LAZY">',
			),
			'expected_when_loading_lazy_lacks_quotes'      => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading=lazy>',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading=lazy>',
			),
			'expected_when_loading_lazy_has_whitespace'    => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading=" lazy ">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading=" lazy ">',
			),
			'not_expected_when_sizes_auto_lacks_quotes'    => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes=auto loading="lazy">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes=auto loading="lazy">',
			),
		);
	}

	/**
	 * @ticket 61847
	 *
	 * @covers ::wp_img_tag_add_auto_sizes
	 *
	 * @dataProvider data_provider_to_test_wp_img_tag_add_auto_sizes
	 *
	 * @param string $input    The input HTML string.
	 * @param string $expected The expected output HTML string.
	 */
	public function test_wp_img_tag_add_auto_sizes( string $input, string $expected ) {
		$this->assertSame(
			$expected,
			wp_img_tag_add_auto_sizes( $input ),
			'Failed asserting that "auto" keyword is correctly added or not added to sizes attribute in the image tag.'
		);
	}

	/**
	 * Helper method to keep track of the last context returned by the 'wp_get_attachment_image_context' filter.
	 *
	 * The method parameter is passed by reference and therefore will always contain the last context value.
	 *
	 * @param mixed $last_context Variable to track last context. Passed by reference.
	 */
	private function track_last_attachment_image_context( &$last_context ) {
		add_filter(
			'wp_get_attachment_image_context',
			static function ( $context ) use ( &$last_context ) {
				$last_context = $context;
				return $context;
			},
			11
		);
	}

	/**
	 * Add threshold to create a `-scaled` output image for testing.
	 */
	public function add_big_image_size_threshold() {
		return 1000;
	}

	/**
	 * Output JPEG files.
	 */
	public function image_editor_output_jpeg() {
		return array( 'image/jpeg' => 'image/jpeg' );
	}

	/**
	 * Output WebP files.
	 */
	public function image_editor_output_webp() {
		return array( 'image/jpeg' => 'image/webp' );
	}

	/**
	 * Changes the quality using very low quality for JPEGs and very high quality
	 * for WebPs, used to verify the filter is applying correctly.
	 *
	 * @param int    $quality   Default quality.
	 * @param string $mime_type Image mime-type.
	 * @return int The changed quality.
	 */
	public function image_editor_change_quality_low_jpeg( $quality, $mime_type ) {
		if ( 'image/jpeg' === $mime_type ) {
			return 1;
		} elseif ( 'image/webp' === $mime_type ) {
			return 100;
		} else {
			return 30;
		}
	}

	/**
	 * Change the omit loading attribute threshold value.
	 *
	 * @param int $threshold Threshold value to change.
	 */
	public function force_omit_loading_attr_threshold( $threshold ) {
		add_filter(
			'wp_omit_loading_attr_threshold',
			static function () use ( $threshold ) {
				return $threshold;
			}
		);
	}

	/**
	 * Returns a new WP_Query.
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 *
	 * @return WP_Query a new query.
	 */
	public function get_new_wp_query_for_published_post() {
		global $wp_query;

		// New query to $wp_query. update global for the loop.
		$wp_query = new WP_Query( array( 'post__in' => array( self::$post_ids['publish'] ) ) );

		return $wp_query;
	}

	/**
	 * Sets a query as main query.
	 *
	 * @global WP_Query $wp_the_query WordPress Query object.
	 *
	 * @param WP_Query $query query to be set as main query.
	 */
	public function set_main_query( $query ) {
		global $wp_the_query;
		$wp_the_query = $query;
	}

	/**
	 * Returns an array with dimension attribute values eligible for a high priority image.
	 *
	 * @return array Associative array with 'width' and 'height' keys.
	 */
	private function get_width_height_for_high_priority() {
		/*
		 * The product of width * height must be >50000 to qualify for high priority image.
		 * 300 * 200 = 60000
		 */
		return array(
			'width'  => 300,
			'height' => 200,
		);
	}

	/**
	 * Returns an array with dimension attribute values ineligible for a high priority image.
	 *
	 * @return array Associative array with 'width' and 'height' keys.
	 */
	private function get_insufficient_width_height_for_high_priority() {
		/*
		 * The product of width * height must be >50000 to qualify for high priority image.
		 * 200 * 100 = 20000
		 */
		return array(
			'width'  => 200,
			'height' => 100,
		);
	}
}

/**
 * Helper class for `test_autoembed`.
 */
class Test_Autoembed extends WP_Embed {
	public function shortcode( $attr, $url = '' ) {
		return '[embed]';
	}
}
