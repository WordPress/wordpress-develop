<?php

require_once __DIR__ . '/Admin_Includes_Schema_TestCase.php';

/**
 * @group admin
 *
 * @covers ::populate_site_meta
 */
class Admin_Includes_Schema_PopulateSiteMeta_Test extends Admin_Includes_Schema_TestCase {

	/**
	 * @ticket 44896
	 * @group multisite
	 * @group ms-required
	 * @dataProvider data_populate_site_meta
	 */
	public function test_populate_site_meta( $meta, $expected ) {
		global $wpdb;

		$orig_blogmeta  = $wpdb->blogmeta;
		$wpdb->blogmeta = self::$blogmeta;

		populate_site_meta( 42, $meta );

		$results = array();
		foreach ( $expected as $meta_key => $value ) {
			$results[ $meta_key ] = get_site_meta( 42, $meta_key, true );
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->blogmeta}" );

		$wpdb->blogmeta = $orig_blogmeta;

		$this->assertSame( $expected, $results );
	}

	public function data_populate_site_meta() {
		return array(
			array(
				array(),
				array(
					'unknown_value' => '',
				),
			),
			array(
				array(
					'custom_meta' => '1',
				),
				array(
					'custom_meta' => '1',
				),
			),
		);
	}
}
