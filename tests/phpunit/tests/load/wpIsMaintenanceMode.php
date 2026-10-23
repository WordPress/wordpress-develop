<?php

/**
 * Tests for wp_is_maintenance_mode().
 *
 * @group load
 *
 * @covers ::wp_is_maintenance_mode
 */
class Tests_Load_WpIsMaintenanceMode extends WP_UnitTestCase {

	/**
	 * Original $upgrading global.
	 *
	 * @var mixed
	 */
	private $original_upgrading;

	/**
	 * Original installing status.
	 *
	 * @var bool
	 */
	private $original_installing;

	/**
	 * Original scrape request values.
	 *
	 * @var array
	 */
	private $original_scrape_request = array();

	public function set_up() {
		parent::set_up();

		$this->original_upgrading  = $GLOBALS['upgrading'] ?? null;
		$this->original_installing = wp_installing();

		foreach ( array( 'wp_scrape_key', 'wp_scrape_nonce' ) as $key ) {
			$this->original_scrape_request[ $key ] = $_REQUEST[ $key ] ?? null;
		}

		$this->delete_maintenance_file();
	}

	public function tear_down() {
		$this->delete_maintenance_file();

		if ( null !== $this->original_upgrading ) {
			$GLOBALS['upgrading'] = $this->original_upgrading;
		} else {
			unset( $GLOBALS['upgrading'] );
		}

		wp_installing( $this->original_installing );

		foreach ( $this->original_scrape_request as $key => $value ) {
			if ( null !== $value ) {
				$_REQUEST[ $key ] = $value;
			} else {
				unset( $_REQUEST[ $key ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Writes a `.maintenance` file with the given upgrading timestamp.
	 *
	 * Note: wp_is_maintenance_mode() hard-codes the path to ABSPATH . '.maintenance',
	 * so this writes a real file there rather than into a temporary directory.
	 * tear_down() removes it. If a test run stops partway through (for example,
	 * a fatal error or an interrupted process), the file can be left behind, and
	 * the next WordPress bootstrap will stop in maintenance mode until the
	 * timestamp expires (up to 10 minutes). If that happens, delete the
	 * `.maintenance` file from the WordPress root manually.
	 *
	 * @param int $upgrading Unix timestamp.
	 */
	private function write_maintenance_file( $upgrading ) {
		file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = ' . (int) $upgrading . ";\n" );
	}

	/**
	 * Deletes the `.maintenance` file if it exists.
	 */
	private function delete_maintenance_file() {
		$file = ABSPATH . '.maintenance';

		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_maintenance_file_is_missing() {
		$this->assertFalse( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_maintenance_file_is_fresh() {
		$this->write_maintenance_file( time() );

		$this->assertTrue( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_maintenance_file_is_expired() {
		$this->write_maintenance_file( time() - ( 10 * MINUTE_IN_SECONDS ) );

		$this->assertFalse( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_while_installing() {
		$this->write_maintenance_file( time() );
		wp_installing( true );

		$this->assertFalse( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_scraping_fatal_errors() {
		$upgrading = time();

		$this->write_maintenance_file( $upgrading );

		$_REQUEST['wp_scrape_key']   = md5( $upgrading );
		$_REQUEST['wp_scrape_nonce'] = (string) $upgrading;

		$this->assertFalse( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_when_scrape_credentials_do_not_match() {
		$upgrading = time();

		$this->write_maintenance_file( $upgrading );

		$_REQUEST['wp_scrape_key']   = 'invalid';
		$_REQUEST['wp_scrape_nonce'] = '0';

		$this->assertTrue( wp_is_maintenance_mode() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_disable_maintenance_mode() {
		$upgrading = time();

		$this->write_maintenance_file( $upgrading );

		$filter = new MockAction();
		add_filter( 'enable_maintenance_mode', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'enable_maintenance_mode', '__return_false', 20 );

		$this->assertFalse( wp_is_maintenance_mode() );
		$this->assertSame(
			array( array( true, $upgrading ) ),
			$filter->get_args()
		);
	}
}
