<?php
/**
 * Unit tests covering WP_HTML_Processor fragment parsing functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.7.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorFragmentParsing extends WP_UnitTestCase {
	/**
	 * Verifies that SCRIPT fragment parses behave as they should.
	 *
	 * @dataProvider data_script_fragments
	 *
	 * @param string      $inner_html    HTML to parse in SCRIPT fragment.
	 * @param string|null $expected_html Expected output of the parse, or `null` if unsupported.
	 */
	public function test_script_tag( string $inner_html, ?string $expected_html ) {
		$processor = WP_HTML_Processor::create_fragment( $inner_html, '<script></script>' );
		$normalized = static::normalize_html( $processor );

		if ( isset( $expected_html ) ) {
			$this->assertSame(
				$expected_html,
				$normalized,
				'Failed to properly parse SCRIPT fragment.'
			);
		} else {
			$this->assertNull(
				$normalized,
				"Should have bailed when parsing but didn't."
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @ticket 61576
	 *
	 * @return array[]
	 */
	public static function data_script_fragments() {
		return array(
			'Basic SCRIPT'      => array( 'const x = 5 < y;', 'const x = 5 < y;' ),
			'Text after SCRIPT' => array( 'const x = 5 < y;</script>test', null ),
			'Tag after SCRIPT'  => array( 'end</script><img>', null ),
			'Double escape'     => array( "<!--<script>\nconsole.log('</script>');\n-->\nconsole.log('<img>');", "<!--<script>\nconsole.log('\</script>');\n-->\nconsole.log('<img'>);" ),
		);
	}

	/**
	 * Produces normalized HTML output given a processor as input, which has not
	 * yet started to proceed through its document.
	 *
	 * This can be used with a full or a fragment parser.
	 *
	 * @param WP_HTML_Processor $processor HTML Processor in READY state at the beginning of its input.
	 * @return string|null Normalized HTML from input processor.
	 */
	private static function normalize_html( WP_HTML_Processor $processor ): ?string {
		$html = '';

		while ( $processor->next_token() ) {
			$token_name = $processor->get_token_name();
			$token_type = $processor->get_token_type();
			$is_closer  = $processor->is_tag_closer();

			switch ( $token_type ) {
				case '#text':
					$html .= $processor->get_modifiable_text();
					break;

				case '#tag':
					if ( $is_closer ) {
						$html .= "</{$token_name}>";
					} else {
						$names = $processor->get_attribute_names_with_prefix( '' );
						if ( ! isset( $names ) ) {
							$html .= "<{$token_name}>";
						} else {
							$html .= "<{$token_name}";
							foreach ( $names as $name ) {
								$value = $processor->get_attribute( $name );
								if ( true === $value ) {
									$html .= " {$name}";
								} else {
									$value = strtr( $value, '"', '&quot;' );
									$html .= " {$name}=\"{$value}\"";
								}
							}
						}

						$text = $processor->get_modifiable_text();
						if ( '' !== $text ) {
							$html .= "{$text}</{$token_name}>";
						}
					}
					break;
			}
		}

		if ( null !== $processor->get_last_error() ) {
			return null;
		}

		return $html;
	}
}
