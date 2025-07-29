<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	private $png_cdn = 'https://s.w.org/images/core/emoji/15.0.3/72x72/';
	private $svn_cdn = 'https://s.w.org/images/core/emoji/15.0.3/svg/';
	private $original_theme_support;

	/**
	 * Set up test environment for HTML5 script type attribute tests.
	 */
	public function set_up() {
		parent::set_up();

		global $_wp_theme_features;
		$this->original_theme_support = $_wp_theme_features;
	}

	/**
	 * Tear down test environment for HTML5 script type attribute tests.
	 */
	public function tear_down() {
		global $_wp_theme_features;
		$_wp_theme_features = $this->original_theme_support;

		parent::tear_down();
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

		$this->assertStringContainsString( wp_json_encode( $this->png_cdn ), $output );
		$this->assertStringContainsString( wp_json_encode( $this->svn_cdn ), $output );
	}

	public function _filtered_emoji_svn_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/svg/';
	}

	/**
	 * @ticket 36525
	 *
	 * @covers ::_print_emoji_detection_script
	 */
	public function test_filtered_emoji_svn_cdn() {
		$filtered_svn_cdn = $this->_filtered_emoji_svn_cdn();

		add_filter( 'emoji_svg_url', array( $this, '_filtered_emoji_svn_cdn' ) );

		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present:
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );

		$this->assertStringContainsString( wp_json_encode( $this->png_cdn ), $output );
		$this->assertStringNotContainsString( wp_json_encode( $this->svn_cdn ), $output );
		$this->assertStringContainsString( wp_json_encode( $filtered_svn_cdn ), $output );

		remove_filter( 'emoji_svg_url', array( $this, '_filtered_emoji_svn_cdn' ) );
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

		$this->assertStringContainsString( wp_json_encode( $filtered_png_cdn ), $output );
		$this->assertStringNotContainsString( wp_json_encode( $this->png_cdn ), $output );
		$this->assertStringContainsString( wp_json_encode( $this->svn_cdn ), $output );

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

	/**
	 * Test emoji detection script typeAttr setting based on HTML5 script support.
	 *
	 * @ticket 51837
	 * @dataProvider data_html5_script_support
	 */
	public function test_emoji_detection_script_html5_type_attribute( $html5_features, $expected_has_type ) {
		remove_theme_support( 'html5' );
		if ( ! empty( $html5_features ) ) {
			add_theme_support( 'html5', $html5_features );
		}
		
		// `_print_emoji_detection_script()` assumes `wp-includes/js/wp-emoji-loader.js` is present.
		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( '_print_emoji_detection_script' );
		
		if ( $expected_has_type ) {
			$this->assertStringContainsString( '"typeAttr":" type=\\"text\/javascript\\""', $output );
		} else {
			$this->assertStringContainsString( '"typeAttr":""', $output );
		}
		
		$this->assertStringContainsString( 'window._wpemojiSettings', $output );
	}

	/**
	 * Test zxcvbn settings typeAttr based on HTML5 script support.
	 *
	 * @ticket 51837
	 * @dataProvider data_html5_script_support
	 */
	public function test_zxcvbn_settings_html5_type_attribute( $html5_features, $expected_has_type ) {
		remove_theme_support( 'html5' );
		if ( ! empty( $html5_features ) ) {
			add_theme_support( 'html5', $html5_features );
		}

		global $wp_scripts;
		$wp_scripts = null;
		wp_default_scripts( wp_scripts() );

		$script_data = wp_scripts()->get_data( 'zxcvbn-async', 'data' );
		
		if ( $expected_has_type ) {
			$this->assertStringContainsString( '"typeAttr":" type=\\"text\/javascript\\""', $script_data );
		} else {
			$this->assertStringContainsString( '"typeAttr":""', $script_data );
		}
		
		$this->assertStringContainsString( '"src":', $script_data );
	}

	/**
	 * Data provider for HTML5 script support scenarios.
	 *
	 * @return array
	 */
	public function data_html5_script_support() {
		return array(
			'no html5 support' => array(
				array(),
				true,
			),
			'html5 script support' => array(
				array( 'script' ),
				false,
			),
			'html5 other features only' => array(
				array( 'comment-form', 'style' ),
				true,
			),
		);
	}
}
