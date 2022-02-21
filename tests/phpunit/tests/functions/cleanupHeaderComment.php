<?php
/**
 * Test _cleanup_header_comment().
 *
 * @ticket 8497
 * @ticket 38101
 *
 * @group functions.php
 * @covers ::_cleanup_header_comment
 */
class Tests_Functions_CleanupHeaderComment extends WP_UnitTestCase {

	/**
	 * Tests _cleanup_header_comment().
	 *
	 * @dataProvider data_cleanup_header_comment
	 *
	 * @param string $test_string Test string.
	 * @param string $expected    Expected return value.
	 */
	public function test_cleanup_header_comment( $test_string, $expected ) {
		$this->assertSameIgnoreEOL( $expected, _cleanup_header_comment( $test_string ) );
	}

	/**
	 * Data provider for test_cleanup_header_comment().
	 *
	 * @return array[] Test parameters {
	 *     @type string $test_string Test string.
	 *     @type string $expected    Expected return value.
	 * }
	 */
	public function data_cleanup_header_comment() {
		return array(
			// Set 0: A string.
			array(
				'ffffffffffffff',
				'ffffffffffffff',
			),
			// Set 1: Trim a string.
			array(
				'	ffffffffffffff ',
				'ffffffffffffff',
			),
			// Set 2: Trim a full comment string.
			array(
				'<?php
/*
Plugin Name: Health Check
Plugin URI: https://wordpress.org/plugins/health-check/
Description: Checks the health of your WordPress install
Version: 0.1.0
Author: The Health Check Team
Author URI: http://health-check-team.example.com
Text Domain: health-check
Domain Path: /languages
*/
',
				'<?php
/*
Plugin Name: Health Check
Plugin URI: https://wordpress.org/plugins/health-check/
Description: Checks the health of your WordPress install
Version: 0.1.0
Author: The Health Check Team
Author URI: http://health-check-team.example.com
Text Domain: health-check
Domain Path: /languages',
			),
			// Set 3: Trim HTML following comment.
			array(
				'<?php
/*
Plugin Name: Health Check
Plugin URI: https://wordpress.org/plugins/health-check/
Description: Checks the health of your WordPress install
Version: 0.1.0
Author: The Health Check Team
Author URI: http://health-check-team.example.com
Text Domain: health-check
Domain Path: /languages
*/ ?>
dddlddfs
',
				'<?php
/*
Plugin Name: Health Check
Plugin URI: https://wordpress.org/plugins/health-check/
Description: Checks the health of your WordPress install
Version: 0.1.0
Author: The Health Check Team
Author URI: http://health-check-team.example.com
Text Domain: health-check
Domain Path: /languages
dddlddfs',
			),
			// Set 4: Trim a docblock style comment.
			array(
				'<?php
/**
 * Plugin Name: Health Check
 * Plugin URI: https://wordpress.org/plugins/health-check/
 * Description: Checks the health of your WordPress install
 * Version: 0.1.0
 * Author: The Health Check Team
 * Author URI: http://health-check-team.example.com
 * Text Domain: health-check
 * Domain Path: /languages
 */',
				'<?php
/**
 * Plugin Name: Health Check
 * Plugin URI: https://wordpress.org/plugins/health-check/
 * Description: Checks the health of your WordPress install
 * Version: 0.1.0
 * Author: The Health Check Team
 * Author URI: http://health-check-team.example.com
 * Text Domain: health-check
 * Domain Path: /languages',
			),
		);
	}
}
