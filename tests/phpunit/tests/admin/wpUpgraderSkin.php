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
	 * @param non-falsy-string $type The update type to pass to the method.
	 * @return string The output of the method.
	 */
	private function decrement_update_count( WP_Upgrader_Skin $skin, string $type ): string {
		$method = new ReflectionMethod( $skin, 'decrement_update_count' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return get_echo( array( $method, 'invoke' ), array( $skin, $type ) );
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
		$skin->result = $result; // @phpstan-ignore assign.propertyType (Invalid assignment on purpose to test.)

		$this->assertSame( '', $this->decrement_update_count( $skin, 'plugin' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ result: mixed }>
	 */
	public function data_results_that_should_print_nothing(): array {
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
	 * filter, and that the update type is JSON-encoded.
	 *
	 * @ticket 59446
	 *
	 * @dataProvider data_update_types
	 *
	 * @covers WP_Upgrader_Skin::decrement_update_count
	 *
	 * @param non-falsy-string $type The update type to pass to the method.
	 */
	public function test_decrement_update_count_prints_inline_script_tag_with_filterable_attributes( string $type ) {
		$nonce = 'test-decrement-update-count-nonce';
		add_filter(
			'wp_inline_script_attributes',
			static function ( array $attributes ) use ( $nonce ): array {
				$attributes['nonce'] = $nonce;
				return $attributes;
			}
		);

		$skin         = new WP_Upgrader_Skin();
		$skin->result = true;

		$actual = $this->decrement_update_count( $skin, $type );

		$processor = new WP_HTML_Tag_Processor( $actual );
		$this->assertTrue( $processor->next_tag( 'SCRIPT' ), 'The expected SCRIPT tag was not printed.' );
		$this->assertSame( $nonce, $processor->get_attribute( 'nonce' ), 'The nonce attribute added via wp_inline_script_attributes was not printed.' );

		$script_text = $processor->get_modifiable_text();
		$this->assertStringContainsString( 'wp.updates.decrementCount( upgradeType )', $script_text, 'The expected JavaScript call was not printed.' );
		$this->assertStringEndsWith(
			')( ' . wp_json_encode( $type, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ' );',
			trim( $script_text ),
			'The upgrade type was not passed as the JSON-encoded argument.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ type: non-falsy-string }>
	 */
	public function data_update_types(): array {
		return array(
			'a plugin type'      => array( 'type' => 'plugin' ),
			'a theme type'       => array( 'type' => 'theme' ),
			'a translation type' => array( 'type' => 'translation' ),
		);
	}
}
