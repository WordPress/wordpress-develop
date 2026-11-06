<?php
/**
 * Tests for the server-side rendering of the Site Logo block, and the registration of its settings.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderSiteLogo extends WP_UnitTestCase {

	/**
	 * ID of the attachment of the logo, an image of 640 x 480 pixels.
	 *
	 * @var int
	 */
	private static $logo_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original Customizer manager.
	 *
	 * @var WP_Customize_Manager|null
	 */
	private $original_customize;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$logo_id = $factory->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		// The logo has a placeholder in the Customizer preview, which another test can leave on.
		$this->original_customize = $GLOBALS['wp_customize'] ?? null;
		$GLOBALS['wp_customize']  = null;

		add_theme_support( 'custom-logo' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		$GLOBALS['wp_customize'] = $this->original_customize;

		remove_theme_support( 'custom-logo' );
		unregister_setting( 'general', 'site_logo' );
		unregister_setting( 'general', 'site_icon' );

		parent::tear_down();
	}

	/**
	 * Sets the logo of the site.
	 */
	private function set_logo() {
		set_theme_mod( 'custom_logo', self::$logo_id );
	}

	/**
	 * Renders the block with the defaults of the block, and the attributes that are passed.
	 *
	 * @param array $attributes Attributes of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array() ) {
		return render_block_core_site_logo(
			array_merge(
				array(
					'isLink'     => true,
					'linkTarget' => '_self',
				),
				$attributes
			)
		);
	}

	/**
	 * Gets the attributes of the first tag of a type of the output.
	 *
	 * @param string $output Rendered block.
	 * @param string $tag    Name of the tag.
	 * @return array|null The attributes, keyed by name, or null when there is no such tag.
	 */
	private function get_attributes_of( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();
		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that nothing is rendered without a logo.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_render_nothing_without_a_logo() {
		$this->assertSame( '', $this->render() );
	}

	/**
	 * Tests that the logo is rendered in a wrapper with a link to the home page.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_render_the_logo_with_a_link_to_the_home_page() {
		$this->set_logo();

		$output = $this->render();

		$this->assertStringStartsWith( '<div class="is-default-size"><a ', $output );
		$this->assertStringEndsWith( '</a></div>', $output );

		$link  = $this->get_attributes_of( $output, 'a' );
		$image = $this->get_attributes_of( $output, 'img' );

		$this->assertSame( home_url( '/' ), $link['href'] );
		$this->assertSame( 'home', $link['rel'] );
		$this->assertArrayNotHasKey( 'target', $link, 'The link should not have a target.' );
		$this->assertSame( 'custom-logo', $image['class'] );
	}

	/**
	 * Tests that the link is removed when it is not requested.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_remove_the_link_when_it_is_not_requested() {
		$this->set_logo();

		$output = $this->render( array( 'isLink' => false ) );

		$this->assertStringNotContainsString( '<a', $output, 'There should be no link.' );
		$this->assertStringNotContainsString( '</a>', $output, 'There should be no link.' );
		$this->assertStringStartsWith( '<div class="is-default-size"><img ', $output, 'The image should be kept.' );
	}

	/**
	 * Tests that the link opens in a new tab.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_open_the_link_in_a_new_tab() {
		$this->set_logo();

		$link = $this->get_attributes_of( $this->render( array( 'linkTarget' => '_blank' ) ), 'a' );

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( '(Home link, opens in a new tab)', $link['aria-label'] );
		$this->assertSame( 'home', $link['rel'], 'The rel should be kept.' );
	}

	/**
	 * Tests that the target is not used without a link.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_not_use_the_target_without_a_link() {
		$this->set_logo();

		$output = $this->render(
			array(
				'isLink'     => false,
				'linkTarget' => '_blank',
			)
		);

		$this->assertStringNotContainsString( 'target=', $output );
		$this->assertStringNotContainsString( 'aria-label', $output );
	}

	/**
	 * Tests that the target is only added to a link of the home page.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_only_add_the_target_to_a_link_with_the_home_rel() {
		$this->set_logo();

		add_filter(
			'get_custom_logo',
			static function ( $html ) {
				return str_replace( 'rel="home"', 'rel="external"', $html );
			}
		);

		$link = $this->get_attributes_of( $this->render( array( 'linkTarget' => '_blank' ) ), 'a' );

		$this->assertArrayNotHasKey( 'target', $link );
		$this->assertArrayNotHasKey( 'aria-label', $link );
	}

	/**
	 * Tests the size of the logo.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_use_the_width_of_the_attributes_and_keep_the_ratio() {
		$this->set_logo();

		$default = $this->get_attributes_of( $this->render(), 'img' );
		$output  = $this->render( array( 'width' => 120 ) );
		$image   = $this->get_attributes_of( $output, 'img' );

		$this->assertSame( '640', $default['width'], 'The default width should be the one of the image.' );
		$this->assertSame( '480', $default['height'], 'The default height should be the one of the image.' );
		$this->assertSame( '120', $image['width'] );
		$this->assertStringEndsWith( 'canola.jpg', $image['src'], 'The image should be kept.' );
		$this->assertSame( '90', $image['height'], 'The height should keep the ratio of the image.' );
		$this->assertStringNotContainsString( 'is-default-size', $output, 'The default size class should not be added with a width.' );
	}

	/**
	 * Tests that the size of an image that has no dimensions is not changed.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 *
	 * @dataProvider data_images_without_dimensions
	 *
	 * @param array|false $image Image that is returned by the filter.
	 */
	public function test_should_not_change_the_size_of_an_image_without_dimensions( $image ) {
		$this->set_logo();

		add_filter(
			'wp_get_attachment_image_src',
			static function ( $original ) use ( $image ) {
				return false === $image ? $original : array( $original[0], $image[0], $image[1] );
			},
			5
		);

		$output = $this->render( array( 'width' => 120 ) );
		$link   = $this->get_attributes_of( $output, 'img' );

		$this->assertNotSame( '120', $link['width'] ?? null, 'The width should not be changed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_images_without_dimensions() {
		return array(
			'no width'  => array( array( 0, 480 ) ),
			'no height' => array( array( 640, 0 ) ),
			'neither'   => array( array( 0, 0 ) ),
		);
	}

	/**
	 * Tests the classes of the wrapper for a width that is empty.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 *
	 * @dataProvider data_widths_that_are_empty
	 *
	 * @param mixed $width Width of the block.
	 */
	public function test_should_use_the_default_size_without_a_width( $width ) {
		$this->set_logo();

		$output = $this->render( array( 'width' => $width ) );
		$image  = $this->get_attributes_of( $output, 'img' );

		$this->assertStringStartsWith( '<div class="is-default-size">', $output );
		$this->assertSame( '640', $image['width'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_widths_that_are_empty() {
		return array(
			'null'         => array( null ),
			'zero'         => array( 0 ),
			'an empty one' => array( '' ),
			'zero as text' => array( '0' ),
		);
	}

	/**
	 * Tests that the filter of the size is removed after the block is rendered.
	 *
	 * @ticket 66532
	 *
	 * @covers ::render_block_core_site_logo
	 */
	public function test_should_remove_the_filter_of_the_size_after_the_block_is_rendered() {
		$this->set_logo();

		$this->render( array( 'width' => 120 ) );

		$image = wp_get_attachment_image_src( self::$logo_id, 'full' );

		$this->assertSame( 640, $image[1], 'The width of the images should not be changed after the block is rendered.' );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66532
	 *
	 * @covers ::register_block_core_site_logo
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/site-logo' );

		if ( $was_registered ) {
			unregister_block_type( 'core/site-logo' );
		}

		register_block_core_site_logo();
		$block_type = $registry->get_registered( 'core/site-logo' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/site-logo' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_site_logo', $block_type->render_callback );
	}

	/**
	 * Tests the setting of the logo.
	 *
	 * @ticket 66532
	 *
	 * @covers ::register_block_core_site_logo_setting
	 */
	public function test_should_register_the_setting_of_the_logo() {
		register_block_core_site_logo_setting();

		$settings = get_registered_settings();

		$this->assertArrayHasKey( 'site_logo', $settings );
		$this->assertSame( 'general', $settings['site_logo']['group'] );
		$this->assertSame( 'integer', $settings['site_logo']['type'] );
		$this->assertSame( array( 'name' => 'site_logo' ), $settings['site_logo']['show_in_rest'] );
	}

	/**
	 * Tests the setting of the icon.
	 *
	 * @ticket 66532
	 *
	 * @covers ::register_block_core_site_icon_setting
	 */
	public function test_should_register_the_setting_of_the_icon() {
		register_block_core_site_icon_setting();

		$settings = get_registered_settings();

		$this->assertArrayHasKey( 'site_icon', $settings );
		$this->assertSame( 'general', $settings['site_icon']['group'] );
		$this->assertSame( 'integer', $settings['site_icon']['type'] );
		$this->assertTrue( $settings['site_icon']['show_in_rest'] );
	}

	/**
	 * Tests that the settings are registered with the REST API.
	 *
	 * @ticket 66532
	 *
	 * @covers ::register_block_core_site_logo_setting
	 * @covers ::register_block_core_site_icon_setting
	 */
	public function test_should_hook_the_settings_to_the_rest_api_init_action() {
		$this->assertSame( 10, has_action( 'rest_api_init', 'register_block_core_site_logo_setting' ) );
		$this->assertSame( 10, has_action( 'rest_api_init', 'register_block_core_site_icon_setting' ) );
	}
}
