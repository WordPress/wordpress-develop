<?php

/**
 * just make sure the test framework is working
 *
 * @group testsuite
 */
class Tests_Basic extends WP_UnitTestCase {

	public function test_license() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$license = file_get_contents( ABSPATH . 'license.txt' );
		preg_match( '#Copyright 2011-(\d+) by the contributors#', $license, $matches );
		$this_year = gmdate( 'Y' );
		$this->assertSame( $this_year, trim( $matches[1] ), "license.txt's year needs to be updated to $this_year." );
	}

	public function test_security_md() {
		// This test is designed to only run on trunk/master.
		$this->skipOnAutomatedBranches();

		$security = file_get_contents( dirname( ABSPATH ) . '/SECURITY.md' );
		preg_match( '#\d.\d.x#', $security, $matches );
		$current_version = substr( $GLOBALS['wp_version'], 0, 3 );
		$latest_stable   = sprintf( '%s.x', (float) $current_version - 0.1 );
		$this->assertSame( $latest_stable, trim( $matches[0] ), "SECURITY.md's version needs to be updated to $latest_stable." );
	}

	public function test_package_json() {
		$package_json    = file_get_contents( dirname( ABSPATH ) . '/package.json' );
		$package_json    = json_decode( $package_json, true );
		list( $version ) = explode( '-', $GLOBALS['wp_version'] );
		// package.json uses x.y.z, so fill cleaned $wp_version for .0 releases.
		if ( 1 === substr_count( $version, '.' ) ) {
			$version .= '.0';
		}
		$this->assertSame( $version, $package_json['version'], "package.json's version needs to be updated to $version." );
		return $package_json;
	}

	/**
	 * @depends test_package_json
	 */
	public function test_package_json_node_engine( $package_json ) {
		$this->assertArrayHasKey( 'engines', $package_json );
		$this->assertArrayHasKey( 'node', $package_json['engines'] );
	}

	// Test some helper utility functions.

	public function test_strip_ws() {
		$this->assertSame( '', strip_ws( '' ) );
		$this->assertSame( 'foo', strip_ws( 'foo' ) );
		$this->assertSame( '', strip_ws( "\r\n\t  \n\r\t" ) );

		$in  = "asdf\n";
		$in .= "asdf asdf\n";
		$in .= "asdf     asdf\n";
		$in .= "\tasdf\n";
		$in .= "\tasdf\t\n";
		$in .= "\t\tasdf\n";
		$in .= "foo bar\n\r\n";
		$in .= "foo\n";

		$expected  = "asdf\n";
		$expected .= "asdf asdf\n";
		$expected .= "asdf     asdf\n";
		$expected .= "asdf\n";
		$expected .= "asdf\n";
		$expected .= "asdf\n";
		$expected .= "foo bar\n";
		$expected .= 'foo';

		$this->assertSame( $expected, strip_ws( $in ) );

	}

	public function test_mask_input_value() {
		$in = <<<EOF
<h2>Assign Authors</h2>
<p>To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as <code>admin</code>s entries.</p>
<p>If a new user is created by WordPress, the password will be set, by default, to "changeme". Quite suggestive, eh? ;)</p>
        <ol id="authors"><form action="?import=wordpress&amp;step=2&amp;id=" method="post"><input type="hidden" name="_wpnonce" value="855ae98911" /><input type="hidden" name="_wp_http_referer" value="wp-test.php" /><li>Current author: <strong>Alex Shiels</strong><br />Create user  <input type="text" value="Alex Shiels" name="user[]" maxlength="30"> <br /> or map to existing<select name="userselect[0]">
EOF;
		// _wpnonce value should be replaced with 'xxx'.
		$expected = <<<EOF
<h2>Assign Authors</h2>
<p>To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as <code>admin</code>s entries.</p>
<p>If a new user is created by WordPress, the password will be set, by default, to "changeme". Quite suggestive, eh? ;)</p>
        <ol id="authors"><form action="?import=wordpress&amp;step=2&amp;id=" method="post"><input type="hidden" name="_wpnonce" value="***" /><input type="hidden" name="_wp_http_referer" value="wp-test.php" /><li>Current author: <strong>Alex Shiels</strong><br />Create user  <input type="text" value="Alex Shiels" name="user[]" maxlength="30"> <br /> or map to existing<select name="userselect[0]">
EOF;
		$this->assertSame( $expected, mask_input_value( $in ) );
	}
}
