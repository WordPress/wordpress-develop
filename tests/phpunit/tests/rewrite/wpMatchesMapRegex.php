<?php

/**
 * Tests for the WP_MatchesMapRegex class.
 *
 * @group rewrite
 *
 * @coversDefaultClass WP_MatchesMapRegex
 */
class Tests_Rewrite_wpMatchesMapRegex extends WP_UnitTestCase {

	/**
	 * Tests that $matches[] placeholders are replaced with URL-encoded matches.
	 *
	 * @ticket 65819
	 *
	 * @covers ::apply
	 * @covers ::__construct
	 * @covers ::_map
	 * @covers ::callback
	 *
	 * @dataProvider data_apply
	 *
	 * @param string $subject  Subject containing placeholders.
	 * @param array  $matches  Regular expression matches.
	 * @param string $expected Expected result.
	 */
	public function test_apply( $subject, $matches, $expected ) {
		$this->assertSame( $expected, WP_MatchesMapRegex::apply( $subject, $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_apply() {
		$twelve_matches = array( 'full', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve' );

		return array(
			'single placeholder'               => array(
				'index.php?pagename=$matches[1]',
				array( 'about/', 'about' ),
				'index.php?pagename=about',
			),
			'several placeholders'             => array(
				'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
				array( '2026/10/page/3', '2026', '10', '3' ),
				'index.php?year=2026&monthnum=10&paged=3',
			),
			'placeholders out of order'        => array(
				'index.php?b=$matches[2]&a=$matches[1]',
				array( 'full', 'first', 'second' ),
				'index.php?b=second&a=first',
			),
			'repeated placeholder'             => array(
				'$matches[1]/$matches[1]',
				array( 'full', 'again' ),
				'again/again',
			),
			'match is URL-encoded'             => array(
				'index.php?s=$matches[1]',
				array( 'full', 'hello world & more/+?=' ),
				'index.php?s=hello+world+%26+more%2F%2B%3F%3D',
			),
			'multibyte match is URL-encoded'   => array(
				'index.php?name=$matches[1]',
				array( 'full', 'café' ),
				'index.php?name=caf%C3%A9',
			),
			'missing match'                    => array(
				'index.php?a=$matches[1]&b=$matches[2]',
				array( 'full', 'first' ),
				'index.php?a=first&b=',
			),
			'empty match'                      => array(
				'index.php?a=$matches[1]&b=$matches[2]',
				array( 'full', '', 'second' ),
				'index.php?a=&b=second',
			),
			'zero string match'                => array(
				'index.php?page=$matches[1]',
				array( 'full', '0' ),
				'index.php?page=0',
			),
			'no matches'                       => array(
				'index.php?a=$matches[1]',
				array(),
				'index.php?a=',
			),
			'two-digit placeholders'           => array(
				'$matches[10]-$matches[12]-$matches[1]',
				$twelve_matches,
				'ten-twelve-one',
			),
			'full match placeholder is kept'   => array(
				'index.php?a=$matches[0]&b=$matches[1]',
				array( 'full', 'first' ),
				'index.php?a=$matches[0]&b=first',
			),
			'leading zero placeholder is kept' => array(
				'index.php?a=$matches[01]',
				array( 'full', 'first' ),
				'index.php?a=$matches[01]',
			),
			'non-numeric placeholder is kept'  => array(
				'index.php?a=$matches[name]&b=$matches[]',
				array(
					0      => 'full',
					'name' => 'named',
				),
				'index.php?a=$matches[name]&b=$matches[]',
			),
			'other variable name is kept'      => array(
				'index.php?a=$match[1]',
				array( 'full', 'first' ),
				'index.php?a=$match[1]',
			),
			'subject without placeholders'     => array(
				'index.php?feed=rss2',
				array( 'full', 'first' ),
				'index.php?feed=rss2',
			),
			'empty subject'                    => array(
				'',
				array( 'full', 'first' ),
				'',
			),
		);
	}

	/**
	 * Tests that a new instance stores the mapped subject in its output property.
	 *
	 * @ticket 65819
	 *
	 * @covers ::__construct
	 * @covers ::_map
	 */
	public function test_constructor_sets_output() {
		$map = new WP_MatchesMapRegex( 'index.php?p=$matches[1]', array( 'full', '123' ) );

		$this->assertSame( 'index.php?p=123', $map->output );
	}

	/**
	 * Tests the replacement returned for a single placeholder.
	 *
	 * @ticket 65819
	 *
	 * @covers ::callback
	 *
	 * @dataProvider data_callback
	 *
	 * @param string $placeholder Matched placeholder.
	 * @param string $expected    Expected replacement.
	 */
	public function test_callback( $placeholder, $expected ) {
		$map = new WP_MatchesMapRegex( '', array( 'full', 'first value', 'second' ) );

		$this->assertSame( $expected, $map->callback( array( $placeholder ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_callback() {
		return array(
			'first match'   => array( '$matches[1]', 'first+value' ),
			'second match'  => array( '$matches[2]', 'second' ),
			'missing match' => array( '$matches[3]', '' ),
		);
	}
}
