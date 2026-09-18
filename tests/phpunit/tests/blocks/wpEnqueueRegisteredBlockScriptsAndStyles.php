<?php
/**
 * Tests for wp_enqueue_registered_block_scripts_and_styles().
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 */

/**
 * Tests for wp_enqueue_registered_block_scripts_and_styles().
 */
class Tests_Blocks_WpEnqueueRegisteredBlockScriptsAndStyles extends WP_UnitTestCase {

	/**
	 * Original script modules instance.
	 *
	 * @var WP_Script_Modules
	 */
	private $original_script_modules;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		global $wp_script_modules;
		$this->original_script_modules = $wp_script_modules;
		$wp_script_modules             = null;
	}

	/**
	 * Save the original script modules instance.
	 */
	public function tear_down() {
		global $wp_script_modules;
		$wp_script_modules = $this->original_script_modules;

		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'test/block' ) ) {
			$registry->unregister( 'test/block' );
		}

		remove_filter( 'should_load_block_assets_on_demand', '__return_false' );

		parent::tear_down();
	}

	/**
	 * Tests that view script modules are enqueued when block assets are not loaded on demand.
	 *
	 * @ticket 64812
	 */
	public function test_view_script_modules_enqueued_when_not_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		$module_id = 'test-module';

		wp_register_script_module( $module_id, '/test.js' );

		register_block_type(
			'test/block',
			array(
				'view_script_module_ids' => array( $module_id ),
			)
		);

		wp_enqueue_registered_block_scripts_and_styles();

		$this->assertContains(
			$module_id,
			wp_script_modules()->get_queue(),
			'Expected view script module to be enqueued.'
		);
	}
}
