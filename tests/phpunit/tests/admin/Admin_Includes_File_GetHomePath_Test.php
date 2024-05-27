<?php

/**
 * @group file
 * @group admin
 *
 * @covers ::get_home_path
 */
class Admin_Includes_File_GetHomePath_Test extends WP_UnitTestCase {

	/**
	 * @ticket 20449
	 */
	public function test_get_home_path() {
		$home    = get_option( 'home' );
		$siteurl = get_option( 'siteurl' );
		$sfn     = $_SERVER['SCRIPT_FILENAME'];
		$this->assertSame( str_replace( '\\', '/', ABSPATH ), get_home_path() );

		update_option( 'home', 'http://localhost' );
		update_option( 'siteurl', 'http://localhost/wp' );

		$_SERVER['SCRIPT_FILENAME'] = 'D:\root\vhosts\site\httpdocs\wp\wp-admin\options-permalink.php';
		$this->assertSame( 'D:/root/vhosts/site/httpdocs/', get_home_path() );

		$_SERVER['SCRIPT_FILENAME'] = '/Users/foo/public_html/trunk/wp/wp-admin/options-permalink.php';
		$this->assertSame( '/Users/foo/public_html/trunk/', get_home_path() );

		$_SERVER['SCRIPT_FILENAME'] = 'S:/home/wordpress/trunk/wp/wp-admin/options-permalink.php';
		$this->assertSame( 'S:/home/wordpress/trunk/', get_home_path() );

		update_option( 'home', $home );
		update_option( 'siteurl', $siteurl );
		$_SERVER['SCRIPT_FILENAME'] = $sfn;
	}
}
