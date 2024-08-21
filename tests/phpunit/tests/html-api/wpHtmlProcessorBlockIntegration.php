<?php
/**
 * Unit tests covering WP_HTML_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.4.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorBlockIntegration extends WP_UnitTestCase {
	const FIXTURES_DIR = DIR_TESTDATA . '/blocks/fixtures';

	/**
	 * @dataProvider data_do_block_test_filenames
	 * @ticket TBD
	 */
	public function test_do_block_output( $html_filename, $server_html_filename ) {
		$html_path        = self::FIXTURES_DIR . '/' . $html_filename;
		$server_html_path = self::FIXTURES_DIR . '/' . $server_html_filename;

		foreach ( array( $html_path, $server_html_path ) as $filename ) {
			if ( ! file_exists( $filename ) ) {
				throw new Exception( "Missing fixture file: '$filename'" );
			}
		}

		$html = do_blocks( file_get_contents( $html_path ) );

		$processor = WP_HTML_Processor::create_fragment( $html );
		while ( $processor->next_token() ) {
			// Proceed through the HTML until completion.
		}

		$this->assertFalse( $processor->paused_at_incomplete_token() );
		$this->assertNull( $processor->get_unsupported_exception() );
		$this->assertNull( $processor->get_last_error() );
	}

	/**
	 * @ticket TBD
	 */
	public function data_do_block_test_filenames() {
		$fixture_filenames = array_merge(
			glob( self::FIXTURES_DIR . '/*.json' ),
			glob( self::FIXTURES_DIR . '/*.html' )
		);

		$fixture_filenames = array_values(
			array_unique(
				array_map(
					array( __CLASS__, 'clean_fixture_filename' ),
					$fixture_filenames
				)
			)
		);

		return array_map(
			array( __CLASS__, 'pass_parser_fixture_filenames' ),
			$fixture_filenames
		);
	}

	/**
	 * Helper function to remove relative paths and extension from a filename, leaving just the fixture name.
	 *
	 * @param string $filename The filename to clean.
	 * @return string The cleaned fixture name.
	 */
	private static function clean_fixture_filename( $filename ) {
		$filename = wp_basename( $filename );
		$filename = preg_replace( '/\..+$/', '', $filename );
		return $filename;
	}

	/**
	 * Helper function to return the filenames needed to test the parser output.
	 *
	 * @param string $filename The cleaned fixture name.
	 * @return array The input and expected output filenames for that fixture.
	 */
	private static function pass_parser_fixture_filenames( $filename ) {
		return array(
			"$filename.html",
			"$filename.server.html",
		);
	}
}
