<?php
/**
 * Test WP_Font_Utils::get_font_face_slug().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers WP_Font_Utils::get_font_face_slug
 */
class Tests_Fonts_WpFontUtils_GetFontFaceSlug extends WP_UnitTestCase {
	/**
	 * @dataProvider data_get_font_face_slug_normalizes_values
	 *
	 * @param string[] $settings      Settings to test.
	 * @param string   $expected_slug Expected slug results.
	 */
	public function test_get_font_face_slug_normalizes_values( $settings, $expected_slug ) {
		$slug = WP_Font_Utils::get_font_face_slug( $settings );

		$this->assertSame( $expected_slug, $slug );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_font_face_slug_normalizes_values() {
		return array(
			'Sets defaults'                           => array(
				'settings'      => array(
					'fontFamily' => 'Open Sans',
				),
				'expected_slug' => 'open sans;normal;400;100%;U+0-10FFFF',
			),
			'Converts normal weight to 400'           => array(
				'settings'      => array(
					'fontFamily' => 'Open Sans',
					'fontWeight' => 'normal',
				),
				'expected_slug' => 'open sans;normal;400;100%;U+0-10FFFF',
			),
			'Converts bold weight to 700'             => array(
				'settings'      => array(
					'fontFamily' => 'Open Sans',
					'fontWeight' => 'bold',
				),
				'expected_slug' => 'open sans;normal;700;100%;U+0-10FFFF',
			),
			'Converts normal font-stretch to 100%'    => array(
				'settings'      => array(
					'fontFamily'  => 'Open Sans',
					'fontStretch' => 'normal',
				),
				'expected_slug' => 'open sans;normal;400;100%;U+0-10FFFF',
			),
			'Removes double quotes from fontFamilies' => array(
				'settings'      => array(
					'fontFamily' => '"Open Sans"',
				),
				'expected_slug' => 'open sans;normal;400;100%;U+0-10FFFF',
			),
			'Removes single quotes from fontFamilies' => array(
				'settings'      => array(
					'fontFamily' => "'Open Sans'",
				),
				'expected_slug' => 'open sans;normal;400;100%;U+0-10FFFF',
			),
			'Removes spaces between comma separated font families' => array(
				'settings'      => array(
					'fontFamily' => 'Open Sans, serif',
				),
				'expected_slug' => 'open sans,serif;normal;400;100%;U+0-10FFFF',
			),
			'Removes tabs between comma separated font families' => array(
				'settings'      => array(
					'fontFamily' => "Open Sans,\tserif",
				),
				'expected_slug' => 'open sans,serif;normal;400;100%;U+0-10FFFF',
			),
			'Removes new lines between comma separated font families' => array(
				'settings'      => array(
					'fontFamily' => "Open Sans,\nserif",
				),
				'expected_slug' => 'open sans,serif;normal;400;100%;U+0-10FFFF',
			),

			// Trac #63568: the slug uses the decoded font name.
			'Keeps a comma inside a quoted name'      => array(
				'settings'      => array(
					'fontFamily' => '"ACME, Sans"',
				),
				'expected_slug' => 'acme%2c sans;normal;400;100%;U+0-10FFFF',
			),
			'Keeps an apostrophe'                     => array(
				'settings'      => array(
					'fontFamily' => "O'Reilly Sans",
				),
				'expected_slug' => "o'reilly sans;normal;400;100%;U+0-10FFFF",
			),
			'Keeps a percent sequence'                => array(
				'settings'      => array(
					'fontFamily' => '"Font 50%AB"',
				),
				'expected_slug' => 'font 50%25ab;normal;400;100%;U+0-10FFFF',
			),
			'Keeps both spaces'                       => array(
				'settings'      => array(
					'fontFamily' => '"A  B"',
				),
				'expected_slug' => 'a  b;normal;400;100%;U+0-10FFFF',
			),
			'Escapes a semicolon inside a name'       => array(
				'settings'      => array(
					'fontFamily' => '"A;B"',
				),
				'expected_slug' => 'a%3bb;normal;400;100%;U+0-10FFFF',
			),
			'Decodes a hexadecimal escape'            => array(
				'settings'      => array(
					'fontFamily' => '"Tom \\26  Jerry"',
				),
				'expected_slug' => 'tom %26 jerry;normal;400;100%;U+0-10FFFF',
			),
		);
	}

	/**
	 * Values that write the same name with different CSS escapes must share a slug.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_equivalent_font_families
	 *
	 * @param string[] $font_families Equivalent font family values.
	 */
	public function test_equivalent_font_families_share_a_slug( $font_families ) {
		$slugs = array();

		foreach ( $font_families as $font_family ) {
			$slugs[] = WP_Font_Utils::get_font_face_slug( array( 'fontFamily' => $font_family ) );
		}

		$this->assertCount( 1, array_unique( $slugs ), 'Equivalent values should share one slug.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_equivalent_font_families() {
		return array(
			'quoted and unquoted'   => array( array( 'Open Sans', '"Open Sans"', "'Open Sans'" ) ),
			'escaped and literal'   => array( array( '"Tom \\26  Jerry"', '"Tom & Jerry"', '"Tom \\000026  Jerry"' ) ),
			'escaped comma'         => array( array( 'ACME\\,Sans', '"ACME,Sans"' ) ),
			'a quoted generic name' => array( array( '"serif"', "'serif'" ) ),
		);
	}

	/**
	 * Distinct names must not share a slug.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_distinct_font_families
	 *
	 * @param string $first  First font family value.
	 * @param string $second Second font family value.
	 */
	public function test_distinct_font_families_have_distinct_slugs( $first, $second ) {
		$this->assertNotSame(
			WP_Font_Utils::get_font_face_slug( array( 'fontFamily' => $first ) ),
			WP_Font_Utils::get_font_face_slug( array( 'fontFamily' => $second ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_distinct_font_families() {
		return array(
			'a comma in a name against a list' => array( '"ACME, Sans"', '"ACME", "Sans"' ),
			'one space against two spaces'     => array( '"A B"', '"A  B"' ),
			'a semicolon against no semicolon' => array( '"A;B"', '"AB"' ),
			'different names'                  => array( '"Open Sans"', '"OpenSans"' ),
		);
	}
}
