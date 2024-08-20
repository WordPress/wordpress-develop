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
	 * Verifies that the fragment parser doesn't allow invalid context nodes.
	 *
	 * This includes void elements and self-contained elements because they can
	 * contain no inner HTML. Operations on self-contained elements should occur
	 * through methods such as {@see WP_HTML_Tag_Processor::set_modifiable_text}.
	 *
	 * @ticket 61576
	 *
	 * @dataProvider data_invalid_fragment_contexts
	 *
	 * @param string $context Invalid context node for fragment parser.
	 */
	public function test_rejects_invalid_fragment_contexts( string $context ) {
		$this->assertNull(
			WP_HTML_Processor::create_fragment( 'just a test', $context ),
			"Should not have been able to create a fragment parser with context node {$context}"
		);
	}

	/**
	 * Data provider.
	 *
	 * @ticket 61576
	 *
	 * @return array[]
	 */
	public static function data_invalid_fragment_contexts() {
		return array(
			// Invalid contexts.
			'Invalid text'     => array( 'just some text' ),
			'Invalid comment'  => array( '<!-- comment -->' ),
			'Invalid closing'  => array( '</div>' ),
			'Invalid DOCTYPE'  => array( '<!DOCTYPE html>' ),

			// Void elements.
			'AREA'             => array( '<area>' ),
			'BASE'             => array( '<base>' ),
			'BASEFONT'         => array( '<basefont>' ),
			'BGSOUND'          => array( '<bgsound>' ),
			'BR'               => array( '<br>' ),
			'COL'              => array( '<col>' ),
			'EMBED'            => array( '<embed>' ),
			'FRAME'            => array( '<frame>' ),
			'HR'               => array( '<hr>' ),
			'IMG'              => array( '<img>' ),
			'INPUT'            => array( '<input>' ),
			'KEYGEN'           => array( '<keygen>' ),
			'LINK'             => array( '<link>' ),
			'META'             => array( '<meta>' ),
			'PARAM'            => array( '<param>' ),
			'SOURCE'           => array( '<source>' ),
			'TRACK'            => array( '<track>' ),
			'WBR'              => array( '<wbr>' ),

			// Self-contained elements.
			'IFRAME'           => array( '<iframe>' ),
			'NOEMBED'          => array( '<noembed>' ),
			'NOFRAMES'         => array( '<noframes>' ),
			'SCRIPT'           => array( '<script>' ),
			'SCRIPT with type' => array( '<script type="javascript">' ),
			'STYLE'            => array( '<style>' ),
			'TEXTAREA'         => array( '<textarea>' ),
			'TITLE'            => array( '<title>' ),
			'XMP'              => array( '<xmp>' ),
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
