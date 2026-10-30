<?php

/**
 * Tests for _get_plugin_data_markup_translate().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::_get_plugin_data_markup_translate
 */
class Tests_Admin_Includes_Plugin_GetPluginDataMarkupTranslate extends WP_UnitTestCase {

	/**
	 * The original text domain registry.
	 *
	 * @var WP_Textdomain_Registry
	 */
	private $original_registry;

	/**
	 * The original loaded translations.
	 *
	 * @var array
	 */
	private $original_l10n;

	/**
	 * Strings passed to translate(), as text and domain pairs.
	 *
	 * @var array[]
	 */
	private $translated = array();

	/**
	 * Saves the text domain registry and the loaded translations.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_registry = $GLOBALS['wp_textdomain_registry'];
		$this->original_l10n     = $GLOBALS['l10n'];
	}

	/**
	 * Restores the text domain registry and the loaded translations.
	 */
	public function tear_down() {
		$GLOBALS['wp_textdomain_registry'] = $this->original_registry;
		$GLOBALS['l10n']                   = $this->original_l10n;

		parent::tear_down();
	}

	/**
	 * Tests that the fields are sanitized without markup.
	 *
	 * @ticket 65819
	 */
	public function test_should_sanitize_fields_without_markup() {
		$plugin_data = $this->get_plugin_data(
			array(
				'Name'        => 'My <strong>Plugin</strong> <a href="https://example.org/">Link</a><script>alert(1)</script>',
				'PluginURI'   => 'javascript:alert(1)',
				'Version'     => '1.0 <em>beta</em> <b>bold</b>',
				'Description' => 'A <em>fine</em> <a href="https://example.org/" onclick="alert(1)">plugin</a> <cite>cited</cite> & "quoted".',
				'Author'      => '<a href="https://example.org/" onclick="alert(1)">Jane</a> & <i>Co</i>',
				'AuthorURI'   => 'https://example.org/author?a=1&b="2"',
			)
		);

		$this->assertSame(
			array(
				'Name'        => 'My <strong>Plugin</strong> Link',
				'PluginURI'   => '',
				'Version'     => '1.0 <em>beta</em> bold',
				'Description' => 'A <em>fine</em> <a href="https://example.org/">plugin</a> cited &amp; "quoted".',
				'Author'      => '<a href="https://example.org/">Jane</a> &amp; Co',
				'AuthorURI'   => 'https://example.org/author?a=1&#038;b=2',
				'TextDomain'  => '',
				'DomainPath'  => '',
				'Title'       => 'My <strong>Plugin</strong> Link',
				'AuthorName'  => '<a href="https://example.org/">Jane</a> &amp; Co',
			),
			_get_plugin_data_markup_translate( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', $plugin_data, false, false )
		);
	}

	/**
	 * Tests that markup links the name and author and credits the author.
	 *
	 * @ticket 65819
	 */
	public function test_should_add_markup() {
		$plugin_data = $this->get_plugin_data(
			array(
				'Name'        => 'My Plugin',
				'PluginURI'   => 'https://example.org/plugin/',
				'Description' => "It's a plugin -- really.",
				'Author'      => 'Jane',
				'AuthorURI'   => 'https://example.org/jane/',
			)
		);

		$result = _get_plugin_data_markup_translate( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', $plugin_data, true, false );

		$this->assertSame( '<a href="https://example.org/plugin/">My Plugin</a>', $result['Title'], 'The title should link to the plugin URI.' );
		$this->assertSame( '<a href="https://example.org/jane/">Jane</a>', $result['Author'], 'The author should link to the author URI.' );
		$this->assertSame( 'Jane', $result['AuthorName'], 'The author name should not be linked.' );
		$this->assertSame( 'My Plugin', $result['Name'], 'The name should not be linked.' );
		$this->assertSame(
			'It&#8217;s a plugin &#8212; really. <cite>By <a href="https://example.org/jane/">Jane</a>.</cite>',
			$result['Description'],
			'The description should be texturized and credit the author.'
		);
	}

	/**
	 * Tests that markup leaves out links and the author credit when their fields are empty.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_markup_fields
	 *
	 * @param array $fields   The plugin data fields.
	 * @param array $expected The expected Title, Author and Description.
	 */
	public function test_should_skip_markup_for_empty_fields( $fields, $expected ) {
		$result = _get_plugin_data_markup_translate( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', $this->get_plugin_data( $fields ), true, false );

		$this->assertSame(
			$expected,
			array(
				'Title'       => $result['Title'],
				'Author'      => $result['Author'],
				'Description' => $result['Description'],
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_markup_fields() {
		return array(
			'no plugin URI' => array(
				array(
					'Name'        => 'My Plugin',
					'Description' => 'Text.',
					'Author'      => 'Jane',
				),
				array(
					'Title'       => 'My Plugin',
					'Author'      => 'Jane',
					'Description' => 'Text. <cite>By Jane.</cite>',
				),
			),
			'no name'       => array(
				array(
					'PluginURI'   => 'https://example.org/plugin/',
					'Description' => 'Text.',
				),
				array(
					'Title'       => '',
					'Author'      => '',
					'Description' => 'Text.',
				),
			),
			'no author URI' => array(
				array(
					'Name'   => 'My Plugin',
					'Author' => 'Jane',
				),
				array(
					'Title'       => 'My Plugin',
					'Author'      => 'Jane',
					'Description' => ' <cite>By Jane.</cite>',
				),
			),
			'author "0"'    => array(
				array(
					'Name'      => 'My Plugin',
					'Author'    => '0',
					'AuthorURI' => 'https://example.org/jane/',
				),
				array(
					'Title'       => 'My Plugin',
					'Author'      => '0',
					'Description' => '',
				),
			),
		);
	}

	/**
	 * Tests that the translatable fields are translated with the plugin's text domain.
	 *
	 * @ticket 65819
	 */
	public function test_should_translate_fields_with_text_domain() {
		$GLOBALS['l10n']['my-plugin'] = new MO();

		add_filter( 'gettext', array( $this, 'translate_string' ), 10, 3 );

		$result = _get_plugin_data_markup_translate(
			WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			$this->get_plugin_data(
				array(
					'Name'        => 'My Plugin',
					'PluginURI'   => 'https://example.org/plugin/',
					'Version'     => '1.0',
					'Description' => 'Text.',
					'Author'      => 'Jane',
					'TextDomain'  => 'my-plugin',
				)
			),
			false,
			true
		);

		$this->assertSameSets(
			array(
				array( 'My Plugin', 'my-plugin' ),
				array( 'https://example.org/plugin/', 'my-plugin' ),
				array( 'Text.', 'my-plugin' ),
				array( 'Jane', 'my-plugin' ),
				array( '1.0', 'my-plugin' ),
			),
			$this->translated,
			'Only the non-empty fields should be translated, with the plugin text domain.'
		);
		$this->assertSame( '[My Plugin]', $result['Name'], 'The name should be translated.' );
		$this->assertSame( 'https://example.org/plugin/de/', $result['PluginURI'], 'The plugin URI should be translated.' );
		$this->assertSame( '[Text.]', $result['Description'], 'The description should be translated.' );
		$this->assertSame( '[Jane]', $result['Author'], 'The author should be translated.' );
		$this->assertSame( '[1.0]', $result['Version'], 'The version should be translated.' );
	}

	/**
	 * Tests that the fields are not translated when translation is disabled.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_translate_when_disabled() {
		add_filter( 'gettext', array( $this, 'translate_string' ), 10, 3 );

		$result = _get_plugin_data_markup_translate(
			WP_PLUGIN_DIR . '/hello.php',
			$this->get_plugin_data(
				array(
					'Name'       => 'My Plugin',
					'TextDomain' => 'my-plugin',
				)
			),
			false,
			false
		);

		$this->assertSame( array(), $this->translated, 'No field should be translated.' );
		$this->assertSame( 'My Plugin', $result['Name'], 'The name should not change.' );
	}

	/**
	 * Tests the text domain used for a plugin without one.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plugins_without_text_domain
	 *
	 * @param string $plugin_file The plugin file, relative to the plugins directory.
	 * @param array  $expected    The expected translate() calls.
	 */
	public function test_should_handle_plugin_without_text_domain( $plugin_file, $expected ) {
		add_filter( 'gettext', array( $this, 'translate_string' ), 10, 3 );

		_get_plugin_data_markup_translate(
			WP_PLUGIN_DIR . '/' . $plugin_file,
			$this->get_plugin_data( array( 'Name' => 'My Plugin' ) ),
			false,
			true
		);

		$this->assertSame( $expected, $this->translated );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugins_without_text_domain() {
		return array(
			'Hello Dolly uses the default text domain' => array(
				'hello.php',
				array( array( 'My Plugin', 'default' ) ),
			),
			'Hello Dolly in a plugin directory'        => array(
				'hello-dolly/hello.php',
				array( array( 'My Plugin', 'default' ) ),
			),
			'other plugins are not translated'         => array(
				'my-plugin/my-plugin.php',
				array(),
			),
		);
	}

	/**
	 * Tests that the plugin text domain is loaded from the plugin's directory.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_text_domain_paths
	 *
	 * @param string $domain_path   The Domain Path header.
	 * @param string $expected_path The expected path, relative to the plugins directory.
	 */
	public function test_should_load_text_domain_from_plugin_directory( $domain_path, $expected_path ) {
		$registry = $this->createMock( 'WP_Textdomain_Registry' );
		$registry->expects( $this->once() )
			->method( 'set_custom_path' )
			->with( 'my-plugin', WP_PLUGIN_DIR . '/' . $expected_path );

		$GLOBALS['wp_textdomain_registry'] = $registry;

		_get_plugin_data_markup_translate(
			WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			$this->get_plugin_data(
				array(
					'TextDomain' => 'my-plugin',
					'DomainPath' => $domain_path,
				)
			),
			false,
			true
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_domain_paths() {
		return array(
			'no domain path' => array( '', 'my-plugin' ),
			'domain path'    => array( '/languages', 'my-plugin/languages' ),
		);
	}

	/**
	 * Tests that a text domain that is already loaded is not loaded again.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_reload_loaded_text_domain() {
		$registry = $this->createMock( 'WP_Textdomain_Registry' );
		$registry->expects( $this->never() )->method( 'set_custom_path' );

		$GLOBALS['wp_textdomain_registry'] = $registry;
		$GLOBALS['l10n']['my-plugin']      = new MO();

		_get_plugin_data_markup_translate(
			WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			$this->get_plugin_data( array( 'TextDomain' => 'my-plugin' ) ),
			false,
			true
		);
	}

	/**
	 * Returns plugin data with all fields the function reads.
	 *
	 * @param array $fields The fields to set.
	 * @return array The plugin data.
	 */
	private function get_plugin_data( $fields ) {
		return array_merge(
			array(
				'Name'        => '',
				'PluginURI'   => '',
				'Version'     => '',
				'Description' => '',
				'Author'      => '',
				'AuthorURI'   => '',
				'TextDomain'  => '',
				'DomainPath'  => '',
			),
			$fields
		);
	}

	/**
	 * Records strings passed to translate() and returns a translation.
	 *
	 * URLs get a language path, other strings are wrapped in brackets.
	 *
	 * @param string $translation The translated text.
	 * @param string $text        The text to translate.
	 * @param string $domain      The text domain.
	 * @return string The translation.
	 */
	public function translate_string( $translation, $text, $domain ) {
		$this->translated[] = array( $text, $domain );

		if ( str_starts_with( $text, 'https://' ) ) {
			return $text . 'de/';
		}

		return '[' . $text . ']';
	}
}
