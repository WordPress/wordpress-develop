<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_delete_link
 */
class Tests_Admin_Includes_Bookmark_WpDeleteLink_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_delete_the_link_and_return_true() {
		$link_id = self::factory()->bookmark->create();

		$this->assertTrue( wp_delete_link( $link_id ), 'wp_delete_link() should return true.' );
		$this->assertNull( get_bookmark( $link_id ), 'The link was not deleted.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_remove_the_link_category_relationships() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'link_category' ) );
		$link_id = self::factory()->bookmark->create( array( 'link_category' => array( $term_id ) ) );

		wp_delete_link( $link_id );

		$this->assertSame( array(), wp_get_object_terms( $link_id, 'link_category', array( 'fields' => 'ids' ) ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_clear_the_bookmark_cache() {
		$link_id = self::factory()->bookmark->create();

		// Prime the cache.
		get_bookmark( $link_id );

		wp_delete_link( $link_id );

		$this->assertFalse( wp_cache_get( $link_id, 'bookmark' ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_fire_the_delete_actions_around_the_deletion() {
		global $wpdb;

		$link_id = self::factory()->bookmark->create();

		$link_exists = static function () use ( $wpdb, $link_id ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_id = %d", $link_id ) );
		};

		$fired = array();
		add_action(
			'delete_link',
			static function ( $id ) use ( &$fired, $link_exists ) {
				$fired[] = array( 'delete_link', $id, $link_exists() );
			}
		);
		add_action(
			'deleted_link',
			static function ( $id ) use ( &$fired, $link_exists ) {
				$fired[] = array( 'deleted_link', $id, $link_exists() );
			}
		);

		wp_delete_link( $link_id );

		$this->assertSame(
			array(
				array( 'delete_link', $link_id, true ),
				array( 'deleted_link', $link_id, false ),
			),
			$fired
		);
	}
}
