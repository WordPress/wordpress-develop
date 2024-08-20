<?php
/**
 * HTML API: WP_HTML_Doctype_Info class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.7.0
 */

/**
 * Core class used by the HTML API to represent a DOCTYPE declaration.
 *
 * This class is an important for the HTML API when parsing full HTML documents. It is unlikely
 * to be of interest outside of the HTML API implementation except for cases such as faithfully
 * constructing an HTML tree representation where the DOCTYPE is essential.
 *
 * The most important functions of DOCTYPEs in HTML is to determine the compatibility mode:
 * "no-quirks", "limited-quirks", or "quirks". DOCTYPEs in HTML can contain additional information,
 * but there is little reason to use anything other than the HTML5 doctype: `<!DOCTYPE html>`.
 * It's recommended to always use that DOCTYPE when authoring HTML.
 *
 * The HTML standard says this about DOCTYPEs:
 *
 * > DOCTYPEs are required for legacy reasons. When omitted, browsers tend to use a different
 * > rendering mode that is incompatible with some specifications. Including the DOCTYPE in a
 * > document ensures that the browser makes a best-effort attempt at following the
 * > relevant specifications.
 *
 * @see https://html.spec.whatwg.org/multipage/syntax.html#the-doctype
 *
 * This class exposes a "compatibility mode" property as well as the the three pieces of information
 * that may appear in a DOCTYPE: name, public id and system id. The compatibility mode is the result
 * of a applying set of rules described in the HTML standard for how a DOCTYPE determines the
 * compatibility mode of an HTML document.
 *
 * @see https://html.spec.whatwg.org/#the-initial-insertion-mode
 *
 * @since 6.7.0
 */
class WP_HTML_Doctype_Info {
	/**
	 * The name of the DOCTYPE.
	 *
	 * This value should be considered "read only" and not modified.
	 *
	 * HTML5 documents should always use the name "html":
	 *
	 *             ⬐ Name "html"
	 * <!DOCTYPE html>
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @var string|null
	 */
	public $name = null;

	/**
	 * The public identifier of the DOCTYPE.
	 *
	 * This value should be considered "read only" and not modified.
	 *
	 * The public identifier is optional and should not appear in HTML5 doctypes.
	 * If this value is null, it indicates the public identifier was not present in the DOCTYPE.
	 *
	 *             ⬐ Name     ⬐ ------- Public ID ------- ⬎
	 * <!DOCTYPE html PUBLIC "public id goes here in quotes">
	 *
	 * @var string|null
	 */
	public $public_identifier = null;

	/**
	 * The system identifier of the DOCTYPE.
	 *
	 * This value should be considered "read only" and not modified.
	 *
	 * The system identifier is optional and should not appear in HTML5 doctypes.
	 * If this value is null, it indicates the system identifier was not present in the DOCTYPE.
	 *
	 * With a Public ID:
	 *
	 *             ⬐ Name     ⬐ ------- Public ID ------- ⬎   ⬐ ------- System ID ------- ⬎
	 * <!DOCTYPE html PUBLIC "public id goes here in quotes" "system id goes here in quotes">
	 *
	 * Without a Public ID:
	 *
	 *             ⬐ Name     ⬐ ------- System ID ------- ⬎
	 * <!DOCTYPE html SYSTEM "system id goes here in quotes">
	 *
	 * @var string|null
	 */
	public $system_identifier = null;

	/**
	 * The compatibility (quirks) mode of the document that results from parsing this DOCTYPE.
	 * One of "no-quirks", "limited-quirks", or "quirks".
	 *
	 * This value should be considered "read only" and not modified.
	 *
	 * When a DOCTYPE is encountered in the "initial" insertion mode, the DOCTYPE is used
	 * to determine the document's compatibility or "quirks" mode.
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#the-initial-insertion-mode
	 *
	 * @since 6.7.0
	 *
	 * @var string One of "no-quirks", "limited-quirks", or "quirks".
	 */
	public $compatibility_mode;

	/**
	 * Constructor.
	 *
	 * The arguments to this constructor correspond to the "DOCTYPE token" as defined in the
	 * HTML specification.
	 *
	 * > DOCTYPE tokens have a name, a public identifier, a system identifier,
	 * > and a force-quirks flag. When a DOCTYPE token is created, its name, public identifier,
	 * > and system identifier must be marked as missing (which is a distinct state from the
	 * > empty string), and the force-quirks flag must be set to off (its other state is on).
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @since 6.7.0
	 *
	 * @param string|null $name              The name of the DOCTYPE.
	 * @param string|null $public_identifier The public identifier of the DOCTYPE.
	 * @param string|null $system_identifier The system identifier of the DOCTYPE.
	 * @param bool        $force_quirks_flag Whether the DOCTYPE token force-quirks flag is set.
	 */
	private function __construct(
		?string $name,
		?string $public_identifier,
		?string $system_identifier,
		bool $force_quirks_flag
	) {
		$this->name              = $name;
		$this->public_identifier = $public_identifier;
		$this->system_identifier = $system_identifier;

		/*
		 * > [If] the DOCTYPE token matches one of the conditions in the following list, then set
		 * > the Document to quirks mode:
		 */

		/*
		 * > The force-quirks flag is set to on.
		 */
		if ( $force_quirks_flag ) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * > The name is not "html".
		 */
		if ( 'html' !== $name ) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * Set up some variables to handle the rest of the conditions.
		 *
		 * > A system identifier whose value is the empty string is not considered missing for the
		 * > purposes of the conditions above.
		 * > The system identifier and public identifier strings must be compared to the values
		 * > …
		 * > given in the lists above in an ASCII case-insensitive manner. A system identifier whose
		 * > value is the empty string is not considered missing for the purposes of the conditions above.
		 */
		$system_identifier_is_missing = null === $system_identifier;
		$public_identifier            = null === $public_identifier ? '' : strtolower( $public_identifier );
		$system_identifier            = null === $system_identifier ? '' : strtolower( $system_identifier );

		/*
		 * > The public identifier is set to…
		 */
		if (
			'-//w3o//dtd w3 html strict 3.0//en//' === $public_identifier ||
			'-/w3c/dtd html 4.0 transitional/en' === $public_identifier ||
			'html' === $public_identifier
		) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * > The system identifier is set to…
		 */
		if ( 'http://www.ibm.com/data/dtd/v11/ibmxhtml1-transitional.dtd' === $system_identifier ) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * All of the following conditions depend on matching the public identifier.
		 * If the public identifier is falsy, none of the following conditions will match.
		 */
		if ( '' === $public_identifier ) {
			$this->compatibility_mode = 'no-quirks';
			return;
		}

		/*
		 * > The public identifier starts with…
		 */
		if (
			str_starts_with( $public_identifier, '+//silmaril//dtd html pro v0r11 19970101//' ) ||
			str_starts_with( $public_identifier, '-//as//dtd html 3.0 aswedit + extensions//' ) ||
			str_starts_with( $public_identifier, '-//advasoft ltd//dtd html 3.0 aswedit + extensions//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0 level 1//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0 level 2//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0 strict level 1//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0 strict level 2//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0 strict//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.0//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 2.1e//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 3.0//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 3.2 final//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 3.2//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html 3//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html level 0//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html level 1//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html level 2//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html level 3//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html strict level 0//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html strict level 1//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html strict level 2//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html strict level 3//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html strict//' ) ||
			str_starts_with( $public_identifier, '-//ietf//dtd html//' ) ||
			str_starts_with( $public_identifier, '-//metrius//dtd metrius presentational//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 2.0 html strict//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 2.0 html//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 2.0 tables//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 3.0 html strict//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 3.0 html//' ) ||
			str_starts_with( $public_identifier, '-//microsoft//dtd internet explorer 3.0 tables//' ) ||
			str_starts_with( $public_identifier, '-//netscape comm. corp.//dtd html//' ) ||
			str_starts_with( $public_identifier, '-//netscape comm. corp.//dtd strict html//' ) ||
			str_starts_with( $public_identifier, "-//o'reilly and associates//dtd html 2.0//" ) ||
			str_starts_with( $public_identifier, "-//o'reilly and associates//dtd html extended 1.0//" ) ||
			str_starts_with( $public_identifier, "-//o'reilly and associates//dtd html extended relaxed 1.0//" ) ||
			str_starts_with( $public_identifier, '-//sq//dtd html 2.0 hotmetal + extensions//' ) ||
			str_starts_with( $public_identifier, '-//softquad software//dtd hotmetal pro 6.0::19990601::extensions to html 4.0//' ) ||
			str_starts_with( $public_identifier, '-//softquad//dtd hotmetal pro 4.0::19971010::extensions to html 4.0//' ) ||
			str_starts_with( $public_identifier, '-//spyglass//dtd html 2.0 extended//' ) ||
			str_starts_with( $public_identifier, '-//sun microsystems corp.//dtd hotjava html//' ) ||
			str_starts_with( $public_identifier, '-//sun microsystems corp.//dtd hotjava strict html//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 3 1995-03-24//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 3.2 draft//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 3.2 final//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 3.2//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 3.2s draft//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 4.0 frameset//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html 4.0 transitional//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html experimental 19960712//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd html experimental 970421//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd w3 html//' ) ||
			str_starts_with( $public_identifier, '-//w3o//dtd w3 html 3.0//' ) ||
			str_starts_with( $public_identifier, '-//webtechs//dtd mozilla html 2.0//' ) ||
			str_starts_with( $public_identifier, '-//webtechs//dtd mozilla html//' )
		) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * > The system identifier is missing and the public identifier starts with…
		 */
		if (
			$system_identifier_is_missing && (
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 frameset//' ) ||
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 transitional//' )
			)
		) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		/*
		 * > Otherwise, [if] the DOCTYPE token matches one of the conditions in
		 * > the following list, then set the Document to limited-quirks mode.
		 */

		/*
		 * > The public identifier starts with…
		 */
		if (
			str_starts_with( $public_identifier, '-//w3c//dtd xhtml 1.0 frameset//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd xhtml 1.0 transitional//' )
		) {
			$this->compatibility_mode = 'limited-quirks';
			return;
		}

		/*
		 * > The system identifier is not missing and the public identifier starts with…
		 */
		if (
			! $system_identifier_is_missing && (
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 frameset//' ) ||
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 transitional//' )
			)
		) {
			$this->compatibility_mode = 'limited-quirks';
			return;
		}

		$this->compatibility_mode = 'no-quirks';
	}

	/**
	 * Creates a WP_HTML_Doctype_Info instance from a DOCTYPE HTML string.
	 *
	 * This method is the primary way to create a WP_HTML_Doctype_Info instance. The
	 * WP_HTML_Doctype_Info constructor method is private and the class cannot be instantiated via
	 * the new keyword: `new WP_HTML_Doctype_Info()`.
	 *
	 * This is not a general purpose HTML parser. The provided HTML must correspond precisely to a
	 * DOCTYPE HTML Token, that is a string that:
	 *
	 * - Starts with "<!DOCTYPE" (case insensitive).
	 * - Ends with ">".
	 * - Contains no other ">" characters.
	 *
	 * If these conditions are not satisfied, this function will reject the input by returning
	 * `null`. Otherwise, the DOCTYPE will be parsed and an instance of the WP_HTML_Doctype_Info
	 * class will be returned that provides information about the parsed DOCTYPE. Note that the
	 * DOCTYPE must be a valid DOCTYPE token satisfying the conditions above, but it does not need
	 * to be a "correct" HTML5 DOCTYPE. For example, a DOCTYPE like `<!doctypeJSON SILLY "nonsense'>`
	 * is an acceptable DOCTYPE token even if it appears nonsensical.
	 *
	 * @example
	 * // This is the normative HTML5 DOCTYPE that should be used for all modern HTML documents.
	 * WP_HTML_Doctype_Info::from_html( '<!DOCTYPE html>' ) instanceof WP_HTML_Doctype_Info;
	 *
	 * // This DOCTYPE token is silly, but returns an instance of WP_HTML_Doctype_Info.
	 * WP_HTML_Doctype_Info::from_html( '<!doctypeJSON SILLY "nonsense\'>' ) instanceof WP_HTML_Doctype_Info;
	 *
	 * // NULL: The provided HTML string contains extra characters.
	 * null === WP_HTML_Doctype_Info::from_html( '<!DOCTYPE ><p>' );
	 *
	 * // NULL: The provided HTML string is not parsed as a DOCTYPE token.
	 * null === WP_HTML_Doctype_Info::from_html( '<!TYPEDOC>' );
	 *
	 * @param string $doctype_html The complete DOCTYPE HTML starting with "<!DOCTYPE"
	 *                             (case-insensitive) and terminate with the next ">". ">" must
	 *                             be the last character of the string and may not appear elswehre.
	 *
	 * @return WP_HTML_Doctype_Info|null A WP_HTML_Doctype_Info instance will be returned if the
	 *                                   provided DOCTYPE HTML is a valid DOCTYPE. Otherwise, null.
	 */
	public static function from_html( string $doctype_html ): ?self {
		$doctype_name      = null;
		$doctype_public_id = null;
		$doctype_system_id = null;

		$end = strlen( $doctype_html ) - 1;

		/*
		 * - Valid DOCTYPE HTML token must be at least `<!DOCTYPE>` assuming a complete token not
		 *   ending in end-of-file.
		 * - It must start with a case insensitive `<!DOCTYPE`.
		 * - The only occurance of `>` must be the final byte in the HTML string.
		 */
		if ( $end < 9 ) {
			return null;
		}
		if ( 0 !== substr_compare( $doctype_html, '<!DOCTYPE', 0, 9, true ) ) {
			return null;
		}
		$at = 9;
		if (
			'>' !== $doctype_html[ $end ] ||
			$end > strcspn( $doctype_html, '>', $at ) + $at
		) {
			return null;
		}

		/*
		 * Perform newline normalization and ensure the $end value is correct after normalization.
		 *
		 * @see https://html.spec.whatwg.org/#preprocessing-the-input-stream
		 * @see https://infra.spec.whatwg.org/#normalize-newlines
		 */
		$doctype_html = str_replace( "\r\n", "\n", $doctype_html );
		$doctype_html = str_replace( "\r", "\n", $doctype_html );
		$end          = strlen( $doctype_html ) - 1;

		/*
		 * In this state, the doctype token has been found and its "content" optionally including
		 * name, public ID, and system ID is between $at and $end.
		 *
		 * "<!DOCTYPE...declaration...>"
		 *           ⬑ $at            ⬑ $end
		 *
		 * It's possible that the declaration part is empty.
		 *
		 *           ⬐ $at
		 * "<!DOCTYPE>"
		 *           ⬑ $end
		 *
		 * Rules for parsing ">" which terminates the DOCTYPE do not need to be considered as they
		 * have been handled above in the condition that the provided DOCTYPE HTML must contain
		 * exactly one ">" character in the final position.
		 */

		/*
		 *
		 * Parsing effectively begins in "Before DOCTYPE name state". Ignore whitespace and
		 * proceed to the next state.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#before-doctype-name-state
		 */
		$at += strspn( $doctype_html, " \t\n\f\r", $at );

		if ( $at >= $end ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}
		$name_length  = strcspn( $doctype_html, " \t\n\f\r", $at, $end - $at );
		$doctype_name = str_replace( "\0", "\u{FFFD}", strtolower( substr( $doctype_html, $at, $name_length ) ) );

		$at += $name_length;
		$at += strspn( $doctype_html, " \t\n\f\r", $at, $end - $at );
		if ( $at >= $end ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, false );
		}

		/*
		 * "After DOCTYPE name state"
		 *
		 * Find a case insensitive match for "PUBLIC" or "SYSTEM" at this point.
		 * Otherwise, set force-quirks and enter bogus DOCTYPE state (skip the rest of the doctype).
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#after-doctype-name-state
		 */
		if ( $at + 6 >= $end ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}

		if ( 0 === substr_compare( $doctype_html, 'PUBLIC', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_html, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
			}
			goto parse_doctype_public_identifier;
		}
		if ( 0 === substr_compare( $doctype_html, 'SYSTEM', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_html, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
			}
			goto parse_doctype_system_identifier;
		}

		return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );

		parse_doctype_public_identifier:
		/*
		 * The parser should enter "DOCTYPE public identifier (double-quoted) state" or
		 * "DOCTYPE public identifier (single-quoted) state" by finding one of the valid quotes.
		 * Anything else forces quirks mode and ignores the rest of the contents.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-public-identifier-(double-quoted)-state
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-public-identifier-(single-quoted)-state
		 */
		$closer_quote = $doctype_html[ $at ];
		if ( '"' !== $closer_quote && "'" !== $closer_quote ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}
		++$at;

		$identifier_length = strcspn( $doctype_html, $closer_quote, $at, $end - $at );
		$doctype_public_id = str_replace( "\0", "\u{FFFD}", substr( $doctype_html, $at, $identifier_length ) );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_html[ $at ] ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}
		++$at;

		/*
		 * "Between DOCTYPE public and system identifiers state"
		 *
		 * Advance through whitespace between public and system identifiers.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#between-doctype-public-and-system-identifiers-state
		 */
		$at += strspn( $doctype_html, " \t\n\f\r", $at, $end - $at );
		if ( $at >= $end ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, false );
		}

		parse_doctype_system_identifier:
		/*
		 * The parser should enter "DOCTYPE system identifier (double-quoted) state" or
		 * "DOCTYPE system identifier (single-quoted) state" by finding one of the valid quotes.
		 * Anything else forces quirks mode and ignores the rest of the contents.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-system-identifier-(double-quoted)-state
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-system-identifier-(single-quoted)-state
		 */
		$closer_quote = $doctype_html[ $at ];
		if ( '"' !== $closer_quote && "'" !== $closer_quote ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}
		++$at;

		$identifier_length = strcspn( $doctype_html, $closer_quote, $at, $end - $at );
		$doctype_system_id = str_replace( "\0", "\u{FFFD}", substr( $doctype_html, $at, $identifier_length ) );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_html[ $at ] ) {
			return new self( $doctype_name, $doctype_public_id, $doctype_system_id, true );
		}

		return new self( $doctype_name, $doctype_public_id, $doctype_system_id, false );
	}
}
