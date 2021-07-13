<?php

/**
 * @group functions.php
 * @covers ::wp_removable_query_args
 */
class Tests_Functions_wp_removable_query_args extends WP_UnitTestCase {

	/**
	 * @ticket 53651
	 */
	public function test_wp_removable_query_args() {
		$removable_query_args = array(
			'activate',
			'activated',
			'admin_email_remind_later',
			'approved',
			'core-major-auto-updates-saved',
			'deactivate',
			'delete_count',
			'deleted',
			'disabled',
			'doing_wp_cron',
			'enabled',
			'error',
			'hotkeys_highlight_first',
			'hotkeys_highlight_last',
			'ids',
			'locked',
			'message',
			'same',
			'saved',
			'settings-updated',
			'skipped',
			'spammed',
			'trashed',
			'unspammed',
			'untrashed',
			'update',
			'updated',
			'wp-post-new-reload',
		);

		$this->assertSame( $removable_query_args, wp_removable_query_args() );
	}

	/**
	 * @ticket 53651
	 */
	public function test_wp_removable_query_args_filter() {
		add_filter( 'removable_query_args', array( $this, 'removable_query_args_filter' ) );

		$this->assertSame( 'removable_query_args_filter', wp_removable_query_args() );

		remove_filter( 'removable_query_args', array( $this, 'removable_query_args_filter' ) );
	}

	public function removable_query_args_filter($removable_query_args ) {
		return 'removable_query_args_filter';
	}

}
