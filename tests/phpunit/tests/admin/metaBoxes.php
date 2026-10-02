<?php
/**
 * @group admin
 */
class Tests_Admin_MetaBoxes extends WP_UnitTestCase {
	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private static $editor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public function tear_down() {
		remove_filter( 'post_submitbox_custom_statuses', array( $this, 'filter_post_submitbox_custom_statuses' ) );
		_unregister_post_status( 'reviewed' );
		_unregister_post_status( 'hidden-status' );
		unset( $GLOBALS['post'], $GLOBALS['action'] );

		parent::tear_down();
	}

	/**
	 * Adds the reviewed status to the post Publish meta box.
	 *
	 * @param string[] $statuses Post status names.
	 * @param WP_Post  $post     Current post object.
	 * @return string[] Post status names.
	 */
	public function filter_post_submitbox_custom_statuses( $statuses, $post ) {
		if ( 'post' === $post->post_type ) {
			$statuses[] = 'reviewed';
		}

		return $statuses;
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 */
	public function test_post_submit_meta_box_includes_filtered_custom_post_statuses() {
		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

		register_post_status(
			'reviewed',
			array(
				'label'                     => 'Reviewed & approved',
				'protected'                 => true,
				'show_in_admin_status_list' => false,
			)
		);
		register_post_status(
			'hidden-status',
			array(
				'label'                     => 'Hidden status',
				'protected'                 => true,
				'show_in_admin_status_list' => true,
			)
		);
		add_filter( 'post_submitbox_custom_statuses', array( $this, 'filter_post_submitbox_custom_statuses' ), 10, 2 );

		$post = self::factory()->post->create_and_get();

		wp_set_current_user( self::$editor_id );
		$GLOBALS['post']   = $post;
		$GLOBALS['action'] = 'edit';

		ob_start();
		post_submit_meta_box( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="reviewed" data-save-text="Save as Reviewed &amp; approved">Reviewed &amp; approved</option>', $output );
		$this->assertStringNotContainsString( 'Hidden status', $output );

		$page            = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$GLOBALS['post'] = $page;

		ob_start();
		post_submit_meta_box( $page );
		$page_output = ob_get_clean();

		$this->assertStringNotContainsString( 'Reviewed &amp; approved', $page_output );
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 * @covers ::_wp_translate_postdata
	 */
	public function test_post_submit_meta_box_includes_and_preserves_current_custom_post_status() {
		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';

		register_post_status(
			'hidden-status',
			array(
				'label'     => 'Hidden status',
				'protected' => true,
			)
		);

		wp_set_current_user( self::$editor_id );
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$editor_id,
				'post_status' => 'hidden-status',
			)
		);

		$GLOBALS['post']   = $post;
		$GLOBALS['action'] = 'edit';

		ob_start();
		post_submit_meta_box( $post );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<span id="post-status-display">\s*Hidden status\s*<\/span>/', $output );
		$this->assertMatchesRegularExpression( '/<option[^>]+selected=[\'\"]selected[\'\"][^>]+value=[\'\"]hidden-status[\'\"][^>]*>Hidden status<\/option>/', $output );
		$this->assertStringContainsString( 'value="Save as Hidden status"', $output );

		$translated_post_data = _wp_translate_postdata(
			true,
			array(
				'post_ID'     => $post->ID,
				'post_type'   => 'post',
				'post_status' => 'hidden-status',
				'save'        => 'Save as Hidden status',
			)
		);

		$this->assertSame( 'hidden-status', $translated_post_data['post_status'] );
	}
}
