<?php
/**
 * @group block-supports
 *
 * @covers ::_wp_add_block_level_preset_styles
 */

class Tests_Block_Supports_Settings extends WP_UnitTestCase {

	private ?string $test_block_name     = null;
	private ?string $original_stylesheet = null;

	public function set_up() {
		parent::set_up();
		$this->original_stylesheet = get_stylesheet();
		switch_theme( 'block-theme' );
	}

	public function tear_down() {
		if ( null !== $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;
		if ( null !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}
		parent::tear_down();
	}

	/**
	 * An earlier non-null filter result must bypass the block renderer unchanged.
	 * A null result must continue through the normal rendering path.
	 *
	 * @ticket 59551
	 * @dataProvider data_preserve_pre_rendered_content
	 *
	 * @param bool                 $supports_settings Whether the block supports preset settings.
	 * @param array<string, mixed> $settings          The block's preset settings.
	 * @param string|null          $pre_render        The earlier filter's render result.
	 */
	public function test_preserve_pre_rendered_content( $supports_settings, $settings, $pre_render ) {
		$theme = wp_get_theme();
		$this->assertTrue( wp_is_block_theme(), "'{$theme->name}' is not a block theme: check test setup." );

		$this->test_block_name = 'test/settings-pre-render';

		register_block_type(
			$this->test_block_name,
			array(
				'api_version'     => 3,
				'attributes'      => array( 'settings' => array( 'type' => 'object' ) ),
				'supports'        => array( '__experimentalSettings' => $supports_settings ),
				'render_callback' => static function () {
					return '<div>Rendered settings block</div>';
				},
			)
		);

		$block_content = get_comment_delimited_block_content(
			$this->test_block_name,
			$settings ? array( 'settings' => $settings ) : array(),
			''
		);

		// Compare the real rendering path before and after adding the earlier filter.
		$normal_content = do_blocks( $block_content );

		$pre_render_filter = function ( $content, $parsed_block ) use ( $pre_render ) {
			if ( $this->test_block_name !== $parsed_block['blockName'] ) {
				return $content;
			}
			return $pre_render;
		};
		add_filter( 'pre_render_block', $pre_render_filter, 9, 2 );

		$actual = do_blocks( $block_content );
		$this->assertSame( null === $pre_render ? $normal_content : $pre_render, $actual );
	}

	/**
	 * @return array<non-falsy-string, array{ 0: bool, 1: array<string, mixed>, 2: string|null }>
	 */
	public static function data_preserve_pre_rendered_content(): array {
		$settings = array(
			'color' => array(
				'palette' => array(
					'custom' => array(
						array(
							'name'  => 'Test settings pre-render',
							'slug'  => 'test-settings-pre-render',
							'color' => '#123abc',
						),
					),
				),
			),
		);
		$branches = array(
			'unsupported block' => array( false, $settings ),
			'no settings'       => array( true, array() ),
			'preset settings'   => array( true, $settings ),
		);
		$results  = array(
			'empty string'  => '',
			'zero string'   => '0',
			'cached markup' => '<div>Cached settings block</div>',
			'normal render' => null,
		);
		$cases    = array();
		foreach ( $branches as $branch_name => $branch ) {
			foreach ( $results as $result_name => $pre_render ) {
				$cases[ $branch_name . ', ' . $result_name ] = array( $branch[0], $branch[1], $pre_render );
			}
		}
		return $cases;
	}
}
