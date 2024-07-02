<?php
/**
 * Tests for the features using get_hooked_blocks_by_anchor_block function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.7.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_GetHookedBlocksByAnchorBlock extends WP_UnitTestCase {

	const TEST_THEME_NAME = 'block-theme-with-hooked-blocks';

	/**
	 * Tear down after each test.
	 *
	 * @since 6.7.0
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

	private static function create_block_template_object() {
		$template              = new WP_Block_Template();
		$template->type        = 'wp_template';
		$template->theme       = 'test-theme';
		$template->slug        = 'single';
		$template->id          = $template->theme . '//' . $template->slug;
		$template->title       = 'Single';
		$template->content     = '<!-- wp:tests/anchor-block /-->';
		$template->description = 'Description of my template';

		return $template;
	}

	/**
	 * @ticket 60769
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 */
	public function test_get_hooked_blocks_by_anchor_block_no_match_found() {
		$result = get_hooked_blocks_by_anchor_block( 'core/test-block', 'before', array() );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 60769
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 */
	public function test_get_hooked_blocks_by_anchor_block_matches_found_block_json() {
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
			array( 'tests/injected-one', 'tests/injected-two' ),
			get_hooked_blocks_by_anchor_block( 'tests/hooked-at-before', 'before', array() )
		);

		$this->assertSame(
			array( 'tests/injected-two' ),
			get_hooked_blocks_by_anchor_block( 'tests/hooked-at-first-child', 'first_child', array() )
		);

		$this->assertSame(
			array( 'tests/injected-two' ),
			get_hooked_blocks_by_anchor_block( 'tests/hooked-at-last-child', 'last_child', array() )
		);

		$this->assertSame(
			array( 'tests/injected-two' ),
			get_hooked_blocks_by_anchor_block( 'tests/hooked-at-before-and-after', 'after', array() )
		);
	}

	/**
	 * @ticket 60769
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 */
	public function test_get_hooked_blocks_by_anchor_block_matches_found_filter() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
			if (
				! $context instanceof WP_Block_Template ||
				! property_exists( $context, 'slug' ) ||
				'single' !== $context->slug
			) {
				return $hooked_block_types;
			}

			if ( 'tests/anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block-added-by-filter';
			}

			return $hooked_block_types;
		};

		$template = self::create_block_template_object();

		add_filter( 'hooked_block_types', $filter, 10, 4 );
		$hooked_blocks = get_hooked_blocks_by_anchor_block( 'tests/anchor-block', 'after', $template );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame( array( 'tests/hooked-block-added-by-filter' ), $hooked_blocks );
	}

	/**
	 * @ticket 60769
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 */
	public function test_get_hooked_blocks_by_anchor_block_corrupt_filter() {
		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
			$hooked_block_types = 'corrupt';
			return $hooked_block_types;
		};

		$template = self::create_block_template_object();

		add_filter( 'hooked_block_types', $filter, 10, 4 );
		$hooked_blocks = get_hooked_blocks_by_anchor_block( 'tests/anchor-block', 'after', $template );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame( array(), $hooked_blocks );
	}

	/**
	 * @ticket 60769
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 */
	public function test_get_hooked_blocks_by_anchor_block_matches_found_filter_and_block_json() {
		register_block_type(
			'tests/block-json-block',
			array(
				'block_hooks' => array(
					'tests/anchor-block' => 'after',
				),
			)
		);

		$filter = function ( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
			if (
				! $context instanceof WP_Block_Template ||
				! property_exists( $context, 'slug' ) ||
				'single' !== $context->slug
			) {
				return $hooked_block_types;
			}

			if ( 'tests/anchor-block' === $anchor_block_type && 'after' === $relative_position ) {
				$hooked_block_types[] = 'tests/hooked-block-added-by-filter';
			}

			return $hooked_block_types;
		};

		$template = self::create_block_template_object();

		add_filter( 'hooked_block_types', $filter, 10, 4 );
		$hooked_blocks = get_hooked_blocks_by_anchor_block( 'tests/anchor-block', 'after', $template );
		remove_filter( 'hooked_block_types', $filter, 10 );

		$this->assertSame( array( 'tests/block-json-block', 'tests/hooked-block-added-by-filter' ), $hooked_blocks );
	}

	/**
	 * @ticket 60769
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
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
	 * @ticket 60769
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
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
	 * @ticket 60769
	 * @ticket 59313
	 * @ticket 60008
	 * @ticket 60506
	 *
	 * @covers ::get_hooked_blocks_by_anchor_block
	 * @covers ::get_hooked_blocks
	 * @covers ::get_block_file_template
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
