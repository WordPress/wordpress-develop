<?php
/**
 * WP_Fake_Block_Type for testing
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 */

/**
 * Test class extending WP_Block_Type
 *
 * @since 5.0.0
 */
class WP_Fake_Block_Type extends WP_Block_Type {

	/**
	 * Render the fake block.
	 *
	 * @param array  $attributes Optional. Block attributes. Default empty array.
	 * @param string $content    Optional. Block content. Default empty string.
	 * @return string Rendered block HTML.
	 */
	public function render( $attributes = array(), $content = '' ) {
		return '<div>' . $content . '</div>';
	}
}
