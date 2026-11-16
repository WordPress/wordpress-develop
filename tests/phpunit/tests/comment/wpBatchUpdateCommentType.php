<?php

/**
 * @group comment
 *
 * @covers ::_wp_batch_update_comment_type
 */
class Tests_Comment_wpBatchUpdateCommentType extends WP_UnitTestCase {

	/**
	 * @ticket 49236
	 */
	public function test__wp_batch_update_comment_type() {
		global $wpdb;

		$comment_ids     = self::factory()->comment->create_many( 3 );
		$comment_id_list = implode( ',', $comment_ids );

		$wpdb->query(
			"UPDATE {$wpdb->comments}
			SET comment_type = ''
			WHERE comment_type = 'comment'
			AND comment_ID in ({$comment_id_list})" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		clean_comment_cache( $comment_ids );

		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );
			$this->assertEmpty( $comment->comment_type );
		}

		add_filter( 'wp_update_comment_type_batch_size', array( $this, 'filter_comment_type_batch_size' ) );
		add_filter( 'schedule_event', '__return_null' );

		_wp_batch_update_comment_type();

		remove_filter( 'wp_update_comment_type_batch_size', array( $this, 'filter_comment_type_batch_size' ) );
		remove_filter( 'schedule_event', '__return_null' );

		foreach ( $comment_ids as $comment_id ) {
			$updated_comment = get_comment( $comment_id );
			$this->assertSame( 'comment', $updated_comment->comment_type );
		}
	}

	/**
	 * Tests that the lock option is stored with the 'off' autoload value.
	 *
	 * @ticket 61045
	 */
	public function test__wp_batch_update_comment_type_lock_is_not_autoloaded() {
		global $wpdb;

		$comment_id = self::factory()->comment->create();

		$wpdb->update( $wpdb->comments, array( 'comment_type' => '' ), array( 'comment_ID' => $comment_id ) );
		clean_comment_cache( $comment_id );

		// The lock is deleted at the end of the run, so read its autoload value while the run is in progress.
		$autoload           = null;
		$read_lock_autoload = static function ( $batch_size ) use ( &$autoload, $wpdb ) {
			$autoload = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
					'update_comment_type.lock'
				)
			);
			return $batch_size;
		};

		add_filter( 'wp_update_comment_type_batch_size', $read_lock_autoload );
		add_filter( 'schedule_event', '__return_null' );

		_wp_batch_update_comment_type();

		remove_filter( 'wp_update_comment_type_batch_size', $read_lock_autoload );
		remove_filter( 'schedule_event', '__return_null' );

		$this->assertSame( 'off', $autoload );
	}

	public function filter_comment_type_batch_size() {
		return 3;
	}
}
