<?php
/**
 * Tests for the Distributed Editing REST save-path stale-base probe.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 * @group restapi
 */

class Tests_DE_RTC_REST_Save_Stale_Base extends WP_Test_REST_TestCase {

	protected static $admin_user_id;

	protected $server;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		wp_set_current_user( self::$admin_user_id );
		add_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );
	}

	public function tear_down() {
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		global $wp_rest_server;

		$wp_rest_server = null;
		$this->server   = null;

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 * @covers ::wp_de_rtc_is_rest_save_stale_base_probe_request
	 * @covers ::wp_de_rtc_rest_save_probe_request_matches_post_type
	 * @covers ::wp_de_rtc_get_rest_save_probe_request_rest_base
	 * @covers ::wp_de_rtc_get_stale_base_rejection_error
	 */
	public function test_post_update_stale_base_probe_rejects_before_persistence() {
		$current_content  = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Save path current content.</p><!-- /wp:paragraph -->',
			6
		);
		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC save path stale base post',
				'post_content' => $current_content,
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content'                  => '<!-- wp:paragraph --><p>Rejected save path update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe'  => true,
				'client_base_version'      => '4',
				'pending_change_count'     => 2,
				'remote_change_count'      => 3,
				'can_attempt_local_rebase' => true,
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$error           = $response->as_error();
		$data            = $error->get_error_data( 'stale_base_version_rejected' );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'stale_base_version_rejected', $data['reason_code'] );
		$this->assertSame( 'stale_base_version_rejected', $data['detail'] );
		$this->assertSame( $post_id, $data['post_id'] );
		$this->assertSame( '4', $data['client_base_version'] );
		$this->assertSame( '6', $data['server_version'] );
		$this->assertSame( 2, $data['pending_change_count'] );
		$this->assertSame( 3, $data['remote_change_count'] );
		$this->assertTrue( $data['requires_server_state_refetch'] );
		$this->assertFalse( $data['can_attempt_local_rebase'] );
		$this->assertFalse( $data['requires_manual_conflict_resolution'] );
		$this->assertTrue( $data['can_export_local_updates'] );
		$this->assertSame( 'post_save_stale_base_probe', $data['rest_route'] );
		$this->assertTrue( $data['permission_contract']['feature_enabled'] );
		$this->assertSame( 'post', $data['permission_contract']['post_type'] );
		$this->assertSame( 'posts', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_post_update_without_stale_base_probe_saves_normally() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC normal save path post',
				'post_content' => '<!-- wp:paragraph --><p>Normal save current content.</p><!-- /wp:paragraph -->',
			)
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content' => '<!-- wp:paragraph --><p>Normal save updated content.</p><!-- /wp:paragraph -->',
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$after_post = get_post( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<!-- wp:paragraph --><p>Normal save updated content.</p><!-- /wp:paragraph -->', $after_post->post_content );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 */
	public function test_post_update_stale_base_probe_requires_feature_enablement() {
		remove_filter( 'wp_de_rtc_enabled_for_post', '__return_true' );

		$post_id          = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC disabled save path post',
				'post_content' => '<!-- wp:paragraph --><p>Disabled save path current content.</p><!-- /wp:paragraph -->',
			)
		);
		$before_post      = get_post( $post_id );
		$before_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$request          = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'content'                 => '<!-- wp:paragraph --><p>Rejected disabled update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe' => true,
				'client_base_version'     => '4',
			)
		);

		$response        = rest_get_server()->dispatch( $request );
		$after_post      = get_post( $post_id );
		$after_revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);

		$this->assertErrorResponse( 'de_rtc_feature_disabled', $response, 403 );
		$this->assertSame( $before_post->post_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	/**
	 * @covers ::wp_de_rtc_rest_pre_insert_stale_base_probe
	 * @covers ::wp_de_rtc_rest_save_probe_request_matches_post_type
	 */
	public function test_page_update_stale_base_probe_rejects_before_persistence() {
		$current_content = $this->add_sync_meta_to_content(
			'<!-- wp:paragraph --><p>Save path page current content.</p><!-- /wp:paragraph -->',
			9
		);
		$page_id         = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC save path stale base page',
				'post_type'    => 'page',
				'post_content' => $current_content,
			)
		);
		$before_page     = get_post( $page_id );
		$request         = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
		$request->set_body_params(
			array(
				'content'                 => '<!-- wp:paragraph --><p>Rejected page save path update.</p><!-- /wp:paragraph -->',
				'de_rtc_stale_base_probe' => true,
				'client_base_version'     => '7',
				'pending_change_count'    => 1,
				'remote_change_count'     => 2,
			)
		);

		$response   = rest_get_server()->dispatch( $request );
		$error      = $response->as_error();
		$data       = $error->get_error_data( 'stale_base_version_rejected' );
		$after_page = get_post( $page_id );

		$this->assertErrorResponse( 'stale_base_version_rejected', $response, 409 );
		$this->assertSame( 'post_save_stale_base_probe', $data['rest_route'] );
		$this->assertSame( '7', $data['client_base_version'] );
		$this->assertSame( '9', $data['server_version'] );
		$this->assertSame( 'page', $data['permission_contract']['post_type'] );
		$this->assertSame( 'pages', $data['permission_contract']['post_type_rest_base'] );
		$this->assertSame( $before_page->post_content, $after_page->post_content );
	}

	/**
	 * Adds synthetic sync metadata with a version to content.
	 *
	 * @param string $content Post content.
	 * @param int    $version Sync metadata version.
	 * @return string Content with sync metadata.
	 */
	private function add_sync_meta_to_content( $content, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content_with_sync_meta );

		return $content_with_sync_meta;
	}
}
