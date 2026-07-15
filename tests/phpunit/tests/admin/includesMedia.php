<?php

/**
 * @group admin
 * @group media
 *
 * @covers ::get_media_item
 * @covers ::get_compat_media_markup
 */
class Tests_Admin_IncludesMedia extends WP_UnitTestCase {
	private static $attachment_id;

	private $required = false;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$attachment_id = $factory->post->create(
			array(
				'post_title'     => 'Test attachment',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/media.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'filter_attachment_fields_to_edit' ) );
	}

	public function test_get_media_item_does_not_show_required_fields_message_without_required_fields() {
		$item = get_media_item( self::$attachment_id );

		$this->assertStringNotContainsString( 'media-types-required-info', $item );
	}

	public function test_get_media_item_shows_required_fields_message_with_required_field() {
		$this->required = true;

		$item = get_media_item( self::$attachment_id );

		$this->assertStringContainsString( 'media-types-required-info', $item );
	}

	public function test_get_compat_media_markup_does_not_show_required_fields_message_without_required_fields() {
		$markup = get_compat_media_markup( self::$attachment_id );

		$this->assertStringNotContainsString( 'media-types-required-info', $markup['item'] );
	}

	public function test_get_compat_media_markup_shows_required_fields_message_with_required_field() {
		$this->required = true;

		$markup = get_compat_media_markup( self::$attachment_id );

		$this->assertStringContainsString( 'media-types-required-info', $markup['item'] );
	}

	public function filter_attachment_fields_to_edit() {
		return array(
			'test_field' => array(
				'label'    => 'Test field',
				'input'    => 'text',
				'value'    => '',
				'required' => $this->required,
			),
		);
	}
}
