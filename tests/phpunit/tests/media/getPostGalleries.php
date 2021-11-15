<?php
/**
 * @group media
 *
 * @covers ::get_post_galleries
 */
class Tests_Functions_getPostGalleries extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->img_meta = array(
			'width'  => 100,
			'height' => 100,
			'sizes'  => '',
		);
	}

	/**
	 * @ticket 39304
	 */
	public function test_post_galleries_images_without_global_post() {
		// Set up an unattached image.
		$this->factory->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_content' => '[gallery]',
			)
		);

		$galleries = get_post_galleries( $post_id, false );

		$this->assertEmpty( $galleries[0]['src'] );
	}

	/**
	 * @ticket 39304
	 */
	public function test_post_galleries_ignores_global_post() {
		$global_post_id = $this->factory->post->create(
			array(
				'post_content' => 'Global Post',
			)
		);
		$post_id        = $this->factory->post->create(
			array(
				'post_content' => '[gallery]',
			)
		);
		$this->factory->attachment->create_object(
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

		$this->assertNotEmpty( $galleries[0]['src'] );
		$this->assertSame( $galleries[0]['src'], $expected_srcs );
	}

	/**
	 * @ticket 39304
	 */
	public function test_post_galleries_respects_id_attrs() {
		$post_id     = $this->factory->post->create(
			array(
				'post_content' => 'No gallery defined',
			)
		);
		$post_id_two = $this->factory->post->create(
			array(
				'post_content' => "[gallery id='$post_id']",
			)
		);
		$this->factory->attachment->create_object(
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
		$this->assertSame( $galleries, $galleries_with_global_context );

		$this->assertNotEmpty( $galleries[0]['src'] );
		$this->assertSame( $galleries[0]['src'], $expected_srcs );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_block_post_galleries() {
		// Set up an unattached image.
		$this->factory->attachment->create_object(
			array(
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_content' => '<!-- wp:gallery -->',
			)
		);

		$galleries = get_post_galleries( $post_id, false );

		$this->assertTrue( is_array( $galleries ) );
		$this->assertEmpty( $galleries[0]['src'] );
	}

	/**
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_mixed_post_galleries() {
		// Test the get_post_galleries() function in $html=false mode, with both shortcode and block galleries
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
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), $this->img_meta );
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
	 * @ticket 43826
	 * @group blocks
	 */
	public function test_mixed_post_galleries_data() {
		// Test attributes returned by get_post_galleries() function in $html=false mode, with both shortcode and block galleries
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
			$metadata      = array_merge( array( 'file' => "image$i.jpg" ), $this->img_meta );
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
					// The shortcode code passes arbitrary attributes
					'type' => 'type',
					'foo'  => 'bar',
					'src'  => array_slice( $ids_srcs, 0, 3 ),
				),
				array(
					'ids' => $ids2_joined,
					// The block only passes ids, no other attributes
					'src' => array_slice( $ids_srcs, 3, 3 ),
				),
			),
			$galleries
		);

	}
}
