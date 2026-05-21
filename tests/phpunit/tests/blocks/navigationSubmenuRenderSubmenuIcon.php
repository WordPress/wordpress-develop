<?php
/**
 * Tests for the deprecated block_core_navigation_submenu_render_submenu_icon() shim.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_NavigationSubmenuRenderSubmenuIcon extends WP_UnitTestCase {
	/**
	 * @ticket 65287
	 * @expectedDeprecated block_core_navigation_submenu_render_submenu_icon
	 */
	public function test_returns_same_markup_as_shared_helper() {
		$this->assertSame(
			block_core_shared_navigation_render_submenu_icon(),
			block_core_navigation_submenu_render_submenu_icon()
		);
	}
}
