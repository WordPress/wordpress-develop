<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	private $png_cdn = 'https://s.w.org/images/core/emoji/16.0.1/72x72/';
	private $svg_cdn = 'https://s.w.org/images/core/emoji/16.0.1/svg/';

	/**
	 * @ticket 63842
	 *
	 * @covers ::_print_emoji_detection_script
	 */
	public function test_script_tag_printing() {
		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'SCRIPT', $processor->get_tag() );
		$this->assertSame( 'wp-emoji-settings', $processor->get_attribute( 'id' ) );
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
		$this->assertNull( $processor->get_attribute( 'src' ) );
		$this->assertFalse( $processor->next_tag() );
	}

	/**
	 * @ticket 36525
	 *
	 * @covers ::_print_emoji_detection_script
	 */
	public function test_unfiltered_emoji_cdns() {
		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );

		$this->assertStringContainsString( wp_json_encode( $this->png_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
		$this->assertStringContainsString( wp_json_encode( $this->svg_cdn, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ), $output );
	}

	public function _filtered_emoji_svg_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/svg/';
	}

	/**
	 * @ticket 36525
	 *
	 * @covers ::_print_emoji_detection_script
	 */
	public function test_filtered_emoji_svn_cdn() {
		$filtered_svn_cdn = $this->_filtered_emoji_svg_cdn();

		add_filter( 'emoji_svg_url', array( $this, '_filtered_emoji_svg_cdn' ) );

		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );

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
	 *
	 * @covers ::_print_emoji_detection_script
	 */
	public function test_filtered_emoji_png_cdn() {
		$filtered_png_cdn = $this->_filtered_emoji_png_cdn();

		add_filter( 'emoji_url', array( $this, '_filtered_emoji_png_cdn' ) );

		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );

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
		// Emoji 15 contains 3718 entities, this number will only increase.
		$this->assertGreaterThanOrEqual( 3718, count( $entities ), 'Entities should contain at least 3718 items' );
		$this->assertSame( $default, $entities, 'Entities should be returned by default' );

		$partials = _wp_emoji_list( 'partials' );
		$this->assertNotEmpty( $partials, 'Partials should not be empty' );
		$this->assertIsArray( $partials, 'Partials should be an array' );
		// Emoji 15 contains 1424 partials, this number will only increase.
		$this->assertGreaterThanOrEqual( 1424, count( $partials ), 'Partials should contain at least 1424 items' );

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
