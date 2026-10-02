<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	private $png_cdn = 'https://s.w.org/images/core/emoji/17.0.2/72x72/';
	private $svg_cdn = 'https://s.w.org/images/core/emoji/17.0.2/svg/';

	/**
	 * Tests that the emoji detection script is hooked onto the front end footer
	 * when the footer scripts have not yet been printed.
	 *
	 * @ticket 64076
	 * @ticket 65310
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @covers ::print_emoji_detection_script
	 */
	public function test_print_emoji_detection_script_on_front_end(): void {
		$this->assertFalse( is_admin(), 'Expected to not be in the admin.' );
		$this->assertFalse(
			has_action( 'wp_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not yet be hooked onto wp_print_footer_scripts.'
		);

		print_emoji_detection_script();

		$this->assertSame(
			10,
			has_action( 'wp_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to be hooked onto wp_print_footer_scripts.'
		);
		$this->assertFalse(
			has_action( 'admin_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not be hooked onto admin_print_footer_scripts.'
		);
	}

	/**
	 * Tests that the emoji detection script is printed directly when the front
	 * end footer scripts have already been printed.
	 *
	 * @ticket 64076
	 * @ticket 65310
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @covers ::print_emoji_detection_script
	 */
	public function test_print_emoji_detection_script_on_front_end_after_footer_scripts_printed(): void {
		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );

		$this->assertFalse( is_admin(), 'Expected to not be in the admin.' );

		// Fire (and discard the output of) the footer scripts action so it counts as already done.
		get_echo( 'do_action', array( 'wp_print_footer_scripts' ) );
		$this->assertGreaterThanOrEqual(
			1,
			did_action( 'wp_print_footer_scripts' ),
			'Expected the wp_print_footer_scripts action to have fired.'
		);

		$output = get_echo( 'print_emoji_detection_script' );

		$this->assertStringContainsString(
			'wp-emoji-settings',
			$output,
			'Expected the emoji detection script to be printed directly.'
		);
		$this->assertFalse(
			has_action( 'wp_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not be hooked since it was printed directly.'
		);

		// A subsequent call should short-circuit via the static $printed guard and print nothing.
		$output = get_echo( 'print_emoji_detection_script' );
		$this->assertSame(
			'',
			$output,
			'Expected nothing to be printed on a subsequent call due to the static $printed guard.'
		);
	}

	/**
	 * Tests that the emoji detection script is hooked onto the admin footer
	 * when the footer scripts have not yet been printed.
	 *
	 * @ticket 64076
	 * @ticket 65310
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @covers ::print_emoji_detection_script
	 */
	public function test_print_emoji_detection_script_in_admin(): void {
		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin(), 'Expected to be in the admin.' );
		$this->assertFalse(
			has_action( 'admin_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not yet be hooked onto admin_print_footer_scripts.'
		);

		print_emoji_detection_script();

		$this->assertSame(
			10,
			has_action( 'admin_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to be hooked onto admin_print_footer_scripts.'
		);
		$this->assertFalse(
			has_action( 'wp_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not be hooked onto wp_print_footer_scripts.'
		);
	}

	/**
	 * Tests that the emoji detection script is printed directly when the admin
	 * footer scripts have already been printed.
	 *
	 * @ticket 64076
	 * @ticket 65310
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @covers ::print_emoji_detection_script
	 */
	public function test_print_emoji_detection_script_in_admin_after_footer_scripts_printed(): void {
		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );

		set_current_screen( 'edit-post' );
		$this->assertTrue( is_admin(), 'Expected to be in the admin.' );

		// Fire (and discard the output of) the footer scripts action so it counts as already done.
		get_echo( 'do_action', array( 'admin_print_footer_scripts' ) );
		$this->assertGreaterThanOrEqual(
			1,
			did_action( 'admin_print_footer_scripts' ),
			'Expected the admin_print_footer_scripts action to have fired.'
		);

		$output = get_echo( 'print_emoji_detection_script' );

		$this->assertStringContainsString(
			'wp-emoji-settings',
			$output,
			'Expected the emoji detection script to be printed directly.'
		);
		$this->assertFalse(
			has_action( 'admin_print_footer_scripts', '_print_emoji_detection_script' ),
			'Expected _print_emoji_detection_script to not be hooked since it was printed directly.'
		);

		// A subsequent call should short-circuit via the static $printed guard and print nothing.
		$output = get_echo( 'print_emoji_detection_script' );
		$this->assertSame(
			'',
			$output,
			'Expected nothing to be printed on a subsequent call due to the static $printed guard.'
		);
	}

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

	/**
	 * Tests that emoji inside ignored tags are not staticized, with or without attributes.
	 *
	 * @ticket 66134
	 * @dataProvider data_wp_staticize_emoji_ignored_tags
	 *
	 * @covers ::wp_staticize_emoji
	 *
	 * @param string $element The ignored element name.
	 */
	public function test_wp_staticize_emoji_ignores_tags_with_attributes( $element ) {
		// U+1F642 as the HTML entity produced by _wp_emoji_list( 'entities' ).
		$emoji = '&#x1f642;';

		$no_attr = "<$element>$emoji</$element>";
		$this->assertSame( $no_attr, wp_staticize_emoji( $no_attr ), "Emoji inside <$element> should not be staticized." );

		$with_attr = "<$element data-foo=\"bar\">$emoji</$element>";
		$this->assertSame( $with_attr, wp_staticize_emoji( $with_attr ), "Emoji inside <$element data-foo=\"...\"> should not be staticized." );
	}

	/**
	 * Data provider for test_wp_staticize_emoji_ignores_tags_with_attributes().
	 *
	 * @return array[]
	 */
	public function data_wp_staticize_emoji_ignored_tags() {
		return array(
			'code'     => array( 'code' ),
			'pre'      => array( 'pre' ),
			'style'    => array( 'style' ),
			'script'   => array( 'script' ),
			'textarea' => array( 'textarea' ),
		);
	}

	/**
	 * Tests where the ignore block starts and ends, by counting staticized emoji.
	 *
	 * @ticket 66134
	 * @dataProvider data_wp_staticize_emoji_ignore_block_boundaries
	 *
	 * @covers ::wp_staticize_emoji
	 *
	 * @param string $text     The content to staticize.
	 * @param int    $expected The expected number of staticized emoji.
	 */
	public function test_wp_staticize_emoji_ignore_block_boundaries( $text, $expected ) {
		$this->assertSame( $expected, substr_count( wp_staticize_emoji( $text ), 'class="wp-smiley"' ) );
	}

	/**
	 * Data provider for test_wp_staticize_emoji_ignore_block_boundaries().
	 *
	 * @return array[]
	 */
	public function data_wp_staticize_emoji_ignore_block_boundaries() {
		return array(
			'emoji after closing tag is staticized'        => array( '<pre class="wp-block-code">&#x1f642;</pre>&#x1f642;', 1 ),
			'code block markup'                            => array( '<pre class="wp-block-code"><code>&#x1f642;</code></pre>', 0 ),
			'raw emoji character'                          => array( "<pre class=\"wp-block-code\">\u{1F642}</pre>\u{1F642}", 1 ),
			'slash after tag name'                         => array( '<textarea/>&#x1f642;</textarea>&#x1f642;', 1 ),
			'uppercase tags'                               => array( '<PRE class="foo">&#x1f642;</PRE>&#x1f642;', 1 ),
			'whitespace in closing tag'                    => array( '<pre>&#x1f642;</pre >&#x1f642;', 1 ),
			'custom element starting with ignored name'    => array( '<code-snippet>&#x1f642;</code-snippet>&#x1f642;', 2 ),
			'unknown element starting with ignored name'   => array( '<preview>&#x1f642;</preview>&#x1f642;', 2 ),
			'element name prefixed by ignored script name' => array( '<script-loader>&#x1f642;</script-loader>&#x1f642;', 2 ),
		);
	}
}
