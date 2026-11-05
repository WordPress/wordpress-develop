<?php
/**
 * Reads HTML, URLs and XML as browsers and XML parsers do, as an oracle for invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Asks PHP's standards-based parsers how markup, URLs and XML would be read.
 *
 * - Dom\HTMLDocument (PHP 8.4) is the HTML5 parser of lexbor: it builds the tree a browser would.
 * - Uri\WhatWg\Url (PHP 8.5) parses URLs as browsers do.
 * - Dom\XMLDocument (PHP 8.4) is a strict XML parser.
 *
 * None of them share code with WordPress, so they are an independent reading of
 * what WordPress produces.
 */
final class WP_Invariant_Browser {

	/**
	 * Parses HTML as the contents of a BODY element, as a browser does with innerHTML.
	 *
	 * @param string $html HTML.
	 * @return Dom\HTMLElement The BODY element.
	 */
	public static function body( $html ) {
		$body            = Dom\HTMLDocument::createFromString( '<!DOCTYPE html><body>', LIBXML_NOERROR )->body;
		$body->innerHTML = $html; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
		return $body;
	}

	/**
	 * Returns every element a browser would build from HTML, as the contents of a BODY element.
	 *
	 * Elements inside TEMPLATE elements are included: a browser does not render
	 * them, but script can clone them into the page.
	 *
	 * @param string $html HTML.
	 * @return Dom\Element[]
	 */
	public static function elements( $html ) {
		$elements = array();
		foreach ( self::body( $html )->querySelectorAll( '*' ) as $element ) {
			$elements[] = $element;
			if ( 'template' === self::tag_name( $element ) ) {
				$elements = array_merge( $elements, self::elements( $element->innerHTML ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
			}
		}

		return $elements;
	}

	/**
	 * Returns how a browser would serialize HTML after parsing it as the contents of a BODY element.
	 *
	 * Two pieces of HTML which serialize the same build the same tree.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function tree( $html ) {
		return self::body( $html )->innerHTML; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
	}

	/**
	 * Returns the lower-case name of an element, such as `a` or `foreignobject`.
	 *
	 * @param Dom\Element $element Element.
	 * @return string
	 */
	public static function tag_name( $element ) {
		return strtolower( $element->localName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
	}

	/**
	 * Returns how a browser reads the value of an attribute, given its markup between double quotes.
	 *
	 * @param string $value Attribute value, as it appears in HTML.
	 * @return string|null The value the browser sees, or null when the markup does not give one attribute.
	 */
	public static function attribute_value( $value ) {
		$elements = self::elements( '<a href="' . $value . '"></a>' );
		if ( 1 !== count( $elements ) || 1 !== count( $elements[0]->attributes ) ) {
			return null;
		}

		return $elements[0]->getAttribute( 'href' );
	}

	/**
	 * Returns the scheme a browser would give a URL, or null when it is relative or cannot be parsed.
	 *
	 * A relative URL, such as `/path` or `//host/path`, takes the scheme of the page it is on.
	 *
	 * @param string $url URL, as a browser would read it from an attribute.
	 * @return string|null Lower-case scheme.
	 */
	public static function url_scheme( $url ) {
		$parsed = Uri\WhatWg\Url::parse( $url );
		return null === $parsed ? null : $parsed->getScheme();
	}

	/**
	 * Returns how many child elements XML content would have inside an element, or null when it is not well-formed.
	 *
	 * @param string $xml XML content.
	 * @return int|null
	 */
	public static function xml_child_elements( $xml ) {
		try {
			return Dom\XMLDocument::createFromString( '<r>' . $xml . '</r>', LIBXML_NOERROR )->documentElement->childElementCount;
		} catch ( Exception $e ) {
			return null;
		}
	}
}
