<?php

/**
 * Tests for the dead_db() function.
 *
 * @group functions
 *
 * @covers ::dead_db
 */
class Tests_Functions_DeadDb extends WP_UnitTestCase {

	/**
	 * Original database error message.
	 *
	 * @var string
	 */
	private $orig_wpdb_error;

	public function set_up() {
		parent::set_up();

		global $wpdb;
		$this->orig_wpdb_error = $wpdb->error;
	}

	public function tear_down() {
		global $wpdb;
		$wpdb->error = $this->orig_wpdb_error;

		parent::tear_down();
	}

	/**
	 * Tests that dead_db() outputs the verbose database error message in an admin context.
	 *
	 * @ticket 60102
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_dead_db_admin(): void {
		global $wpdb;

		define( 'WP_ADMIN', true );
		$wpdb->error = 'Database connection error';

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Database connection error' );

		dead_db();
	}

	/**
	 * Tests that dead_db() outputs the verbose database error message during installation.
	 *
	 * @ticket 60102
	 */
	public function test_dead_db_installing(): void {
		global $wpdb;

		wp_installing( true );
		$wpdb->error = 'Database connection error';

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Database connection error' );

		try {
			dead_db();
		} finally {
			wp_installing( false );
		}
	}

	/**
	 * Tests that dead_db() outputs the generic database error message in a public context.
	 *
	 * @ticket 60102
	 */
	public function test_dead_db(): void {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( '<h1>Error establishing a database connection</h1>' );

		dead_db();
	}
}
