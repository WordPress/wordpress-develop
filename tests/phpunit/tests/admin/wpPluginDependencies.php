<?php

/**
 * Unit tests for  `class WP_Plugin_Dependencies`.
 *
 * group: admin
 */

use WP_Plugin_Dependencies as Dependencies;

/**
 * Sample test case.
 */
class wpPluginDependencies extends WP_UnitTestCase {

	/**
	 * Test data.
	 *
	 * @return void
	 */
	public function slug_data() {
		return array(
			array( array( 'test' => array( 'RequiresPlugins' => 'hello-dolly' ) ), array( 'hello-dolly' ) ),
			array( array( 'test2' => array( 'RequiresPlugins' => 'hello-dolly, woocommerce' ) ), array( 'hello-dolly', 'woocommerce' ) ),
			array( array( 'test3' => array( 'RequiresPlugins' => 'woocommerce, hello-dolly' ) ), array( 'hello-dolly', 'woocommerce' ) ),
			array( array( 'test4' => array( 'RequiresPlugins' => 'hello-dolly,gutenberg,  "junk-list , here for test", 435_bad' ) ), array( 'gutenberg', 'hello-dolly' ) ),
		);
	}

	/**
	 * @dataProvider slug_data
	 *
	 * @return void
	 */
	public function test_slug_sanitization( $headers, $expected ) {
		$actual = ( new Dependencies() )->sanitize_required_headers( $headers );
		$this->assertSame( $expected, $actual );
	}

}
