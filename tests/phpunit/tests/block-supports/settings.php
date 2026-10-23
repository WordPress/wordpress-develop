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
	 * Preset CSS must still be enqueued when that markup uses the block's settings.
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
		$render_calls          = 0;
		$filter_calls          = 0;

		register_block_type(
			$this->test_block_name,
			array(
				'api_version'     => 3,
				'attributes'      => array( 'settings' => array( 'type' => 'object' ) ),
				'supports'        => array( '__experimentalSettings' => $supports_settings ),
				'render_callback' => static function () use ( &$render_calls ) {
					++$render_calls;
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
		$this->assertSame( 1, $render_calls );
		$render_calls = 0;

		$pre_render_filter = function ( $content, $parsed_block ) use ( $pre_render, &$filter_calls ) {
			if ( $this->test_block_name !== $parsed_block['blockName'] ) {
				return $content;
			}
			++$filter_calls;
			$this->assertNull( $content );
			return $pre_render;
		};
		add_filter( 'pre_render_block', $pre_render_filter, 9, 2 );

		$style_hooks = array();
		foreach ( array( 'wp_head', 'wp_footer' ) as $hook_name ) {
			$style_hooks[ $hook_name ] = isset( $GLOBALS['wp_filter'][ $hook_name ] )
				? clone $GLOBALS['wp_filter'][ $hook_name ]
				: null;
		}

		$actual = do_blocks( $block_content );
		$this->assertSame( null === $pre_render ? $normal_content : $pre_render, $actual );
		$this->assertSame( null === $pre_render ? 1 : 0, $render_calls );
		$this->assertSame( 1, $filter_calls );

		$styles = $this->get_added_style_output( $style_hooks );
		if ( $supports_settings && $settings ) {
			foreach ( $settings['color']['palette']['custom'] as $preset ) {
				$this->assertStringContainsString( '--wp--preset--color--' . $preset['slug'], $styles );
				$this->assertStringContainsString( $preset['color'], $styles );
				$this->assertStringContainsString( '.has-' . $preset['slug'] . '-color', $styles );
			}
		} else {
			$this->assertSame( '', $styles );
		}
	}

	/**
	 * Captures the real style callbacks added by the rendered block only.
	 *
	 * @param array $original_hooks Style hooks before rendering.
	 * @return string Newly enqueued stylesheet output.
	 */
	private function get_added_style_output( $original_hooks ) {
		ob_start();
		try {
			foreach ( $original_hooks as $hook_name => $original_hook ) {
				if ( ! isset( $GLOBALS['wp_filter'][ $hook_name ] ) ) {
					continue;
				}
				foreach ( $GLOBALS['wp_filter'][ $hook_name ]->callbacks as $priority => $callbacks ) {
					$original_callbacks = null === $original_hook ? array() : ( $original_hook->callbacks[ $priority ] ?? array() );
					foreach ( array_diff_key( $callbacks, $original_callbacks ) as $callback ) {
						call_user_func( $callback['function'] );
					}
				}
			}
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
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
