<?php
/**
 * @group dependencies
 * @group scripts
 * @covers ::wp_enqueue_style
 * @covers ::wp_register_style
 * @covers ::wp_print_styles
 * @covers ::wp_style_add_data
 * @covers ::wp_add_inline_style
 */
class Tests_Dependencies_Styles extends WP_UnitTestCase {
	private $old_wp_styles;
	private $old_wp_scripts;

	public function set_up() {
		parent::set_up();

		if ( empty( $GLOBALS['wp_styles'] ) ) {
			$GLOBALS['wp_styles'] = null;
		}

		$this->old_wp_styles = $GLOBALS['wp_styles'];

		if ( empty( $GLOBALS['wp_scripts'] ) ) {
			$GLOBALS['wp_scripts'] = null;
		}

		$this->old_wp_styles = $GLOBALS['wp_scripts'];

		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$GLOBALS['wp_styles']                  = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );
	}

	public function tear_down() {
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;

		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

		if ( current_theme_supports( 'wp-block-styles' ) ) {
			remove_theme_support( 'wp-block-styles' );
		}

		parent::tear_down();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 * @ticket 64372
	 */
	public function test_wp_enqueue_style() {
		wp_enqueue_style( 'no-deps-no-version', 'example.com' );
		wp_enqueue_style( 'no-deps-version', 'example.com', array(), '1.2' );
		wp_enqueue_style( 'no-deps-null-version', 'example.com', array(), null );
		wp_enqueue_style( 'no-deps-null-version-print-media', 'example.com', array(), null, 'print' );
		wp_enqueue_style( 'no-deps-arg-in-handle-with-ver?arg1=foo&arg2=bar', 'https://example.com/test.css', array(), '2.0' );
		wp_enqueue_style( 'no-deps-arg-in-handle-without-ver?arg1=foo&arg2=bar', 'https://example.com/test.css', array(), null );
		wp_register_style( 'registered-no-qs-handle-null-version-enqueued-with-qs', 'https://example.com/test.css' );
		wp_enqueue_style( 'registered-no-qs-handle-null-version-enqueued-with-qs?arg1=foo&arg2=bar' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<link rel='stylesheet' id='no-deps-no-version-css' href='http://example.com?ver=$ver' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-version-css' href='http://example.com?ver=1.2' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-null-version-css' href='http://example.com' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-null-version-print-media-css' href='http://example.com' media='print' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-arg-in-handle-with-ver-css' href='https://example.com/test.css?ver=2.0&#038;arg1=foo&#038;arg2=bar' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='no-deps-arg-in-handle-without-ver-css' href='https://example.com/test.css?arg1=foo&#038;arg2=bar' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='registered-no-qs-handle-null-version-enqueued-with-qs-css' href='https://example.com/test.css?ver={$ver}&#038;arg1=foo&#038;arg2=bar' media='all' />\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );

		// No styles left to print.
		$this->assertSame( '', get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @covers WP_Styles::do_item
	 */
	public function test_style_attributes_filter_runs_before_legacy_style_loader_tag_filter() {
		$source             = 'https://example.com/style.css?one=1&two=2';
		$filtered_attrs     = array();
		$legacy_filter_args = array();

		$attributes_filter = static function ( $attributes ) use ( &$filtered_attrs ) {
			$filtered_attrs[]    = $attributes;
			$attributes['nonce'] = 'style-nonce';
			return $attributes;
		};

		$legacy_filter = static function ( $tag, $handle, $href, $media ) use ( &$legacy_filter_args ) {
			$processor = new WP_HTML_Tag_Processor( $tag );
			$processor->next_tag( 'link' );

			$legacy_filter_args[] = array(
				'handle' => $handle,
				'href'   => $href,
				'media'  => $media,
				'nonce'  => $processor->get_attribute( 'nonce' ),
				'tag'    => $tag,
			);

			return $tag;
		};

		add_filter( 'wp_style_attributes', $attributes_filter );
		add_filter( 'style_loader_tag', $legacy_filter, 10, 4 );

		wp_enqueue_style( 'filtered-style', $source, array(), null, 'screen' );
		$markup = get_echo( 'wp_print_styles' );

		remove_filter( 'wp_style_attributes', $attributes_filter );
		remove_filter( 'style_loader_tag', $legacy_filter, 10 );

		$this->assertCount( 1, $filtered_attrs );
		$this->assertSame( $source, $filtered_attrs[0]['href'] );
		$this->assertSame(
			array(
				'handle' => 'filtered-style',
				'href'   => esc_url( $source ),
				'media'  => 'screen',
				'nonce'  => 'style-nonce',
			),
			array_diff_key( $legacy_filter_args[0], array( 'tag' => true ) )
		);
		$this->assertStringNotContainsString( '"', $legacy_filter_args[0]['tag'] );

		$processor = new WP_HTML_Tag_Processor( $markup );
		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( 'style-nonce', $processor->get_attribute( 'nonce' ) );
		$this->assertSame( $source, $processor->get_attribute( 'href' ) );
	}

	/**
	 * @covers WP_Styles::do_item
	 */
	public function test_style_attribute_quotes_do_not_create_extra_attributes() {
		$attribute_value = "It's O'Reilly's \"quoted\" text; data-injected=\"should-remain-text\"";
		$filter          = static function ( $attributes ) use ( $attribute_value ) {
			$attributes['data-quote-test'] = $attribute_value;
			return $attributes;
		};

		add_filter( 'wp_style_attributes', $filter );

		wp_enqueue_style( 'quoted-style', 'https://example.com/style.css', array(), null );
		$markup = get_echo( 'wp_print_styles' );

		remove_filter( 'wp_style_attributes', $filter );

		$processor = new WP_HTML_Tag_Processor( $markup );
		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( $attribute_value, $processor->get_attribute( 'data-quote-test' ) );
		$this->assertNull( $processor->get_attribute( 'data-injected' ) );
	}

	/**
	 * @covers WP_Styles::do_item
	 */
	public function test_rtl_alternate_styles_use_both_attribute_and_legacy_filters() {
		global $wp_styles;

		$wp_styles->text_direction = 'rtl';

		$filtered_attrs = array();
		$legacy_hrefs   = array();

		$attributes_filter = static function ( $attributes ) use ( &$filtered_attrs ) {
			$filtered_attrs[]          = $attributes;
			$attributes['data-filter'] = 'applied';
			return $attributes;
		};

		$legacy_filter = static function ( $tag, $handle, $href, $media ) use ( &$legacy_hrefs ) {
			$legacy_hrefs[] = $href;
			return $tag;
		};

		add_filter( 'wp_style_attributes', $attributes_filter );
		add_filter( 'style_loader_tag', $legacy_filter, 10, 4 );

		wp_enqueue_style( 'theme-style', 'https://example.com/theme.css', array(), null );
		wp_style_add_data( 'theme-style', 'alt', true );
		wp_style_add_data( 'theme-style', 'title', 'Alternate theme' );
		wp_style_add_data( 'theme-style', 'rtl', true );

		$markup = get_echo( 'wp_print_styles' );

		remove_filter( 'wp_style_attributes', $attributes_filter );
		remove_filter( 'style_loader_tag', $legacy_filter, 10 );

		$tags      = array();
		$processor = new WP_HTML_Tag_Processor( $markup );
		while ( $processor->next_tag( 'link' ) ) {
			$tags[] = array(
				'id'          => $processor->get_attribute( 'id' ),
				'rel'         => $processor->get_attribute( 'rel' ),
				'title'       => $processor->get_attribute( 'title' ),
				'href'        => $processor->get_attribute( 'href' ),
				'data-filter' => $processor->get_attribute( 'data-filter' ),
			);
		}

		$this->assertCount( 2, $filtered_attrs );
		$this->assertCount( 2, $legacy_hrefs );
		$this->assertSame( 'alternate stylesheet', $tags[0]['rel'] );
		$this->assertSame( 'theme-style-css', $tags[0]['id'] );
		$this->assertSame( 'Alternate theme', $tags[0]['title'] );
		$this->assertSame( 'https://example.com/theme.css', $tags[0]['href'] );
		$this->assertSame( 'theme-style-rtl-css', $tags[1]['id'] );
		$this->assertSame( 'https://example.com/theme-rtl.css', $tags[1]['href'] );
		$this->assertSame( 'applied', $tags[0]['data-filter'] );
		$this->assertSame( 'applied', $tags[1]['data-filter'] );
		$this->assertSame( esc_url( 'https://example.com/theme.css' ), $legacy_hrefs[0] );
		$this->assertSame( esc_url( 'https://example.com/theme-rtl.css' ), $legacy_hrefs[1] );
	}

	/**
	 * @covers ::_print_styles
	 */
	public function test_concatenated_styles_apply_style_attributes_once_without_legacy_filter() {
		global $wp_styles;

		$wp_styles->do_concat    = true;
		$wp_styles->default_dirs = array( '/wp-admin/' );

		$attributes_filter_calls = 0;
		$legacy_filter_calls     = 0;

		$attributes_filter = static function ( $attributes ) use ( &$attributes_filter_calls ) {
			++$attributes_filter_calls;
			$attributes['nonce'] = 'concatenated-style';
			return $attributes;
		};

		$legacy_filter = static function ( $tag ) use ( &$legacy_filter_calls ) {
			++$legacy_filter_calls;
			return $tag;
		};

		add_filter( 'wp_style_attributes', $attributes_filter );
		add_filter( 'style_loader_tag', $legacy_filter );

		wp_enqueue_style( 'concat-one', '/wp-admin/one.css', array(), null );
		wp_enqueue_style( 'concat-two', '/wp-admin/two.css', array(), null );

		wp_print_styles();
		$markup = get_echo( '_print_styles' );

		remove_filter( 'wp_style_attributes', $attributes_filter );
		remove_filter( 'style_loader_tag', $legacy_filter );

		$this->assertSame( 1, $attributes_filter_calls );
		$this->assertSame( 0, $legacy_filter_calls );

		$processor = new WP_HTML_Tag_Processor( $markup );
		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( 'concatenated-style', $processor->get_attribute( 'nonce' ) );
		$this->assertStringContainsString( 'load-styles.php', $processor->get_attribute( 'href' ) );
	}

	/**
	 * @covers ::wp_admin_css
	 */
	public function test_wp_admin_css_uses_style_attributes_and_legacy_filter() {
		$legacy_files  = array();
		$legacy_tags   = array();
		$filter        = static function ( $attributes ) {
			$attributes['nonce'] = 'admin-style';
			return $attributes;
		};
		$legacy_filter = static function ( $tag, $file ) use ( &$legacy_files, &$legacy_tags ) {
			$legacy_files[] = $file;
			$legacy_tags[]  = $tag;
			$processor      = new WP_HTML_Tag_Processor( $tag );
			$processor->next_tag( 'link' );
			if ( 'admin-style' !== $processor->get_attribute( 'nonce' ) ) {
				return '';
			}
			return $tag;
		};

		add_filter( 'wp_style_attributes', $filter );
		add_filter( 'wp_admin_css', $legacy_filter, 10, 2 );

		$markup = get_echo( 'wp_admin_css', array( 'stylesheet-link-api-test' ) );

		remove_filter( 'wp_style_attributes', $filter );
		remove_filter( 'wp_admin_css', $legacy_filter, 10 );

		$this->assertSame( array( 'stylesheet-link-api-test' ), $legacy_files );
		$this->assertStringNotContainsString( '"', $legacy_tags[0] );
		$processor = new WP_HTML_Tag_Processor( $markup );
		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( 'admin-style', $processor->get_attribute( 'nonce' ) );
		$this->assertStringContainsString( 'stylesheet-link-api-test.css', $processor->get_attribute( 'href' ) );
	}

	/**
	 * @covers ::locale_stylesheet
	 */
	public function test_locale_stylesheet_uses_style_attributes_filter() {
		$stylesheet_uri_filter = static function ( $stylesheet_uri, $stylesheet_dir_uri ) {
			return 'https://example.com/locale.css?one=1&two=2';
		};
		$attributes_filter     = static function ( $attributes ) {
			$attributes['nonce'] = 'locale-style';
			return $attributes;
		};

		add_filter( 'locale_stylesheet_uri', $stylesheet_uri_filter, 10, 2 );
		add_filter( 'wp_style_attributes', $attributes_filter );

		$markup = get_echo( 'locale_stylesheet' );

		remove_filter( 'locale_stylesheet_uri', $stylesheet_uri_filter, 10 );
		remove_filter( 'wp_style_attributes', $attributes_filter );

		$processor = new WP_HTML_Tag_Processor( $markup );
		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( 'locale-style', $processor->get_attribute( 'nonce' ) );
		$this->assertSame( 'https://example.com/locale.css?one=1&two=2', $processor->get_attribute( 'href' ) );
	}

	/**
	 * @ticket 42804
	 */
	public function test_wp_enqueue_style_with_html5_support_does_not_contain_type_attribute() {
		add_theme_support( 'html5', array( 'style' ) );

		$GLOBALS['wp_styles']                  = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );

		wp_enqueue_style( 'no-deps-no-version', 'example.com' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<link rel='stylesheet' id='no-deps-no-version-css' href='http://example.com?ver=$ver' media='all' />\n";

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test assorted handles to make sure they are output correctly.
	 *
	 * @dataProvider data_awkward_handles_are_supported_consistently
	 *
	 * @ticket 30036
	 */
	public function test_awkward_handles_are_supported_consistently( $handle ) {
		wp_enqueue_style( $handle, 'example.com', array(), null );

		$processor = new WP_HTML_Tag_Processor( get_echo( 'wp_print_styles' ) );

		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( 'stylesheet', $processor->get_attribute( 'rel' ) );
		$this->assertSame( $handle . '-css', $processor->get_attribute( 'id' ) );
		$this->assertSame( 'http://example.com', $processor->get_attribute( 'href' ) );
		$this->assertSame( 'all', $processor->get_attribute( 'media' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_awkward_handles_are_supported_consistently() {
		return array(
			'some spaces'       => array( 'with some spaces' ),
			'snowman'           => array( 'with-☃-snowman' ),
			'trailing space'    => array( 'with-trailing-space ' ),
			'leading space'     => array( ' with-leading-space' ),
			'an "ironic" title' => array( 'an &quot;ironic&quot; title' ),
		);
	}

	/**
	 * Test the different protocol references in wp_enqueue_style
	 *
	 * @global WP_Styles $wp_styles
	 * @ticket 16560
	 */
	public function test_protocols() {
		// Init.
		global $wp_styles;
		$base_url_backup     = $wp_styles->base_url;
		$wp_styles->base_url = 'http://example.com/wordpress';
		$expected            = '';
		$ver                 = get_bloginfo( 'version' );

		// Try with an HTTP reference.
		wp_enqueue_style( 'reset-css-http', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-http-css' href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' media='all' />\n";

		// Try with an HTTPS reference.
		wp_enqueue_style( 'reset-css-https', 'http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-https-css' href='http://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' media='all' />\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_style( 'reset-css-doubleslash', '//yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-doubleslash-css' href='//yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' media='all' />\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/style.css';
		wp_enqueue_style( 'plugin-style', $url );
		$expected .= "<link rel='stylesheet' id='plugin-style-css' href='$url?ver=$ver' media='all' />\n";

		// Try with a bad protocol.
		wp_enqueue_style( 'reset-css-ftp', 'ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css' );
		$expected .= "<link rel='stylesheet' id='reset-css-ftp-css' href='{$wp_styles->base_url}ftp://yui.yahooapis.com/2.8.1/build/reset/reset-min.css?ver=$ver' media='all' />\n";

		// Go!
		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );

		// No styles left to print.
		$this->assertSame( '', get_echo( 'wp_print_styles' ) );

		// Cleanup.
		$wp_styles->base_url = $base_url_backup;
	}

	/**
	 * Test if inline styles work
	 *
	 * @ticket 24813
	 */
	public function test_inline_styles() {

		$style  = ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<style id='handle-inline-css'>\n";
		$expected .= "$style\n";
		$expected .= "/*# sourceURL=handle-inline-css */\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test if inline styles work with concatenation
	 *
	 * @global WP_Styles $wp_styles
	 * @ticket 24813
	 */
	public function test_inline_styles_concat() {
		global $wp_styles;

		$wp_styles->do_concat    = true;
		$wp_styles->default_dirs = array( '/wp-admin/', '/wp-includes/css/' ); // Default dirs as in wp-includes/script-loader.php.

		$style  = ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<style id='handle-inline-css'>\n";
		$expected .= "$style\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style );

		wp_print_styles();
		$this->assertEqualHTML( $expected, $wp_styles->print_html );
	}

	/**
	 * Test normalizing relative links in CSS.
	 *
	 * @dataProvider data_normalize_relative_css_links
	 *
	 * @ticket 54243
	 * @ticket 54922
	 * @ticket 58069
	 *
	 * @covers ::_wp_normalize_relative_css_links
	 *
	 * @param string $css      Given CSS to test.
	 * @param string $expected Expected result.
	 */
	public function test_normalize_relative_css_links( $css, $expected ) {
		$this->assertEqualHTML(
			$expected,
			_wp_normalize_relative_css_links( $css, site_url( 'wp-content/themes/test/style.css' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_normalize_relative_css_links() {
		return array(
			'Double quotes, same path'                     => array(
				'css'      => 'p {background:url( "image0.svg" );}',
				'expected' => 'p {background:url( "/wp-content/themes/test/image0.svg" );}',
			),
			'Single quotes, same path, prefixed with "./"' => array(
				'css'      => 'p {background-image: url(\'./image2.png\');}',
				'expected' => 'p {background-image: url(\'/wp-content/themes/test/image2.png\');}',
			),
			'Single quotes, one level up, prefixed with "../"' => array(
				'css'      => 'p {background-image: url(\'../image1.jpg\');}',
				'expected' => 'p {background-image: url(\'/wp-content/themes/test/../image1.jpg\');}',
			),
			'URLs with absolute path, shouldn\'t change'   => array(
				'css'      => 'p {background:url( "/image0.svg" );}',
				'expected' => 'p {background:url( "/image0.svg" );}',
			),
			'External URLs, shouldn\'t change'             => array(
				'css'      => 'p {background-image: url(\'http://foo.com/image2.png\');}',
				'expected' => 'p {background-image: url(\'http://foo.com/image2.png\');}',
			),
			'An HTML ID'                                   => array(
				'css'      => 'clip-path: url(#image1);',
				'expected' => 'clip-path: url(#image1);',
			),
			'Data URIs, shouldn\'t change'                 => array(
				'css'      => 'img {mask-image: url(\'data:image/svg+xml;utf8,<svg></svg>\');}',
				'expected' => 'img {mask-image: url(\'data:image/svg+xml;utf8,<svg></svg>\');}',
			),
			'URLs with path beginning with http'           => array(
				'css'      => 'p {background:url( "http-is-awesome.png" );}',
				'expected' => 'p {background:url( "/wp-content/themes/test/http-is-awesome.png" );}',
			),
			'URLs with path beginning with https'          => array(
				'css'      => 'p {background:url( "https-is-more-awesome.png" );}',
				'expected' => 'p {background:url( "/wp-content/themes/test/https-is-more-awesome.png" );}',
			),
		);
	}

	/**
	 * Test if multiple inline styles work
	 *
	 * @ticket 24813
	 */
	public function test_multiple_inline_styles() {

		$style1  = ".thing1 {\n";
		$style1 .= "\tbackground: red;\n";
		$style1 .= '}';

		$style2  = ".thing2 {\n";
		$style2 .= "\tbackground: blue;\n";
		$style2 .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<style id='handle-inline-css'>\n";
		$expected .= "$style1\n";
		$expected .= "$style2\n";
		$expected .= "/*# sourceURL=handle-inline-css */\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_add_inline_style( 'handle', $style1 );
		wp_add_inline_style( 'handle', $style2 );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test if a plugin doing it the wrong way still works
	 *
	 * @expectedIncorrectUsage wp_add_inline_style
	 * @ticket 24813
	 */
	public function test_plugin_doing_inline_styles_wrong() {

		$style  = ".thing {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';

		$expected  = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<style id='handle-inline-css'>\n";
		$expected .= "$style\n";
		$expected .= "/*# sourceURL=handle-inline-css */\n";
		$expected .= "</style>\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );

		wp_add_inline_style( 'handle', "<style>{$style}</style>" );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test to make sure <style> tags aren't output if there are no inline styles.
	 *
	 * @ticket 24813
	 */
	public function test_unnecessary_style_tags() {

		$expected = "<link rel='stylesheet' id='handle-css' href='http://example.com?ver=1' media='all' />\n";

		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Test to make sure that inline styles attached to conditional
	 * stylesheets are also conditional.
	 *
	 * @expectedDeprecated WP_Dependencies::add_data()
	 */
	public function test_conditional_inline_styles_are_also_conditional() {
		wp_enqueue_style( 'handle', 'http://example.com', array(), 1 );
		wp_style_add_data( 'handle', 'conditional', 'IE' );
		wp_add_inline_style( 'handle', 'a { color: blue; }' );

		// Conditional styles are disabled.
		$this->assertSame( '', get_echo( 'wp_print_styles' ) );
	}

	/**
	 * Testing 'wp_register_style' return boolean success/failure value.
	 *
	 * @ticket 31126
	 */
	public function test_wp_register_style() {
		$this->assertTrue( wp_register_style( 'duplicate-handler', 'http://example.com' ) );
		$this->assertFalse( wp_register_style( 'duplicate-handler', 'http://example.com' ) );
	}

	/**
	 * @ticket 35229
	 */
	public function test_wp_add_inline_style_for_handle_without_source() {
		$style = 'a { color: blue; }';

		$expected  = "<link rel='stylesheet' id='handle-one-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<link rel='stylesheet' id='handle-two-css' href='http://example.com?ver=1' media='all' />\n";
		$expected .= "<style id='handle-three-inline-css'>\n";
		$expected .= "$style\n";
		$expected .= "/*# sourceURL=handle-three-inline-css */\n";
		$expected .= "</style>\n";

		wp_register_style( 'handle-one', 'http://example.com', array(), 1 );
		wp_register_style( 'handle-two', 'http://example.com', array(), 1 );
		wp_register_style( 'handle-three', false, array( 'handle-one', 'handle-two' ) );

		wp_enqueue_style( 'handle-three' );
		wp_add_inline_style( 'handle-three', $style );

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @ticket 35921
	 * @dataProvider data_styles_with_media
	 */
	public function test_wp_enqueue_style_with_media( $media ) {
		wp_enqueue_style( 'handle', 'http://example.com', array(), 1, $media );

		$processor = new WP_HTML_Tag_Processor( get_echo( 'wp_print_styles' ) );

		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( $media, $processor->get_attribute( 'media' ) );
	}

	public function data_styles_with_media() {
		return array(
			array( 'all' ),
			array( '(orientation: portrait)' ),
			array( '(max-width: 640px)' ),
			array( 'print and (min-width: 25cm)' ),
			array( 'screen and (color), projection and (color)' ),
			array( 'not screen and (color)' ),
		);
	}

	/**
	 * Tests that visual block styles are not be enqueued in the editor when there is not theme support for 'wp-block-styles'.
	 *
	 * @ticket 57561
	 *
	 * @covers ::wp_enqueue_style
	 */
	public function test_block_styles_for_editing_without_theme_support() {
		// Confirm we are without theme support by default.
		$this->assertFalse( current_theme_supports( 'wp-block-styles' ) );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_enqueue_style( 'wp-edit-blocks' );
		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ), "The 'wp-block-library-theme' style should not be in the queue after enqueuing 'wp-edit-blocks'" );
	}

	/**
	 * Tests that visual block styles are enqueued when there is theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should always be enqueued when editing to avoid the appearance of a broken editor.
	 *
	 * @covers ::wp_common_block_scripts_and_styles
	 */
	public function test_block_styles_for_editing_with_theme_support() {
		// Override wp_load_classic_theme_block_styles_on_demand().
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		add_theme_support( 'wp-block-styles' );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_common_block_scripts_and_styles();
		$this->assertTrue( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that visual block styles are not enqueued for viewing when there is no theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should not be enqueued unless a theme opts in.
	 * This way we avoid style conflicts with existing themes.
	 *
	 * @covers ::wp_enqueue_style
	 */
	public function test_no_block_styles_for_viewing_without_theme_support() {
		// Confirm we are without theme support by default.
		$this->assertFalse( current_theme_supports( 'wp-block-styles' ) );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_enqueue_style( 'wp-block-library' );
		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that visual block styles are enqueued for viewing when there is theme support for 'wp-block-styles'.
	 *
	 * Visual block styles should be enqueued when a theme opts in.
	 *
	 * @covers ::wp_common_block_scripts_and_styles
	 */
	public function test_block_styles_for_viewing_with_theme_support() {
		// Override wp_load_classic_theme_block_styles_on_demand().
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		add_theme_support( 'wp-block-styles' );

		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertFalse( wp_style_is( 'wp-block-library-theme' ) );
		wp_common_block_scripts_and_styles();
		$this->assertTrue( wp_style_is( 'wp-block-library-theme' ) );
	}

	/**
	 * Tests that the main "style.css" file gets enqueued when the site doesn't opt in to separate core block assets.
	 *
	 * @ticket 50263
	 *
	 * @covers ::wp_default_styles
	 */
	public function test_block_styles_for_viewing_without_split_styles() {
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );
		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertSame(
			'/' . WPINC . '/css/dist/block-library/style.css',
			$GLOBALS['wp_styles']->registered['wp-block-library']->src
		);
	}

	/**
	 * Tests that the "common.css" file gets enqueued when the site opts in to separate core block assets.
	 *
	 * @ticket 50263
	 *
	 * @covers ::wp_default_styles
	 */
	public function test_block_styles_for_viewing_with_split_styles() {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertSame(
			'/' . WPINC . '/css/dist/block-library/common.css',
			$GLOBALS['wp_styles']->registered['wp-block-library']->src
		);
	}

	/**
	 * Tests that the design tokens stylesheet is registered in core.
	 *
	 * @ticket 65646
	 *
	 * @covers ::wp_default_styles
	 */
	public function test_wp_theme_style_is_registered() {
		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertArrayHasKey( 'wp-theme', $GLOBALS['wp_styles']->registered );
		$this->assertSame(
			'/' . WPINC . '/css/dist/theme/design-tokens.css',
			$GLOBALS['wp_styles']->registered['wp-theme']->src
		);
	}

	/**
	 * Tests that wp-components depends on wp-theme so tokens load before component styles.
	 *
	 * @ticket 65646
	 *
	 * @covers ::wp_default_styles
	 */
	public function test_wp_components_depends_on_wp_theme() {
		wp_default_styles( $GLOBALS['wp_styles'] );

		$this->assertContains(
			'wp-theme',
			$GLOBALS['wp_styles']->registered['wp-components']->deps
		);
	}

	/**
	 * Tests that wp-edit-blocks loads design tokens before other editor styles.
	 *
	 * @ticket 65646
	 *
	 * @covers ::wp_default_styles
	 */
	public function test_wp_edit_blocks_depends_on_wp_theme_first() {
		wp_default_styles( $GLOBALS['wp_styles'] );

		$deps = $GLOBALS['wp_styles']->registered['wp-edit-blocks']->deps;

		$this->assertContains( 'wp-theme', $deps );
		$this->assertSame( 0, array_search( 'wp-theme', $deps, true ) );
	}

	/**
	 * @group assets
	 *
	 * @ticket 58394
	 * @ticket 63887
	 *
	 * @covers ::wp_maybe_inline_styles
	 * @covers ::wp_add_inline_style
	 * @covers ::wp_print_styles
	 * @covers WP_Styles::do_items
	 * @covers WP_Styles::do_item
	 * @covers WP_Styles::print_inline_style
	 *
	 * @dataProvider data_provider_test_wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles( ?string $additional_inline_style, ?int $styles_inline_size_limit ) {
		$rel_path = 'css/classic-themes.css';
		$src_url  = includes_url( $rel_path );
		$src_path = ABSPATH . WPINC . '/' . $rel_path;
		$css      = file_get_contents( $src_path );
		$handle   = 'test-handle';

		if ( isset( $styles_inline_size_limit ) ) {
			add_filter(
				'styles_inline_size_limit',
				static function () use ( $styles_inline_size_limit ): int {
					return $styles_inline_size_limit;
				}
			);
		}
		wp_register_style( $handle, $src_url, array(), null );
		wp_style_add_data( $handle, 'path', $src_path );
		if ( isset( $additional_inline_style ) ) {
			wp_add_inline_style( $handle, $additional_inline_style );
		}
		wp_enqueue_style( $handle );

		wp_maybe_inline_styles();

		$expected_after = array();
		if ( ! isset( $styles_inline_size_limit ) || strlen( $css ) <= $styles_inline_size_limit ) {
			$expected_after[] = $css;
			$this->assertFalse( wp_styles()->registered[ $handle ]->src, 'Source of style should be reset to false' );
			$this->assertArrayHasKey( 'inlined_src', wp_styles()->registered[ $handle ]->extra );
			$this->assertSame( $src_url, wp_styles()->registered[ $handle ]->extra['inlined_src'] );
		} else {
			$this->assertArrayNotHasKey( 'inlined_src', wp_styles()->registered[ $handle ]->extra );
		}

		if ( isset( $additional_inline_style ) ) {
			$expected_after[] = $additional_inline_style;
		}

		$after = wp_styles()->get_data( $handle, 'after' );
		if ( false === $after ) {
			$after = array();
		}
		$this->assertSameSets( $after, $expected_after, 'Source of style should set to after property' );

		$printed_styles = get_echo( 'wp_print_styles', array( $handle ) );
		$processor      = new WP_HTML_Tag_Processor( $printed_styles );

		if ( isset( $styles_inline_size_limit ) && strlen( $css ) > $styles_inline_size_limit ) {
			$this->assertTrue( $processor->next_tag() );
			$this->assertSame( 'LINK', $processor->get_tag() );
			$this->assertSame( 'stylesheet', $processor->get_attribute( 'rel' ) );
			$this->assertSame( $src_url, $processor->get_attribute( 'href' ) );
		}

		if ( count( $expected_after ) > 0 ) {
			$this->assertTrue( $processor->next_tag() );
			$this->assertSame( 'STYLE', $processor->get_tag() );
			$this->assertSame( $handle . '-inline-css', $processor->get_attribute( 'id' ) );
			$this->assertNull( $processor->get_attribute( 'type' ) );

			$expected_inline_styles = $expected_after;
			if ( isset( $additional_inline_style ) ) {
				$source_url = $handle . '-inline-css';
			} else {
				$source_url = $src_url;
			}
			$expected_inline_styles[] = "/*# sourceURL=$source_url */";

			$expected_text = "\n" . implode( "\n", $expected_inline_styles ) . "\n";
			$this->assertSame( $expected_text, $processor->get_modifiable_text() );
		}

		$this->assertFalse( $processor->next_tag() );
	}

	/**
	 * Data provider for test_wp_maybe_inline_styles.
	 *
	 * @see self::test_wp_maybe_inline_styles()
	 * @return array<string, array{additional_inline_style: string|null, styles_inline_size_limit: int|null}>
	 */
	public static function data_provider_test_wp_maybe_inline_styles(): array {
		return array(
			'regular_limit_without_additional_inline_styles' => array(
				'additional_inline_style'  => null,
				'styles_inline_size_limit' => null,
			),
			'regular_limit_with_additional_inline_style' => array(
				'additional_inline_style'  => '/* additional inline style */',
				'styles_inline_size_limit' => null,
			),
			'zero_limit_without_additional_inline_style' => array(
				'additional_inline_style'  => null,
				'styles_inline_size_limit' => 0,
			),
			'zero_limit_with_additional_inline_style'    => array(
				'additional_inline_style'  => '/* additional inline style */',
				'styles_inline_size_limit' => 0,
			),
		);
	}

	/**
	 * @ticket 58394
	 *
	 * @covers ::wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_dequeue_styles() {
		$filter = new MockAction();
		add_filter( 'pre_wp_filesize', array( $filter, 'filter' ) );
		wp_register_style( 'test-handle', '/' . WPINC . '/css/classic-themes.css' );
		wp_style_add_data( 'test-handle', 'path', ABSPATH . WPINC . '/css/classic-themes.css' );

		wp_enqueue_style( 'test-handle' );

		wp_deregister_style( 'test-handle' );

		wp_maybe_inline_styles();

		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * wp_filesize should be only be called once, as on the second run of wp_maybe_inline_styles,
	 * src will be set to false and filesize will not be requested.
	 *
	 * @group assets
	 *
	 * @ticket 58394
	 *
	 * @covers ::wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_multiple_runs() {
		$filter = new MockAction();
		add_filter( 'pre_wp_filesize', array( $filter, 'filter' ) );
		wp_register_style( 'test-handle', '/' . WPINC . '/css/classic-themes.css' );
		wp_style_add_data( 'test-handle', 'path', ABSPATH . WPINC . '/css/classic-themes.css' );

		wp_enqueue_style( 'test-handle' );

		wp_maybe_inline_styles();
		wp_maybe_inline_styles();

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @ticket 58394
	 * @ticket 64447
	 *
	 * @covers ::wp_maybe_inline_styles
	 * @expectedIncorrectUsage wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_missing_file() {
		$filter = new MockAction();
		add_filter( 'pre_wp_filesize', array( $filter, 'filter' ) );
		$url = '/' . WPINC . '/css/invalid.css';
		wp_register_style( 'test-handle', $url );
		wp_style_add_data( 'test-handle', 'path', ABSPATH . WPINC . '/css/invalid.css' );

		wp_enqueue_style( 'test-handle' );

		wp_maybe_inline_styles();

		$this->assertSame( $GLOBALS['wp_styles']->registered['test-handle']->src, $url, 'Source should not change' );
		$this->assertArrayNotHasKey( 'after', $GLOBALS['wp_styles']->registered['test-handle']->extra, 'Source of style not should set to after property' );
		$this->assertSame( 1, $filter->get_call_count(), 'wp_filesize should only be called once' );
	}

	/**
	 * @ticket 58394
	 *
	 * @covers ::wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_no_src() {
		wp_register_style( 'test-handle', false );
		wp_style_add_data( 'test-handle', 'path', ABSPATH . WPINC . '/css/classic-themes.css' );

		wp_enqueue_style( 'test-handle' );

		wp_maybe_inline_styles();

		$this->assertFalse( $GLOBALS['wp_styles']->registered['test-handle']->src, 'Source of style should remain false' );
		$this->assertArrayNotHasKey( 'after', $GLOBALS['wp_styles']->registered['test-handle']->extra, 'Source of style not should set to after property' );
	}

	/**
	 * @ticket 58394
	 *
	 * @covers ::wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_no_path() {
		$url = '/' . WPINC . '/css/classic-themes.css';
		wp_register_style( 'test-handle', $url );

		wp_enqueue_style( 'test-handle' );

		wp_maybe_inline_styles();

		$this->assertSame( $GLOBALS['wp_styles']->registered['test-handle']->src, $url );
	}

	/**
	 * @ticket 64447
	 *
	 * @covers ::wp_maybe_inline_styles
	 * @expectedIncorrectUsage wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_bad_path_with_file_size_provided() {
		$style_path = '/css/invalid.css'; // Does not exist.

		// This ensures the initial file size check is bypassed.
		add_filter(
			'pre_wp_filesize',
			static function ( $size, $path ) use ( $style_path ) {
				if ( str_contains( $path, $style_path ) ) {
					$size = 1000;
				}
				return $size;
			},
			10,
			2
		);

		$handle = 'test-handle';
		$url    = '/' . WPINC . $style_path;
		wp_register_style( $handle, $url );
		wp_style_add_data( $handle, 'path', ABSPATH . WPINC . $style_path );
		wp_enqueue_style( $handle );

		wp_maybe_inline_styles();

		$this->assertSame( $GLOBALS['wp_styles']->registered[ $handle ]->src, $url );
	}

	/**
	 * @group assets
	 *
	 * @ticket 64447
	 *
	 * @covers ::wp_maybe_inline_styles
	 */
	public function test_wp_maybe_inline_styles_good_path_with_zero_file_size_provided() {
		$style_path = '/css/classic-themes.css';

		// This simulates the file having a zero size.
		add_filter(
			'pre_wp_filesize',
			static function ( $size, $path ) use ( $style_path ) {
				if ( str_contains( $path, $style_path ) ) {
					$size = 0;
				}
				return $size;
			},
			10,
			2
		);

		$handle = 'test-handle';
		wp_register_style( $handle, '/' . WPINC . $style_path );
		wp_style_add_data( $handle, 'path', ABSPATH . WPINC . $style_path );
		wp_enqueue_style( $handle );

		wp_maybe_inline_styles();

		$this->assertFalse( $GLOBALS['wp_styles']->registered[ $handle ]->src );
	}

	/**
	 * @ticket 63887
	 */
	public function test_source_url_encoding() {
		$handle = '# test/</style> #';
		wp_enqueue_style( $handle, '/example.css', array(), '0.0' );
		wp_add_inline_style( $handle, 'custom-el { content: "ok"; }' );

		$expected = <<<HTML
<link rel='stylesheet' href="/example.css?ver=0.0" id="# test/</style> #-css" media="all">
<style id="# test/</style> #-inline-css">
custom-el { content: "ok"; }
/*# sourceURL=%23%20test%2F%3C%2Fstyle%3E%20%23-inline-css */
</style>

HTML;

		$this->assertEqualHTML( $expected, get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @ticket 63887
	 */
	public function test_source_url_with_concat() {
		global $wp_styles, $wp_version;
		add_theme_support( 'html5', array( 'style' ) );

		$wp_styles->do_concat    = true;
		$wp_styles->default_dirs = array( '/wp-admin/' );

		wp_enqueue_style( 'one', '/wp-admin/1.css' );
		wp_enqueue_style( 'two', '/wp-admin/2.css' );
		wp_add_inline_style( 'one', 'h1 { background: blue; }' );
		wp_add_inline_style( 'two', 'h2 { color: green; }' );

		wp_print_styles();
		$printed = get_echo( '_print_styles' );

		$expected = <<<HTML
<link
	rel="stylesheet"
	href="/wp-admin/load-styles.php?c=0&dir=ltr&load%5Bchunk_0%5D=one,two&ver={$wp_version}"
	media="all"
>
<style>
h1 { background: blue; }h2 { color: green; }
/*# sourceURL=css-inline-concat-one%2Ctwo */
</style>

HTML;

		$this->assertEqualHTML( $expected, $printed );
	}

	/**
	 * Tests that WP_Styles emits a _doing_it_wrong() notice for missing dependencies.
	 *
	 * @ticket 64229
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_wp_style_doing_it_wrong_for_missing_dependencies() {
		$expected_incorrect_usage = 'WP_Styles::add';
		$this->setExpectedIncorrectUsage( $expected_incorrect_usage );

		wp_enqueue_style(
			'main-style',
			'/main-style.css',
			array( 'missing-style-dep' )
		);

		$markup = get_echo( 'wp_print_styles' );
		$this->assertStringNotContainsString( 'main-style.css', $markup, 'Expected style to be absent.' );

		$this->assertArrayHasKey(
			$expected_incorrect_usage,
			$this->caught_doing_it_wrong,
			"Expected $expected_incorrect_usage to trigger a _doing_it_wrong() notice for missing dependency."
		);

		$this->assertStringContainsString(
			'The style with the handle "main-style" was enqueued with dependencies that are not registered: missing-style-dep',
			$this->caught_doing_it_wrong[ $expected_incorrect_usage ],
			'Expected _doing_it_wrong() notice to indicate missing dependencies for enqueued styles.'
		);
	}

	/**
	 * Test query string on handle when enqueuing styles directly.
	 *
	 * @ticket 64372
	 *
	 * @covers WP_Styles::do_item
	 *
	 * @dataProvider data_varying_versions_handle_args
	 *
	 * @param mixed  $version               Version to pass when enqueuing.
	 * @param string $expected_query_string Expected query string portion of the style sheet URL.
	 */
	public function test_varying_versions_added_to_handle_args_enqueued_styles( $version, $expected_query_string ) {
		wp_enqueue_style( 'test-style?qs1=q1&qs2=q2', '/test-style.css', array(), $version );
		$markup = get_echo( 'wp_print_styles' );

		$expected = "<link rel='stylesheet' href='/test-style.css?{$expected_query_string}' id='test-style-css' media='all' />\n";
		$this->assertEqualHTML( $expected, $markup, '<body>', 'Expected equal snapshot for wp_print_styles() with version ' . var_export( $version, true ) . ":\n$markup" );
	}

	/**
	 * Test query string on handle when registering then enqueuing styles.
	 *
	 * @ticket 64372
	 *
	 * @covers WP_Styles::do_item
	 *
	 * @dataProvider data_varying_versions_handle_args
	 *
	 * @param mixed  $version               Version to pass when enqueuing.
	 * @param string $expected_query_string Expected query string portion of the style sheet URL.
	 */
	public function test_varying_versions_added_to_handle_args_registered_then_enqueued_styles( $version, $expected_query_string ) {
		wp_register_style( 'test-style', '/test-style.css', array(), $version );
		wp_enqueue_style( 'test-style?qs1=q1&qs2=q2' );
		$markup = get_echo( 'wp_print_styles' );

		$expected = "<link rel='stylesheet' href='/test-style.css?{$expected_query_string}' id='test-style-css' media='all' />\n";
		$this->assertEqualHTML( $expected, $markup, '<body>', 'Expected equal snapshot for wp_print_styles() with version ' . var_export( $version, true ) . ":\n$markup" );
	}

	/**
	 * Tests that duplicate query vars and fragments are preserved in styles.
	 *
	 * @ticket 64372
	 *
	 * @dataProvider data_duplicate_query_vars_and_fragments_preserved_in_styles
	 *
	 * @param string           $src          The stylesheet's source URL.
	 * @param string|bool|null $ver          The style's version.
	 * @param string           $expected_url The expected URL.
	 * @param string           $handle       Optional. The style's registered handle. Default 'test-style'.
	 */
	public function test_duplicate_query_vars_and_fragments_preserved_in_styles( string $src, $ver, string $expected_url, string $handle = 'test-style' ): void {
		wp_enqueue_style( $handle, $src, array(), $ver );
		$output    = get_echo( 'wp_print_styles' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'link' ) );
		$this->assertSame( $expected_url, $processor->get_attribute( 'href' ) );
	}

	/**
	 * Data provider for test_duplicate_query_vars_and_fragments_preserved_in_styles.
	 *
	 * @return array<string, array{src: string, ver: string|bool|null, expected_url: string, handle?: string}> Data provider.
	 */
	public function data_duplicate_query_vars_and_fragments_preserved_in_styles(): array {
		$ver = get_bloginfo( 'version' );

		return array(
			'duplicate query vars'                => array(
				'src'          => 'https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap',
				'ver'          => '1.0',
				'expected_url' => 'https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap&ver=1.0',
			),
			'duplicate query vars, null version'  => array(
				'src'          => 'https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap',
				'ver'          => null,
				'expected_url' => 'https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap',
			),
			'duplicate query vars, false version' => array(
				'src'          => 'https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap',
				'ver'          => false,
				'expected_url' => "https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap&ver=$ver",
			),
			'duplicate query vars in handle'      => array(
				'src'          => 'https://example.com/test-style.css',
				'ver'          => '1.0',
				'expected_url' => 'https://example.com/test-style.css?ver=1.0&a=1&a=2',
				'handle'       => 'test-style?a=1&a=2',
			),
			'duplicate query vars and fragments'  => array(
				'src'          => 'https://example.com/style.css?arg=1&arg=2#anchor',
				'ver'          => '1.0',
				'expected_url' => 'https://example.com/style.css?arg=1&arg=2&ver=1.0#anchor',
			),
			'zero query var in handle'            => array(
				'src'          => 'https://example.com/test-style.css',
				'ver'          => '1.0',
				'expected_url' => 'https://example.com/test-style.css?ver=1.0&0',
				'handle'       => 'test-style?0',
			),
		);
	}

	/**
	 * Data provider for:
	 * - test_varying_versions_added_to_handle_args_enqueued_styles
	 * - test_varying_versions_added_to_handle_args_registered_then_enqueued_styles
	 *
	 * @return array[] Data provider.
	 */
	public function data_varying_versions_handle_args() {
		$default_version = get_bloginfo( 'version' );

		return array(
			'string'       => array(
				'1.0.0',
				'ver=1.0.0&amp;qs1=q1&amp;qs2=q2',
			),
			'null'         => array(
				null,
				'qs1=q1&amp;qs2=q2',
			),
			'false'        => array(
				false,
				"ver={$default_version}&amp;qs1=q1&amp;qs2=q2",
			),
			'empty-string' => array(
				'',
				"ver={$default_version}&amp;qs1=q1&amp;qs2=q2",
			),
			'zero-string'  => array(
				'0',
				"ver={$default_version}&amp;qs1=q1&amp;qs2=q2",
			),
			'integer'      => array(
				123,
				'ver=123&amp;qs1=q1&amp;qs2=q2',
			),
			'zero-integer' => array(
				0,
				"ver={$default_version}&amp;qs1=q1&amp;qs2=q2",
			),
			'float'        => array(
				1.23,
				'ver=1.23&amp;qs1=q1&amp;qs2=q2',
			),
			'zero-float'   => array(
				0.0,
				"ver={$default_version}&amp;qs1=q1&amp;qs2=q2",
			),
		);
	}
}
