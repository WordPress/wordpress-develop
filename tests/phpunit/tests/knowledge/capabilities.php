<?php
/**
 * Tests for the dynamically granted wp_knowledge capabilities.
 *
 * @package WordPress
 * @subpackage Knowledge
 *
 * @group knowledge
 * @group capabilities
 *
 * @covers ::wp_maybe_grant_knowledge_caps
 */
class Tests_Knowledge_Capabilities extends WP_UnitTestCase {

	/**
	 * User IDs keyed by role.
	 *
	 * @var int[]
	 */
	private static array $users = array();

	/**
	 * A private knowledge row owned by the contributor.
	 */
	private static int $own_private;

	/**
	 * A published knowledge row owned by the contributor.
	 */
	private static int $own_published;

	/**
	 * A private knowledge row owned by the author.
	 */
	private static int $others_private;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$throw_if_not_int = static function ( $value ): int {
			if ( ! is_int( $value ) ) {
				throw new Exception( 'Value is not an int.' );
			}
			return $value;
		};

		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			self::$users[ $role ] = $throw_if_not_int( $factory->user->create( array( 'role' => $role ) ) );
		}

		self::$own_private = $throw_if_not_int(
			$factory->post->create(
				array(
					'post_type'   => 'wp_knowledge',
					'post_status' => 'private',
					'post_author' => self::$users['contributor'],
				)
			)
		);

		self::$own_published = $throw_if_not_int(
			$factory->post->create(
				array(
					'post_type'   => 'wp_knowledge',
					'post_status' => 'publish',
					'post_author' => self::$users['contributor'],
				)
			)
		);

		self::$others_private = $throw_if_not_int(
			$factory->post->create(
				array(
					'post_type'   => 'wp_knowledge',
					'post_status' => 'private',
					'post_author' => self::$users['author'],
				)
			)
		);
	}

	/**
	 * @ticket 65476
	 */
	public function test_administrator_has_every_primitive(): void {
		wp_set_current_user( self::$users['administrator'] );

		$this->assertTrue( current_user_can( 'read_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'edit_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'edit_others_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'publish_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'delete_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'delete_others_knowledge_items' ) );
		$this->assertTrue( current_user_can( 'read_private_knowledge_items' ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_administrator_can_act_on_others_rows(): void {
		wp_set_current_user( self::$users['administrator'] );

		$this->assertTrue( current_user_can( 'edit_post', self::$others_private ) );
		$this->assertTrue( current_user_can( 'read_post', self::$others_private ) );
		$this->assertTrue( current_user_can( 'delete_post', self::$others_private ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_subscriber_has_no_access(): void {
		wp_set_current_user( self::$users['subscriber'] );

		$this->assertFalse( current_user_can( 'read_knowledge_items' ) );
		$this->assertFalse( current_user_can( 'edit_knowledge_items' ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_anonymous_has_no_access(): void {
		wp_set_current_user( 0 );

		$this->assertFalse( current_user_can( 'read_knowledge_items' ) );
		$this->assertFalse( current_user_can( 'edit_knowledge_items' ) );
	}

	/**
	 * @ticket 65476
	 *
	 * @dataProvider data_contributor_level_roles
	 *
	 * @param string $role Role slug.
	 */
	public function test_contributor_level_ambient_floor( $role ): void {
		wp_set_current_user( self::$users[ $role ] );

		// May list and create knowledge.
		$this->assertTrue( current_user_can( 'read_knowledge_items' ), "$role should read_knowledge_items" );
		$this->assertTrue( current_user_can( 'edit_knowledge_items' ), "$role should edit_knowledge_items" );

		// May not publish or act on other users' rows.
		$this->assertFalse( current_user_can( 'publish_knowledge_items' ), "$role should not publish_knowledge_items" );
		$this->assertFalse( current_user_can( 'edit_others_knowledge_items' ), "$role should not edit_others_knowledge_items" );
		$this->assertFalse( current_user_can( 'delete_others_knowledge_items' ), "$role should not delete_others_knowledge_items" );
	}

	public function data_contributor_level_roles(): array {
		return array(
			'contributor' => array( 'contributor' ),
			'author'      => array( 'author' ),
			'editor'      => array( 'editor' ),
		);
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_can_manage_own_private_row(): void {
		wp_set_current_user( self::$users['contributor'] );

		$this->assertTrue( current_user_can( 'edit_post', self::$own_private ) );
		$this->assertTrue( current_user_can( 'read_post', self::$own_private ) );
		$this->assertTrue( current_user_can( 'delete_post', self::$own_private ) );
	}

	/**
	 * A contributor keeps control of their own row after trashing it.
	 *
	 * Trashing flips the status to `trash`, but the pre-trash `private` status is
	 * preserved in `_wp_trash_meta_status`, so the per-post grant must still apply
	 * and let the author permanently delete (or restore) their own trashed row.
	 *
	 * @ticket 65476
	 */
	public function test_contributor_can_delete_own_trashed_row(): void {
		wp_set_current_user( self::$users['contributor'] );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'wp_knowledge',
				'post_status' => 'private',
				'post_author' => self::$users['contributor'],
			)
		);
		$this->assertIsInt( $post_id );

		wp_trash_post( $post_id );
		$this->assertSame( 'trash', get_post_status( $post_id ) );

		$this->assertTrue( current_user_can( 'delete_post', $post_id ) );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_edit_own_published_row(): void {
		wp_set_current_user( self::$users['contributor'] );

		// Publishing is reserved for administrators, so an already-published
		// row falls outside the per-post grant.
		$this->assertFalse( current_user_can( 'edit_post', self::$own_published ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_contributor_cannot_act_on_others_rows(): void {
		wp_set_current_user( self::$users['contributor'] );

		$this->assertFalse( current_user_can( 'edit_post', self::$others_private ) );
		$this->assertFalse( current_user_can( 'read_post', self::$others_private ) );
		$this->assertFalse( current_user_can( 'delete_post', self::$others_private ) );
	}

	/**
	 * @ticket 65476
	 */
	public function test_grant_does_not_apply_to_other_post_types(): void {
		wp_set_current_user( self::$users['contributor'] );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_author' => self::$users['contributor'],
			)
		);

		// The knowledge per-post grant must not leak into other post types.
		$this->assertFalse( current_user_can( 'edit_post', $page_id ) );
	}
}
