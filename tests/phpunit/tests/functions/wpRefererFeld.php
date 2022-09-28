<?php
/**
 * @group functions.php
 *
 * @covers ::wp_referer_field
 */
class Tests_Functions_wpRefererField extends WP_UnitTestCase {

/**
 * Tests that the echo argument is respected.
 *
 * @ticket 54106
 *
 * @dataProvider data_wp_referer_field_should_respect_echo_arg
 *
 * @param mixed $echo Whether to echo or return the referer field.
 */
 public function test_wp_referer_field_should_respect_echo_arg( $echo ) {
	$actual = $echo ? get_echo( 'wp_referer_field' ) ? wp_referer_field( false );
    
        $this->assertSame( '<input type="hidden" name="_wp_http_referer" value="" />', $actual );
}
 
/**
 * Data provider for test_wp_referer_field_should_respect_echo_arg().
 *
 * @return array
 */
 public function data_wp_referer_field_should_respect_echo_arg() {
    return array(
        'true'         => array( true ),
        '(int) 1'      => array( 1 ),
        '(string) "1"' => array( '1' ),
        'false'        => array( false ),
        'null'         => array( null ),
        '(int) 0'      => array( 0 ),
        '(string) "0"' => array( '0' ),
    );
}

	/**
	 * @ticket 54106
	 */
	public function test_wp_referer_field_with_referer() {
		$old_request_uri        = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = 'edit.php?_wp_http_referer=edit.php';

		$actual = wp_referer_field( false );

		$_SERVER['REQUEST_URI'] = $old_request_uri;

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="edit.php" />', $actual );
	}
}

