<?php
require_once ABSPATH . 'wp-admin/includes/class-custom-image-header.php';

/**
 * @group image
 * @group header
 */
class Tests_Image_Header extends WP_UnitTestCase {
	public $custom_image_header;

	public function set_up() {
		parent::set_up();
		$this->custom_image_header = new Custom_Image_Header( '__return_null' );
	}

	public function test_header_image_has_correct_dimensions_with_max_width() {
		global $_wp_theme_features;

		$_wp_theme_features['custom-header'][0]['max-width']   = 1600;
		$_wp_theme_features['custom-header'][0]['width']       = 1200;
		$_wp_theme_features['custom-header'][0]['height']      = 230;
		$_wp_theme_features['custom-header'][0]['flex-width']  = false;
		$_wp_theme_features['custom-header'][0]['flex-height'] = false;

		$dimensions = $this->custom_image_header->get_header_dimensions(
			array(
				'width'  => 1600,
				'height' => 1200,
			)
		);
		$this->assertSame( 1200, $dimensions['dst_width'] );
		$this->assertSame( 230, $dimensions['dst_height'] );

	}

	public function test_header_image_has_correct_dimensions_with_fixed() {
		global $_wp_theme_features;

		unset( $_wp_theme_features['custom-header'][0]['max-width'] );
		$_wp_theme_features['custom-header'][0]['width']       = 1200;
		$_wp_theme_features['custom-header'][0]['height']      = 230;
		$_wp_theme_features['custom-header'][0]['flex-width']  = false;
		$_wp_theme_features['custom-header'][0]['flex-height'] = false;

		$dimensions = $this->custom_image_header->get_header_dimensions(
			array(
				'width'  => 1600,
				'height' => 1200,
			)
		);
		$this->assertSame( 1200, $dimensions['dst_width'] );
		$this->assertSame( 230, $dimensions['dst_height'] );

	}

	public function test_header_image_has_correct_dimensions_with_flex_height() {
		global $_wp_theme_features;

		unset( $_wp_theme_features['custom-header'][0]['max-width'] );
		$_wp_theme_features['custom-header'][0]['width']       = 1200;
		$_wp_theme_features['custom-header'][0]['height']      = 230;
		$_wp_theme_features['custom-header'][0]['flex-width']  = false;
		$_wp_theme_features['custom-header'][0]['flex-height'] = true;

		$dimensions = $this->custom_image_header->get_header_dimensions(
			array(
				'width'  => 1600,
				'height' => 1200,
			)
		);
		$this->assertSame( 1200, $dimensions['dst_width'] );
		$this->assertSame( 900, $dimensions['dst_height'] );

	}

	public function test_header_image_has_correct_dimensions_with_flex_width() {
		global $_wp_theme_features;

		unset( $_wp_theme_features['custom-header'][0]['max-width'] );
		$_wp_theme_features['custom-header'][0]['width']       = 1200;
		$_wp_theme_features['custom-header'][0]['height']      = 230;
		$_wp_theme_features['custom-header'][0]['flex-width']  = true;
		$_wp_theme_features['custom-header'][0]['flex-height'] = false;

		$dimensions = $this->custom_image_header->get_header_dimensions(
			array(
				'width'  => 1600,
				'height' => 1200,
			)
		);
		$this->assertSame( 1500, $dimensions['dst_width'] ); // Max width.
		$this->assertSame( 230, $dimensions['dst_height'] );

	}

	public function test_header_image_has_correct_dimensions_with_flex_width_and_height() {
		global $_wp_theme_features;

		$_wp_theme_features['custom-header'][0]['max-width']   = 1800;
		$_wp_theme_features['custom-header'][0]['width']       = 1200;
		$_wp_theme_features['custom-header'][0]['height']      = 230;
		$_wp_theme_features['custom-header'][0]['flex-width']  = true;
		$_wp_theme_features['custom-header'][0]['flex-height'] = true;

		$dimensions = $this->custom_image_header->get_header_dimensions(
			array(
				'width'  => 1600,
				'height' => 1200,
			)
		);
		$this->assertSame( 1600, $dimensions['dst_width'] );
		$this->assertSame( 1200, $dimensions['dst_height'] );

	}

	public function test_create_attachment_object() {
		$id = wp_insert_attachment(
			array(
				'post_status' => 'publish',
				'post_title'  => 'foo.png',
				'post_type'   => 'post',
				'guid'        => 'http://localhost/foo.png',
			)
		);

		$cropped = 'foo-cropped.png';

		$object = $this->custom_image_header->create_attachment_object( $cropped, $id );
		$this->assertSame( 'foo-cropped.png', $object['post_title'] );
		$this->assertSame( 'http://localhost/' . $cropped, $object['guid'] );
		$this->assertSame( 'custom-header', $object['context'] );
		$this->assertSame( 'image/jpeg', $object['post_mime_type'] );
	}

	public function test_insert_cropped_attachment() {
		$id = wp_insert_attachment(
			array(
				'post_status' => 'publish',
				'post_title'  => 'foo.png',
				'post_type'   => 'post',
				'guid'        => 'http://localhost/foo.png',
			)
		);

		$cropped = 'foo-cropped.png';
		$object  = $this->custom_image_header->create_attachment_object( $cropped, $id );

		$cropped_id = $this->custom_image_header->insert_attachment( $object, $cropped );

		$this->assertIsInt( $cropped_id );
		$this->assertGreaterThan( 0, $cropped_id );
	}

	/**
	 * @ticket 21819
	 */
	public function test_check_get_previous_crop() {
		$id = wp_insert_attachment(
			array(
				'post_status' => 'publish',
				'post_title'  => 'foo.png',
				'post_type'   => 'post',
				'guid'        => 'http://localhost/foo.png',
			)
		);

		// Create inital crop object.
		$cropped_1 = 'foo-cropped-1.png';
		$object    = $this->custom_image_header->create_attachment_object( $cropped_1, $id );

		// Ensure no previous crop exists.
		$previous = $this->custom_image_header->get_previous_crop( $object );
		$this->assertFalse( $previous );

		// Create the inital crop attachment and set it as the header.
		$cropped_1_id = $this->custom_image_header->insert_attachment( $object, $cropped_1 );
		$key          = '_wp_attachment_custom_header_last_used_' . get_stylesheet();
		update_post_meta( $cropped_1_id, $key, time() );
		update_post_meta( $cropped_1_id, '_wp_attachment_is_custom_header', get_stylesheet() );

		// Create second crop.
		$cropped_2 = 'foo-cropped-2.png';
		$object    = $this->custom_image_header->create_attachment_object( $cropped_2, $id );

		// Test that a previous crop is found.
		$previous = $this->custom_image_header->get_previous_crop( $object );
		$this->assertSame( $previous, $cropped_1_id );
	}
}
