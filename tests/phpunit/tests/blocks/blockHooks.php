<?php
/**
 * Tests for block hooks feature functions.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.4.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_BlockHooks extends WP_UnitTestCase {

	/**
	 * Registered block names.
	 *
	 * @var string[]
	 */
	private $registered_block_names = array();

	/**
	 * Tear down after each test.
	 *
	 * @since 6.4.0
	 */
	public function tear_down() {
		while ( ! empty( $this->registered_block_names ) ) {
			$block_name = array_pop( $this->registered_block_names );
			unregister_block_type( $block_name );
		}

		parent::tear_down();
	}

	/**
	 * @see register_block_type()
	 */
	private function register_block_type( $block_type, $args = array() ) {
		$result                         = register_block_type( $block_type, $args );
		$this->registered_block_names[] = $result->name;

		return $result;
	}

	private function switch_to_block_theme_hooked_blocks() {
		switch_theme( 'block-theme-hooked-blocks' );

		$theme_blocks_dir = wp_normalize_path( realpath( get_theme_file_path( 'blocks' ) ) );
		$this->register_block_type( $theme_blocks_dir . '/hooked-before' );
		$this->register_block_type( $theme_blocks_dir . '/hooked-after' );
		$this->register_block_type( $theme_blocks_dir . '/hooked-first-child' );
		$this->register_block_type( $theme_blocks_dir . '/hooked-last-child' );
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_no_match_found() {
		$result = get_hooked_blocks( 'tests/no-hooked-blocks' );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 */
	public function test_get_hooked_blocks_matches_found() {
		$this->register_block_type(
			'tests/injected-one',
			array(
				'block_hooks' => array(
					'tests/hooked-at-before'           => 'before',
					'tests/hooked-at-after'            => 'after',
					'tests/hooked-at-before-and-after' => 'before',
				),
			)
		);
		$this->register_block_type(
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
				'before' => array(
					'tests/injected-one',
					'tests/injected-two',
				),
			),
			get_hooked_blocks( 'tests/hooked-at-before' ),
			'block hooked at the before position'
		);
		$this->assertSame(
			array(
				'after' => array(
					'tests/injected-one',
					'tests/injected-two',
				),
			),
			get_hooked_blocks( 'tests/hooked-at-after' ),
			'block hooked at the after position'
		);
		$this->assertSame(
			array(
				'first_child' => array(
					'tests/injected-two',
				),
			),
			get_hooked_blocks( 'tests/hooked-at-first-child' ),
			'block hooked at the first child position'
		);
		$this->assertSame(
			array(
				'last_child' => array(
					'tests/injected-two',
				),
			),
			get_hooked_blocks( 'tests/hooked-at-last-child' ),
			'block hooked at the last child position'
		);
		$this->assertSame(
			array(
				'before' => array(
					'tests/injected-one',
				),
				'after'  => array(
					'tests/injected-two',
				),
			),
			get_hooked_blocks( 'tests/hooked-at-before-and-after' ),
			'block hooked before one block and after another'
		);
	}

	/**
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 * @covers ::get_block_file_template
	 */
	public function test_template_with_hooked_blocks() {
		$this->switch_to_block_theme_hooked_blocks();

		$template = get_block_file_template( get_stylesheet() . '//single' );

		$this->assertStringNotContainsString(
			'<!-- wp:tests/hooked-before /-->',
			$template->content
		);
		$this->assertStringContainsString(
			'<!-- wp:post-content {"layout":{"type":"constrained"}} /--><!-- wp:tests/hooked-after /-->',
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
	 * @ticket 59383
	 *
	 * @covers ::get_hooked_blocks
	 * @covers ::get_block_file_template
	 */
	public function test_template_part_with_hooked_blocks() {
		$this->switch_to_block_theme_hooked_blocks();

		$template = get_block_file_template( get_stylesheet() . '//header', 'wp_template_part' );

		$this->assertStringContainsString(
			'<!-- wp:tests/hooked-before /--><!-- wp:navigation {"layout":{"type":"flex","setCascadingProperties":true,"justifyContent":"right"}} /-->',
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
}
