<?php
/**
 * @group media
 *
 * @covers ::get_post_galleries
 */
class Tests_Media_GetPostGalleries extends WP_UnitTestCase {

	const IMG_META = array(
		'width'  => 100,
		'height' => 100,
		'sizes'  => '',
	);

	/**
	 * Tests that an empty array is returned for a post that does not exist.
	 *
	 * @ticket 43826
	 */
	public function test_returns_empty_array_with_non_existent_post() {
		$galleries = get_post_galleries( 99999, false );
		$this->assertEmpty( $galleries );
	}

	/**
	 * Tests that an empty array is returned for a post that has no gallery.
	 *
	 * @ticket 43826
	 */
	public function test_returns_empty_array_with_post_with_no_gallery() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<p>A post with no gallery</p>',
			)
		);

		$galleries = get_post_galleries( $post_id, false );
		$this->assertEmpty( $galleries );
	}

	/**
	 * Tests that only galleries are returned.
	 *
	 * @dataProvider data_returns_only_galleries
	 *
	 * @ticket 55203
	 *
	 * @param string $content The content of the post.
	 * @param string $needle  The content of a non-gallery block.
	 */
	public function test_returns_only_galleries( $content, $needle ) {
		$image_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg';

		$content = str_replace(
			array( 'IMAGE_ID', 'IMAGE_URL' ),
			array( $image_id, $image_url ),
			$content
		);

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
			)
		);

		$galleries = get_post_galleries( $post_id );
		$actual    = implode( '', $galleries );

		$this->assertStringNotContainsString( $needle, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_returns_only_galleries() {
		$gallery = '
		<!-- wp:gallery {"linkTo":"none","className":"columns-2"} -->
		<figure
		class="wp-block-gallery has-nested-images columns-default is-cropped columns-2"
		>
		<!-- wp:image {"id":IMAGE_ID,"sizeSlug":"large","linkDestination":"none"} -->
		<figure class="wp-block-image size-large">
		<img
		src="IMAGE_URL"
		alt="Image gallery image"
		class="wp-image-IMAGE_ID"
		/>
		</figure>
		<!-- /wp:image -->
		</figure>
		<!-- /wp:gallery -->
		';

		return array(
			'a paragraph before a gallery' => array(
				'content' => '<!-- wp:paragraph --><p>A paragraph before a gallery.</p><!-- /wp:paragraph -->' . $gallery,
				'needle'  => 'A paragraph before a gallery.',
			),
			'a paragraph after a gallery'  => array(
				'content' => $gallery . '<!-- wp:paragraph --><p>A paragraph after a gallery.</p><!-- /wp:paragraph -->',
				'needle'  => 'A paragraph after a gallery.',
			),
		);
	}

	/**
	 * Tests that no srcs are returned for a shortcode gallery
	 * in a post with no attached images.
	 *
	 * @ticket 39304
	 *
	 * @group shortcode
	 */
	public function test_returns_no_srcs_with_shortcode_in_post_with_no_attached_images() {
		// Set up an unattached image.
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '[gallery]',
			)
		);

		$galleries = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertEmpty(
			$galleries[0]['src'],
			'The src key is not empty.'
		);
	}

	/**
	 * Tests that no srcs are returned for a gallery block
	 * in a post with no attached images.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_no_srcs_with_block_in_post_with_no_attached_images() {
		// Set up an unattached image.
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:gallery -->',
			)
		);

		$galleries = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of strings
		 * instead of an array of arrays.
		 */
		$this->assertIsArray(
			$galleries[0],
			'The returned data does not contain an array.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertEmpty(
			$galleries[0]['src'],
			'The src key of the first gallery is not empty.'
		);
	}

	/**
	 * Tests that no srcs are returned for a gallery block v2
	 * in a post with no attached images.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_no_srcs_with_block_v2_in_post_with_no_attached_images() {
		// Set up an unattached image.
		$image_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg';

		$blob = <<< BLOB
<!-- wp:gallery {"linkTo":"none","className":"columns-2"} -->
<figure
	class="wp-block-gallery has-nested-images columns-default is-cropped columns-2"
>
	<!-- wp:image {"id":$image_id,"sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large">
		<img
			src="$image_url"
			alt="Image gallery image"
			class="wp-image-$image_id"
		/>
	</figure>
	<!-- /wp:image -->
</figure>
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);

		$expected_srcs = array( $image_url );
		$galleries     = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of strings
		 * instead of an array of arrays.
		 */
		$this->assertIsArray(
			$galleries[0],
			'The returned data does not contain an array.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			$expected_srcs,
			$galleries[0]['src'],
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that HTML is returned for a shortcode gallery.
	 *
	 * @ticket 43826
	 *
	 * @group shortcode
	 */
	public function test_returns_html_with_shortcode_gallery() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'I have no gallery',
			)
		);

		$post_id_two = self::factory()->post->create(
			array(
				'post_content' => "[gallery id='$post_id']",
			)
		);

		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$expected  = 'src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg"';
		$galleries = get_post_galleries( $post_id_two );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of arrays
		 * instead of an array of strings.
		 */
		$this->assertIsString(
			$galleries[0],
			'Did not return the data as a string.'
		);

		$this->assertStringContainsString(
			$expected,
			$galleries[0],
			'The returned data did not contain a src attribute with the expected image URL.'
		);
	}

	/**
	 * Tests that HTML is returned for a block gallery.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_html_with_block_gallery() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'I have no gallery.',
			)
		);

		// Set up an unattached image.
		$image_id = self::factory()->attachment->create(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg';

		$blob = <<< BLOB
<!-- wp:gallery -->
<figure><img src="$image_url" data-id="$image_id" /></figure>
<!-- /wp:gallery -->
BLOB;

		$post_id_two = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);

		$expected  = 'src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg"';
		$galleries = get_post_galleries( $post_id_two );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of arrays
		 * instead of an array of strings.
		 */
		$this->assertIsString(
			$galleries[0],
			'Did not return the data as a string.'
		);

		$this->assertStringContainsString(
			$expected,
			$galleries[0],
			'The returned data did not contain a src attribute with the expected image URL.'
		);
	}

	/**
	 * Tests that HTML is returned for a block gallery v2.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_html_with_block_gallery_v2() {
		$image_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg';

		$blob = <<< BLOB
<!-- wp:gallery {"linkTo":"none","className":"columns-2"} -->
<figure
	class="wp-block-gallery has-nested-images columns-default is-cropped columns-2"
>
	<!-- wp:image {"id":$image_id,"sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large">
		<img
			src="$image_url"
			alt="Image gallery image"
			class="wp-image-$image_id"
		/>
	</figure>
	<!-- /wp:image -->
</figure>
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);

		$expected  = 'src="http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg"';
		$galleries = get_post_galleries( $post_id );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of arrays
		 * instead of an array of strings.
		 */
		$this->assertIsString(
			$galleries[0],
			'Did not return the data as a string.'
		);

		$this->assertStringContainsString(
			$expected,
			$galleries[0],
			'The returned data did not contain a src attribute with the expected image URL.'
		);
	}

	/**
	 * Tests that the global post object does not override
	 * a provided post ID with a shortcode gallery.
	 *
	 * @ticket 39304
	 *
	 * @group shortcode
	 */
	public function test_respects_post_id_with_shortcode_gallery() {
		$global_post_id = self::factory()->post->create(
			array(
				'post_content' => 'Global Post',
			)
		);
		$post_id        = self::factory()->post->create(
			array(
				'post_content' => '[gallery]',
			)
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$expected_srcs = array(
			'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg',
		);

		// Set the global $post context to the other post.
		$GLOBALS['post'] = get_post( $global_post_id );

		$galleries = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			$expected_srcs,
			$galleries[0]['src'],
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that the global post object does not override
	 * a provided post ID with a block gallery.
	 *
	 * @ticket 43826
	 *
	 * @group block
	 */
	public function test_respects_post_id_with_block_gallery() {
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

		$ids_joined = join( ',', $ids );

		$global_post_id = self::factory()->post->create(
			array(
				'post_content' => 'Global Post',
			)
		);

		$blob = <<< BLOB
<!-- wp:gallery {"ids":[$ids_joined]} -->
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$expected_srcs = array(
			'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg',
		);

		// Set the global $post context to the other post.
		$GLOBALS['post'] = get_post( $global_post_id );

		$galleries = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			array(
				array(
					'ids' => $ids_joined,
					'src' => $ids_srcs,
				),
			),
			$galleries,
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that the global post object does not override
	 * a provided post ID with a block gallery v2.
	 *
	 * @ticket 43826
	 *
	 * @group block
	 */
	public function test_respects_post_id_with_block_gallery_v2() {
		$attachment_id  = self::factory()->attachment->create_object(
			'image1.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$metadata       = array_merge( array( 'file' => 'image1.jpg' ), self::IMG_META );
		$url            = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/' . 'image1.jpg';
		$global_post_id = self::factory()->post->create(
			array(
				'post_content' => 'Global Post',
			)
		);

		wp_update_attachment_metadata( $attachment_id, $metadata );

		$blob = <<< BLOB
<!-- wp:gallery {"linkTo":"none","className":"columns-2"} -->
<figure
	class="wp-block-gallery has-nested-images columns-default is-cropped columns-2"
>
	<!-- wp:image {"id":$attachment_id,"sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large">
		<img
			src="$url"
			alt="Image gallery image"
			class="wp-image-$attachment_id"
		/>
	</figure>
	<!-- /wp:image -->
</figure>
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$expected_srcs = array(
			'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg',
		);

		// Set the global $post context to the other post.
		$GLOBALS['post'] = get_post( $global_post_id );

		$galleries = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			array(
				array(
					'ids' => (string) $attachment_id,
					'src' => array( $url ),
				),
			),
			$galleries,
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that the gallery only contains images specified in
	 * the shortcode's id attribute.
	 *
	 * @ticket 39304
	 *
	 * @group shortcode
	 */
	public function test_respects_shortcode_id_attribute() {
		$post_id     = self::factory()->post->create(
			array(
				'post_content' => 'No gallery defined',
			)
		);
		$post_id_two = self::factory()->post->create(
			array(
				'post_content' => "[gallery id='$post_id']",
			)
		);
		self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		$expected_srcs = array(
			'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg',
		);

		$galleries = get_post_galleries( $post_id_two, false );

		// Set the global $post context.
		$GLOBALS['post']               = get_post( $post_id_two );
		$galleries_with_global_context = get_post_galleries( $post_id_two, false );

		// Check that the global post state doesn't affect the results.
		$this->assertSameSetsWithIndex(
			$galleries,
			$galleries_with_global_context,
			'The global post state affected the results.'
		);

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of strings
		 * instead of an array of arrays.
		 */
		$this->assertIsArray(
			$galleries[0],
			'The returned data does not contain an array.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			$expected_srcs,
			$galleries[0]['src'],
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that galleries only contain images specified in the
	 * id attribute of their respective shortcode and block.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 * @group shortcode
	 */
	public function test_respects_shortcode_and_block_id_attributes() {
		/*
		 * Test the get_post_galleries() function in `$html = false` mode,
		 * with both shortcode and block galleries.
		 */
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

		$ids1_joined = join( ',', array_slice( $ids, 0, 3 ) );
		$ids2_joined = join( ',', array_slice( $ids, 3, 3 ) );

		$blob = <<<BLOB
[gallery ids="$ids1_joined"]

<!-- wp:gallery {"ids":[$ids2_joined]} -->
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );

		$galleries = get_post_galleries( $post_id, false );
		$this->assertSameSetsWithIndex(
			array(
				array(
					'ids' => $ids1_joined,
					'src' => array_slice( $ids_srcs, 0, 3 ),
				),
				array(
					'ids' => $ids2_joined,
					'src' => array_slice( $ids_srcs, 3, 3 ),
				),
			),
			$galleries
		);

	}

	/**
	 * Tests that galleries contain the additional attributes
	 * specified for their respective shortcode and block.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 * @group shortcode
	 */
	public function test_respects_additional_shortcode_and_block_attributes() {
		/*
		 * Test attributes returned by get_post_galleries() function in `$html = false` mode,
		 * with both shortcode and block galleries.
		 */
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

		$ids1_joined = join( ',', array_slice( $ids, 0, 3 ) );
		$ids2_joined = join( ',', array_slice( $ids, 3, 3 ) );
		$blob        = <<<BLOB
[gallery ids="$ids1_joined" type="type" foo="bar"]

<!-- wp:gallery {"ids":[$ids2_joined],"columns":3,"imageCrop":false,"linkTo":"media"} -->
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create( array( 'post_content' => $blob ) );

		$galleries = get_post_galleries( $post_id, false );
		$this->assertSameSetsWithIndex(
			array(
				array(
					'ids'  => $ids1_joined,
					// The shortcode code passes arbitrary attributes.
					'type' => 'type',
					'foo'  => 'bar',
					'src'  => array_slice( $ids_srcs, 0, 3 ),
				),
				array(
					'ids' => $ids2_joined,
					// The block only passes ids, no other attributes.
					'src' => array_slice( $ids_srcs, 3, 3 ),
				),
			),
			$galleries
		);

	}

	/**
	 * Tests that srcs are retrieved from the HTML of a block gallery
	 * that has no JSON blob.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_srcs_from_html_with_block_with_no_json_blob() {
		// Set up an unattached image.
		$image_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$image_url = 'http://' . WP_TESTS_DOMAIN . '/wp-content/uploads/test.jpg';

		$blob = <<< BLOB
<!-- wp:gallery -->
<ul class="wp-block-gallery columns-2 is-cropped"><li class="blocks-gallery-item">
<figure>
<img src="$image_url" alt="title"/>
</figure>
</li>
</ul>
<!-- /wp:gallery -->
BLOB;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $blob,
			)
		);

		$expected_srcs = array( $image_url );
		$galleries     = get_post_galleries( $post_id, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of strings
		 * instead of an array of arrays.
		 */
		$this->assertIsArray(
			$galleries[0],
			'The returned data does not contain an array.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertSameSetsWithIndex(
			$expected_srcs,
			$galleries[0]['src'],
			'The expected and actual srcs are not the same.'
		);
	}

	/**
	 * Tests that srcs are returned for a block gallery nested within
	 * other blocks.
	 *
	 * @ticket 43826
	 *
	 * @group blocks
	 */
	public function test_returns_srcs_with_nested_block_gallery() {
		$post_id  = self::factory()->post->create(
			array(
				'post_content' => 'I have no gallery.',
			)
		);
		$image_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$blob = <<<BLOB
<!-- wp:columns -->
<!-- wp:column -->
<!-- wp:gallery {"ids":[$image_id]} -->
<!-- /wp:gallery -->
<!-- /wp:column -->
<!-- /wp:columns -->
BLOB;

		$post_id_two = self::factory()->post->create( array( 'post_content' => $blob ) );

		$galleries = get_post_galleries( $post_id_two, false );

		// The method can return an empty array.
		$this->assertNotEmpty(
			$galleries,
			'The galleries array is empty.'
		);

		/*
		 * The method can return an array of strings
		 * instead of an array of arrays.
		 */
		$this->assertIsArray(
			$galleries[0],
			'The returned data does not contain an array.'
		);

		/*
		 * This prevents future changes from causing
		 * backwards compatibility breaks.
		 */
		$this->assertArrayHasKey(
			'src',
			$galleries[0],
			'A src key does not exist.'
		);

		$this->assertNotEmpty(
			$galleries[0]['src'],
			'The src key of the first gallery is empty.'
		);
	}
}
