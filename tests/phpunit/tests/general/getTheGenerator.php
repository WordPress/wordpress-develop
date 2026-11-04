<?php

/**
 * Tests for `get_the_generator()`, `the_generator()`, and `wp_generator()`.
 *
 * @group general
 * @group template
 *
 * @covers ::get_the_generator
 * @covers ::the_generator
 * @covers ::wp_generator
 */
class Tests_General_GetTheGenerator extends WP_UnitTestCase {

	/**
	 * Tests get_the_generator() with supported generator types.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_generator_types
	 *
	 * @param string $type     Generator type.
	 * @param string $expected Expected markup template with placeholder for version.
	 */
	public function test_get_the_generator_types( $type, $expected ) {
		$version = ( 'atom' === $type || 'rss2' === $type || 'rdf' === $type )
			? get_bloginfo_rss( 'version' )
			: get_bloginfo( 'version' );

		$this->assertSame( sprintf( $expected, $version ), get_the_generator( $type ) );
	}

	/**
	 * Data provider for test_get_the_generator_types().
	 *
	 * @return array[]
	 */
	public static function data_generator_types() {
		return array(
			'html'    => array(
				'html',
				'<meta name="generator" content="WordPress %s">',
			),
			'xhtml'   => array(
				'xhtml',
				'<meta name="generator" content="WordPress %s" />',
			),
			'atom'    => array(
				'atom',
				'<generator uri="https://wordpress.org/" version="%s">WordPress</generator>',
			),
			'rss2'    => array(
				'rss2',
				'<generator>https://wordpress.org/?v=%s</generator>',
			),
			'rdf'     => array(
				'rdf',
				'<admin:generatorAgent rdf:resource="https://wordpress.org/?v=%s" />',
			),
			'comment' => array(
				'comment',
				'<!-- generator="WordPress/%s" -->',
			),
		);
	}

	/**
	 * Tests get_the_generator( 'export' ) formats export comment markup.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_generator_export() {
		$output  = get_the_generator( 'export' );
		$version = get_bloginfo_rss( 'version' );

		$this->assertMatchesRegularExpression(
			'/^<!-- generator="WordPress\/' . preg_quote( $version, '/' ) . '" created="\d{4}-\d{2}-\d{2} \d{2}:\d{2}" -->$/',
			$output
		);
	}

	/**
	 * Tests that get_the_generator() returns null when $type is empty and no action hook is active.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_generator_returns_null_when_type_empty() {
		$this->assertNull( get_the_generator() );
		$this->assertNull( get_the_generator( '' ) );
	}

	/**
	 * Tests that get_the_generator_$type filter modifies the generator markup.
	 *
	 * @ticket 65819
	 */
	public function test_get_the_generator_filter() {
		add_filter(
			'get_the_generator_xhtml',
			static function ( $gen, $type ) {
				return '<meta name="custom-generator" content="' . $type . '">';
			},
			10,
			2
		);

		$this->assertSame( '<meta name="custom-generator" content="xhtml">', get_the_generator( 'xhtml' ) );
	}

	/**
	 * Tests that the_generator() echoes generator markup with a newline.
	 *
	 * @ticket 65819
	 */
	public function test_the_generator_echoes_with_newline() {
		$expected = get_the_generator( 'xhtml' ) . "\n";

		$this->expectOutputString( $expected );
		the_generator( 'xhtml' );
	}

	/**
	 * Tests that the the_generator filter modifies the displayed generator markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_generator_filter() {
		add_filter(
			'the_generator',
			static function ( $generator, $type ) {
				return '<!-- custom ' . $type . ' -->';
			},
			10,
			2
		);

		$this->expectOutputString( "<!-- custom html -->\n" );
		the_generator( 'html' );
	}

	/**
	 * Tests that wp_generator() displays the XHTML generator.
	 *
	 * @ticket 65819
	 */
	public function test_wp_generator_displays_xhtml_generator() {
		$expected = get_the_generator( 'xhtml' ) . "\n";

		$this->expectOutputString( $expected );
		wp_generator();
	}

	/**
	 * Tests that the wp_generator_type filter changes the generator type used by wp_generator().
	 *
	 * @ticket 65819
	 */
	public function test_wp_generator_type_filter() {
		add_filter(
			'wp_generator_type',
			static function () {
				return 'html';
			}
		);

		$expected = get_the_generator( 'html' ) . "\n";

		$this->expectOutputString( $expected );
		wp_generator();
	}
}
