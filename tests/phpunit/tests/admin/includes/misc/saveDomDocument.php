<?php
/**
 * Tests for the saveDomDocument() function.
 *
 * @group admin
 *
 * @covers ::saveDomDocument
 */
class Tests_saveDomDocument extends WP_UnitTestCase {
	/**
	 * Path to the temporary file.
	 *
	 * @var string
	 */
	private $temp_file;

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$this->temp_file = wp_tempnam( 'saveDomDocument' );
	}

	/**
	 * Clean up the test environment.
	 */
	public function tear_down() {
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		parent::tear_down();
	}

	/**
	 * Tests that saveDomDocument() correctly saves a DOMDocument to a file.
	 *
	 * @ticket 65173
	 */
	public function test_saveDomDocument_saves_file() {
		$doc  = new DOMDocument();
		$root = $doc->createElement( 'root' );
		$doc->appendChild( $root );

		saveDomDocument( $doc, $this->temp_file );

		$this->assertFileExists( $this->temp_file, 'The file should be saved.' );
		$this->assertXmlStringEqualsXmlFile( $this->temp_file, $doc->saveXML(), 'The saved file content should match the XML.' );
	}

	/**
	 * Tests that saveDomDocument() converts LF to CRLF.
	 *
	 * @ticket 65173
	 */
	public function test_saveDomDocument_converts_line_endings() {
		$doc = new DOMDocument();
		// Create a multi-line XML.
		$root   = $doc->createElement( 'root' );
		$child1 = $doc->createElement( 'child', 'one' );
		$child2 = $doc->createElement( 'child', 'two' );
		$root->appendChild( $child1 );
		$root->appendChild( $child2 );
		$doc->appendChild( $root );
		$doc->formatOutput = true;

		saveDomDocument( $doc, $this->temp_file );

		$content = file_get_contents( $this->temp_file );

		// The function replaces [^\r]\n with \r\n.
		// Note: DOMDocument::saveXML() usually outputs LF on Linux environments where these tests run in Docker.

		$this->assertStringContainsString( "\r\n", $content, 'The output should contain CRLF line endings.' );
		$this->assertStringNotContainsString( "(?<!\r)\n", $content, 'The output should not contain bare LF line endings.' );
	}
}
