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
	 *
	 * @ticket TBD
	 */
	public function test_do_block_output( string $html_filename ) {
		$html_path = self::FIXTURES_DIR . '/' . $html_filename;

		if ( ! file_exists( $html_path ) ) {
			throw new Exception( "Missing fixture file: '$html_path'" );
		}

		$html = file_get_contents( $html_path );

		$processor = WP_HTML_Processor::create_fragment( $html );
		while ( $processor->next_token() ) {
			// Proceed through the HTML until completion.
		}

		$this->assertFalse( $processor->paused_at_incomplete_token() );
		$this->assertNull( $processor->get_unsupported_exception() );
		$this->assertNull( $processor->get_last_error() );
	}

	/**
	 * Data provider.
	 */
	public static function data_do_block_test_filenames() {
		$fixture_filenames = glob( self::FIXTURES_DIR . '/*.server.html' );

		$fixture_filenames = array_values(
			array_unique(
				array_map(
					function ( string $filename ): string {
						$filename = wp_basename( $filename );
						$filename = preg_replace( '/\..+$/', '.html', $filename );
						return $filename;
					},
					$fixture_filenames
				)
			)
		);

		return array_map(
			function ( string $filename ): array {
				return array( $filename );
			},
			$fixture_filenames
		);
	}
}
