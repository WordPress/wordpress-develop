<?php
/**
 * Tests for the features using get_hooked_blocks function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.4.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_GetHookedBlocks extends WP_UnitTestCase {

	const TEST_THEME_NAME = 'block-theme-with-hooked-blocks';

	/**
	 * Tear down after each test.
	 *
	 * @since 6.4.0
	 */
	public function tear_down() {
		// Removes test block types registered by test cases.
		$block_types = WP_Block_Type_Registry::get_instance()->get_all_registered();
		foreach ( $block_types as $block_type ) {
			$block_name = $block_type->name;
			if ( str_starts_with( $block_name, 'tests/' ) ) {
				unregister_block_type( $block_name );
			}
		}

		// Removes test block patterns registered with the test theme.
		$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		foreach ( $patterns as $pattern ) {
			if ( empty( $pattern['slug'] ) ) {
				continue;
			}
			$pattern_name = $pattern['slug'];
			if ( str_starts_with( $pattern_name, self::TEST_THEME_NAME ) ) {
				unregister_block_pattern( $pattern_name );
			}
		}

		parent::tear_down();
	}

	private function switch_to_block_theme_hooked_blocks() {
		switch_theme( self::TEST_THEME_NAME );

		_register_theme_block_patterns();

		$theme_blocks_dir = wp_normalize_path( realpath( get_theme_file_path( 'blocks' ) ) );
		register_block_type( $theme_blocks_dir . '/hooked-before' );
		register_block_type( $theme_blocks_dir . '/hooked-after' );
		register_block_type( $theme_blocks_dir . '/hooked-first-child' );
		register_block_type( $theme_blocks_dir . '/hooked-last-child' );
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_no_match_found() {
		$result = get_hooked_blocks();

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_matches_found() {
		register_block_type(
			'tests/injected-one',
			array(
				'block_hooks' => array(
					'tests/hooked-at-before'           => 'before',
					'tests/hooked-at-after'            => 'after',
					'tests/hooked-at-before-and-after' => 'before',
				),
			)
		);
		register_block_type(
			'tests/injected-two',
			array(
				'block_hooks' => array(
					'tests/hooked-at-before'           => 'before',
					'tests/hooked-at-after'            => 'after',
					'tests/hooked-at-before-and-after' => 'after',
					'tests/hooked-at-first-child'      => 'first_child',
					'tests/hooked-at-last-child'       => 'last_child',
				),
			)
		);

		$this->assertSame(
			array(
				'tests/hooked-at-before'           => array(
					'before' => array(
						'tests/injected-one',
						'tests/injected-two',
					),
				),
				'tests/hooked-at-after'            => array(
					'after' => array(
						'tests/injected-one',
						'tests/injected-two',
					),
				),
				'tests/hooked-at-before-and-after' => array(
					'before' => array(
						'tests/injected-one',
					),
					'after'  => array(
						'tests/injected-two',
					),
				),
				'tests/hooked-at-first-child'      => array(
					'first_child' => array(
						'tests/injected-two',
					),
				),
				'tests/hooked-at-last-child'       => array(
					'last_child' => array(
						'tests/injected-two',
					),
				),
			),
			get_hooked_blocks()
		);
	}

	/**
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks
	 * @covers ::get_block_file_template
	 */
	public function test_loading_template_with_hooked_blocks() {
		$this->switch_to_block_theme_hooked_blocks();

		$template = get_block_file_template( get_stylesheet() . '//single' );

		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-before /-->',
			$template->content
		);
		$this->assertStringContainsString(
			'<!-- wp:post-content {"layout":{"type":"constrained"},"metadata":{"ignoredHookedBlocks":["tests/hooked-after"]}} /-->'
			. '<!-- wp:tests/hooked-after /-->',
			$template->content
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-first-child /-->',
			$template->content
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-last-child /-->',
			$template->content
		);
	}

	/**
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks
	 * @covers ::get_block_file_template
	 */
	public function test_loading_template_part_with_hooked_blocks() {
		$this->switch_to_block_theme_hooked_blocks();

		$template = get_block_file_template( get_stylesheet() . '//header', 'wp_template_part' );

		$this->assertStringContainsString(
			'<!-- wp:tests/hooked-before /-->'
			. '<!-- wp:navigation {"layout":{"type":"flex","setCascadingProperties":true,"justifyContent":"right"},"metadata":{"ignoredHookedBlocks":["tests/hooked-before"]}} /-->',
			$template->content
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-after /-->',
			$template->content
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-first-child /-->',
			$template->content
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-last-child /-->',
			$template->content
		);
	}

	/**
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks
	 * @covers WP_Block_Patterns_Registry::get_registered
	 */
	public function test_loading_pattern_with_hooked_blocks() {
		$this->switch_to_block_theme_hooked_blocks();

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(
			get_stylesheet() . '/hidden-comments'
		);

		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-before /-->',
			$pattern['content']
		);
		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-after /-->',
			$pattern['content']
		);
		$this->assertStringContainsString(
			'<!-- wp:comments {"metadata":{"ignoredHookedBlocks":["tests/hooked-first-child"]}} -->'
			. '<div class="wp-block-comments">'
			. '<!-- wp:tests/hooked-first-child /-->',
			str_replace( array( "\n", "\t" ), '', $pattern['content'] )
		);
		$this->assertStringContainsString(
			'<!-- wp:tests/hooked-last-child /-->'
			. '<!-- /wp:comment-template -->',
			str_replace( array( "\n", "\t" ), '', $pattern['content'] )
		);
	}
}
