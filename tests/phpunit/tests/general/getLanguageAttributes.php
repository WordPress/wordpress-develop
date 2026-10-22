<?php

/**
 * Tests for the `get_language_attributes()` and `language_attributes()` functions.
 *
 * @group general
 * @group template
 * @group i18n
 */
class Tests_General_GetLanguageAttributes extends WP_UnitTestCase {

	/**
	 * Original text direction of the global locale.
	 *
	 * @var string
	 */
	private $original_text_direction;

	public function set_up() {
		parent::set_up();

		$this->original_text_direction = $GLOBALS['wp_locale']->text_direction;
	}

	public function tear_down() {
		$GLOBALS['wp_locale']->text_direction = $this->original_text_direction;

		parent::tear_down();
	}

	/**
	 * Tests the attributes for each doctype and HTML type.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_language_attributes
	 *
	 * @dataProvider data_doctypes_and_html_types
	 *
	 * @param string $doctype   Doctype passed to the function.
	 * @param string $html_type Value of the html_type option.
	 * @param string $expected  Expected attributes.
	 */
	public function test_doctypes_and_html_types( $doctype, $html_type, $expected ) {
		update_option( 'html_type', $html_type );

		$this->assertSame( $expected, get_language_attributes( $doctype ) );
	}

	/**
	 * Data provider for test_doctypes_and_html_types().
	 *
	 * @return array[]
	 */
	public static function data_doctypes_and_html_types() {
		return array(
			'html, text/html'              => array( 'html', 'text/html', 'lang="en-US"' ),
			'xhtml, text/html'             => array( 'xhtml', 'text/html', 'lang="en-US" xml:lang="en-US"' ),
			'html, application/xhtml+xml'  => array( 'html', 'application/xhtml+xml', 'lang="en-US" xml:lang="en-US"' ),
			'xhtml, application/xhtml+xml' => array( 'xhtml', 'application/xhtml+xml', 'xml:lang="en-US"' ),
			'html, empty html_type'        => array( 'html', '', 'lang="en-US"' ),
			'xhtml, empty html_type'       => array( 'xhtml', '', 'xml:lang="en-US"' ),
		);
	}

	/**
	 * Tests that the default doctype is 'html'.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_language_attributes
	 */
	public function test_defaults_to_html_doctype() {
		update_option( 'html_type', 'application/xhtml+xml' );

		$this->assertSame( 'lang="en-US" xml:lang="en-US"', get_language_attributes() );
	}

	/**
	 * Tests that the language comes from the site locale.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_language_attributes
	 */
	public function test_uses_site_language() {
		add_filter(
			'determine_locale',
			static function () {
				return 'pt_BR';
			}
		);

		$this->assertSame( 'lang="pt-BR" xml:lang="pt-BR"', get_language_attributes( 'xhtml' ) );
	}

	/**
	 * Tests that the dir attribute is added for right-to-left locales.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_language_attributes
	 */
	public function test_adds_dir_for_rtl() {
		$GLOBALS['wp_locale']->text_direction = 'rtl';

		$this->assertSame( 'dir="rtl" lang="en-US"', get_language_attributes() );
	}

	/**
	 * Tests the arguments passed to the 'language_attributes' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_language_attributes
	 */
	public function test_language_attributes_filter() {
		$filter = new MockAction();
		add_filter( 'language_attributes', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'language_attributes',
			static function ( $output, $doctype ) {
				return $output . ' data-doctype="' . $doctype . '"';
			},
			20,
			2
		);

		$output = get_language_attributes( 'xhtml' );

		$this->assertSame( array( 'lang="en-US" xml:lang="en-US"', 'xhtml' ), $filter->get_args()[0], 'The filter should receive the attributes and the doctype.' );
		$this->assertSame( 'lang="en-US" xml:lang="en-US" data-doctype="xhtml"', $output, 'The filtered attributes should be returned.' );
	}

	/**
	 * Tests that language_attributes() displays the attributes for the given doctype.
	 *
	 * @ticket 65819
	 *
	 * @covers ::language_attributes
	 *
	 * @dataProvider data_language_attributes_output
	 *
	 * @param string|null $doctype  Doctype passed to the function, or null for the default.
	 * @param string      $expected Expected output.
	 */
	public function test_language_attributes_output( $doctype, $expected ) {
		$this->expectOutputString( $expected );

		if ( null === $doctype ) {
			language_attributes();
		} else {
			language_attributes( $doctype );
		}
	}

	/**
	 * Data provider for test_language_attributes_output().
	 *
	 * @return array[]
	 */
	public static function data_language_attributes_output() {
		return array(
			'default doctype' => array( null, 'lang="en-US"' ),
			'xhtml doctype'   => array( 'xhtml', 'lang="en-US" xml:lang="en-US"' ),
		);
	}
}
