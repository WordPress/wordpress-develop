<?php
/**
 * Test the block style variations block support.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 6.6.0
 *
 * @group block-supports
 */
class WP_Block_Supports_Block_Style_Variations_Test extends WP_UnitTestCase {
	/**
	 * Theme root directory.
	 *
	 * @var string|null
	 */
	private $theme_root;

	/**
	 * Original theme directory.
	 *
	 * @var array|null
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();
		$this->theme_root = realpath( DIR_TESTDATA . '/themedir1' );

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		add_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		// Reset data between tests.
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	/**
	 * Tests that block style variations registered via either
	 * `register_block_style` with a style object, or a standalone block style
	 * variation file within `/styles`, are added to the theme data.
	 *
	 * @ticket 61312
	 * @ticket 61440
	 * @ticket 61451
	 */
	public function test_add_registered_block_styles_to_theme_data() {
		switch_theme( 'block-theme' );

		$variation_styles_data = array(
			'color'    => array(
				'background' => 'darkslateblue',
				'text'       => 'lavender',
			),
			'blocks'   => array(
				'core/heading' => array(
					'color' => array(
						'text' => 'violet',
					),
				),
			),
			'elements' => array(
				'link' => array(
					'color'  => array(
						'text' => 'fuchsia',
					),
					':hover' => array(
						'color' => array(
							'text' => 'deeppink',
						),
					),
				),
			),
		);

		/*
		 * This style is to be deliberately overwritten by the theme.json partial
		 * See `tests/phpunit/data/themedir1/block-theme/styles/block-style-variation-with-slug.json`.
		 */
		register_block_style(
			'core/group',
			array(
				'name'       => 'WithSlug',
				'style_data' => array(
					'color' => array(
						'background' => 'whitesmoke',
						'text'       => 'black',
					),
				),
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'       => 'my-variation',
				'style_data' => $variation_styles_data,
			)
		);

		$theme_json   = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();
		$group_styles = $theme_json['styles']['blocks']['core/group'] ?? array();
		$expected     = array(
			'variations' => array(

				/*
				 * The following block style variations are registered
				 * automatically from their respective JSON files within the
				 * theme's `/styles` directory.
				 */
				'block-style-variation-a' => array(
					'color' => array(
						'background' => 'indigo',
						'text'       => 'plum',
					),
				),
				'block-style-variation-b' => array(
					'color' => array(
						'background' => 'midnightblue',
						'text'       => 'lightblue',
					),
				),

				/*
				 * Manually registered variations.
				 * @ticket 61440
				 */
				'WithSlug'                => array(
					'color' => array(
						'background' => 'aliceblue',
						'text'       => 'midnightblue',
					),
				),
				'my-variation'            => $variation_styles_data,
			),
		);

		unregister_block_style( 'core/group', 'my-variation' );
		unregister_block_style( 'core/group', 'WithSlug' );

		$this->assertSameSetsWithIndex( $expected, $group_styles, 'Variation data does not match' );
	}

	/**
	 * Tests that block style variations resolve any `ref` values when generating styles.
	 *
	 * @ticket 61589
	 */
	public function test_block_style_variation_ref_values() {
		switch_theme( 'block-theme' );

		$variation_data = array(
			'color'    => array(
				'text'       => array(
					'ref' => 'styles.does-not-exist',
				),
				'background' => array(
					'ref' => 'styles.blocks.core/group.variations.block-style-variation-a.color.text',
				),
			),
			'blocks'   => array(
				'core/heading' => array(
					'color' => array(
						'text'       => array(
							'ref' => 'styles.blocks.core/group.variations.block-style-variation-a.color.background',
						),
						'background' => array(
							'ref' => '',
						),
					),
				),
			),
			'elements' => array(
				'link' => array(
					'color'  => array(
						'text'       => array(
							'ref' => 'styles.blocks.core/group.variations.block-style-variation-b.color.text',
						),
						'background' => array(
							'ref' => null,
						),
					),
					':hover' => array(
						'color' => array(
							'text' => array(
								'ref' => 'styles.blocks.core/group.variations.block-style-variation-b.color.background',
							),
						),
					),
				),
			),
		);

		$theme_json = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();

		wp_resolve_block_style_variation_ref_values( $variation_data, $theme_json );

		$expected = array(
			'color'    => array( 'background' => 'plum' ),
			'blocks'   => array(
				'core/heading' => array(
					'color' => array( 'text' => 'indigo' ),
				),
			),
			'elements' => array(
				'link' => array(
					'color'  => array( 'text' => 'lightblue' ),
					':hover' => array(
						'color' => array( 'text' => 'midnightblue' ),
					),
				),
			),
		);

		$this->assertSameSetsWithIndex( $expected, $variation_data, 'Variation data with resolved ref values does not match' );
	}
}
