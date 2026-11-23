<?php

/**
 * @group admin
 * @group rewrite
 *
 * @covers ::extract_from_markers
 */
class Tests_Admin_Includes_Misc_ExtractFromMarkers_Test extends WP_UnitTestCase {

	/**
	 * Path to the temporary file used by the current test.
	 *
	 * @var string
	 */
	private $filename;

	public function set_up() {
		parent::set_up();
		$this->filename = wp_tempnam( 'markers' );
	}

	public function tear_down() {
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
		parent::tear_down();
	}

	/**
	 * @ticket 26829
	 *
	 * @dataProvider data_line_endings
	 *
	 * @param string $eol Line ending to use in the file.
	 */
	public function test_extract_from_markers( $eol ) {
		$lines = array(
			'',
			'# BEGIN Empty',
			'# END Empty',
			'',
			'# BEGIN FooBar',
			'FooBar line',
			'# END FooBar',
			'',
			'# BEGIN Foo Bar',
			'Foo Bar line',
			'# END Foo Bar',
			'',
			'# BEGIN Foo',
			'Foo line',
			'# END Foo',
			'',
			'# BEGIN Multiline',
			'# A comment that is skipped.',
			'',
			'Multiline 2',
			'Multiline 3',
			'',
			'Multiline 5',
			'',
			'# END Multiline',
		);
		file_put_contents( $this->filename, implode( $eol, $lines ) . $eol );

		$this->assertSame( array( 'Foo line' ), extract_from_markers( $this->filename, 'Foo' ), 'A marker that is a prefix of other markers should only match its own section.' );
		$this->assertSame( array( 'FooBar line' ), extract_from_markers( $this->filename, 'FooBar' ), 'A marker without spaces was not extracted correctly.' );
		$this->assertSame( array( 'Foo Bar line' ), extract_from_markers( $this->filename, 'Foo Bar' ), 'A marker containing a space was not extracted correctly.' );
		$this->assertSame(
			array( '', 'Multiline 2', 'Multiline 3', '', 'Multiline 5', '' ),
			extract_from_markers( $this->filename, 'Multiline' ),
			'A multiline section, including empty lines, was not extracted correctly.'
		);
		$this->assertSame( array(), extract_from_markers( $this->filename, 'Empty' ), 'An empty section should return an empty array.' );
		$this->assertSame( array(), extract_from_markers( $this->filename, 'Null' ), 'A missing section should return an empty array.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_line_endings() {
		return array(
			'LF'   => array( "\n" ),
			'CRLF' => array( "\r\n" ),
		);
	}

	/**
	 * @ticket 26829
	 */
	public function test_extract_from_markers_with_indented_markers() {
		file_put_contents( $this->filename, "\t# BEGIN Foo\n  Foo line\n  # Indented comment\n    # END Foo\n" );

		$this->assertSame( array( '  Foo line' ), extract_from_markers( $this->filename, 'Foo' ) );
	}

	/**
	 * @ticket 26829
	 */
	public function test_extract_from_markers_returns_empty_array_for_empty_file() {
		$this->assertSame( array(), extract_from_markers( $this->filename, 'Foo' ) );
	}

	/**
	 * @ticket 26829
	 */
	public function test_extract_from_markers_returns_empty_array_for_missing_file() {
		unlink( $this->filename );

		$this->assertSame( array(), extract_from_markers( $this->filename, 'Foo' ) );
	}
}
