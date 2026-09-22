<?php

/**
 * @group admin
 * @group upgrade
 *
 * @covers WP_Upgrader_Skin
 */
class Tests_Admin_WpUpgraderSkin extends WP_UnitTestCase {

	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
	}

	/**
	 * Calls the protected `WP_Upgrader_Skin::decrement_update_count()` method
	 * and returns whatever it printed.
	 *
	 * @param WP_Upgrader_Skin $skin The skin instance to call the method on.
	 * @param string           $type The update type to pass to the method.
	 * @return string The output of the method.
	 */
	private function decrement_update_count( WP_Upgrader_Skin $skin, $type ) {
		$method = new ReflectionMethod( $skin, 'decrement_update_count' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( $skin, $type );
		return ob_get_clean();
	}

	/**
	 * Tests that `WP_Upgrader_Skin::decrement_update_count()` prints nothing
	 * when there is no usable result.
	 *
	 * @ticket 59446
	 *
	 * @dataProvider data_results_that_should_print_nothing
	 *
	 * @covers WP_Upgrader_Skin::decrement_update_count
	 *
	 * @param mixed $result The result to set on the skin before calling the method.
	 */
	public function test_decrement_update_count_should_print_nothing_without_a_usable_result( $result ) {
		$skin         = new WP_Upgrader_Skin();
		$skin->result = $result;

		$this->assertSame( '', $this->decrement_update_count( $skin, 'plugin' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_results_that_should_print_nothing() {
		return array(
			'a false result'         => array( 'result' => false ),
			'a WP_Error result'      => array( 'result' => new WP_Error( 'test_error' ) ),
			"an 'up_to_date' result" => array( 'result' => 'up_to_date' ),
		);
	}

	/**
	 * Tests that `WP_Upgrader_Skin::decrement_update_count()` prints its data
	 * through `wp_print_inline_script_tag()`, so attributes such as a
	 * per-request nonce can be attached via the `wp_inline_script_attributes`
	 * filter.
	 *
	 * @ticket 59446
	 *
	 * @covers WP_Upgrader_Skin::decrement_update_count
	 */
	public function test_decrement_update_count_prints_inline_script_tag_with_filterable_attributes() {
		add_filter(
			'wp_inline_script_attributes',
			static function ( array $attributes ): array {
				$attributes['nonce'] = 'test-decrement-update-count-nonce';
				return $attributes;
			}
		);

		$skin         = new WP_Upgrader_Skin();
		$skin->result = true;

		$actual = $this->decrement_update_count( $skin, 'plugin' );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'SCRIPT' ), 'The expected SCRIPT tag was not printed.' );
		$this->assertSame( 'test-decrement-update-count-nonce', $processor->get_attribute( 'nonce' ), 'The nonce attribute added via wp_inline_script_attributes was not printed.' );
		$this->assertStringContainsString( 'wp.updates.decrementCount( "plugin" )', $processor->get_modifiable_text(), 'The expected JavaScript call was not printed.' );
	}

	/**
	 * Tests that `WP_Upgrader_Skin::decrement_update_count()` prints the
	 * `postMessage` variant of the script during an iframe request, still
	 * through `wp_print_inline_script_tag()`.
	 *
	 * @ticket 59446
	 *
	 * @covers WP_Upgrader_Skin::decrement_update_count
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_decrement_update_count_prints_post_message_script_during_iframe_request() {
		define( 'IFRAME_REQUEST', true );

		add_filter(
			'wp_inline_script_attributes',
			static function ( array $attributes ): array {
				$attributes['nonce'] = 'test-decrement-update-count-iframe-nonce';
				return $attributes;
			}
		);

		$skin         = new WP_Upgrader_Skin();
		$skin->result = true;

		$actual = $this->decrement_update_count( $skin, 'theme' );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'SCRIPT' ), 'The expected SCRIPT tag was not printed.' );
		$this->assertSame( 'test-decrement-update-count-iframe-nonce', $processor->get_attribute( 'nonce' ), 'The nonce attribute added via wp_inline_script_attributes was not printed.' );
		$this->assertStringContainsString( 'window.postMessage', $processor->get_modifiable_text(), 'The expected postMessage call was not printed.' );
		$this->assertStringContainsString( 'upgradeType: "theme"', $processor->get_modifiable_text(), 'The expected upgrade type was not printed.' );
	}
}
