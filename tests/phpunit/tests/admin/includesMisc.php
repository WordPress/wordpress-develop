<?php

/**
 * @group admin
 */
class Tests_Admin_includesMisc extends WP_UnitTestCase {
	function test_shorten_url() {
		$tests = array(
			'wordpress\.org/about/philosophy'
				=> 'wordpress\.org/about/philosophy',     // No longer strips slashes.
			'wordpress.org/about/philosophy'
				=> 'wordpress.org/about/philosophy',
			'http://wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, trailing slash.
			'http://www.wordpress.org/about/philosophy/'
				=> 'wordpress.org/about/philosophy',      // Remove http, www.
			'http://wordpress.org/about/philosophy/#box'
				=> 'wordpress.org/about/philosophy/#box',      // Don't shorten 35 characters.
			'http://wordpress.org/about/philosophy/#decisions'
				=> 'wordpress.org/about/philosophy/#&hellip;', // Shorten to 32 if > 35 after cleaning.
		);
		foreach ( $tests as $k => $v ) {
			$this->assertSame( $v, url_shorten( $k ) );
		}
	}

	function test_extract_from_markers_invalid_file() {
		$result = extract_from_markers( '', 'WordPress' );
		$this->assertEquals( array(), $result );
	}

	function test_extract_from_markers_unreadable_file() {
		$filename = tempnam( sys_get_temp_dir(), 'marker' );
		chmod( $filename, 0000 );
		$result = extract_from_markers( $filename, 'WordPress' );
		unlink( $filename );
		$this->assertEquals( array(), $result );
	}

	function test_extract_from_markers_success() {
		$filename = tempnam( sys_get_temp_dir(), 'marker' );
		$marker   = 'WordPress';
		$content  = array( 'hello', 'world' );
		file_put_contents( $filename, sprintf( "# BEGIN %1\$s\n%2\$s\n# END %1\$s", $marker, implode( "\n", $content ) ) );
		$result = extract_from_markers( $filename, $marker );
		unlink( $filename );
		$this->assertEquals( $content, $result );
	}

	function test_insert_with_markers_invalid_file() {
		$result = insert_with_markers( '', 'WordPress', 'hello' );
		$this->assertFalse( $result );
	}

	function test_insert_with_markers_non_writable_file() {
		$filename = tempnam( sys_get_temp_dir(), 'marker' );
		chmod( $filename, 0000 );
		$result = insert_with_markers( $filename, 'WordPress', 'hello' );
		unlink( $filename );
		$this->assertFalse( $result );
	}

	function test_insert_with_markers_existing_empty_file_is_success() {
		$filename = tempnam( sys_get_temp_dir(), 'marker' );
		$result   = insert_with_markers( $filename, 'WordPress', 'hello' );
		$content  = file_get_contents( $filename );
		unlink( $filename );
		$this->assertTrue( $result );
		$this->assertContains( 'hello', $content );
	}

	function test_insert_with_markers_new_file_is_success() {
		$filename = tempnam( sys_get_temp_dir(), 'marker' );
		unlink( $filename );
		$result  = insert_with_markers( $filename, 'WordPress', 'hello' );
		$content = file_get_contents( $filename );
		unlink( $filename );
		$this->assertTrue( $result );
		$this->assertContains( 'hello', $content );
	}

	function test_insert_with_markers_existing_file_is_success() {
		$filename         = tempnam( sys_get_temp_dir(), 'marker' );
		$existing_content = sha1( uniqid() );
		file_put_contents( $filename, $existing_content );
		$result  = insert_with_markers( $filename, 'WordPress', 'hello' );
		$content = file_get_contents( $filename );
		unlink( $filename );
		$this->assertTrue( $result );
		$this->assertContains( 'hello', $content );
		$this->assertContains( $existing_content, $content );
	}
}
