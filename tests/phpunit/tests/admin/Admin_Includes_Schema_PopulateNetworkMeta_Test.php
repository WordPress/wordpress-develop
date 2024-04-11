<?php

require_once __DIR__ . '/Admin_Includes_Schema_TestCase.php';

/**
 * @group admin
 *
 * @covers ::populate_network_meta
 */
class Admin_Includes_Schema_PopulateNetworkMeta_Test extends Admin_Includes_Schema_TestCase {

	/**
	 * @ticket 44895
	 * @group multisite
	 * @dataProvider data_populate_network_meta
	 */
	public function test_populate_network_meta( $meta, $expected ) {
		global $wpdb;

		$orig_sitemeta  = $wpdb->sitemeta;
		$wpdb->sitemeta = self::$sitemeta;

		populate_network_meta( 42, $meta );

		$results = array();
		foreach ( $expected as $meta_key => $value ) {
			if ( is_multisite() ) {
				$results[ $meta_key ] = get_network_option( 42, $meta_key );
			} else {
				$results[ $meta_key ] = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $meta_key, 42 ) );
			}
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->sitemeta}" );

		$wpdb->sitemeta = $orig_sitemeta;

		$this->assertSame( $expected, $results );
	}

	public function data_populate_network_meta() {
		return array(
			array(
				array(),
				array(
					// Random meta to check.
					'registration'      => 'none',
					'blog_upload_space' => '100',
					'fileupload_maxk'   => '1500',
				),
			),
			array(
				array(
					'site_name' => 'My Great Network',
					'WPLANG'    => 'fr_FR',
				),
				array(
					// Random meta to check.
					'site_name'         => 'My Great Network',
					'registration'      => 'none',
					'blog_upload_space' => '100',
					'fileupload_maxk'   => '1500',
					'WPLANG'            => 'fr_FR',
				),
			),
			array(
				array(
					'custom_meta' => '1',
				),
				array(
					// Random meta to check.
					'custom_meta'       => '1',
					'registration'      => 'none',
					'blog_upload_space' => '100',
					'fileupload_maxk'   => '1500',
				),
			),
		);
	}
}
