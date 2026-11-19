<?php
/**
 * Invariants of the XML WordPress generates, checked against a strict XML parser.
 *
 * These hook WordPress functions, not built-in ones, so they are apart from
 * specs/xml.php, which WP_INVARIANTS_SKIP=xml leaves out for speed.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'well-formed' )
		->describe( 'The XML documents and fragments WordPress generates for sitemaps, oEmbed and XML-RPC are well-formed, as a strict XML parser reads them.' )
		->include_test_callers()
		->after_each(
			array( 'WP_Sitemaps_Renderer::get_sitemap_xml', 'WP_Sitemaps_Renderer::get_sitemap_index_xml', '_oembed_create_xml', 'IXR_Request::getXml' ),
			static function ( WP_Invariant_Call $call ) {
				$xml = $call->return_value();
				if ( is_string( $xml ) ) {
					$call->expect( WP_Invariant_Browser::xml_well_formed( $xml ), '%s() returned XML which is not well-formed: %s', $call->target(), $xml );
				}
			}
		)
		->after_each(
			array( 'IXR_Error::getXml', 'IXR_Value::getXml', 'IXR_Date::getXml', 'IXR_Base64::getXml' ),
			static function ( WP_Invariant_Call $call ) {
				$xml = $call->return_value();
				if ( is_string( $xml ) ) {
					$call->expect( null !== WP_Invariant_Browser::xml_child_elements( $xml ), '%s() returned XML which is not well-formed: %s', $call->target(), $xml );
				}
			}
		),
);
