<?php
// phpcs:ignoreFile
declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/src/wp-load.php';

/**
 * @BeforeMethods("setUp")
 * @AfterMethods("tearDown")
 */
abstract class AddMetaBenchBase {
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

	public function provideMetaData(): Generator {
		$rows = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i );
		}
		yield '3 metas, 10-30 bytes' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i );
		}
		yield '20 metas, 10-200 bytes' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 50; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '12345', $i );
		}
		yield '50 metas, 5-250 bytes' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 100; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '12345', $i );
		}
		yield '100 metas, 5-500 bytes' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 10 );
		}
		yield '10 metas, 0.1-1 Kb' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 100 );
		}
		yield '10 metas, 1-10 Kb' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 3000 );
		}
		yield '3 metas, 30-90 Kb' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 1000 );
		}
		yield '10 metas, 10-100 Kb' => $rows;

		$rows = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			$rows[ "key_{$i}" ] = str_repeat( '1234567890', $i * 1000 );
		}
		yield '20 metas, 10-200 Kb' => $rows;
	}
}
