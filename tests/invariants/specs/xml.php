<?php
/**
 * Invariants of XML parsing.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=xml.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'no-entity-substitution' )
		->describe( 'XML is never parsed with options which load external entities or DTDs (LIBXML_NOENT, LIBXML_DTDLOAD, LIBXML_DTDATTR), which would allow XML external entity attacks.' )
		->before_each(
			array( 'DOMDocument::loadXML', 'DOMDocument::load', 'DOMDocument::loadHTML', 'simplexml_load_string', 'simplexml_load_file', 'Dom\\XMLDocument::createFromString', 'Dom\\XMLDocument::createFromFile' ),
			static function ( WP_Invariant_Call $call ) {
				$options = (int) $call->arg( 'options' );
				$call->expect( 0 === ( $options & ( LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR ) ), '%s() was given the options %d, which load entities or DTDs.', $call->target(), $options );
			}
		),

	WP_Invariant::define( 'no-entity-declarations' )
		->describe( 'XML given to xml_parse() declares no entities, which the expat parser would expand.' )
		->before(
			'xml_parse',
			static function ( WP_Invariant_Call $call ) {
				$data = $call->arg( 'data' );
				$call->expect( ! is_string( $data ) || false === stripos( $data, '<!ENTITY' ), 'xml_parse() was given XML which declares entities: %s', $data );
			}
		),
);
