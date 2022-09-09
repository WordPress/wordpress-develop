<?php

/**
 * Tests for dominant-color module.
 *
 * @group dominant-color
 */
class Dominant_Color_Test extends DominantColorTestCase {
	public $editor_engines = array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' );
	public $editor_engine  = 'WP_Image_Editor_GD';
	/**
	 * Tests dominant_color_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_metadata
	 */
	public function test_dominant_color_metadata( $image_path, $expected_color, $expected_transparency ) {

		foreach ( $this->editor_engines as $editor ) {
			$this->editor_engine = $editor;
			// Non existing attachment.
			$dominant_color_metadata = dominant_color_metadata( array(), 1 );
			$this->assertEmpty( $dominant_color_metadata );

			// Creating attachment.
			$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
			wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );
			$dominant_color_metadata = dominant_color_metadata( array(), $attachment_id );
			$this->assertArrayHasKey( 'dominant_color', $dominant_color_metadata );
			$this->assertNotEmpty( $dominant_color_metadata['dominant_color'] );
			$this->assertContains( $dominant_color_metadata['dominant_color'], $expected_color );

		}

	}

	/**
	 * Tests dominant_color_get_dominant_color().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_get_dominant_color
	 */
	public function test_dominant_color_get_dominant_color( $image_path, $expected_color, $expected_transparency ) {
		foreach ( $this->editor_engines as $editor ) {
			$this->editor_engine = $editor;
			// Creating attachment.
			$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
			$this->assertContains( dominant_color_get_dominant_color( $attachment_id ), $expected_color );
		}
	}

	/**
	 * Tests has_transparency_metadata().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::has_transparency_metadata
	 */
	public function test_has_transparency_metadata( $image_path, $expected_color, $expected_transparency ) {
		foreach ( $this->editor_engines as $editor ) {
			$this->editor_engine = $editor;

			// Non existing attachment.
			$transparency_metadata = dominant_color_metadata( array(), 1 );
			$this->assertEmpty( $transparency_metadata );

			$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
			wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );
			$transparency_metadata = dominant_color_metadata( array(), $attachment_id );
			$this->assertArrayHasKey( 'has_transparency', $transparency_metadata );
			$this->assertSame( $expected_transparency, $transparency_metadata['has_transparency'] );
		}
	}

	/**
	 * Tests dominant_color_get_dominant_color().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_get_dominant_color
	 */
	public function test_dominant_color_has_transparency( $image_path, $expected_color, $expected_transparency ) {
		foreach ( $this->editor_engines as $editor ) {
			$this->editor_engine = $editor;
			// Creating attachment.
			$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
			$this->assertSame( $expected_transparency, dominant_color_has_transparency( $attachment_id ) );
		}
	}

	/**
	 * Tests tag_add_adjust().
	 *
	 * @dataProvider provider_get_dominant_color
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 */
	public function test_tag_add_adjust_to_image_attributes( $image_path, $expected_color, $expected_transparency ) {
		foreach ( $this->editor_engines as $editor ) {
			$this->editor_engine = $editor;

			$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
			wp_maybe_generate_attachment_metadata( get_post( $attachment_id ) );

			list( $src, $width, $height ) = wp_get_attachment_image_src( $attachment_id );
			// Testing tag_add_adjust() with image being lazy load.
			$filtered_image_mock_lazy_load = sprintf( '<img loading="lazy" class="test" src="%s" width="%d" height="%d" />', $src, $width, $height );

			$filtered_image_tags_added = img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );
			$this->assertStringContainsString( 'data-has-transparency="' . json_encode( $expected_transparency ) . '"', $filtered_image_tags_added );

			foreach ( $expected_color as $color ) {
				if ( false !== strpos( $color, $filtered_image_tags_added ) ) {
					$this->assertStringContainsString( 'style="--dominant-color: #' . $expected_color . ';"', $filtered_image_tags_added );
					$this->assertStringContainsString( 'data-dominant-color="' . $expected_color . '"', $filtered_image_tags_added );
					break;
				}
			}

			// Deactivate filter.
			add_filter( 'img_tag_add_dominant_color', '__return_false' );
			$filtered_image_tags_not_added = img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );
			$this->assertEquals( $filtered_image_mock_lazy_load, $filtered_image_tags_not_added );
			remove_filter( 'img_tag_add_dominant_color', '__return_false' );
		}
	}


	/**
	 * Tests get_hex_from_rgb().
	 *
	 * @dataProvider provider_get_hex_color
	 *
	 * @covers ::get_hex_from_rgb
	 */
	public function test_get_hex_from_rgb( $red, $green, $blue, $hex ) {
		$this->assertSame( $hex, get_hex_from_rgb( $red, $green, $blue ) );
	}

	public function provider_get_hex_color() {
		return array(
			'black'   => array(
				'red'   => 0,
				'green' => 0,
				'blue'  => 0,
				'hex'   => '000000',
			),
			'white'   => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 255,
				'hex'   => 'ffffff',
			),
			'blue'    => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 0,
				'hex'   => 'ff0000',
			),
			'teal'    => array(
				'red'   => 255,
				'green' => 255,
				'blue'  => 0,
				'hex'   => 'ffff00',
			),
			'pink'    => array(
				'red'   => 255,
				'green' => 0,
				'blue'  => 255,
				'hex'   => 'ff00ff',
			),
			'purple'  => array(
				'red'   => 88,
				'green' => 42,
				'blue'  => 158,
				'hex'   => '582a9e',
			),
			'invalid' => array(
				'red'   => -1,
				'green' => -1,
				'blue'  => -1,
				'hex'   => null,
			),
		);
	}
}
