<?php
/**
 * Unit tests covering WP_Widget_Block functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-block.php
 *
 * @group widgets
 */
class Tests_Widgets_wpWidgetBlock extends WP_UnitTestCase {

	/**
	 * Block content carrying custom CSS in its attributes.
	 *
	 * @var string
	 */
	const CONTENT_WITH_CUSTOM_CSS = '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->';

	/**
	 * Tests that custom CSS is stripped from the content for a user without edit_css.
	 *
	 * @ticket 64771
	 *
	 * @covers WP_Widget_Block::update
	 */
	public function test_update_strips_custom_css_without_edit_css() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'map_meta_cap', array( $this, 'revoke_edit_css_cap' ), 10, 2 );
		$this->assertFalse( current_user_can( 'edit_css' ) );

		$widget = new WP_Widget_Block();
		$result = $widget->update( array( 'content' => self::CONTENT_WITH_CUSTOM_CSS ), array() );
		remove_filter( 'map_meta_cap', array( $this, 'revoke_edit_css_cap' ), 10 );

		$blocks = parse_blocks( $result['content'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertArrayNotHasKey( 'style', $blocks[0]['attrs'], 'Custom CSS should be stripped for a user without edit_css.' );
		$this->assertStringContainsString( '<p>Hello</p>', $result['content'] );
	}

	/**
	 * Tests that custom CSS is kept in the content for a user with edit_css.
	 *
	 * @ticket 64771
	 *
	 * @covers WP_Widget_Block::update
	 */
	public function test_update_keeps_custom_css_with_edit_css() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'edit_css' ) );

		$widget = new WP_Widget_Block();
		$result = $widget->update( array( 'content' => self::CONTENT_WITH_CUSTOM_CSS ), array() );

		$this->assertSame( self::CONTENT_WITH_CUSTOM_CSS, $result['content'] );
	}

	/**
	 * Revoke edit_css cap via map_meta_cap.
	 *
	 * @param array  $caps    Returns the user's actual capabilities.
	 * @param string $cap     Capability name.
	 * @return array Caps.
	 */
	public function revoke_edit_css_cap( $caps, $cap ) {
		if ( 'edit_css' === $cap ) {
			$caps   = array_diff( $caps, array( 'unfiltered_html' ) );
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}
}
