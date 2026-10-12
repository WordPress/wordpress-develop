<?php

/**
 * @group block-supports
 *
 */
class Tests_Block_Supports_WpStripCustomCssFromBlocks extends WP_UnitTestCase {

	/**
	 * Tests that style.css is stripped from block attributes.
	 *
	 * @ticket 64771
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @dataProvider data_strips_css_from_blocks
	 *
	 * @param string $content  Post content containing blocks.
	 * @param string $message  Assertion message.
	 */
	public function test_strips_css_from_blocks( $content, $message ) {
		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'] ?? array(), $message );
		$this->assertArrayNotHasKey( 'style', $blocks[0]['attrs'] ?? array(), 'style key should be fully removed when css was the only property.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strips_css_from_blocks() {
		return array(
			'single block' => array(
				'content' => '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->',
				'message' => 'style.css should be stripped from block attributes.',
			),
		);
	}

	/**
	 * Tests that style.css is stripped from nested inner blocks.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_strips_css_from_inner_blocks() {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$inner_block = $blocks[0]['innerBlocks'][0];
		$this->assertArrayNotHasKey( 'css', $inner_block['attrs']['style'] ?? array(), 'style.css should be stripped from inner block attributes.' );
	}

	/**
	 * Tests that content without blocks is returned unchanged.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_returns_non_block_content_unchanged() {
		$content = '<p>This is plain HTML content with no blocks.</p>';

		$result = wp_strip_custom_css_from_blocks( $content );

		$this->assertSame( $content, $result, 'Non-block content should be returned unchanged.' );
	}

	/**
	 * Tests that content without style.css attributes is returned unchanged.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_returns_unchanged_when_no_css_attributes() {
		$content = '<!-- wp:paragraph {"style":{"color":{"text":"#ff0000"}}} --><p class="has-text-color" style="color:#ff0000">Hello</p><!-- /wp:paragraph -->';

		$result = wp_strip_custom_css_from_blocks( $content );

		$this->assertSame( $content, $result, 'Content without style.css attributes should be returned unchanged.' );
	}

	/**
	 * Tests that other style properties are preserved when css is stripped.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_preserves_other_style_properties() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;","color":{"text":"#ff0000"}}} --><p>Hello</p><!-- /wp:paragraph -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'], 'style.css should be stripped.' );
		$this->assertSame( '#ff0000', $blocks[0]['attrs']['style']['color']['text'], 'Other style properties should be preserved.' );
	}

	/**
	 * Tests that empty style object is cleaned up after stripping css.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_cleans_up_empty_style_object() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->';

		$result = wp_unslash( wp_strip_custom_css_from_blocks( $content ) );
		$blocks = parse_blocks( $result );

		$this->assertArrayNotHasKey( 'style', $blocks[0]['attrs'], 'Empty style object should be cleaned up after stripping css.' );
	}

	/**
	 * Tests that slashed content is handled correctly.
	 *
	 * @covers ::wp_strip_custom_css_from_blocks
	 * @ticket 64771
	 */
	public function test_handles_slashed_content() {
		$content = '<!-- wp:paragraph {"style":{"css":"color: red;"}} --><p>Hello</p><!-- /wp:paragraph -->';
		$slashed = wp_slash( $content );

		$result = wp_strip_custom_css_from_blocks( $slashed );
		$blocks = parse_blocks( wp_unslash( $result ) );

		$this->assertArrayNotHasKey( 'css', $blocks[0]['attrs']['style'] ?? array(), 'style.css should be stripped even from slashed content.' );
	}

	/**
	 * Tests that the content_save_pre filter is added for a user without edit_css.
	 *
	 * @ticket 64771
	 *
	 * @covers ::wp_custom_css_kses_init
	 * @covers ::wp_custom_css_kses_init_filters
	 */
	public function test_filter_added_for_user_without_edit_css() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );
		wp_custom_css_kses_init();

		$this->assertSame( 8, has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'content_save_pre filter should be added at priority 8 for users without edit_css.' );
		$this->assertSame( 8, has_filter( 'content_filtered_save_pre', 'wp_strip_custom_css_from_blocks' ), 'content_filtered_save_pre filter should be added at priority 8 for users without edit_css.' );

		wp_set_current_user( 0 );
		wp_custom_css_remove_filters();
	}

	/**
	 * Tests that the content_save_pre filter is not added for a user with edit_css.
	 *
	 * @ticket 64771
	 *
	 * @covers ::wp_custom_css_kses_init
	 * @covers ::wp_custom_css_remove_filters
	 */
	public function test_filter_not_added_for_user_with_edit_css() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );
		wp_custom_css_kses_init();

		$this->assertFalse( has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'content_save_pre filter should not be added for users with edit_css.' );
		$this->assertFalse( has_filter( 'content_filtered_save_pre', 'wp_strip_custom_css_from_blocks' ), 'content_filtered_save_pre filter should not be added for users with edit_css.' );

		if ( is_multisite() ) {
			revoke_super_admin( $admin_id );
		}
		wp_set_current_user( 0 );
		wp_custom_css_remove_filters();
	}

	/**
	 * Tests that switching to a user with edit_css removes the filter via the set_current_user action.
	 *
	 * wp_custom_css_kses_init() is hooked to set_current_user, so wp_set_current_user()
	 * alone should update the filter state without a manual call.
	 *
	 * @ticket 64771
	 *
	 * @covers ::wp_custom_css_kses_init
	 */
	public function test_set_current_user_action_triggers_reinit() {
		$admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		// Switching to a user without edit_css should add the filter via the set_current_user action.
		wp_set_current_user( $author_id );
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'Filter should be active for user without edit_css.' );

		// Switching to a user with edit_css should remove the filter via the set_current_user action.
		wp_set_current_user( $admin_id );
		$this->assertFalse( has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'Filter should be removed after switching to a user with edit_css.' );

		if ( is_multisite() ) {
			revoke_super_admin( $admin_id );
		}
		wp_set_current_user( 0 );
		wp_custom_css_remove_filters();
	}

	/**
	 * Tests that the filter is enabled during import regardless of user capability.
	 *
	 * @ticket 64771
	 *
	 * @covers ::wp_custom_css_force_filtered_html_on_import_filter
	 */
	public function test_force_filtered_html_on_import_enables_filter_for_privileged_user() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );
		wp_custom_css_kses_init();

		$this->assertFalse( has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'Filter should not be active for admin before import.' );

		apply_filters( 'force_filtered_html_on_import', true );

		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_strip_custom_css_from_blocks' ), 'Filter should be enabled during import regardless of user capability.' );

		if ( is_multisite() ) {
			revoke_super_admin( $admin_id );
		}
		wp_set_current_user( 0 );
		wp_custom_css_remove_filters();
	}
}
