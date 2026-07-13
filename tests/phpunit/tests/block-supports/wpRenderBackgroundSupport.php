<?php
/**
 * @group block-supports
 *
 * @covers ::wp_render_background_support
 */
class Tests_Block_Supports_WpRenderBackgroundSupport extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

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
		$this->test_block_name = null;
		$this->theme_root      = realpath( DIR_TESTDATA . '/themedir1' );
		$this->orig_theme_dir  = $GLOBALS['wp_theme_directories'];

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

		// Clear up the filters to modify the theme root.
		remove_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		unregister_block_type( $this->test_block_name );
		$this->test_block_name = null;
		parent::tear_down();
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	/**
	 * Tests that background image block support works as expected.
	 *
	 * @ticket 59357
	 * @ticket 60175
	 * @ticket 61123
	 * @ticket 61720
	 * @ticket 61858
	 *
	 * @covers ::wp_render_background_support
	 *
	 * @dataProvider data_background_block_support
	 *
	 * @param string $theme_name          The theme to switch to.
	 * @param string $block_name          The test block name to register.
	 * @param mixed  $background_settings The background block support settings.
	 * @param mixed  $background_style    The background styles within the block attributes.
	 * @param string $expected_wrapper    Expected markup for the block wrapper.
	 * @param string $wrapper             Existing markup for the block wrapper.
	 */
	public function test_background_block_support( $theme_name, $block_name, $background_settings, $background_style, $expected_wrapper, $wrapper ) {
		switch_theme( $theme_name );
		$this->test_block_name = $block_name;

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'background' => $background_settings,
				),
			)
		);

		$block = array(
			'blockName' => $block_name,
			'attrs'     => array(
				'style' => array(
					'background' => $background_style,
				),
			),
		);

		$actual = wp_render_background_support( $wrapper, $block );

		$this->assertSame(
			$expected_wrapper,
			$actual,
			'Background block wrapper markup should be correct'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_background_block_support() {
		return array(
			'background image style is applied' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage' => array(
						'url' => 'https://example.com/image.jpg',
					),
				),
				'expected_wrapper'    => '<div class="has-background" style="background-image:url(&apos;https://example.com/image.jpg&apos;);background-size:cover;">Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
			'background image style with contain, position, attachment, and repeat is applied' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage'      => array(
						'url' => 'https://example.com/image.jpg',
					),
					'backgroundRepeat'     => 'no-repeat',
					'backgroundSize'       => 'contain',
					'backgroundAttachment' => 'fixed',
				),
				'expected_wrapper'    => '<div class="has-background" style="background-image:url(&apos;https://example.com/image.jpg&apos;);background-position:50% 50%;background-repeat:no-repeat;background-size:contain;background-attachment:fixed;">Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
			'background image style is appended if a style attribute already exists' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage' => array(
						'url' => 'https://example.com/image.jpg',
					),
				),
				'expected_wrapper'    => '<div class="wp-block-test has-background" style="color: red;background-image:url(&apos;https://example.com/image.jpg&apos;);background-size:cover;">Content</div>',
				'wrapper'             => '<div class="wp-block-test" style="color: red">Content</div>',
			),
			'background image style is appended if a style attribute containing multiple styles already exists' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage' => array(
						'url' => 'https://example.com/image.jpg',
					),
				),
				'expected_wrapper'    => '<div class="wp-block-test has-background" style="color: red;font-size: 15px;background-image:url(&apos;https://example.com/image.jpg&apos;);background-size:cover;">Content</div>',
				'wrapper'             => '<div class="wp-block-test" style="color: red;font-size: 15px;">Content</div>',
			),
			'background image style is appended if a boolean style attribute already exists' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage' => array(
						'url'    => 'https://example.com/image.jpg',
						'source' => 'file',
					),
				),
				'expected_wrapper'    => '<div class="has-background" classname="wp-block-test" style="background-image:url(&apos;https://example.com/image.jpg&apos;);background-size:cover;">Content</div>',
				'wrapper'             => '<div classname="wp-block-test" style>Content</div>',
			),
			'background image style is not applied if the block does not support background image' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-not-output',
				'background_settings' => array(
					'backgroundImage' => false,
				),
				'background_style'    => array(
					'backgroundImage' => array(
						'url' => 'https://example.com/image.jpg',
					),
				),
				'expected_wrapper'    => '<div>Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
			'background image with attachment id and repeat falls back to CSS' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage'  => array(
						'id'  => 99999,
						'url' => 'https://example.com/image.jpg',
					),
					'backgroundRepeat' => 'repeat',
				),
				'expected_wrapper'    => '<div class="has-background" style="background-image:url(&apos;https://example.com/image.jpg&apos;);background-repeat:repeat;background-size:cover;">Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
			'background image with attachment id and fixed attachment falls back to CSS' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-output',
				'background_settings' => array(
					'backgroundImage' => true,
				),
				'background_style'    => array(
					'backgroundImage'      => array(
						'id'  => 99999,
						'url' => 'https://example.com/image.jpg',
					),
					'backgroundSize'       => 'cover',
					'backgroundAttachment' => 'fixed',
				),
				'expected_wrapper'    => '<div class="has-background" style="background-image:url(&apos;https://example.com/image.jpg&apos;);background-size:cover;background-attachment:fixed;">Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
		);
	}

	/**
	 * Tests that a background image with backgroundRepeat:'no-repeat' still injects
	 * an img element (no-repeat is compatible with object-fit; only tiled/repeat
	 * values require CSS background-image).
	 *
	 * @ticket 64725
	 *
	 * @covers ::wp_render_background_support
	 */
	public function test_background_img_element_is_injected_with_no_repeat() {
		switch_theme( 'block-theme-child-with-fluid-typography' );
		$this->test_block_name = 'test/background-img-element-no-repeat';

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			array( 'post_mime_type' => 'image/jpeg' )
		);

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'background' => array(
						'backgroundImage' => true,
					),
				),
			)
		);

		$block = array(
			'blockName' => $this->test_block_name,
			'attrs'     => array(
				'style' => array(
					'background' => array(
						'backgroundImage'  => array(
							'id'  => $attachment_id,
							'url' => wp_get_attachment_url( $attachment_id ),
						),
						'backgroundSize'   => 'contain',
						'backgroundRepeat' => 'no-repeat',
					),
				),
			),
		);

		$actual = wp_render_background_support( '<div>Content</div>', $block );

		$this->assertStringContainsString( '<img', $actual, 'no-repeat with contain should still inject an img element.' );
		$this->assertStringNotContainsString( 'background-image:', $actual, 'Output should not contain background-image CSS.' );
		$this->assertStringContainsString( 'has-background', $actual, 'Wrapper should have has-background class.' );
		$this->assertStringContainsString( 'position:relative', $actual, 'Wrapper should have position:relative style.' );

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that a background image with an attachment ID injects an img element.
	 *
	 * @ticket 64725
	 *
	 * @covers ::wp_render_background_support
	 */
	public function test_background_img_element_is_injected() {
		switch_theme( 'block-theme-child-with-fluid-typography' );
		$this->test_block_name = 'test/background-img-element';

		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			0,
			array( 'post_mime_type' => 'image/jpeg' )
		);

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'background' => array(
						'backgroundImage' => true,
					),
				),
			)
		);

		$block = array(
			'blockName' => $this->test_block_name,
			'attrs'     => array(
				'style' => array(
					'background' => array(
						'backgroundImage' => array(
							'id'  => $attachment_id,
							'url' => wp_get_attachment_url( $attachment_id ),
						),
						'backgroundSize'  => 'cover',
					),
				),
			),
		);

		$actual = wp_render_background_support( '<div>Content</div>', $block );

		$this->assertStringContainsString( '<img', $actual, 'Output should contain an img element.' );
		$this->assertStringNotContainsString( 'background-image:', $actual, 'Output should not contain background-image CSS.' );
		$this->assertStringContainsString( 'has-background', $actual, 'Wrapper should have has-background class.' );
		$this->assertStringContainsString( 'position:relative', $actual, 'Wrapper should have position:relative style.' );

		wp_delete_attachment( $attachment_id, true );
	}
}
