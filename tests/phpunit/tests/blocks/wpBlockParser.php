<?php
/**
 * Tests for WP_Block_Parser.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.0.0
 *
 * @group blocks
 *
 * @coversDefaultClass WP_Block_Parser
 */
class Tests_Blocks_wpBlockParser extends WP_UnitTestCase {
	/**
	 * The location of the fixtures to test with.
	 *
	 * @since 5.0.0
	 * @var string
	 */
	protected static $fixtures_dir;

	/**
	 * Ensures that a basic block parses into the expected data structure.
	 *
	 * @ticket 66138
	 */
	public function test_parses_basic_empty_block() {
		$p      = new WP_Block_Parser();
		$blocks = $p->parse( '<!-- wp:block -->' );

		$this->assertSame(
			array(
				array(
					'blockName'    => 'core/block',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			$blocks
		);
	}

	/**
	 * Ensures that implicitly closing a final open block does not repeat content parsed before it.
	 *
	 * @ticket 66138
	 *
	 * @dataProvider data_documents_ending_in_an_open_block
	 *
	 * @param string $document Document whose last block has no closer.
	 * @param array  $expected Expected block names and inner HTML, in order.
	 */
	public function test_implicitly_closed_final_block_does_not_repeat_content( $document, $expected ) {
		$p      = new WP_Block_Parser();
		$blocks = array_map(
			static function ( $block ) {
				return array( $block['blockName'], $block['innerHTML'] );
			},
			$p->parse( $document )
		);

		$this->assertSame( $expected, $blocks );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_documents_ending_in_an_open_block() {
		return array(
			'After a closed block'                  => array(
				'<!-- wp:paragraph -->A<!-- /wp:paragraph --><!-- wp:latest-posts -->',
				array(
					array( 'core/paragraph', 'A' ),
					array( 'core/latest-posts', '' ),
				),
			),
			'After leading HTML and a closed block' => array(
				'Hi<!-- wp:paragraph -->A<!-- /wp:paragraph --><!-- wp:latest-posts -->',
				array(
					array( null, 'Hi' ),
					array( 'core/paragraph', 'A' ),
					array( 'core/latest-posts', '' ),
				),
			),
			'After a closed block and HTML'         => array(
				'<!-- wp:paragraph -->A<!-- /wp:paragraph -->Bye<!-- wp:latest-posts -->',
				array(
					array( 'core/paragraph', 'A' ),
					array( null, 'Bye' ),
					array( 'core/latest-posts', '' ),
				),
			),
			'After a closed void block'             => array(
				'<!-- wp:separator /--><!-- wp:group -->A',
				array(
					array( 'core/separator', '' ),
					array( 'core/group', 'A' ),
				),
			),
		);
	}

	/**
	 * @dataProvider data_parsing_test_filenames
	 * @ticket 45109
	 *
	 * @covers ::parse
	 */
	public function test_default_parser_output( $html_filename, $parsed_json_filename ) {
		$html_path        = self::$fixtures_dir . '/' . $html_filename;
		$parsed_json_path = self::$fixtures_dir . '/' . $parsed_json_filename;

		foreach ( array( $html_path, $parsed_json_path ) as $filename ) {
			if ( ! file_exists( $filename ) ) {
				throw new Exception( "Missing fixture file: '$filename'" );
			}
		}

		$html            = self::strip_r( file_get_contents( $html_path ) );
		$expected_parsed = json_decode( self::strip_r( file_get_contents( $parsed_json_path ) ), true );

		$parser = new WP_Block_Parser();
		$result = json_decode( json_encode( $parser->parse( $html ) ), true );

		$this->assertSame(
			$expected_parsed,
			$result,
			"File '$parsed_json_filename' does not match expected value"
		);
	}

	/**
	 * @ticket 45109
	 */
	public function data_parsing_test_filenames() {
		self::$fixtures_dir = DIR_TESTDATA . '/blocks/fixtures';

		$fixture_filenames = array_merge(
			glob( self::$fixtures_dir . '/*.json' ),
			glob( self::$fixtures_dir . '/*.html' )
		);

		$fixture_filenames = array_values(
			array_unique(
				array_map(
					array( $this, 'clean_fixture_filename' ),
					$fixture_filenames
				)
			)
		);

		return array_map(
			array( $this, 'pass_parser_fixture_filenames' ),
			$fixture_filenames
		);
	}

	/**
	 * Helper function to remove relative paths and extension from a filename, leaving just the fixture name.
	 *
	 * @since 5.0.0
	 *
	 * @param string $filename The filename to clean.
	 * @return string The cleaned fixture name.
	 */
	protected function clean_fixture_filename( $filename ) {
		$filename = wp_basename( $filename );
		$filename = preg_replace( '/\..+$/', '', $filename );
		return $filename;
	}

	/**
	 * Helper function to return the filenames needed to test the parser output.
	 *
	 * @since 5.0.0
	 *
	 * @param string $filename The cleaned fixture name.
	 * @return array The input and expected output filenames for that fixture.
	 */
	protected function pass_parser_fixture_filenames( $filename ) {
		return array(
			"$filename.html",
			"$filename.parsed.json",
		);
	}

	/**
	 * Helper function to remove '\r' characters from a string.
	 *
	 * @since 5.0.0
	 *
	 * @param string $input The string to remove '\r' from.
	 * @return string The input string, with '\r' characters removed.
	 */
	protected function strip_r( $input ) {
		return str_replace( "\r", '', $input );
	}
}
