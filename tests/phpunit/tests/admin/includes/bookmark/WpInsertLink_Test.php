<?php

/**
 * @group admin
 * @group bookmark
 *
 * @covers ::wp_insert_link
 */
class Tests_Admin_Includes_Bookmark_WpInsertLink_Test extends WP_UnitTestCase {

	/**
	 * @ticket 66019
	 */
	public function test_should_insert_a_link_and_return_its_id() {
		$link_id = wp_insert_link(
			array(
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			)
		);

		$this->assertIsInt( $link_id, 'The link ID should be an integer.' );

		$link = get_bookmark( $link_id );

		$this->assertSame( 'https://example.com/', $link->link_url, 'The link URL was not stored.' );
		$this->assertSame( 'Example', $link->link_name, 'The link name was not stored.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_return_zero_when_the_url_is_empty() {
		$this->assertSame( 0, wp_insert_link( array( 'link_name' => 'Example' ) ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_use_the_url_as_the_name_when_the_name_is_empty() {
		$link_id = wp_insert_link( array( 'link_url' => 'https://example.com/' ) );

		$this->assertSame( 'https://example.com/', get_bookmark( $link_id )->link_name );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_default_link_visible_to_yes() {
		$link_id = wp_insert_link(
			array(
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			)
		);

		$this->assertSame( 'Y', get_bookmark( $link_id )->link_visible );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_default_the_owner_to_the_current_user() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$link_id = wp_insert_link(
			array(
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			)
		);

		$this->assertSame( $user_id, (int) get_bookmark( $link_id )->link_owner );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_update_the_existing_link_when_a_link_id_is_provided() {
		$link_id = self::factory()->bookmark->create( array( 'link_name' => 'Original' ) );

		$result = wp_insert_link(
			array(
				'link_id'   => $link_id,
				'link_url'  => 'https://example.com/',
				'link_name' => 'Updated',
			)
		);

		$this->assertSame( $link_id, $result, 'The existing link ID should be returned.' );
		$this->assertSame( 'Updated', get_bookmark( $link_id )->link_name, 'The link was not updated.' );
		$this->assertCount( 1, get_bookmarks( array( 'hide_invisible' => false ) ), 'A second link was inserted.' );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_return_zero_when_the_database_insert_fails() {
		$this->assertSame( 0, $this->insert_link_with_a_failing_query( false ) );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_return_an_error_when_the_database_insert_fails_and_wp_error_is_true() {
		$result = $this->insert_link_with_a_failing_query( true );

		$this->assertWPError( $result );
		$this->assertSame( 'db_insert_error', $result->get_error_code() );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_fire_the_add_link_action_when_inserting() {
		$fired = array();
		add_action(
			'add_link',
			static function ( $link_id ) use ( &$fired ) {
				$fired[] = $link_id;
			}
		);

		$link_id = wp_insert_link(
			array(
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			)
		);

		$this->assertSame( array( $link_id ), $fired );
	}

	/**
	 * @ticket 66019
	 */
	public function test_should_fire_the_edit_link_action_when_updating() {
		$link_id = self::factory()->bookmark->create();

		$fired = array();
		add_action(
			'edit_link',
			static function ( $link_id ) use ( &$fired ) {
				$fired[] = $link_id;
			}
		);

		wp_insert_link(
			array(
				'link_id'   => $link_id,
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			)
		);

		$this->assertSame( array( $link_id ), $fired );
	}

	/**
	 * Calls wp_insert_link() with the link insert query rewritten to fail.
	 *
	 * @param bool $wp_error Whether to request a WP_Error object on failure.
	 * @return int|WP_Error The value returned by wp_insert_link().
	 */
	private function insert_link_with_a_failing_query( $wp_error ) {
		global $wpdb;

		$rewrite_query = static function ( $query ) use ( $wpdb ) {
			if ( str_starts_with( $query, "INSERT INTO `$wpdb->links`" ) ) {
				return "INSERT INTO `{$wpdb->prefix}links_no_such_table` (`link_id`) VALUES (1)";
			}

			return $query;
		};

		$wpdb->suppress_errors( true );
		add_filter( 'query', $rewrite_query );

		$result = wp_insert_link(
			array(
				'link_url'  => 'https://example.com/',
				'link_name' => 'Example',
			),
			$wp_error
		);

		remove_filter( 'query', $rewrite_query );
		$wpdb->suppress_errors( false );

		return $result;
	}
}
