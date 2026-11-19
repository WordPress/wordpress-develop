<?php

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/revision.php';

/**
 * Tests for wp_prepare_revisions_for_js().
 *
 * @group admin
 * @group revisions
 *
 * @covers ::wp_prepare_revisions_for_js
 */
class Tests_Admin_includesPrepareRevisionsForJs extends WP_UnitTestCase {

	/**
	 * ID of an administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'The Admin',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Creates a post with revisions.
	 *
	 * @param int   $updates How many times to update the content of the post.
	 * @param array $args    Optional. The arguments of the post. Default none.
	 * @return int The ID of the post.
	 */
	private function create_post_with_revisions( $updates = 2, $args = array() ) {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_title'   => 'The title',
					'post_content' => 'Content 0',
					'post_author'  => self::$admin_id,
				),
				$args
			)
		);

		for ( $i = 1; $i <= $updates; $i++ ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => "Content $i",
				)
			);
		}

		return $post_id;
	}

	/**
	 * Returns the IDs of the revisions of a post, from the oldest.
	 *
	 * @param int $post_id The ID of the post.
	 * @return int[] The IDs.
	 */
	private function get_revision_ids( $post_id ) {
		return array_keys( wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) ) );
	}

	/**
	 * Tests the data that is returned.
	 *
	 * @ticket 66607
	 */
	public function test_should_return_the_data_of_the_revisions_screen() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );
		$selected     = end( $revision_ids );

		$data = wp_prepare_revisions_for_js( $post_id, $selected );

		$this->assertSame( $post_id, $data['postId'] );
		$this->assertSame( $selected, $data['to'] );
		$this->assertSame( wp_create_nonce( 'revisions-ajax-nonce' ), $data['nonce'] );
		$this->assertSame( parse_url( admin_url( 'revision.php' ), PHP_URL_PATH ), $data['baseUrl'] );
		$this->assertSame( 0, $data['compareTwoMode'] );
		$this->assertSame( $revision_ids, $data['revisionIds'] );
		$this->assertSame( $revision_ids, wp_list_pluck( $data['revisionData'], 'id' ) );
		$this->assertCount( 1, $data['diffData'] );
	}

	/**
	 * Tests the revision that the selected one is compared with.
	 *
	 * @ticket 66607
	 */
	public function test_should_compare_the_selected_revision_with_the_one_before_it() {
		$post_id      = $this->create_post_with_revisions( 3 );
		$revision_ids = $this->get_revision_ids( $post_id );

		$this->assertGreaterThan( 2, count( $revision_ids ) );

		$data = wp_prepare_revisions_for_js( $post_id, $revision_ids[2] );

		$this->assertSame( $revision_ids[1], $data['from'] );
		$this->assertSame( "{$revision_ids[1]}:{$revision_ids[2]}", $data['diffData'][0]['id'] );
		$this->assertSame(
			wp_get_revision_ui_diff( $post_id, $revision_ids[1], $revision_ids[2] ),
			$data['diffData'][0]['fields']
		);
	}

	/**
	 * Tests that the first revision is compared with nothing.
	 *
	 * @ticket 66607
	 */
	public function test_should_compare_the_first_revision_with_nothing() {
		$post_id      = $this->create_post_with_revisions( 3 );
		$revision_ids = $this->get_revision_ids( $post_id );

		$data = wp_prepare_revisions_for_js( $post_id, $revision_ids[0] );

		$this->assertSame( 0, $data['from'] );
		$this->assertSame( "0:{$revision_ids[0]}", $data['diffData'][0]['id'] );
	}

	/**
	 * Tests the mode that compares two revisions.
	 *
	 * @ticket 66607
	 */
	public function test_should_compare_two_revisions() {
		$post_id      = $this->create_post_with_revisions( 3 );
		$revision_ids = $this->get_revision_ids( $post_id );

		$data = wp_prepare_revisions_for_js( $post_id, $revision_ids[2], $revision_ids[0] );

		$this->assertSame( $revision_ids[0], $data['from'] );
		$this->assertSame( 1, $data['compareTwoMode'] );
		$this->assertSame( "{$revision_ids[0]}:{$revision_ids[2]}", $data['diffData'][0]['id'] );
	}

	/**
	 * Tests that the post can be a post object.
	 *
	 * @ticket 66607
	 */
	public function test_should_accept_a_post_object() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		$data = wp_prepare_revisions_for_js( get_post( $post_id ), end( $revision_ids ) );

		$this->assertSame( $post_id, $data['postId'] );
	}

	/**
	 * Tests the data of a revision.
	 *
	 * @ticket 66607
	 */
	public function test_should_return_the_data_of_each_revision() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );
		$revision     = get_post( $revision_ids[0] );

		$data = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) );
		$item = $data['revisionData'][0];

		$this->assertSame( $revision_ids[0], $item['id'] );
		$this->assertSame( 'The title', $item['title'] );
		$this->assertFalse( $item['autosave'] );
		$this->assertSame( date_i18n( 'M j, Y @ H:i', strtotime( $revision->post_modified ) ), $item['date'] );
		$this->assertSame( date_i18n( 'j M Y @ H:i', strtotime( $revision->post_modified ) ), $item['dateShort'] );
		$this->assertMatchesRegularExpression( '/^.+ ago$/', $item['timeAgo'] );
	}

	/**
	 * Tests the author of the revisions.
	 *
	 * @ticket 66607
	 */
	public function test_should_return_the_author_with_the_avatar() {
		update_option( 'show_avatars', 1 );

		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		$author = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) )['revisionData'][0]['author'];

		$this->assertSame( self::$admin_id, $author['id'] );
		$this->assertSame( 'The Admin', $author['name'] );
		$this->assertStringStartsWith( '<img ', $author['avatar'] );
		$this->assertStringContainsString( "width='32'", $author['avatar'] );
	}

	/**
	 * Tests the author without avatars.
	 *
	 * @ticket 66607
	 */
	public function test_should_return_the_author_without_the_avatar_when_avatars_are_off() {
		update_option( 'show_avatars', 0 );

		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		$author = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) )['revisionData'][0]['author'];

		$this->assertSame( '', $author['avatar'] );
		$this->assertSame( 'The Admin', $author['name'] );
	}

	/**
	 * Tests the restore link.
	 *
	 * @ticket 66607
	 */
	public function test_should_return_the_restore_link_for_a_user_who_can_edit_the_post() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		$data = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) );
		$url  = $data['revisionData'][0]['restoreUrl'];

		$this->assertStringContainsString( admin_url( 'revision.php' ), $url );
		$this->assertStringContainsString( 'revision=' . $revision_ids[0], $url );
		$this->assertStringContainsString( 'action=restore', $url );
		$this->assertStringContainsString( '_wpnonce=' . wp_create_nonce( 'restore-post_' . $revision_ids[0] ), $url );
		$this->assertStringNotContainsString( '&amp;', $url );
	}

	/**
	 * Tests that there is no restore link for a user who cannot edit the post.
	 *
	 * @ticket 66607
	 */
	public function test_should_not_return_a_restore_link_for_a_user_who_cannot_edit_the_post() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$data = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) );

		$this->assertSame( array( false ), array_values( array_unique( wp_list_pluck( $data['revisionData'], 'restoreUrl' ) ) ) );
	}

	/**
	 * Tests the current revision.
	 *
	 * @ticket 66607
	 */
	public function test_should_mark_one_revision_as_the_current_one() {
		$post_id      = $this->create_post_with_revisions( 3 );
		$revision_ids = $this->get_revision_ids( $post_id );

		$data    = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) );
		$current = array_keys( array_filter( wp_list_pluck( $data['revisionData'], 'current' ) ) );

		$this->assertCount( 1, $current, 'Only one revision should be the current one.' );
		$this->assertSame( end( $revision_ids ), $data['revisionData'][ $current[0] ]['id'], 'The latest revision with the date of the post is the current one.' );
	}

	/**
	 * Tests an autosave.
	 *
	 * @ticket 66607
	 */
	public function test_should_mark_the_autosaves() {
		$post_id = $this->create_post_with_revisions();

		$autosave_id = wp_create_post_autosave(
			array(
				'post_ID'      => $post_id,
				'post_type'    => 'post',
				'post_title'   => 'Autosaved title',
				'post_content' => 'Autosaved content',
				'post_excerpt' => '',
			)
		);

		$this->assertGreaterThan( 0, $autosave_id );

		$data = wp_prepare_revisions_for_js( $post_id, $autosave_id );
		$map  = array_column( $data['revisionData'], 'autosave', 'id' );

		$this->assertTrue( $map[ $autosave_id ] );
		$this->assertContains( false, $map );
		$this->assertSame( 1, count( array_filter( $map ) ), 'Only the autosave should be marked.' );
	}

	/**
	 * Tests a post with one revision: the post itself is added.
	 *
	 * @ticket 66607
	 */
	public function test_should_add_the_post_when_there_is_one_revision() {
		$post_id      = $this->create_post_with_revisions( 1 );
		$revision_ids = $this->get_revision_ids( $post_id );

		$this->assertCount( 1, $revision_ids, 'The post should have one revision.' );

		$data = wp_prepare_revisions_for_js( $post_id, $revision_ids[0] );
		$ids  = wp_list_pluck( $data['revisionData'], 'id' );

		$this->assertCount( 2, $ids );
		$this->assertContains( $post_id, $ids );
		$this->assertContains( $revision_ids[0], $ids );
	}

	/**
	 * Tests a post whose revisions are turned off.
	 *
	 * @ticket 66607
	 */
	public function test_should_only_use_the_post_and_the_autosaves_when_revisions_are_off() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );

		$this->assertNotEmpty( $revision_ids );

		$autosave_id = wp_create_post_autosave(
			array(
				'post_ID'      => $post_id,
				'post_type'    => 'post',
				'post_title'   => 'Autosaved title',
				'post_content' => 'Autosaved content',
				'post_excerpt' => '',
			)
		);

		add_filter( 'wp_revisions_to_keep', '__return_zero' );

		$data = wp_prepare_revisions_for_js( $post_id, $post_id );

		$this->assertSame( array( $post_id, $autosave_id ), wp_list_pluck( $data['revisionData'], 'id' ), 'The revisions that are not autosaves should be left out.' );
		$this->assertTrue( $data['revisionData'][0]['current'] );
		$this->assertTrue( $data['revisionData'][1]['autosave'] );
		$this->assertSame( array( $post_id, $autosave_id ), $data['revisionIds'] );
	}

	/**
	 * Tests the filter of the data of a revision.
	 *
	 * @ticket 66607
	 */
	public function test_should_filter_the_data_of_each_revision() {
		$post_id      = $this->create_post_with_revisions();
		$revision_ids = $this->get_revision_ids( $post_id );
		$received     = array();

		add_filter(
			'wp_prepare_revision_for_js',
			static function ( $data, $revision, $post ) use ( &$received ) {
				$received[] = array( $data['id'], $revision->ID, $post->ID );

				$data['extra'] = 'x' . $revision->ID;

				return $data;
			},
			10,
			3
		);

		$data = wp_prepare_revisions_for_js( $post_id, end( $revision_ids ) );

		$this->assertSame( array_map( static fn( $id ) => array( $id, $id, $post_id ), $revision_ids ), $received );
		$this->assertSame( 'x' . $revision_ids[0], $data['revisionData'][0]['extra'] );
	}
}
