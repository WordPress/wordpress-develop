<?php
/**
 * Tests for block supports related to layout.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 6.0.0
 *
 * @group block-supports
 *
 * @covers ::wp_restore_image_outer_container
 */
class Tests_Block_Supports_Layout extends WP_UnitTestCase {

	/**
	 * Theme root directory.
	 *
	 * @var string
	 */
	private $theme_root;

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();
		$this->theme_root     = realpath( DIR_TESTDATA . '/themedir1' );
		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		// Set up the new root.
		add_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;

		// Clear up the filters to modify the theme root.
		remove_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	/**
	 * @ticket 55505
	 */
	public function test_outer_container_not_restored_for_non_aligned_image_block_with_non_themejson_theme() {
		// The "default" theme doesn't have theme.json support.
		switch_theme( 'default' );
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array(),
		);
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg"/></figure>';
		$expected      = '<figure class="wp-block-image size-full"><img src="/my-image.jpg"/></figure>';

		$this->assertEqualHTML( $expected, wp_restore_image_outer_container( $block_content, $block ) );
	}

	/**
	 * @ticket 55505
	 */
	public function test_outer_container_restored_for_aligned_image_block_with_non_themejson_theme() {
		// The "default" theme doesn't have theme.json support.
		switch_theme( 'default' );
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array(),
		);
		$block_content = '<figure class="wp-block-image alignright size-full"><img src="/my-image.jpg"/></figure>';
		$expected      = '<div class="wp-block-image"><figure class="alignright size-full"><img src="/my-image.jpg"/></figure></div>';

		$this->assertEqualHTML( $expected, wp_restore_image_outer_container( $block_content, $block ) );
	}

	/**
	 * @ticket 55505
	 *
	 * @dataProvider data_block_image_html_restored_outer_container
	 *
	 * @param string $block_image_html The block image HTML passed to `wp_restore_image_outer_container`.
	 * @param string $expected         The expected block image HTML.
	 */
	public function test_additional_styles_moved_to_restored_outer_container_for_aligned_image_block_with_non_themejson_theme( $block_image_html, $expected ) {
		// The "default" theme doesn't have theme.json support.
		switch_theme( 'default' );
		$block = array(
			'blockName' => 'core/image',
			'attrs'     => array(
				'className' => 'is-style-round my-custom-classname',
			),
		);

		$this->assertEqualHTML( $expected, wp_restore_image_outer_container( $block_image_html, $block ) );
	}

	/**
	 * Data provider for test_additional_styles_moved_to_restored_outer_container_for_aligned_image_block_with_non_themejson_theme().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $block_image_html The block image HTML passed to `wp_restore_image_outer_container`.
	 *         @type string $expected         The expected block image HTML.
	 *     }
	 * }
	 */
	public function data_block_image_html_restored_outer_container() {
		$expected = '<div class="wp-block-image is-style-round my-custom-classname"><figure class="alignright size-full"><img src="/my-image.jpg"/></figure></div>';

		return array(
			array(
				'<figure class="wp-block-image alignright size-full is-style-round my-custom-classname"><img src="/my-image.jpg"/></figure>',
				$expected,
			),
			array(
				'<figure class="is-style-round my-custom-classname wp-block-image alignright size-full"><img src="/my-image.jpg"/></figure>',
				$expected,
			),
			array(
				'<figure class="wp-block-image is-style-round my-custom-classname alignright size-full"><img src="/my-image.jpg"/></figure>',
				$expected,
			),
			array(
				'<figure class="is-style-round wp-block-image alignright my-custom-classname size-full"><img src="/my-image.jpg"/></figure>',
				$expected,
			),
			array(
				'<figure style="color: red" class=\'is-style-round wp-block-image alignright my-custom-classname size-full\' data-random-tag=">"><img src="/my-image.jpg"/></figure>',
				'<div class="wp-block-image is-style-round my-custom-classname"><figure style="color: red" class=\'alignright size-full\' data-random-tag=">"><img src="/my-image.jpg"/></figure></div>',
			),
		);
	}

	/**
	 * @ticket 55505
	 */
	public function test_outer_container_not_restored_for_aligned_image_block_with_themejson_theme() {
		switch_theme( 'block-theme' );
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array(
				'className' => 'is-style-round my-custom-classname',
			),
		);
		$block_content = '<figure class="wp-block-image alignright size-full is-style-round my-custom-classname"><img src="/my-image.jpg"/></figure>';
		$expected      = '<figure class="wp-block-image alignright size-full is-style-round my-custom-classname"><img src="/my-image.jpg"/></figure>';

		$this->assertEqualHTML( $expected, wp_restore_image_outer_container( $block_content, $block ) );
	}

	/**
	 * @ticket 57584
	 * @ticket 58548
	 * @ticket 60292
	 * @ticket 61111
	 *
	 * @dataProvider data_layout_support_flag_renders_classnames_on_wrapper
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @param array  $args            Dataset to test.
	 * @param string $expected_output The expected output.
	 */
	public function test_layout_support_flag_renders_classnames_on_wrapper( $args, $expected_output ) {
		switch_theme( 'default' );
		$actual_output = wp_render_layout_support_flag( $args['block_content'], $args['block'] );
		$this->assertEqualHTML( $expected_output, $actual_output );
	}

	/**
	 * Data provider for test_layout_support_flag_renders_classnames_on_wrapper.
	 *
	 * @return array
	 */
	public function data_layout_support_flag_renders_classnames_on_wrapper() {
		return array(
			'single wrapper block layout with flow type'   => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'default',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group"></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group is-layout-flow wp-block-group-is-layout-flow"></div>',
			),
			'single wrapper block layout with constrained type' => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'constrained',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group"></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained"></div>',
			),
			'multiple wrapper block layout with flow type' => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"><div class="wp-block-group__inner-wrapper"></div></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'default',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"><div class="wp-block-group__inner-wrapper"></div></div>',
						'innerContent' => array(
							'<div class="wp-block-group"><div class="wp-block-group__inner-wrapper">',
							' ',
							' </div></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group"><div class="wp-block-group__inner-wrapper is-layout-flow wp-block-group-is-layout-flow"></div></div>',
			),
			'block with child layout'                      => array(
				'args'            => array(
					'block_content' => '<p>Some text.</p>',
					'block'         => array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(
							'style' => array(
								'layout' => array(
									'columnSpan' => '2',
								),
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Some text.</p>',
						'innerContent' => array(
							'<p>Some text.</p>',
						),
					),
				),
				'expected_output' => '<p class="wp-container-content-b7aa651c">Some text.</p>',
			),
			'single wrapper block layout with flex type'   => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type'        => 'flex',
								'orientation' => 'horizontal',
								'flexWrap'    => 'nowrap',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group"></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group is-horizontal is-nowrap is-layout-flex wp-container-core-group-is-layout-67f0b8e2 wp-block-group-is-layout-flex"></div>',
			),
			'single wrapper block layout with grid type'   => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'grid',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group"></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group is-layout-grid wp-container-core-group-is-layout-9649a0d9 wp-block-group-is-layout-grid"></div>',
			),
			'skip classname output if block does not support layout and there are no child layout classes to be output' => array(
				'args'            => array(
					'block_content' => '<p>A paragraph</p>',
					'block'         => array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(
							'style' => array(
								'layout' => array(
									'selfStretch' => 'fit',
								),
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>A paragraph</p>',
						'innerContent' => array( '<p>A paragraph</p>' ),
					),
				),
				'expected_output' => '<p>A paragraph</p>',
			),
		);
	}

	/**
	 * Check that wp_restore_group_inner_container() restores the legacy inner container on the Group block.
	 *
	 * @ticket 60130
	 *
	 * @covers ::wp_restore_group_inner_container
	 *
	 * @dataProvider data_restore_group_inner_container
	 *
	 * @param array  $args            Dataset to test.
	 * @param string $expected_output The expected output.
	 */
	public function test_restore_group_inner_container( $args, $expected_output ) {
		$actual_output = wp_restore_group_inner_container( $args['block_content'], $args['block'] );
		$this->assertSame( $expected_output, $actual_output );
	}

	/**
	 * Data provider for test_restore_group_inner_container.
	 *
	 * @return array
	 */
	public function data_restore_group_inner_container() {
		return array(
			'group block with existing inner container'    => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"><div class="wp-block-group__inner-container"></div></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'default',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"><div class="wp-block-group__inner-container"></div></div>',
						'innerContent' => array(
							'<div class="wp-block-group"><div class="wp-block-group__inner-container">',
							' ',
							' </div></div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group"><div class="wp-block-group__inner-container"></div></div>',
			),
			'group block with no existing inner container' => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'default',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group">',
							' ',
							' </div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group"><div class="wp-block-group__inner-container"></div></div>',
			),
			'group block with layout classnames'           => array(
				'args'            => array(
					'block_content' => '<div class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained"></div>',
					'block'         => array(
						'blockName'    => 'core/group',
						'attrs'        => array(
							'layout' => array(
								'type' => 'default',
							),
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '<div class="wp-block-group"></div>',
						'innerContent' => array(
							'<div class="wp-block-group">',
							' ',
							' </div>',
						),
					),
				),
				'expected_output' => '<div class="wp-block-group"><div class="wp-block-group__inner-container is-layout-constrained wp-block-group-is-layout-constrained"></div></div>',
			),
		);
	}

	/**
	 * Checks that `wp_add_parent_layout_to_parsed_block` adds the parent layout attribute to the block object.
	 *
	 * @ticket 61111
	 *
	 * @covers ::wp_add_parent_layout_to_parsed_block
	 *
	 * @dataProvider data_wp_add_parent_layout_to_parsed_block
	 *
	 * @param array    $block        The block object.
	 * @param WP_Block $parent_block The parent block object.
	 * @param array    $expected     The expected block object.
	 */
	public function test_wp_add_parent_layout_to_parsed_block( $block, $parent_block, $expected ) {
		$actual = wp_add_parent_layout_to_parsed_block( $block, array(), $parent_block );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_wp_add_parent_layout_to_parsed_block.
	 *
	 * @return array
	 */
	public function data_wp_add_parent_layout_to_parsed_block() {
		return array(
			'block with no parent layout' => array(
				'block'        => array(
					'blockName' => 'core/group',
					'attrs'     => array(
						'layout' => array(
							'type' => 'default',
						),
					),
				),
				'parent_block' => array(),
				'expected'     => array(
					'blockName' => 'core/group',
					'attrs'     => array(
						'layout' => array(
							'type' => 'default',
						),
					),
				),
			),
			'block with parent layout'    => array(
				'block'        => array(
					'blockName' => 'core/group',
					'attrs'     => array(
						'layout' => array(
							'type' => 'default',
						),
					),
				),
				'parent_block' => new WP_Block(
					array(
						'blockName' => 'core/group',
						'attrs'     => array(
							'layout' => array(
								'type' => 'grid',
							),
						),
					)
				),
				'expected'     => array(
					'blockName'    => 'core/group',
					'attrs'        => array(
						'layout' => array(
							'type' => 'default',
						),
					),
					'parentLayout' => array(
						'type' => 'grid',
					),
				),
			),
		);
	}

	/**
	 * Check that wp_render_layout_support_flag() renders consistent hashes
	 * for the container class when the relevant layout properties are the same.
	 *
	 * @dataProvider data_layout_support_flag_renders_consistent_container_hash
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @param array $block_attrs     Dataset to test.
	 * @param array $expected_class  Class generated for the passed dataset.
	 */
	public function test_layout_support_flag_renders_consistent_container_hash( $block_attrs, $expected_class ) {
		switch_theme( 'default' );

		$block_content = '<div class="wp-block-group"></div>';
		$block         = array(
			'blockName'    => 'core/group',
			'innerBlocks'  => array(),
			'innerHTML'    => '<div class="wp-block-group"></div>',
			'innerContent' => array(
				'<div class="wp-block-group"></div>',
			),
			'attrs'        => $block_attrs,
		);

		/*
		 * The `appearance-tools` theme support is temporarily added to ensure
		 * that the block gap support is enabled during rendering, which is
		 * necessary to compute styles for layouts with block gap values.
		 */
		add_theme_support( 'appearance-tools' );
		$output = wp_render_layout_support_flag( $block_content, $block );
		remove_theme_support( 'appearance-tools' );

		// Process the output and look for the expected class in the first rendered element.
		$processor = new WP_HTML_Tag_Processor( $output );
		$processor->next_tag();

		$this->assertTrue(
			$processor->has_class( $expected_class ),
			"Expected class '$expected_class' not found in the rendered output, probably because of a different hash."
		);
	}

	/**
	 * Data provider for test_layout_support_flag_renders_consistent_container_hash.
	 *
	 * @return array
	 */
	public function data_layout_support_flag_renders_consistent_container_hash() {
		return array(
			'default type block gap 12px'      => array(
				'block_attributes' => array(
					'layout' => array(
						'type' => 'default',
					),
					'style'  => array(
						'spacing' => array(
							'blockGap' => '12px',
						),
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-c5c7d83f',
			),
			'default type block gap 24px'      => array(
				'block_attributes' => array(
					'layout' => array(
						'type' => 'default',
					),
					'style'  => array(
						'spacing' => array(
							'blockGap' => '24px',
						),
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-634f0b9d',
			),
			'constrained type justified left'  => array(
				'block_attributes' => array(
					'layout' => array(
						'type'           => 'constrained',
						'justifyContent' => 'left',
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-12dd3699',
			),
			'constrained type justified right' => array(
				'block_attributes' => array(
					'layout' => array(
						'type'           => 'constrained',
						'justifyContent' => 'right',
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-f1f2ed93',
			),
			'flex type horizontal'             => array(
				'block_attributes' => array(
					'layout' => array(
						'type'        => 'flex',
						'orientation' => 'horizontal',
						'flexWrap'    => 'nowrap',
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-2487dcaa',
			),
			'flex type vertical'               => array(
				'block_attributes' => array(
					'layout' => array(
						'type'        => 'flex',
						'orientation' => 'vertical',
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-fe9cc265',
			),
			'grid type'                        => array(
				'block_attributes' => array(
					'layout' => array(
						'type' => 'grid',
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-478b6e6b',
			),
			'grid type 3 columns'              => array(
				'block_attributes' => array(
					'layout' => array(
						'type'        => 'grid',
						'columnCount' => 3,
					),
				),
				'expected_class'   => 'wp-container-core-group-is-layout-d3b710ac',
			),
		);
	}
}
