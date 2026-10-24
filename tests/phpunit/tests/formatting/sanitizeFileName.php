<?php

/**
 * @group formatting
 *
 * @covers ::sanitize_file_name
 */
class Tests_Formatting_SanitizeFileName extends WP_UnitTestCase {
	public function test_munges_extensions() {
		// r17990
		$file_name = sanitize_file_name( 'test.phtml.txt' );
		$this->assertSame( 'test.phtml_.txt', $file_name );
	}

	public function test_removes_special_chars() {
		$special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ) );
		$string        = 'test';
		foreach ( $special_chars as $char ) {
			$string .= $char;
		}
		$string .= 'test';
		$this->assertSame( 'testtest', sanitize_file_name( $string ) );
	}

	/**
	 * @ticket 30495
	 * @dataProvider data_control_characters
	 *
	 * @param string $filename Filename containing control characters.
	 * @param string $expected Expected sanitized filename.
	 */
	public function test_sanitizes_control_characters( $filename, $expected ) {
		$this->assertSame( $expected, sanitize_file_name( $filename ) );
	}

	/**
	 * Data provider for test_sanitizes_control_characters().
	 *
	 * @return array[]
	 */
	public function data_control_characters() {
		$data = array();

		for ( $control = 0; $control < 32; ++$control ) {
			$data[ sprintf( 'U+%04X', $control ) ] = array( 'menu' . chr( $control ) . '2026.pdf', 'menu2026.pdf' );
		}

		// These whitespace controls are already replaced with a dash.
		$data['U+0009'][1] = 'menu-2026.pdf';
		$data['U+000A'][1] = 'menu-2026.pdf';
		$data['U+000D'][1] = 'menu-2026.pdf';

		$data['leading control']           = array( "\x0Bmenu2026.pdf", 'menu2026.pdf' );
		$data['trailing control']          = array( "menu2026.pdf\x0C", 'menu2026.pdf' );
		$data['mixed controls and spaces'] = array( "\x01menu\t\x0B\r\n 2026\x1F.pdf\x0C", 'menu-2026.pdf' );
		$data['multiple extensions']       = array( "te\x0Bst.pht\x01ml.tx\x1Ft", 'test.phtml_.txt' );
		$data['accented characters']       = array( "café\x0B-menu.pdf", 'cafe-menu.pdf' );
		$data['Korean characters']         = array( "메뉴\x0B 2026.pdf", '메뉴-2026.pdf' );
		$data['emoji']                     = array( "menu😀\x0B2026.pdf", 'menu😀2026.pdf' );

		return $data;
	}

	/**
	 * @ticket 30495
	 */
	public function test_control_characters_are_filterable() {
		$filename = "menu\x0B2026.pdf";
		$filter   = function ( $special_chars, $filename_raw ) use ( $filename ) {
			$this->assertContains( "\x0B", $special_chars );
			$this->assertSame( $filename, $filename_raw );

			return array_diff( $special_chars, array( "\x0B" ) );
		};

		add_filter( 'sanitize_file_name_chars', $filter, 10, 2 );
		try {
			$actual = sanitize_file_name( $filename );
		} finally {
			remove_filter( 'sanitize_file_name_chars', $filter, 10 );
		}

		$this->assertSame( $filename, $actual );
	}

	/**
	 * @ticket 30495
	 * @dataProvider data_control_characters_final_filter
	 *
	 * @param string $filename Filename containing control characters.
	 * @param string $expected Expected sanitized filename.
	 */
	public function test_control_characters_with_final_filter( $filename, $expected ) {
		$filter = function ( $sanitized, $filename_raw ) use ( $filename, $expected ) {
			$this->assertSame( $expected, $sanitized );
			$this->assertSame( $filename, $filename_raw );

			return 'filtered-' . $sanitized;
		};

		add_filter( 'sanitize_file_name', $filter, 10, 2 );
		try {
			$actual = sanitize_file_name( $filename );
		} finally {
			remove_filter( 'sanitize_file_name', $filter, 10 );
		}

		$this->assertSame( 'filtered-' . $expected, $actual );
	}

	/**
	 * Data provider for test_control_characters_with_final_filter().
	 *
	 * @return array[]
	 */
	public function data_control_characters_final_filter() {
		return array(
			'single extension'    => array( "menu\x0B2026.pdf", 'menu2026.pdf' ),
			'multiple extensions' => array( "te\x0Bst.phtml.txt", 'test.phtml_.txt' ),
		);
	}

	/**
	 * @ticket 22363
	 */
	public function test_removes_accents() {
		$in  = 'àáâãäåæçèéêëìíîïñòóôõöøùúûüýÿ';
		$out = 'aaaaaaaeceeeeiiiinoooooouuuuyy';
		$this->assertSame( $out, sanitize_file_name( $in ) );
	}

	/**
	 * Test that spaces are correctly replaced with dashes.
	 *
	 * @ticket 16330
	 * @ticket 62995
	 */
	public function test_replaces_spaces() {
		$urls = array(
			'unencoded space.png'                         => 'unencoded-space.png',
			'encoded-space.jpg'                           => 'encoded-space.jpg',
			'plus+space.jpg'                              => 'plusspace.jpg',
			'multi %20 +space.png'                        => 'multi-20-space.png',
			"Screenshot 2025-02-19 at 2.17.33\u{202F}PM.png" => 'Screenshot-2025-02-19-at-2.17.33-PM.png',
			"Filename with non-breaking\u{00A0}space.txt" => 'Filename-with-non-breaking-space.txt',
			"Filename with thin\u{2009}space.txt"         => 'Filename-with-thin-space.txt',
		);

		foreach ( $urls as $test => $expected ) {
			$this->assertSame( $expected, sanitize_file_name( $test ) );
		}
	}

	public function test_replaces_any_number_of_hyphens_with_one_hyphen() {
		$this->assertSame( 'a-t-t', sanitize_file_name( 'a----t----t' ) );
	}

	public function test_trims_trailing_hyphens() {
		$this->assertSame( 'a-t-t', sanitize_file_name( 'a----t----t----' ) );
	}

	public function test_replaces_any_amount_of_whitespace_with_one_hyphen() {
		$this->assertSame( 'a-t', sanitize_file_name( 'a          t' ) );
		$this->assertSame( 'a-t', sanitize_file_name( "a    \n\n\nt" ) );
	}

	/**
	 * @ticket 16226
	 */
	public function test_replaces_percent_sign() {
		$this->assertSame( 'a22b.jpg', sanitize_file_name( 'a%22b.jpg' ) );
	}

	public function test_replaces_unnamed_file_extensions() {
		// Test filenames with both supported and unsupported extensions.
		$this->assertSame( 'unnamed-file.exe', sanitize_file_name( '_.exe' ) );
		$this->assertSame( 'unnamed-file.jpg', sanitize_file_name( '_.jpg' ) );
	}

	public function test_replaces_unnamed_file_extensionless() {
		// Test a filenames that becomes extensionless.
		$this->assertSame( 'no-extension', sanitize_file_name( '_.no-extension' ) );
	}

	/**
	 * @dataProvider data_wp_filenames
	 */
	public function test_replaces_invalid_utf8_characters( $input, $expected ) {
		$this->assertSame( $expected, sanitize_file_name( $input ) );
	}

	public static function data_wp_filenames() {
		return array(
			array( urldecode( '%B1myfile.png' ), 'myfile.png' ),
			array( urldecode( '%B1myfile' ), 'myfile' ),
			array( 'demo bar.png', 'demo-bar.png' ),
			array( 'demo' . json_decode( '"\u00a0"' ) . 'bar.png', 'demo-bar.png' ),
		);
	}

	/**
	 * Tests that sanitize_file_name() replaces consecutive periods
	 * with a single period.
	 *
	 * @ticket 57242
	 *
	 * @dataProvider data_sanitize_file_name_should_replace_consecutive_periods_with_a_single_period
	 *
	 * @param string $filename A filename with consecutive periods.
	 * @param string $expected The expected filename after sanitization.
	 */
	public function test_sanitize_file_name_should_replace_consecutive_periods_with_a_single_period( $filename, $expected ) {
		$this->assertSame( $expected, sanitize_file_name( $filename ) );
	}

	/**
	 * Data provider for test_sanitize_file_name_should_replace_consecutive_periods_with_a_single_period().
	 *
	 * @return array[]
	 */
	public static function data_sanitize_file_name_should_replace_consecutive_periods_with_a_single_period() {
		return array(
			'consecutive periods at the start'         => array(
				'filename' => '...filename.png',
				'expected' => 'filename.png',
			),
			'consecutive periods in the middle'        => array(
				'filename' => 'file.......name.png',
				'expected' => 'file.name_.png',
			),
			'consecutive periods before the extension' => array(
				'filename' => 'filename....png',
				'expected' => 'filename.png',
			),
			'consecutive periods after the extension'  => array(
				'filename' => 'filename.png...',
				'expected' => 'filename.png',
			),
			'consecutive periods at the start, middle, before, after the extension' => array(
				'filename' => '.....file....name...png......',
				'expected' => 'file.name_.png',
			),
			'consecutive periods and no extension'     => array(
				'filename' => 'filename...',
				'expected' => 'filename',
			),
		);
	}
}
