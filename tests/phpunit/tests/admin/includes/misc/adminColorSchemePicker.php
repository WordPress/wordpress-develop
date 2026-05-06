<?php

/**
 * @group admin
 * @group misc
 * @covers ::admin_color_scheme_picker
 */
class Tests_admin_color_scheme_picker extends WP_UnitTestCase {

	/**
	 * Original value of $_wp_admin_css_colors.
	 *
	 * @var array
	 */
	private $orig_wp_admin_css_colors;

	public function set_up() {
		parent::set_up();
		global $_wp_admin_css_colors;
		$this->orig_wp_admin_css_colors = $_wp_admin_css_colors;
	}

	public function tear_down() {
		global $_wp_admin_css_colors;
		$_wp_admin_css_colors = $this->orig_wp_admin_css_colors;
		parent::tear_down();
	}

	/**
	 * @ticket 65184
	 */
	public function test_admin_color_scheme_picker_default_selection() {
		global $_wp_admin_css_colors;

		// Mock color schemes.
		$_wp_admin_css_colors = array(
			'fresh'  => (object) array(
				'name'        => 'Default',
				'url'         => 'fresh.css',
				'colors'      => array( '#1d2327', '#2c3338', '#2271b1', '#72aee6' ),
				'icon_colors' => array(
					'base' => '#a7aaad',
					'focus' => '#72aee6',
					'current' => '#fff',
				),
			),
			'modern' => (object) array(
				'name'        => 'Modern',
				'url'         => 'modern.css',
				'colors'      => array( '#1e1e1e', '#383838', '#007cba', '#e0e0e0' ),
				'icon_colors' => array(
					'base' => '#f0f0f1',
					'focus' => '#fff',
					'current' => '#fff',
				),
			),
		);

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		ob_start();
		admin_color_scheme_picker( $user_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="color-picker"', $output );
		$this->assertStringContainsString( 'value="modern"', $output );
		$this->assertStringContainsString( 'checked=\'checked\'', $output );
		$this->assertStringContainsString( 'name="admin_color"', $output );

		// Verify "modern" is selected by default when no option is set.
		$this->assertStringContainsString( 'id="admin_color_modern" type="radio" value="modern"  checked=\'checked\'', $output );
	}

	/**
	 * @ticket 65184
	 */
	public function test_admin_color_scheme_picker_custom_selection() {
		global $_wp_admin_css_colors;

		$_wp_admin_css_colors = array(
			'fresh'  => (object) array(
				'name'        => 'Default',
				'url'         => 'fresh.css',
				'colors'      => array( '#1d2327' ),
				'icon_colors' => array( 'base' => '#a7aaad' ),
			),
			'blue'   => (object) array(
				'name'        => 'Blue',
				'url'         => 'blue.css',
				'colors'      => array( '#096484' ),
				'icon_colors' => array( 'base' => '#e5f8ff' ),
			),
			'modern' => (object) array(
				'name'        => 'Modern',
				'url'         => 'modern.css',
				'colors'      => array( '#1e1e1e' ),
				'icon_colors' => array( 'base' => '#f0f0f1' ),
			),
		);

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'admin_color', 'blue' );
		wp_set_current_user( $user_id );

		ob_start();
		admin_color_scheme_picker( $user_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="blue"', $output );
		$this->assertStringContainsString( 'id="admin_color_blue" type="radio" value="blue"  checked=\'checked\'', $output );
		$this->assertStringNotContainsString( 'id="admin_color_modern" type="radio" value="modern"  checked=\'checked\'', $output );
	}

	/**
	 * @ticket 65184
	 */
	public function test_admin_color_scheme_picker_invalid_selection_fallback() {
		global $_wp_admin_css_colors;

		$_wp_admin_css_colors = array(
			'fresh'  => (object) array(
				'name'        => 'Default',
				'url'         => 'fresh.css',
				'colors'      => array( '#1d2327' ),
				'icon_colors' => array( 'base' => '#a7aaad' ),
			),
			'modern' => (object) array(
				'name'        => 'Modern',
				'url'         => 'modern.css',
				'colors'      => array( '#1e1e1e' ),
				'icon_colors' => array( 'base' => '#f0f0f1' ),
			),
		);

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'admin_color', 'non-existent' );
		wp_set_current_user( $user_id );

		ob_start();
		admin_color_scheme_picker( $user_id );
		$output = ob_get_clean();

		// Should fallback to 'modern'.
		$this->assertStringContainsString( 'id="admin_color_modern" type="radio" value="modern"  checked=\'checked\'', $output );
	}

	/**
	 * @ticket 65184
	 */
	public function test_admin_color_scheme_picker_sorting() {
		global $_wp_admin_css_colors;

		$_wp_admin_css_colors = array(
			'ocean'  => (object) array(
				'name' => 'Ocean',
				'url' => '',
				'colors' => array(),
				'icon_colors' => array(),
			),
			'fresh'  => (object) array(
				'name' => 'Default',
				'url' => '',
				'colors' => array(),
				'icon_colors' => array(),
			),
			'light'  => (object) array(
				'name' => 'Light',
				'url' => '',
				'colors' => array(),
				'icon_colors' => array(),
			),
			'modern' => (object) array(
				'name' => 'Modern',
				'url' => '',
				'colors' => array(),
				'icon_colors' => array(),
			),
			'coffee' => (object) array(
				'name' => 'Coffee',
				'url' => '',
				'colors' => array(),
				'icon_colors' => array(),
			),
		);

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		ob_start();
		admin_color_scheme_picker( $user_id );
		$output = ob_get_clean();

		// The function does ksort first, then if 'modern' exists, it merges with prioritized list.
		// array( 'modern' => '', 'fresh' => '', 'light' => '' ) + $_wp_admin_css_colors
		// The result should have modern, fresh, light first, then the rest sorted alphabetically.

		$dom = new DOMDocument();
		// Suppress warnings for HTML5 elements or invalid HTML.
		libxml_use_internal_errors( true );
		$dom->loadHTML( $output );
		libxml_clear_errors();

		$inputs = $dom->getElementsByTagName( 'input' );
		$found_colors = array();
		foreach ( $inputs as $input ) {
			if ( 'radio' === $input->getAttribute( 'type' ) ) {
				$found_colors[] = $input->getAttribute( 'value' );
			}
		}

		$expected_order = array( 'modern', 'fresh', 'light', 'coffee', 'ocean' );
		$this->assertEquals( $expected_order, $found_colors, 'Color schemes should be ordered with modern, fresh, light first, followed by others alphabetically.' );
	}

	/**
	 * @ticket 65184
	 */
	public function test_admin_color_scheme_picker_html_structure() {
		global $_wp_admin_css_colors;

		$_wp_admin_css_colors = array(
			'modern' => (object) array(
				'name'        => 'Modern Display Name',
				'url'         => 'https://example.com/modern.css',
				'colors'      => array( '#111', '#222' ),
				'icon_colors' => array( 'icons' => '#333' ),
			),
		);

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		ob_start();
		admin_color_scheme_picker( $user_id );
		$output = ob_get_clean();

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $output );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Check fieldset and legend.
		$this->assertEquals( 1, $xpath->query( '//fieldset[@id="color-picker"]' )->length );
		$this->assertEquals( 1, $xpath->query( '//legend' )->length );

		// Check radio input.
		$radio = $xpath->query( '//input[@type="radio"][@value="modern"]' );
		$this->assertEquals( 1, $radio->length );
		$this->assertEquals( 'admin_color_modern', $radio->item( 0 )->getAttribute( 'id' ) );

		// Check hidden inputs.
		$this->assertEquals( 1, $xpath->query( '//input[@type="hidden"][@class="css_url"][@value="https://example.com/modern.css"]' )->length );

		$icon_colors_input = $xpath->query( '//input[@type="hidden"][@class="icon_colors"]' );
		$this->assertEquals( 1, $icon_colors_input->length );
		$json_data = json_decode( $icon_colors_input->item( 0 )->getAttribute( 'value' ), true );
		$this->assertEquals( array( 'icons' => array( 'icons' => '#333' ) ), $json_data );

		// Check label.
		$label = $xpath->query( '//label[@for="admin_color_modern"]' );
		$this->assertEquals( 1, $label->length );
		$this->assertStringContainsString( 'Modern Display Name', $label->item( 0 )->nodeValue );

		// Check color palette.
		$this->assertEquals( 2, $xpath->query( '//div[@class="color-palette-shade"]' )->length );
		$this->assertStringContainsString( 'background-color: #111', $output );
		$this->assertStringContainsString( 'background-color: #222', $output );
	}
}
