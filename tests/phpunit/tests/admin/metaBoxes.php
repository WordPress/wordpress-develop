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

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
	}

	public function tear_down() {
		_unregister_post_status( 'reviewed' );
		_unregister_post_status( 'unfiltered-status' );
		_unregister_post_status( 'current-status' );
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
	public function filter_post_submitbox_custom_statuses( array $statuses, WP_Post $post ): array {
		if ( 'post' === $post->post_type ) {
			$statuses[] = 'reviewed';
		}

		return $statuses;
	}

	/**
	 * Renders the Publish meta box for a post as the editor user.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Meta box markup.
	 */
	private function render_post_submit_meta_box( WP_Post $post ): string {
		wp_set_current_user( self::$editor_id );
		$GLOBALS['post']   = $post;
		$GLOBALS['action'] = 'edit';

		ob_start();
		post_submit_meta_box( $post );
		return ob_get_clean();
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 */
	public function test_post_submit_meta_box_includes_filtered_custom_post_statuses(): void {
		register_post_status(
			'reviewed',
			array(
				'label'                     => 'Reviewed & approved',
				'protected'                 => true,
				'show_in_admin_status_list' => false,
			)
		);
		register_post_status(
			'unfiltered-status',
			array(
				'label'                     => 'Unfiltered status',
				'protected'                 => true,
				'show_in_admin_status_list' => true,
			)
		);
		add_filter( 'post_submitbox_custom_statuses', array( $this, 'filter_post_submitbox_custom_statuses' ), 10, 2 );

		$output = $this->render_post_submit_meta_box( self::factory()->post->create_and_get() );

		$this->assertStringContainsString( 'value="reviewed" data-save-text="Save as Reviewed &amp; approved">Reviewed &amp; approved</option>', $output );
		$this->assertStringNotContainsString( 'Unfiltered status', $output, 'Statuses not added through the filter should not be listed.' );

		$page_output = $this->render_post_submit_meta_box( self::factory()->post->create_and_get( array( 'post_type' => 'page' ) ) );

		$this->assertStringNotContainsString( 'Reviewed &amp; approved', $page_output );
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 */
	public function test_post_submit_meta_box_accepts_non_array_filter_value(): void {
		register_post_status(
			'reviewed',
			array(
				'label'     => 'Reviewed',
				'protected' => true,
			)
		);
		add_filter(
			'post_submitbox_custom_statuses',
			static function () {
				return 'reviewed';
			}
		);

		$output = $this->render_post_submit_meta_box( self::factory()->post->create_and_get() );

		$this->assertStringContainsString( 'value="reviewed"', $output );
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 */
	public function test_post_submit_meta_box_lists_each_custom_post_status_once(): void {
		register_post_status(
			'reviewed',
			array(
				'label'     => 'Reviewed',
				'protected' => true,
			)
		);
		add_filter(
			'post_submitbox_custom_statuses',
			static function ( array $statuses ): array {
				return array_merge( $statuses, array( 'reviewed', 'reviewed' ) );
			}
		);

		$post   = self::factory()->post->create_and_get( array( 'post_status' => 'reviewed' ) );
		$output = $this->render_post_submit_meta_box( $post );

		$this->assertSame( 1, preg_match_all( '/<option[^>]+value="reviewed"/', $output ) );
	}

	/**
	 * @ticket 12706
	 *
	 * @covers ::post_submit_meta_box
	 * @covers ::_wp_translate_postdata
	 */
	public function test_post_submit_meta_box_includes_and_preserves_current_custom_post_status(): void {
		register_post_status(
			'current-status',
			array(
				'label'     => 'Current status',
				'protected' => true,
			)
		);

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$editor_id,
				'post_status' => 'current-status',
			)
		);

		$output = $this->render_post_submit_meta_box( $post );

		$this->assertMatchesRegularExpression( '/<span id="post-status-display">\s*Current status\s*<\/span>/', $output );
		$this->assertMatchesRegularExpression( '/<option[^>]+selected=[\'\"]selected[\'\"][^>]+value=[\'\"]current-status[\'\"][^>]*>Current status<\/option>/', $output );
		$this->assertStringContainsString( 'value="Save as Current status"', $output );

		$translated_post_data = _wp_translate_postdata(
			true,
			array(
				'post_ID'     => $post->ID,
				'post_type'   => 'post',
				'post_status' => 'current-status',
				'save'        => 'Save as Current status',
			)
		);

		$this->assertSame( 'current-status', $translated_post_data['post_status'] );
	}
}
