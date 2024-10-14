<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::wp_getPages
 */
class Tests_XMLRPC_wp_getPages extends WP_XMLRPC_UnitTestCase {
	protected static $post_id;
	protected static $editor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id   = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => $factory->user->create(
					array(
						'user_login' => 'administrator',
						'user_pass'  => 'administrator',
						'role'       => 'administrator',
					)
				),
				'post_date'   => date_format( date_create( '+1 day' ), 'Y-m-d H:i:s' ),
			)
		);
		self::$editor_id = $factory->user->create(
			array(
				'user_login' => 'editor',
				'user_pass'  => 'editor',
				'role'       => 'editor',
			)
		);
	}

	public function test_invalid_username_password() {
		$result = $this->myxmlrpcserver->wp_getPages( array( 1, 'username', 'password' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 403, $result->code );
	}

	public function test_incapable_user() {
		$this->make_user_by_role( 'contributor' );

		$result = $this->myxmlrpcserver->wp_getPages( array( 1, 'contributor', 'contributor' ) );
		$this->assertIXRError( $result );
		$this->assertSame( 401, $result->code );
	}

	public function test_capable_user() {
		$results = $this->myxmlrpcserver->wp_getPages( array( 1, 'administrator', 'administrator' ) );
		$this->assertNotIXRError( $results );

		foreach ( $results as $result ) {
			$page = get_post( $result['page_id'] );
			$this->assertSame( $page->post_type, 'page' );
		}
	}

	public function remove_editor_edit_page_cap( $caps, $cap, $user_id, $args ) {
		if ( in_array( $cap, array( 'edit_page', 'edit_others_pages' ), true ) ) {
			if ( $user_id === self::$editor_id && $args[0] === self::$post_id ) {
				return array( false );
			}
		}

		return $caps;
	}

	/**
	 * @ticket 20629
	 */
	public function test_semi_capable_user() {
		add_filter( 'map_meta_cap', array( $this, 'remove_editor_edit_page_cap' ), 10, 4 );

		$results = $this->myxmlrpcserver->wp_getPages( array( 1, 'editor', 'editor' ) );
		$this->assertNotIXRError( $results );

		$found_incapable = false;
		foreach ( $results as $result ) {
			// WP#20629
			$this->assertNotIXRError( $result );

			if ( $result['page_id'] === self::$post_id ) {
				$found_incapable = true;
				break;
			}
		}
		$this->assertFalse( $found_incapable );

		remove_filter( 'map_meta_cap', array( $this, 'remove_editor_edit_page_cap' ), 10, 4 );
	}
}
