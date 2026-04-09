<?php

/**
 * @group admin
 * @group upgrade
 */
class Tests_Admin_IncludesUpgrade extends WP_UnitTestCase {

	/**
	 * The original database table prefix.
	 *
	 * @var string
	 */
	private $original_prefix;

	/**
	 * The database table prefix used during the test.
	 *
	 * @var string
	 */
	private $test_prefix = 'wptests_install_failure_';

	/**
	 * Loads the upgrade functions before the tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		global $wpdb, $table_prefix;

		parent::set_up();

		$this->original_prefix = $wpdb->base_prefix;

		$wpdb->set_prefix( $this->test_prefix );
		$table_prefix = $this->test_prefix;

		$this->drop_test_tables();
	}

	/**
	 * Tears down the test fixture.
	 */
	public function tear_down() {
		global $wpdb, $table_prefix;

		remove_filter( 'query', array( $this, 'force_create_table_failure' ) );

		$this->drop_test_tables();

		$wpdb->set_prefix( $this->original_prefix );
		$table_prefix = $this->original_prefix;

		parent::tear_down();
	}

	/**
	 * Ensures installation fails when schema creation fails.
	 */
	public function test_wp_install_returns_wp_error_when_schema_creation_fails() {
		global $wpdb;

		$show_errors     = $wpdb->hide_errors();
		$suppress_errors = $wpdb->suppress_errors();

		add_filter( 'query', array( $this, 'force_create_table_failure' ) );

		$result = wp_install( 'WordPress Develop', 'admin', 'test@example.com', 1, '', 'password' );

		remove_filter( 'query', array( $this, 'force_create_table_failure' ) );

		$wpdb->show_errors( $show_errors );
		$wpdb->suppress_errors( $suppress_errors );

		$this->assertWPError( $result );
		$this->assertSame( 'db_install_missing_tables', $result->get_error_code() );
		$this->assertStringContainsString( 'could not create the database tables required for installation', $result->get_error_message() );
		$this->assertContains( $wpdb->options, $result->get_error_data()['missing_tables'] );
	}

	/**
	 * Forces CREATE TABLE queries to fail for the installer schema step.
	 *
	 * @param string $query Database query.
	 * @return string Filtered query.
	 */
	public function force_create_table_failure( $query ) {
		if ( 0 === strpos( trim( $query ), 'CREATE TABLE' ) ) {
			return 'CREATE TABL broken_installer_schema';
		}

		return $query;
	}

	/**
	 * Drops any tables that were created with the test prefix.
	 */
	private function drop_test_tables() {
		global $wpdb;

		$tables = $wpdb->tables( 'all', true );

		if ( empty( $tables ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'DROP TABLE IF EXISTS ' . implode( ', ', array_fill( 0, count( $tables ), '%i' ) ),
				$tables
			)
		);

	}
}
