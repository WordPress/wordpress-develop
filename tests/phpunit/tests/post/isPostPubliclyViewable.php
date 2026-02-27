<?php

/**
 * @group post
 */
class Tests_Post_IsPostPubliclyViewable extends WP_UnitTestCase {

	/**
	 * Array of post IDs to use as parents.
	 *
	 * @var array
	 */
	public static $parent_post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$post_statuses = array( 'publish', 'private', 'future', 'trash', 'delete' );
		foreach ( $post_statuses as $post_status ) {
			$date          = '';
			$actual_status = $post_status;
			if ( 'future' === $post_status ) {
				$date = date_format( date_create( '+1 year' ), 'Y-m-d H:i:s' );
			} elseif ( in_array( $post_status, array( 'trash', 'delete' ), true ) ) {
				$actual_status = 'publish';
			}

			self::$parent_post_ids[ $post_status ] = $factory->post->create(
				array(
					'post_status' => $actual_status,
					'post_name'   => "$post_status-post",
					'post_date'   => $date,
					'post_type'   => 'page',
				)
			);
		}

		wp_trash_post( self::$parent_post_ids['trash'] );
		wp_delete_post( self::$parent_post_ids['delete'], true );
	}

	/**
	 * Unit tests for is_post_publicly_viewable().
	 *
	 * @dataProvider data_is_post_publicly_viewable
	 * @ticket 49380
	 *
	 * @param string $post_type   The post type.
	 * @param string $post_status The post status.
	 * @param bool   $expected    The expected result of the function call.
	 * @param string $parent_key  The parent key as set up in shared fixtures.
	 */
	public function test_is_post_publicly_viewable( $post_type, $post_status, $expected, $parent_key = '' ) {
		$date = '';
		if ( 'future' === $post_status ) {
			$date = date_format( date_create( '+1 year' ), 'Y-m-d H:i:s' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => $post_status,
				'post_parent' => $parent_key ? self::$parent_post_ids[ $parent_key ] : 0,
				'post_date'   => $date,
			)
		);

		$this->assertSame( $expected, is_post_publicly_viewable( $post_id ) );
	}

	/**
	 * Data provider for test_is_post_publicly_viewable().
	 *
	 * return array[] {
	 *     @type string $post_type   The post type.
	 *     @type string $post_status The post status.
	 *     @type bool   $expected    The expected result of the function call.
	 *     @type string $parent_key  The parent key as set up in shared fixtures.
	 * }
	 */
	public function data_is_post_publicly_viewable() {
		return array(
			array( 'post', 'publish', true ),
			array( 'post', 'private', false ),
			array( 'post', 'future', false ),

			array( 'page', 'publish', true ),
			array( 'page', 'private', false ),
			array( 'page', 'future', false ),

			array( 'unregistered_cpt', 'publish', false ),
			array( 'unregistered_cpt', 'private', false ),

			array( 'post', 'unregistered_cps', false ),
			array( 'page', 'unregistered_cps', false ),

			array( 'attachment', 'inherit', true, 'publish' ),
			array( 'attachment', 'inherit', false, 'private' ),
			array( 'attachment', 'inherit', false, 'future' ),
			array( 'attachment', 'inherit', true, 'trash' ),
			array( 'attachment', 'inherit', true, 'delete' ),

			array( 'page', 'publish', true, 'publish' ),
			array( 'page', 'publish', true, 'private' ),
			array( 'page', 'publish', true, 'future' ),
			array( 'page', 'publish', true, 'trash' ),
			array( 'page', 'publish', true, 'delete' ),
		);
	}
}
