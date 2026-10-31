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
	 * @dataProvider data_documents_with_implicit_closings
	 *
	 * @param string[] $tokens List of block and HTML tokens comprising a document, where the first
	 *                         is a partial document ending in open blocks, while the rest are the
	 *                         explicit closers corresponding to those open blocks.
	 */
	public function test_implicitly_closed_blocks_is_equivalent_to_explicitly_closed( $tokens ) {
		$parser        = new WP_Block_Parser();
		$full_document = $parser->parse( implode( '', $tokens ) );
		$document      = array_shift( $tokens );

		foreach ( $tokens as $token ) {
			$document .= $token;

			$this->assertSame(
				$full_document,
				$parser->parse( $document ),
				'Should have implicitly closed open blocks as if the closers had been there.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, list<non-empty-string>>
	 */
	public static function data_documents_with_implicit_closings() {
		return array(
			'After a closed block'                  => array(
				array(
					'<!-- wp:paragraph -->A<!-- /wp:paragraph --><!-- wp:latest-posts -->',
					'<!-- /wp:latest-posts -->',
				),
			),
			'After leading HTML and a closed block' => array(
				array(
					'Hi<!-- wp:paragraph -->A<!-- /wp:paragraph --><!-- wp:latest-posts -->',
					'<!-- /wp:latest-posts -->',
				),
			),
			'After a closed block and HTML'         => array(
				array(
					'<!-- wp:paragraph -->A<!-- /wp:paragraph -->Bye<!-- wp:latest-posts -->',
					'<!-- /wp:latest-posts -->',
				),
			),
			'Deeply-nested open blocks'             => array(
				array(
					'0<!-- wp:a -->1<!-- wp:b -->2<!-- wp:c -->3<!-- wp:d -->4',
					'<!-- /wp:d -->',
					'<!-- /wp:c -->',
					'<!-- /wp:b -->',
					'<!-- /wp:a -->',
				),
			),
			'After a closed void block'             => array(
				array(
					'<!-- wp:separator /--><!-- wp:group -->A',
					'<!-- /wp:group -->',
				),
			),
			'With the wrong closer'                 => array(
				array(
					'<!-- wp:opener -->A',
					'<!-- /wp:closer -->',
				),
			),
			'With an invalid closer'                => array(
				array(
					'<!-- wp:opener -->A',
					'<!-- /wp:opener /-->',
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
	public static function data_parsing_test_filenames() {
		self::$fixtures_dir = DIR_TESTDATA . '/blocks/fixtures';

		$fixture_filenames = array_merge(
			glob( self::$fixtures_dir . '/*.json' ),
			glob( self::$fixtures_dir . '/*.html' )
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
	 * @since 5.0.0
	 *
	 * @param string $filename The filename to clean.
	 * @return string The cleaned fixture name.
	 */
	protected static function clean_fixture_filename( $filename ) {
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
	protected static function pass_parser_fixture_filenames( $filename ) {
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

	/**
	 * Ensures that implicitly closed inner blocks are added as arrays, like every other parsed block.
	 *
	 * @ticket 66138
	 */
	public function test_implicitly_closed_inner_blocks_are_arrays() {
		$processor = new WP_Block_Parser();
		$blocks    = $processor->parse( '<!-- wp:group --><!-- wp:columns --><!-- wp:paragraph -->B' );

		$this->assertSame(
			array(
				array(
					'blockName'    => 'core/group',
					'attrs'        => array(),
					'innerBlocks'  => array(
						array(
							'blockName'    => 'core/columns',
							'attrs'        => array(),
							'innerBlocks'  => array(
								array(
									'blockName'    => 'core/paragraph',
									'attrs'        => array(),
									'innerBlocks'  => array(),
									'innerHTML'    => 'B',
									'innerContent' => array( 'B' ),
								),
							),
							'innerHTML'    => '',
							'innerContent' => array( null ),
						),
					),
					'innerHTML'    => '',
					'innerContent' => array( null ),
				),
			),
			$blocks
		);
	}
}
