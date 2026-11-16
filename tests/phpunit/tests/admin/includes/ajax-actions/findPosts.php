<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_find_posts() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_find_posts
 */
class Tests_Admin_Includes_AjaxActions_findPosts extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Dispatches a search and returns the decoded JSON response.
	 *
	 * @param string      $search Search string.
	 * @param string|null $nonce  Optional. Nonce to send. Default is a valid nonce.
	 * @return array Decoded response.
	 */
	private function find_posts( string $search, ?string $nonce = null ): array {
		$_POST = array(
			'ps'          => $search,
			'_ajax_nonce' => $nonce ?? wp_create_nonce( 'find-posts' ),
		);

		$response = json_decode( $this->get_ajax_response( 'find_posts' ), true );
		$this->assertIsArray( $response, 'The response should be valid JSON.' );

		return $response;
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_returns_matching_posts(): void {
		wp_set_current_user( self::$admin_id );

		$match_id = self::factory()->post->create( array( 'post_title' => 'Searchable Post Title' ) );
		$other_id = self::factory()->post->create( array( 'post_title' => 'Something Else' ) );

		$response = $this->find_posts( 'Searchable' );

		$this->assertTrue( $response['success'] );
		$this->assertStringStartsWith( '<table class="widefat">', $response['data'] );
		$this->assertStringContainsString( "id=\"found-{$match_id}\"", $response['data'] );
		$this->assertStringContainsString( '>Searchable Post Title</label>', $response['data'] );
		$this->assertStringNotContainsString( "id=\"found-{$other_id}\"", $response['data'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_returns_all_posts_for_empty_search(): void {
		wp_set_current_user( self::$admin_id );

		$post_ids = self::factory()->post->create_many( 2 );

		$response = $this->find_posts( '' );

		$this->assertTrue( $response['success'] );
		foreach ( $post_ids as $post_id ) {
			$this->assertStringContainsString( "id=\"found-{$post_id}\"", $response['data'] );
		}
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_excludes_attachments_and_non_public_post_types(): void {
		wp_set_current_user( self::$admin_id );
		register_post_type( 'wptests_private', array( 'public' => false ) );

		$post_id       = self::factory()->post->create( array( 'post_title' => 'Findable post' ) );
		$attachment_id = self::factory()->attachment->create( array( 'post_title' => 'Findable attachment' ) );
		$private_id    = self::factory()->post->create(
			array(
				'post_title' => 'Findable private type',
				'post_type'  => 'wptests_private',
			)
		);

		$response = $this->find_posts( 'Findable' );

		$this->assertStringContainsString( "id=\"found-{$post_id}\"", $response['data'] );
		$this->assertStringNotContainsString( "id=\"found-{$attachment_id}\"", $response['data'] );
		$this->assertStringNotContainsString( "id=\"found-{$private_id}\"", $response['data'] );
	}

	/**
	 * @ticket 65252
	 *
	 * @dataProvider data_status_labels
	 *
	 * @param string $status Post status.
	 * @param string $label  Expected status label.
	 */
	public function test_find_posts_labels_post_status( string $status, string $label ): void {
		wp_set_current_user( self::$admin_id );

		$args = array(
			'post_title'  => 'Status label post',
			'post_status' => $status,
		);
		if ( 'future' === $status ) {
			$args['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
		}
		self::factory()->post->create( $args );

		$response = $this->find_posts( 'Status label post' );

		$this->assertStringContainsString( "<td class=\"no-break\">{$label} </td>", $response['data'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: string, 1: string }>
	 */
	public static function data_status_labels(): array {
		return array(
			'publish' => array( 'publish', 'Published' ),
			'private' => array( 'private', 'Published' ),
			'future'  => array( 'future', 'Scheduled' ),
			'pending' => array( 'pending', 'Pending Review' ),
			'draft'   => array( 'draft', 'Draft' ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_uses_placeholder_for_untitled_posts(): void {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create( array( 'post_title' => '' ) );

		$response = $this->find_posts( '' );

		$this->assertStringContainsString( "<label for=\"found-{$post_id}\">(no title)</label>", $response['data'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_returns_error_when_nothing_matches(): void {
		wp_set_current_user( self::$admin_id );

		$response = $this->find_posts( 'NonExistentTitle' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No items found.', $response['data'] );
	}

	/**
	 * @ticket 65252
	 */
	public function test_find_posts_rejects_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'ps'          => 'Searchable',
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->assertSame( '-1', $this->get_ajax_response( 'find_posts' ) );
	}
}
