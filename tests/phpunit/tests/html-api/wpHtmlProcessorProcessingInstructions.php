<?php
/**
 * Unit tests covering WP_HTML_Processor processing instruction parsing.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 7.1.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorProcessingInstruction extends WP_UnitTestCase {
	/**
	 * Ensures that processing instructions are parsed with the proper target and data.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_processing_instructions
	 *
	 * @param string $html            Input containing a processing instruction.
	 * @param string $expected_target Target of the processing instruction.
	 * @param string $expected_data   Data of the processing instruction.
	 */
	public function test_processing_instructions( string $html, string $expected_target, string $expected_data ): void {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_token();

		$this->assertSame( '#processing-instruction', $processor->get_token_name() );
		$this->assertSame( '#processing-instruction', $processor->get_token_type() );
		$this->assertSame( $expected_target, $processor->get_tag() );
		$this->assertSame( $expected_data, $processor->get_modifiable_text() );
		$this->assertNull( $processor->get_comment_type() );
	}

	/**
	 * Data provider.
	 *
	 * The whitespace following the target is not part of the data, but all
	 * further text through the closing `>` is, excepting a final `?` when
	 * the processing instruction is closed by `?>`.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function data_processing_instructions(): array {
		return array(
			'Basic'                        => array( '<?pi-target Instruction body. ?>', 'pi-target', 'Instruction body. ' ),
			'PHP block'                    => array( '<?php const HTML_COMMENT = true; ?>', 'php', 'const HTML_COMMENT = true; ' ),
			'No data'                      => array( '<?something>', 'something', '' ),
			'No data, questionable close'  => array( '<?something?>', 'something', '' ),
			'Unclosed data'                => array( '<?something good>', 'something', 'good' ),
			'Data with spaces'             => array( '<?something else is good>', 'something', 'else is good' ),
			'Whitespace after target'      => array( "<?hey \t\f\r\n there?>", 'hey', 'there' ),
			'Tab terminates target'        => array( "<?hey\tthere=1?>", 'hey', 'there=1' ),
			'Question mark in data'        => array( '<?hey?there>', 'hey', '?there' ),
			'Question mark data run'       => array( '<?something ? >', 'something', '? ' ),
			'Data ending in question mark' => array( '<?wp-bit smile??>', 'wp-bit', 'smile?' ),
			'Ends at first closer'         => array( '<?t d > ?>', 't', 'd ' ),
			'Case preserved in target'     => array( '<?all-KINDS-of-CaSeS data>', 'all-KINDS-of-CaSeS', 'data' ),
			'Underscore target'            => array( '<?_x132 data>', '_x132', 'data' ),
			'Single-character target'      => array( '<?x data>', 'x', 'data' ),
			'Digits and hyphens in target' => array( '<?r2-d2 data>', 'r2-d2', 'data' ),
			'XML lookalike target prefix'  => array( '<?xml2 data>', 'xml2', 'data' ),
			'IMAGE target not rewritten'   => array( '<?IMAGE data?>', 'IMAGE', 'data' ),
		);
	}

	/**
	 * Ensures that invalid or reserved processing instruction targets
	 * produce comments instead of processing instruction nodes.
	 *
	 * The comment cases are more fully covered in the comment processing tests.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_invalid_targets
	 *
	 * @param string $html Input whose processing-instruction-like syntax has an invalid target.
	 */
	public function test_invalid_targets_become_comments( string $html ): void {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_token();

		$this->assertSame( '#comment', $processor->get_token_name() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_invalid_targets(): array {
		return array(
			'Reserved xml target'            => array( '<?xml version="1.0"?>' ),
			'Reserved xml-stylesheet target' => array( '<?xml-stylesheet href="a.css"?>' ),
			'Reserved target, cased'         => array( '<?XML-StyleSheet href="a.css"?>' ),
			'Leading digit'                  => array( '<?1st-place data?>' ),
			'Leading hyphen'                 => array( '<?-prefix data?>' ),
			'Colon in target'                => array( '<?namespace:tag data?>' ),
			'Dot in target'                  => array( '<?data.v1 data?>' ),
			'Non-ASCII target'               => array( '<?٥-star data?>' ),
			'Empty target'                   => array( '<?>' ),
			'Whitespace target'              => array( '<? >' ),
			'PHP short echo tag'             => array( '<?= "Hello" ?>' ),
		);
	}

	/**
	 * Ensures that a document ending inside a processing instruction
	 * pauses the processor at an incomplete token.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_incomplete_processing_instructions
	 *
	 * @param string $html Input ending inside a processing instruction.
	 */
	public function test_incomplete_processing_instruction( string $html ): void {
		$processor = WP_HTML_Processor::create_full_parser( $html );

		$this->assertFalse( $processor->next_token() );
		$this->assertTrue( $processor->paused_at_incomplete_token() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_incomplete_processing_instructions(): array {
		return array(
			'Bare opener'        => array( '<?' ),
			'Target only'        => array( '<?start' ),
			'Questionable close' => array( '<?start?' ),
			'Target and space'   => array( '<?start ' ),
			'Target and data'    => array( '<?start data' ),
			'Invalid target'     => array( '<?# comment' ),
		);
	}

	/**
	 * Ensures that processing instructions are found in every part of a document.
	 *
	 * In every insertion mode, a processing instruction token is handled
	 * like a comment token: it's inserted in place and does not influence
	 * the surrounding structure.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_processing_instruction_placement
	 *
	 * @param string $html        Input containing a processing instruction somewhere.
	 * @param string $breadcrumbs Breadcrumbs of the processing instruction, joined by " > ".
	 */
	public function test_processing_instruction_placement( string $html, string $breadcrumbs ): void {
		$processor = WP_HTML_Processor::create_full_parser( $html );

		while ( $processor->next_token() ) {
			if ( '#processing-instruction' === $processor->get_token_type() ) {
				$this->assertSame(
					$breadcrumbs,
					implode( ' > ', $processor->get_breadcrumbs() ),
					'Found the processing instruction in the wrong location.'
				);
				return;
			}
		}

		$this->fail( 'Failed to find the processing instruction in the document.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_processing_instruction_placement(): array {
		return array(
			'Before HTML'        => array( '<?wp-bit?><html>', '#processing-instruction' ),
			'In HEAD'            => array( '<head><?wp-bit?>', 'HTML > HEAD > #processing-instruction' ),
			'After HEAD'         => array( '<html><head></head><?wp-bit?><body>', 'HTML > #processing-instruction' ),
			'In BODY'            => array( '<body><div><?wp-bit?></div>', 'HTML > BODY > DIV > #processing-instruction' ),
			'In TABLE'           => array( '<body><table><?wp-bit?></table>', 'HTML > BODY > TABLE > #processing-instruction' ),
			'In TABLE ROW'       => array( '<body><table><tr><?wp-bit?></tr></table>', 'HTML > BODY > TABLE > TBODY > TR > #processing-instruction' ),
			'In TEMPLATE'        => array( '<body><template><?wp-bit?></template>', 'HTML > BODY > TEMPLATE > #processing-instruction' ),
			'In foreign content' => array( '<body><svg><?wp-bit?></svg>', 'HTML > BODY > SVG > #processing-instruction' ),
		);
	}

	/**
	 * Ensures that processing-instruction-like syntax inside raw text
	 * elements is not parsed as a processing instruction.
	 *
	 * @ticket 61530
	 *
	 * @dataProvider data_raw_text_elements
	 *
	 * @param string $html Input containing processing-instruction-like syntax in a raw text element.
	 */
	public function test_no_processing_instruction_in_raw_text( string $html ): void {
		$processor = WP_HTML_Processor::create_fragment( $html );

		while ( $processor->next_token() ) {
			$this->assertNotSame(
				'#processing-instruction',
				$processor->get_token_type(),
				'Should not have found a processing instruction inside a raw text element.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_raw_text_elements(): array {
		return array(
			'SCRIPT'   => array( '<script><?php echo "?>"; ?></script>' ),
			'STYLE'    => array( '<style><?wp-bit?></style>' ),
			'TEXTAREA' => array( '<textarea><?wp-bit?></textarea>' ),
			'TITLE'    => array( '<title><?wp-bit?></title>' ),
		);
	}
}
