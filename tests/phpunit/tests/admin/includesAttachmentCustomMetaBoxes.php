<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

/**
 * Tests for attachment_submit_meta_box() and post_custom_meta_box().
 *
 * @group admin
 */
class Tests_Admin_includesAttachmentCustomMetaBoxes extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Creates the administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * The original global post.
	 *
	 * @var WP_Post|null
	 */
	private $original_post;

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );

		$this->original_post = $GLOBALS['post'] ?? null;
	}

	public function tear_down() {
		$GLOBALS['post'] = $this->original_post;

		parent::tear_down();
	}

	/**
	 * Returns the output of a meta box.
	 *
	 * @param callable $callback The function of the meta box.
	 * @param WP_Post  $post     The post.
	 * @return string The output.
	 */
	private function get_output( $callback, $post ) {
		ob_start();
		$callback( $post );

		return ob_get_clean();
	}

	/**
	 * Returns the attributes of the first element matching the query.
	 *
	 * @param string       $markup The markup.
	 * @param string|array $query  The tag name or the query of the tag processor.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $query ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

		if ( ! $processor->next_tag( $query ) ) {
			return null;
		}

		$attributes = array();

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Returns the names of the custom fields in the list of the markup.
	 *
	 * @param string $markup The markup.
	 * @return string[] The names.
	 */
	private function get_field_names( $markup ) {
		$processor = new WP_HTML_Tag_Processor( $markup );
		$names     = array();

		while ( $processor->next_tag( 'input' ) ) {
			if ( 1 === preg_match( '/^meta\[\d+\]\[key\]$/', (string) $processor->get_attribute( 'name' ) ) ) {
				$names[] = $processor->get_attribute( 'value' );
			}
		}

		return $names;
	}

	/**
	 * Creates an attachment.
	 *
	 * @param array $args Optional. The arguments of the attachment. Default none.
	 * @return WP_Post The attachment.
	 */
	private function create_attachment( $args = array() ) {
		$attachment = get_post(
			self::factory()->attachment->create_object(
				array_merge(
					array(
						'file'           => 'image.jpg',
						'post_mime_type' => 'image/jpeg',
						'post_date'      => '2020-03-15 14:45:00',
					),
					$args
				)
			)
		);

		// A callback of the core on the action of the box reads the global post.
		$GLOBALS['post'] = $attachment;

		return $attachment;
	}

	/**
	 * Tests the box of the attachment.
	 *
	 * @ticket 66606
	 *
	 * @covers ::attachment_submit_meta_box
	 */
	public function test_attachment_submit_meta_box() {
		$output = $this->get_output( 'attachment_submit_meta_box', $this->create_attachment() );

		$this->assertSame( 'submitbox', $this->get_element( $output, 'div' )['class'] );
		$this->assertStringContainsString( '<span id="timestamp">', $output );
		$this->assertStringContainsString( 'Uploaded on: <b>Mar 15, 2020 at 14:45</b>', $output );
		$this->assertStringContainsString( '<div style="display:none;">', $output );
		$this->assertStringContainsString( 'name="save" id="save"', $output );
		$this->assertStringContainsString( '<input name="original_publish" type="hidden" id="original_publish" value="Update" />', $output );
		$this->assertStringContainsString( 'class="button button-primary button-large" id="publish" value="Update"', $output );
		$this->assertStringContainsString( '<span class="spinner"></span>', $output );
	}

	/**
	 * Tests the action of the box.
	 *
	 * @ticket 66606
	 *
	 * @covers ::attachment_submit_meta_box
	 */
	public function test_attachment_submit_meta_box_runs_the_misc_actions() {
		$attachment = $this->create_attachment();
		$received   = null;

		add_action(
			'attachment_submitbox_misc_actions',
			static function ( $post ) use ( &$received ) {
				$received = $post;

				echo '<div id="extra-action"></div>';
			}
		);

		$output = $this->get_output( 'attachment_submit_meta_box', $attachment );

		$this->assertSame( $attachment, $received );
		$this->assertStringContainsString( '<div id="extra-action"></div>', $output );
		$this->assertGreaterThan( strpos( $output, 'id="timestamp"' ), strpos( $output, 'id="extra-action"' ), 'The action should run after the date.' );
	}

	/**
	 * Tests the link to delete the file.
	 *
	 * @ticket 66606
	 *
	 * @covers ::attachment_submit_meta_box
	 */
	public function test_attachment_submit_meta_box_has_the_link_to_delete_the_file() {
		$attachment = $this->create_attachment();

		$output = $this->get_output( 'attachment_submit_meta_box', $attachment );
		$link   = $this->get_element( $output, array( 'class_name' => 'submitdelete' ) );

		if ( EMPTY_TRASH_DAYS && MEDIA_TRASH ) {
			$this->assertSame( html_entity_decode( get_delete_post_link( $attachment->ID ) ), $link['href'] );
			$this->assertStringContainsString( '>Move to Trash</a>', $output );
			$this->assertArrayNotHasKey( 'onclick', $link );
		} else {
			$this->assertSame( html_entity_decode( get_delete_post_link( $attachment->ID, '', true ) ), $link['href'] );
			$this->assertStringContainsString( '>Delete permanently</a>', $output );

			if ( ! MEDIA_TRASH ) {
				$this->assertSame( 'return showNotice.warn();', $link['onclick'] );
			}
		}

		$this->assertSame( 'submitdelete deletion', $link['class'] );
	}

	/**
	 * Tests that the link to delete the file needs the capability.
	 *
	 * @ticket 66606
	 *
	 * @covers ::attachment_submit_meta_box
	 */
	public function test_attachment_submit_meta_box_has_no_link_to_delete_the_file_for_a_user_who_cannot_delete_it() {
		$attachment = $this->create_attachment();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$output = $this->get_output( 'attachment_submit_meta_box', $attachment );

		$this->assertStringNotContainsString( 'submitdelete', $output );
		$this->assertStringContainsString( 'id="publish"', $output, 'The Update button should stay.' );
	}

	/**
	 * Creates a post with custom fields.
	 *
	 * @return WP_Post The post.
	 */
	private function create_post_with_custom_fields() {
		$post_id = self::factory()->post->create();

		add_post_meta( $post_id, 'color', 'red' );
		add_post_meta( $post_id, 'size', 'large' );
		add_post_meta( $post_id, '_hidden', 'secret' );

		return get_post( $post_id );
	}

	/**
	 * Tests the custom fields meta box.
	 *
	 * @ticket 66606
	 *
	 * @covers ::post_custom_meta_box
	 */
	public function test_post_custom_meta_box_lists_the_custom_fields() {
		$post = $this->create_post_with_custom_fields();

		$output = $this->get_output( 'post_custom_meta_box', $post );

		$this->assertStringContainsString( '<div id="postcustomstuff">', $output );
		$this->assertStringContainsString( '<div id="ajax-response"></div>', $output );
		$this->assertSame( array( 'color', 'size' ), $this->get_field_names( $output ) );
		$this->assertStringContainsString( '>red</textarea>', $output );
		$this->assertStringContainsString( '>large</textarea>', $output );
		$this->assertSame( 2, preg_match_all( '/name="deletemeta\[\d+\]"/', $output ), 'Each field should have a delete button.' );
		$this->assertStringContainsString( '<a href="https://wordpress.org/documentation/article/assign-custom-fields/">use in your theme</a>', $output );
	}

	/**
	 * Tests that the protected custom fields are left out.
	 *
	 * @ticket 66606
	 *
	 * @covers ::post_custom_meta_box
	 */
	public function test_post_custom_meta_box_leaves_out_the_protected_fields() {
		$output = $this->get_output( 'post_custom_meta_box', $this->create_post_with_custom_fields() );

		$this->assertStringNotContainsString( '_hidden', $output );
		$this->assertStringNotContainsString( 'secret', $output );
	}

	/**
	 * Tests that the fields that the user cannot edit are left out.
	 *
	 * @ticket 66606
	 *
	 * @covers ::post_custom_meta_box
	 */
	public function test_post_custom_meta_box_leaves_out_the_fields_that_the_user_cannot_edit() {
		$post = $this->create_post_with_custom_fields();

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $user_id, $args ) {
				if ( 'edit_post_meta' === $cap && isset( $args[1] ) && 'size' === $args[1] ) {
					return array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			4
		);

		$output = $this->get_output( 'post_custom_meta_box', $post );

		$this->assertSame( array( 'color' ), $this->get_field_names( $output ) );
		$this->assertStringNotContainsString( '>large</textarea>', $output );
	}

	/**
	 * Tests the form to add a custom field.
	 *
	 * @ticket 66606
	 *
	 * @covers ::post_custom_meta_box
	 */
	public function test_post_custom_meta_box_has_the_form_to_add_a_custom_field() {
		$post = $this->create_post_with_custom_fields();

		$output = $this->get_output( 'post_custom_meta_box', $post );

		$this->assertStringContainsString( 'id="newmeta"', $output );
		$this->assertStringContainsString( 'name="metakeyselect"', $output );
		$this->assertStringContainsString( 'name="metakeyinput"', $output );
		$this->assertStringContainsString( 'name="metavalue"', $output );
		$this->assertStringContainsString( 'Add Custom Field', $output );
		$this->assertStringContainsString( "<option value='color'>color</option>", $output, 'The existing keys should be offered.' );
		$this->assertStringNotContainsString( "<option value='_hidden'>", $output );
	}

	/**
	 * Tests the box of a post without custom fields.
	 *
	 * @ticket 66606
	 *
	 * @covers ::post_custom_meta_box
	 */
	public function test_post_custom_meta_box_without_custom_fields() {
		$post = get_post( self::factory()->post->create() );

		$output = $this->get_output( 'post_custom_meta_box', $post );

		$this->assertStringNotContainsString( 'deletemeta', $output );
		$this->assertStringContainsString( 'id="newmeta"', $output );
		$this->assertStringContainsString( '<table id="list-table" style="display: none;">', $output );
	}
}
