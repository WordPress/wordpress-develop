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
	 * Returns the host a browser would go to for a URL, such as one in a Location header, or null when it cannot be parsed.
	 *
	 * @param string $url  URL.
	 * @param string $base Absolute URL a relative one is resolved against.
	 * @return string|null Host, in ASCII and lower case. Empty for a URL without one, such as `mailto:a@example.org`.
	 */
	public static function url_host( $url, $base ) {
		$parsed = Uri\WhatWg\Url::parse( $url, Uri\WhatWg\Url::parse( $base ) );
		return null === $parsed ? null : (string) $parsed->getAsciiHost();
	}

	/**
	 * Returns how a browser reads one start tag: its name, and the value of each attribute.
	 *
	 * The tag is parsed inside a TEMPLATE element, where a browser keeps tags it
	 * would move or drop elsewhere, such as TD, and the template's contents are
	 * read back from their serialization, which writes every attribute as
	 * `name="value"` with `&`, `"`, `<`, `>` and U+00A0 escaped.
	 *
	 * @param string $tag Markup of one start tag, such as `<a href=x>`.
	 * @return array|null `name`, lower case, and `attributes`, lower-case name => value, as the browser keeps them: the first of duplicates. Null when the browser builds no element from it.
	 */
	public static function start_tag( $tag ) {
		$document = Dom\HTMLDocument::createFromString( '<!DOCTYPE html><body><template>' . $tag . '</template>', LIBXML_NOERROR );
		$template = $document->body->firstElementChild;
		if ( null === $template || 'template' !== self::tag_name( $template ) ) {
			return null;
		}

		$html = $template->innerHTML; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
		if ( ! preg_match( '~^<([^\s/>]+)((?:\s+[^\s="/>]+(?:="[^"]*")?)*)>~', $html, $match ) ) {
			return null;
		}

		preg_match_all( '~\s+([^\s="/>]+)(?:="([^"]*)")?~', $match[2], $attributes, PREG_SET_ORDER );
		$values = array();
		foreach ( $attributes as $attribute ) {
			$name = strtolower( $attribute[1] );
			if ( ! isset( $values[ $name ] ) ) {
				$values[ $name ] = html_entity_decode( isset( $attribute[2] ) ? $attribute[2] : '', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return array(
			'name'       => strtolower( $match[1] ),
			'attributes' => $values,
		);
	}

	/**
	 * Returns the value a browser gives a TEXTAREA, or null when the markup does not stay one TEXTAREA.
	 *
	 * @param string $html What goes between `<textarea>` and `</textarea>`.
	 * @return string|null
	 */
	public static function textarea_value( $html ) {
		$body = self::body( '<textarea>' . $html . '</textarea>' );
		if ( 1 !== $body->childNodes->length || ! $body->firstChild instanceof Dom\Element || 'textarea' !== self::tag_name( $body->firstChild ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Properties of the Dom API.
			return null;
		}

		return $body->firstChild->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Properties of the Dom API.
	}

	/**
	 * Returns the nodes a browser builds directly in a BODY element from HTML.
	 *
	 * @param string $html HTML.
	 * @return string[][] Each as `array( type, text )`: `comment` and its data, `text` and its text, or `element` and its lower-case name.
	 */
	public static function nodes( $html ) {
		$nodes = array();
		foreach ( self::body( $html )->childNodes as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
			if ( $node instanceof Dom\Comment ) {
				$nodes[] = array( 'comment', $node->data );
			} elseif ( $node instanceof Dom\Element ) {
				$nodes[] = array( 'element', self::tag_name( $node ) );
			} else {
				$nodes[] = array( 'text', $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A property of the Dom API.
			}
		}

		return $nodes;
	}

	/**
	 * Returns whether a string is a well-formed XML document.
	 *
	 * @param string $xml XML.
	 * @return bool
	 */
	public static function xml_well_formed( $xml ) {
		try {
			Dom\XMLDocument::createFromString( $xml, LIBXML_NOERROR | LIBXML_NONET );
			return true;
		} catch ( Exception $e ) {
			return false;
		}
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
