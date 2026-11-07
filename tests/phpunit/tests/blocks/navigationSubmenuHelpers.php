<?php
/**
 * Tests for the helper functions of the Navigation Submenu block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_NavigationSubmenuHelpers extends WP_UnitTestCase {

	/**
	 * Tests the visibility of the submenus.
	 *
	 * @ticket 66589
	 *
	 * @covers ::block_core_navigation_submenu_get_submenu_visibility
	 *
	 * @dataProvider data_submenu_visibility
	 *
	 * @param array  $context  The context of the navigation block.
	 * @param string $expected The expected visibility.
	 */
	public function test_should_get_the_submenu_visibility( $context, $expected ) {
		$this->assertSame( $expected, block_core_navigation_submenu_get_submenu_visibility( $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_submenu_visibility() {
		return array(
			'no context'                             => array( array(), 'hover' ),
			'visibility click'                       => array( array( 'submenuVisibility' => 'click' ), 'click' ),
			'visibility always'                      => array( array( 'submenuVisibility' => 'always' ), 'always' ),
			'visibility hover'                       => array( array( 'submenuVisibility' => 'hover' ), 'hover' ),
			'null visibility'                        => array( array( 'submenuVisibility' => null ), 'hover' ),
			'deprecated attribute on'                => array( array( 'openSubmenusOnClick' => true ), 'click' ),
			'deprecated attribute off'               => array( array( 'openSubmenusOnClick' => false ), 'hover' ),
			'deprecated attribute is a string'       => array( array( 'openSubmenusOnClick' => '1' ), 'click' ),
			'deprecated attribute is empty string'   => array( array( 'openSubmenusOnClick' => '' ), 'hover' ),
			'deprecated attribute is null'           => array( array( 'openSubmenusOnClick' => null ), 'hover' ),
			'deprecated attribute is null with mode' => array(
				array(
					'openSubmenusOnClick' => null,
					'submenuVisibility'   => 'always',
				),
				'always',
			),
			'deprecated attribute on wins'           => array(
				array(
					'openSubmenusOnClick' => true,
					'submenuVisibility'   => 'always',
				),
				'click',
			),
			'deprecated attribute off wins'          => array(
				array(
					'openSubmenusOnClick' => false,
					'submenuVisibility'   => 'always',
				),
				'hover',
			),
		);
	}

	/**
	 * Tests that the deprecated function returns the submenu icon of the shared function.
	 *
	 * @ticket 66589
	 *
	 * @covers ::block_core_navigation_submenu_render_submenu_icon
	 *
	 * @expectedDeprecated block_core_navigation_submenu_render_submenu_icon
	 */
	public function test_deprecated_render_submenu_icon_should_return_the_shared_icon() {
		$icon = block_core_navigation_submenu_render_submenu_icon();

		$this->assertSame( block_core_shared_navigation_render_submenu_icon(), $icon );
		$this->assertStringStartsWith( '<svg ', $icon );
		$this->assertStringEndsWith( '</svg>', $icon );
	}
}
