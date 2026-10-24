<?php

/**
 * Tests that the image editor markup can run under a Content Security Policy
 * that does not allow inline event handlers.
 *
 * @group media
 * @group admin
 */
class Tests_Admin_IncludesImageEdit extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $attachment_id;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Returns the names of all event handler attributes in the given HTML.
	 *
	 * @param string $html The HTML to scan.
	 * @return string[] The tag and attribute names, for example `BUTTON[onclick]`.
	 */
	private function get_event_handler_attributes( $html ) {
		$found     = array();
		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag() ) {
			foreach ( (array) $processor->get_attribute_names_with_prefix( 'on' ) as $name ) {
				$found[] = $processor->get_tag() . '[' . $name . ']';
			}
		}

		return $found;
	}

	/**
	 * @ticket 59446
	 *
	 * @covers ::wp_image_editor
	 */
	public function test_wp_image_editor_has_no_inline_event_handlers() {
		$html = get_echo( 'wp_image_editor', array( $this->attachment_id ) );

		$this->assertStringContainsString( 'imgedit-wrap', $html, 'The image editor was not rendered.' );
		$this->assertSame( array(), $this->get_event_handler_attributes( $html ) );
	}

	/**
	 * Tests that every control of the image editor is bound through a data attribute
	 * and that the editor exposes its post ID and nonce for the delegated listeners.
	 *
	 * @ticket 59446
	 *
	 * @covers ::wp_image_editor
	 */
	public function test_wp_image_editor_exposes_context_for_delegated_listeners() {
		$html      = get_echo( 'wp_image_editor', array( $this->attachment_id ) );
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'imgedit-wrap' ) ) );
		$this->assertSame( (string) $this->attachment_id, $processor->get_attribute( 'data-postid' ) );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'INPUT' ) ) );
		$this->assertSame( 'imgedit-nonce-' . $this->attachment_id, $processor->get_attribute( 'id' ) );
		$this->assertSame( 1, wp_verify_nonce( $processor->get_attribute( 'value' ), "image_editor-{$this->attachment_id}" ) );

		$actions   = array();
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			$action = $processor->get_attribute( 'data-imgedit-action' );
			if ( is_string( $action ) ) {
				$actions[] = $action . ( null === $processor->get_attribute( 'data-imgedit-value' ) ? '' : ':' . $processor->get_attribute( 'data-imgedit-value' ) );
			}
		}

		$expected = array(
			'toggleCropTool',
			'toggleControls',
			'togglePopup',
			'flip:1',
			'flip:2',
			'undo',
			'redo',
			'close',
			'save',
			'action:scale',
			'handleCropToolClick',
			'handleCropToolClick',
			'toggleHelp',
		);
		foreach ( $expected as $action ) {
			$this->assertContains( $action, $actions );
		}
	}

	/**
	 * @ticket 59446
	 *
	 * @covers ::edit_form_image_editor
	 */
	public function test_edit_form_image_editor_open_button_has_no_inline_event_handler() {
		$html      = get_echo( 'edit_form_image_editor', array( get_post( $this->attachment_id ) ) );
		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertSame( array(), $this->get_event_handler_attributes( $html ) );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'button' ) ), 'The Edit Image button was not rendered.' );
		$this->assertSame( 'imgedit-open-btn-' . $this->attachment_id, $processor->get_attribute( 'id' ) );
		$this->assertTrue( $processor->get_attribute( 'data-imgedit-open' ) );
		$this->assertSame( (string) $this->attachment_id, $processor->get_attribute( 'data-postid' ) );
		$this->assertSame( 1, wp_verify_nonce( $processor->get_attribute( 'data-nonce' ), "image_editor-{$this->attachment_id}" ) );
	}
}
