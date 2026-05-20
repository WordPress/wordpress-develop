<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	private $png_cdn = 'https://s.w.org/images/core/emoji/17.0.2/72x72/';
	private $svg_cdn = 'https://s.w.org/images/core/emoji/17.0.2/svg/';

	/**
	 * @var WP_Script_Modules|null
	 */
	private $original_script_modules;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		global $wp_script_modules;

		parent::set_up();

		$this->original_script_modules = $wp_script_modules;
		$wp_script_modules             = null;
		wp_script_modules();
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		global $wp_script_modules;

		$wp_script_modules = $this->original_script_modules;
		parent::tear_down();
	}

	/**
	 * Returns the markup printed for the emoji detection script module.
	 *
	 * @return string Emoji detection script module markup.
	 */
	private function get_emoji_detection_script_output() {
		// `_wp_enqueue_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );

		_wp_enqueue_emoji_detection_script();

		return get_echo( array( wp_script_modules(), 'print_script_module_data' ) ) .
			get_echo( array( wp_script_modules(), 'print_enqueued_script_modules' ) );
	}

	/**
	 * @ticket 63842
	 * @ticket 64259
	 *
	 * @covers ::_wp_enqueue_emoji_detection_script
	 * @covers ::_wp_get_emoji_settings
	 * @covers ::_wp_emoji_settings_script_module_data
	 */
	public function test_script_tag_printing() {
		$output = $this->get_emoji_detection_script_output();

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SCRIPT', $processor->get_tag() );
		$this->assertSame( 'wp-script-module-data-wp-emoji-loader', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'application/json', $processor->get_attribute( 'type' ) );
		$text     = $processor->get_modifiable_text();
		$settings = json_decode( $text, true );
		$this->assertIsArray( $settings );

		$this->assertEqualSets(
			array( 'baseUrl', 'ext', 'svgUrl', 'svgExt', 'source' ),
			array_keys( $settings )
		);
		$this->assertSame( $this->png_cdn, $settings['baseUrl'] );
		$this->assertSame( '.png', $settings['ext'] );
		$this->assertSame( $this->svg_cdn, $settings['svgUrl'] );
		$this->assertSame( '.svg', $settings['svgExt'] );
		$this->assertIsArray( $settings['source'] );
		$this->assertArrayHasKey( 'wpemoji', $settings['source'] );
		$this->assertArrayHasKey( 'twemoji', $settings['source'] );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SCRIPT', $processor->get_tag() );
		$this->assertSame( 'module', $processor->get_attribute( 'type' ) );
		$this->assertSame( 'low', $processor->get_attribute( 'fetchpriority' ) );
		$this->assertStringContainsString( 'wp-emoji-loader.js', (string) $processor->get_attribute( 'src' ) );
		$this->assertFalse( $processor->next_tag() );
	}

	/**
	 * @ticket 36525
	 * @ticket 64259
	 *
	 * @covers ::_wp_enqueue_emoji_detection_script
	 */
	public function test_unfiltered_emoji_cdns() {
		$output = $this->get_emoji_detection_script_output();

		$this->assertStringContainsString( wp_json_encode( $this->png_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringContainsString( wp_json_encode( $this->svg_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
	}

	public function _filtered_emoji_svg_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/svg/';
	}

	/**
	 * @ticket 36525
	 * @ticket 64259
	 *
	 * @covers ::_wp_enqueue_emoji_detection_script
	 */
	public function test_filtered_emoji_svn_cdn() {
		$filtered_svn_cdn = $this->_filtered_emoji_svg_cdn();

		add_filter( 'emoji_svg_url', array( $this, '_filtered_emoji_svg_cdn' ) );

		$output = $this->get_emoji_detection_script_output();

		$this->assertStringContainsString( wp_json_encode( $this->png_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringNotContainsString( wp_json_encode( $this->svg_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringContainsString( wp_json_encode( $filtered_svn_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );

		remove_filter( 'emoji_svg_url', array( $this, '_filtered_emoji_svg_cdn' ) );
	}

	public function _filtered_emoji_png_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/png_cdn/';
	}

	/**
	 * @ticket 36525
	 * @ticket 64259
	 *
	 * @covers ::_wp_enqueue_emoji_detection_script
	 */
	public function test_filtered_emoji_png_cdn() {
		$filtered_png_cdn = $this->_filtered_emoji_png_cdn();

		add_filter( 'emoji_url', array( $this, '_filtered_emoji_png_cdn' ) );

		$output = $this->get_emoji_detection_script_output();

		$this->assertStringContainsString( wp_json_encode( $filtered_png_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringNotContainsString( wp_json_encode( $this->png_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringContainsString( wp_json_encode( $this->svg_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );

		remove_filter( 'emoji_url', array( $this, '_filtered_emoji_png_cdn' ) );
	}

	/**
	 * @ticket 41501
	 *
	 * @covers ::_wp_emoji_list
	 */
	public function test_wp_emoji_list_returns_data() {
		$default = _wp_emoji_list();
		$this->assertNotEmpty( $default, 'Default should not be empty' );

		$entities = _wp_emoji_list( 'entities' );
		$this->assertNotEmpty( $entities, 'Entities should not be empty' );
		$this->assertIsArray( $entities, 'Entities should be an array' );
		// Emoji 17 contains 4007 entities, this number will only increase.
		$this->assertGreaterThanOrEqual( 4007, count( $entities ), 'Entities should contain at least 4007 items' );
		$this->assertSame( $default, $entities, 'Entities should be returned by default' );

		$partials = _wp_emoji_list( 'partials' );
		$this->assertNotEmpty( $partials, 'Partials should not be empty' );
		$this->assertIsArray( $partials, 'Partials should be an array' );
		// Emoji 17 contains 1438 partials, this number will only increase.
		$this->assertGreaterThanOrEqual( 1438, count( $partials ), 'Partials should contain at least 1438 items' );

		$this->assertNotSame( $default, $partials );
	}

	public function data_wp_encode_emoji() {
		return array(
			array(
				// Not emoji.
				'’',
				'’',
			),
			array(
				// Simple emoji.
				'🙂',
				'&#x1f642;',
			),
			array(
				// Bird, ZWJ, black large square, emoji selector.
				'🐦‍⬛',
				'&#x1f426;&#x200d;&#x2b1b;',
			),
			array(
				// Unicode 10.
				'🧚',
				'&#x1f9da;',
			),
			array(
				// Hairy creature (Unicode 17).
				'🫈',
				'&#x1fac8;',
			),
		);
	}

	/**
	 * @ticket 35293
	 * @dataProvider data_wp_encode_emoji
	 *
	 * @covers ::wp_encode_emoji
	 */
	public function test_wp_encode_emoji( $emoji, $expected ) {
		$this->assertSame( $expected, wp_encode_emoji( $emoji ) );
	}

	public function data_wp_staticize_emoji() {
		$data = array(
			array(
				// Not emoji.
				'’',
				'’',
			),
			array(
				// Simple emoji.
				'🙂',
				'<img src="' . $this->png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				// Skin tone, gender, ZWJ, emoji selector.
				'👮🏼‍♀️',
				'<img src="' . $this->png_cdn . '1f46e-1f3fc-200d-2640-fe0f.png" alt="👮🏼‍♀️" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				// Unicode 10.
				'🧚',
				'<img src="' . $this->png_cdn . '1f9da.png" alt="🧚" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
			array(
				// Hairy creature (Unicode 17).
				'🫈',
				'<img src="' . $this->png_cdn . '1fac8.png" alt="🫈" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
			),
		);

		return $data;
	}

	/**
	 * @ticket 35293
	 * @dataProvider data_wp_staticize_emoji
	 *
	 * @covers ::wp_staticize_emoji
	 */
	public function test_wp_staticize_emoji( $emoji, $expected ) {
		$this->assertSame( $expected, wp_staticize_emoji( $emoji ) );
	}
}
