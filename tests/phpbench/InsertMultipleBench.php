<?php
// phpcs:ignoreFile
declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/src/wp-load.php';

/**
 * @BeforeMethods("setUp")
 * @AfterMethods("tearDown")
 */
class InsertMultipleBench {
	public $post_id;

	public function setUp(): void {
		$this->post_id = wp_insert_post(
			[
				'post_title' => 'Test Post',
				'post_status' => 'publish',
			]
		);
	}

	public function tearDown(): void {
		wp_delete_post( $this->post_id, true );
	}

	/**
	 * @ParamProviders("provideMetaData")
	 * @Revs(10)
	 * @Iterations(3)
	 * @Warmup(1)
	 */
	public function benchAddMeta( array $meta ): void {
		foreach ( $meta as $key => $value ) {
			add_post_meta( $this->post_id, $key, $value );
		}
	}

	/**
	 * @ParamProviders("provideMetaData")
	 * @Revs(10)
	 * @Iterations(3)
	 * @Warmup(1)
	 */
	public function benchAddMultipleMeta( array $meta ): void {
		bulk_add_post_meta( $this->post_id, $meta );
	}

	public function provideMetaData(): Generator {
		$rows = [];
		// 3 rows ranging from 10 to 30 bytes.
		for ( $i = 1; $i <= 3; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i );
		}
		yield '3 small rows' => $rows;

		$rows = [];
		// 20 rows ranging from 10 to 200 bytes.
		for ( $i = 1; $i <= 20; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i );
		}
		yield '20 small rows' => $rows;

		$rows = [];
		// 50 rows ranging from 5 to 250 bytes.
		for ( $i = 1; $i <= 50; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '12345', $i );
		}
		yield '50 small rows' => $rows;

		$rows = [];
		// 100 rows ranging from 5 to 500 bytes.
		for ( $i = 1; $i <= 100; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '12345', $i );
		}
		yield '100 small rows' => $rows;

		$rows = [];
		// 10 rows ranging from 100 bytes to 1 KB.
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 10 );
		}
		yield '10 medium rows' => $rows;

		$rows = [];
		// 10 rows ranging from 1 KB to 10 KB.
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 100 );
		}
		yield '10 large rows' => $rows;

		$rows = [];
		// 3 rows ranging from 30 KB to 90 KB.
		for ( $i = 1; $i <= 3; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 3000 );
		}
		yield '3 xl rows' => $rows;

		$rows = [];
		// 10 rows ranging from 10 KB to 100 KB.
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 1000 );
		}
		yield '10 xl rows' => $rows;

		$rows = [];
		// 20 rows ranging from 10 KB to 200 KB.
		for ( $i = 1; $i <= 20; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 1000 );
		}
		yield '20 xl rows' => $rows;
	}
}
