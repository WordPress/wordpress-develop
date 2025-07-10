<?php
/**
 * Unit tests covering WP_HTML_Processor META tag handling.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since TBD
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorMetaTag extends WP_UnitTestCase {
	/**
	 * Data provider.
	 */
	public static function data_supported_meta_tags(): array {
		return array(
			'No attributes'                        => array( '<meta>' ),
			'Unrelated attributes'                 => array( '<meta not-charset="OK">' ),
			'Boolean charset'                      => array( '<meta charset>' ),
			'HTTP Equiv: accept'                   => array( '<meta http-equiv="accept" content="">' ),
			'HTTP Equiv: content-type, no content' => array( '<meta http-equiv="content-type">' ),
			'Boolean HTTP Equiv'                   => array( '<meta http-equiv content="">' ),
		);
	}

	/**
	 * Ensures that META tags correctly handle encoding confidence.
	 *
	 * @ticket TBD
	 *
	 * @dataProvider data_supported_meta_tags
	 */
	public function test_supported_meta_tag( string $html ) {
		$html  = '<!DOCTYPE html>' . $html;
		$class = new class('') extends WP_HTML_Processor {
			public function __construct( $html ) {
				parent::__construct( $html, parent::CONSTRUCTOR_UNLOCK_CODE );
			}

			public static function create( string $html ): self {
				$instance = self::create_full_parser( $html );
				// Reset encoding so META tags can handle it.
				$reflection = new ReflectionClass( $instance );
				$property   = $reflection->getParentClass()->getProperty( 'state' );
				$property->setAccessible( true );
				$state                      = $property->getValue( $instance );
				$state->encoding            = null;
				$state->encoding_confidence = 'tentative';
				return $instance;
			}

			public function get_state_encoding(): ?string {
				$reflection = new ReflectionClass( $this );
				return $reflection->getParentClass()->getProperty( 'state' )->getValue( $this )->encoding;
			}

			public function get_state_encoding_confidence(): string {
				$reflection = new ReflectionClass( $this );
				return $reflection->getParentClass()->getProperty( 'state' )->getValue( $this )->encoding_confidence;
			}
		};

		$processor = $class::create( $html );
		$this->assertTrue( $processor->next_tag( 'META' ) );
		$this->assertSame( 'tentative', $processor->get_state_encoding_confidence() );
		$this->assertNull( $processor->get_state_encoding() );
	}

	/**
	 * Data provider.
	 */
	public function data_unsupported_meta_tags(): array {
		return array(
			'With charset'    => array( '<meta charset="utf8">', 'Cannot yet process META tags with charset to determine encoding.' ),
			'With CHARSET'    => array( '<meta CHARSET="utf8">', 'Cannot yet process META tags with charset to determine encoding.' ),
			'With http-equiv' => array( '<meta http-equiv="content-type" content="">', 'Cannot yet process META tags with http-equiv Content-Type to determine encoding.' ),
			'With http-equiv' => array( '<meta http-equiv="Content-Type" content="UTF-8">', 'Cannot yet process META tags with http-equiv Content-Type to determine encoding.' ),
		);
	}

	/**
	 * Ensures that unsupported encoding META tags bail.
	 *
	 * @ticket TBD
	 *
	 * @dataProvider data_unsupported_meta_tags
	 */
	public function test_unsupported_meta_tags( string $html, string $unsupported_message ) {
		$html  = '<!DOCTYPE html>' . $html;
		$class = new class('') extends WP_HTML_Processor {
			public function __construct( $html ) {
				parent::__construct( $html, parent::CONSTRUCTOR_UNLOCK_CODE );
			}

			public static function create( string $html ): self {
				$instance = self::create_full_parser( $html );
				// Reset encoding so META tags can handle it.
				$reflection = new ReflectionClass( $instance );
				$property   = $reflection->getParentClass()->getProperty( 'state' );
				$property->setAccessible( true );
				$state                      = $property->getValue( $instance );
				$state->encoding            = null;
				$state->encoding_confidence = 'tentative';
				return $instance;
			}
		};

		$processor = $class::create( $html );
		$this->assertFalse( $processor->next_tag( 'META' ) );
		$this->assertSame( $unsupported_message, $processor->get_unsupported_exception()->getMessage() );
	}
}
