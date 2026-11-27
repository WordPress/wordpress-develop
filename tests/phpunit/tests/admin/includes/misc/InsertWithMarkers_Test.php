<?php

/**
 * @group admin
 * @group rewrite
 *
 * @covers ::insert_with_markers
 */
class Tests_Admin_Includes_Misc_InsertWithMarkers_Test extends WP_UnitTestCase {

	/**
	 * Path to the temporary file used by the current test.
	 *
	 * @var string
	 */
	private $filename;

	public function set_up() {
		parent::set_up();
		$this->filename = wp_tempnam( 'markers' );

		// Leave out the inline instructions so the expected file contents stay readable.
		add_filter( 'insert_with_markers_inline_instructions', '__return_empty_array' );
	}

	public function tear_down() {
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
		parent::tear_down();
	}

	/**
	 * Tests that a section whose marker is a prefix of an earlier marker
	 * is updated, instead of the earlier section being overwritten.
	 *
	 * @ticket 26829
	 */
	public function test_insert_with_markers_does_not_overwrite_section_with_prefixed_marker() {
		file_put_contents(
			$this->filename,
			"# BEGIN WordPress_Foo\nfoo\n# END WordPress_Foo\n# BEGIN WordPress\nold\n# END WordPress"
		);

		$this->assertTrue( insert_with_markers( $this->filename, 'WordPress', array( 'new' ) ) );
		$this->assertSame(
			"# BEGIN WordPress_Foo\nfoo\n# END WordPress_Foo\n# BEGIN WordPress\nnew\n# END WordPress",
			file_get_contents( $this->filename )
		);
	}

	/**
	 * Tests that repeated inserts do not duplicate a section when a section
	 * with a prefixed marker precedes it, as described in the ticket.
	 *
	 * @ticket 26829
	 */
	public function test_insert_with_markers_does_not_duplicate_sections() {
		file_put_contents( $this->filename, "# BEGIN WordPress_Foo\n\n# END WordPress_Foo" );

		insert_with_markers( $this->filename, 'WordPress', array( 'rules' ) );
		insert_with_markers( $this->filename, 'WordPress', array( 'rules' ) );
		insert_with_markers( $this->filename, 'WordPress', array( 'rules' ) );

		$this->assertSame(
			"# BEGIN WordPress_Foo\n\n# END WordPress_Foo\n# BEGIN WordPress\nrules\n# END WordPress",
			file_get_contents( $this->filename )
		);
	}

	/**
	 * @ticket 26829
	 */
	public function test_insert_with_markers_replaces_indented_section() {
		file_put_contents( $this->filename, "before\n  # BEGIN Foo\n  old\n  # END Foo\nafter" );

		$this->assertTrue( insert_with_markers( $this->filename, 'Foo', array( 'new' ) ) );
		$this->assertSame( "before\n# BEGIN Foo\nnew\n# END Foo\nafter", file_get_contents( $this->filename ) );
	}

	/**
	 * @ticket 26829
	 */
	public function test_insert_with_markers_replaces_section_in_file_with_crlf_line_endings() {
		file_put_contents( $this->filename, "before\r\n# BEGIN Foo\r\nold\r\n# END Foo\r\nafter\r\n" );

		$this->assertTrue( insert_with_markers( $this->filename, 'Foo', array( 'new' ) ) );
		$this->assertSame( array( 'new' ), extract_from_markers( $this->filename, 'Foo' ) );
		$this->assertSame( 1, substr_count( file_get_contents( $this->filename ), '# BEGIN Foo' ) );
	}

	/**
	 * @ticket 26829
	 */
	public function test_insert_with_markers_does_not_modify_unchanged_section() {
		$contents = "# BEGIN Foo\nsame\n# END Foo";
		file_put_contents( $this->filename, $contents );

		$this->assertTrue( insert_with_markers( $this->filename, 'Foo', array( 'same' ) ) );
		$this->assertSame( $contents, file_get_contents( $this->filename ) );
	}

	/**
	 * @ticket 26829
	 */
	public function test_insert_with_markers_appends_missing_section() {
		file_put_contents( $this->filename, "# BEGIN FooBar\nfoobar\n# END FooBar" );

		$this->assertTrue( insert_with_markers( $this->filename, 'Foo', "line 1\nline 2" ) );
		$this->assertSame(
			"# BEGIN FooBar\nfoobar\n# END FooBar\n# BEGIN Foo\nline 1\nline 2\n# END Foo",
			file_get_contents( $this->filename )
		);
	}
}
