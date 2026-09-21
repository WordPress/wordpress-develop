<?php

/**
 * Tests for the dead_db function.
 *
 * @group Functions
 *
 * @covers ::dead_db
 */
class Tests_Functions_deadDb extends WP_UnitTestCase {

	/**
	 * @ticket 60102
	 */
	public function test_dead_db_admin() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( '0' ); // no error so returns 0

		define( 'WP_ADMIN', true );

		dead_db();

		$this->assertEmpty( $this->caught_doing_it_wrong );
	}

	/**
	 * @ticket 60102
	 */
	public function test_dead_db() {
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( '<h1>Error establishing a database connection</h1>' );

		dead_db();

		$this->assertEmpty( $this->caught_doing_it_wrong );
	}
}
