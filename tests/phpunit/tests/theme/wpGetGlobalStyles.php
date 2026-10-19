<?php

/**
 * Tests wp_get_global_styles().
 *
 * @group themes
 *
 * @covers ::wp_get_global_styles
 */
class Tests_Theme_wpGetGlobalStyles extends WP_UnitTestCase {

	public function tear_down(): void {
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	/**
	 * @ticket 66280
	 */
	public function test_reflects_blocks_registered_after_a_previous_call(): void {
		wp_get_global_styles();

		register_block_type(
			'test/block-gap',
			array(
				'supports' => array(
					'__experimentalStyle' => array(
						'spacing' => array( 'blockGap' => '77px' ),
					),
				),
			)
		);

		$styles = wp_get_global_styles();

		unregister_block_type( 'test/block-gap' );

		$this->assertSame(
			'77px',
			$styles['blocks']['test/block-gap']['spacing']['blockGap'] ?? null,
			'Styles for a block registered after a previous call should be present.'
		);
	}
}
