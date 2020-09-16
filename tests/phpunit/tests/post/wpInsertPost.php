<?php

/**
 * @group post
 */
class Tests_WPInsertPost extends WP_UnitTestCase {

	protected static $user_ids = array(
		'administrator' => null,
		'contributor'   => null,
	);

	static function wpSetUpBeforeClass( $factory ) {
		self::$user_ids = array(
			'administrator' => $factory->user->create(
				array(
					'role' => 'administrator',
				)
			),
			'contributor'   => $factory->user->create(
				array(
					'role' => 'contributor',
				)
			),
		);

		$role = get_role( 'administrator' );
		$role->add_cap( 'publish_mapped_meta_caps' );
		$role->add_cap( 'publish_unmapped_meta_caps' );
	}

	static function tearDownAfterClass() {
		$role = get_role( 'administrator' );
		$role->remove_cap( 'publish_mapped_meta_caps' );
		$role->remove_cap( 'publish_unmapped_meta_caps' );

		parent::tearDownAfterClass();
	}

	function setUp() {
		parent::setUp();

		register_post_type(
			'mapped_meta_caps',
			array(
				'capability_type' => array( 'mapped_meta_cap', 'mapped_meta_caps' ),
				'map_meta_cap'    => true,
			)
		);

		register_post_type(
			'unmapped_meta_caps',
			array(
				'capability_type' => array( 'unmapped_meta_cap', 'unmapped_meta_caps' ),
				'map_meta_cap'    => false,
			)
		);

		register_post_type(
			'no_admin_caps',
			array(
				'capability_type' => array( 'no_admin_cap', 'no_admin_caps' ),
				'map_meta_cap'    => false,
			)
		);
	}

	/**
	 * @ticket 11863
	 */
	function test_trashing_a_post_should_add_trashed_suffix_to_post_name() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $trashed_about_page_id );
		$this->assertSame( 'about__trashed', get_post( $trashed_about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	public function test_trashed_suffix_should_be_added_to_post_with__trashed_in_slug() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
				'post_name'   => 'foo__trashed__foo',
			)
		);
		wp_trash_post( $trashed_about_page_id );
		$this->assertSame( 'foo__trashed__foo__trashed', get_post( $trashed_about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	function test_trashed_posts_original_post_name_should_be_reassigned_after_untrashing() {
		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $about_page_id );

		wp_untrash_post( $about_page_id );
		$this->assertSame( 'about', get_post( $about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	function test_creating_a_new_post_should_add_trashed_suffix_to_post_name_of_trashed_posts_with_the_desired_slug() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'trash',
			)
		);

		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'about__trashed', get_post( $trashed_about_page_id )->post_name );
		$this->assertSame( 'about', get_post( $about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	function test_untrashing_a_post_with_a_stored_desired_post_name_should_get_its_post_name_suffixed_if_another_post_has_taken_the_desired_post_name() {
		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $about_page_id );

		$another_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);

		wp_untrash_post( $about_page_id );

		$this->assertSame( 'about', get_post( $another_about_page_id )->post_name );
		$this->assertSame( 'about-2', get_post( $about_page_id )->post_name );
	}

	/**
	 * Data for testing the ability for users to set the post slug.
	 *
	 * @return array Array of test arguments.
	 */
	function data_various_post_types() {
		return array(
			array(
				'mapped_meta_caps',
			),
			array(
				'unmapped_meta_caps',
			),
			array(
				'post',
			),
		);
	}

	/**
	 * Test contributor making changes to the pending post slug.
	 *
	 * @ticket 42464
	 * @dataProvider data_various_post_types
	 */
	function test_contributor_cannot_set_post_slug( $post_type ) {
		wp_set_current_user( self::$user_ids['contributor'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Jefferson claim: nice to have Washington on your side.',
				'post_content' => "I’m in the cabinet. I am complicit in watching him grabbin’ at power and kiss it.\n\nIf Washington isn’t gon’ listen to disciplined dissidents, this is the difference: this kid is out!",
				'post_type'    => $post_type,
				'post_name'    => 'new-washington',
				'post_status'  => 'pending',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Hamilton has Washington on side: Jefferson',
				'post_name'  => 'edited-washington',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test administrator making changes to the pending post slug.
	 *
	 * @ticket 42464
	 * @dataProvider data_various_post_types
	 */
	function test_administrator_can_set_post_slug( $post_type ) {
		wp_set_current_user( self::$user_ids['administrator'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'What is the Conner Project?',
				'post_content' => 'Evan Hansen’s last link to his friend Conner is a signature on his broken arm.',
				'post_type'    => $post_type,
				'post_name'    => 'dear-evan-hansen-explainer',
				'post_status'  => 'pending',
			)
		);

		$expected = 'dear-evan-hansen-explainer';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Conner Project to close',
				'post_name'  => 'dear-evan-hansen-spoiler',
			)
		);

		$expected = 'dear-evan-hansen-spoiler';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test administrator making changes to a pending post slug for a post type they don't
	 * have permission to publish.
	 *
	 * These assertions failed prior to ticket #42464.
	 *
	 * @ticket 42464
	 */
	function test_administrator_cannot_set_post_slug_on_post_type_they_cannot_publish() {
		wp_set_current_user( self::$user_ids['administrator'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Everything is legal in New Jersey',
				'post_content' => 'Shortly before his death, Philip Hamilton was heard to claim everything was legal in the garden state.',
				'post_type'    => 'no_admin_caps',
				'post_name'    => 'yet-another-duel',
				'post_status'  => 'pending',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Ten things illegal in New Jersey',
				'post_name'  => 'foreshadowing-in-nj',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 25347
	 */
	function test_scheduled_post_with_a_past_date_should_be_published() {

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$post_id = $this->factory()->post->create(
			array(
				'post_date_gmt' => $now->modify( '-1 year' )->format( 'Y-m-d H:i:s' ),
				'post_status'   => 'future',
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );

		$post_id = $this->factory()->post->create(
			array(
				'post_date_gmt' => $now->modify( '+50 years' )->format( 'Y-m-d H:i:s' ),
				'post_status'   => 'future',
			)
		);

		$this->assertSame( 'future', get_post_status( $post_id ) );
	}
}
