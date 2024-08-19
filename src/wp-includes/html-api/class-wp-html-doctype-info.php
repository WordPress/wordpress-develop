<?php
/**
 * HTML API: WP_HTML_Doctype_Info class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.7.0
 */

/**
 * Core class used by the HTML API processor to represent a DOCTYPE declaration.
 *
 * @since 6.7.0
 */
class WP_HTML_Doctype_Info {
	/**
	 * The name of the DOCTYPE.
	 *
	 * > When a DOCTYPE token is created, its name… must be marked as missing (which is
	 * > a distinct state from the empty string).
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @var string|null
	 */
	private $name = null;

	/**
	 * The public identifier of the DOCTYPE.
	 *
	 * > When a DOCTYPE token is created, its… public identifier… must be marked as missing
	 * > (which is a distinct state from the empty string).
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @var string|null
	 */
	private $public_identifier = null;

	/**
	 * The system identifier of the DOCTYPE.
	 *
	 * > When a DOCTYPE token is created, its… system identifier must be marked as missing
	 * > (which is a distinct state from the empty string).
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @var string|null
	 */
	private $system_identifier = null;

	/**
	 * Whether the DOCTYPE token force-quirks flag is set.
	 *
	 * > When a DOCTYPE token is created, its force-quirks flag must be set to off
	 * > (its other state is on).
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#tokenization
	 *
	 * @var bool
	 */
	private $force_quirks_flag = false;

	/**
	 * Constructor.
	 *
	 * Do not instantiate this class directly. To access DOCTYPE information, use
	 * the `get_doctype_info()` method from WP_HTML_Tag_Processor or WP_HTML_Processor classes.
	 *
	 * This class should be instantiated with the result of `get_modifiable_text()` on a DOCTYPE
	 * token.
	 *
	 * @access private
	 *
	 * @since 6.7.0
	 *
	 * @param string $doctype_content The contents of the DOCTYPE declaration. This function
	 *                                should be passed the DOCTYPE string starting after
	 *                                "<!DOCTYPE" and ending before the closing ">". Newline
	 *                                normalization and null-byte replacement should have been
	 *                                handled already.
	 */
	public function __construct( string $doctype_content ) {
		/*
		 * In this state, the doctype token has been found and its "content" has been passed
		 * to this constructor. $doctype_content is the entire text of the DOCTYPE
		 * declaration, including the name, public, system, and force-quirks flags.
		 *
		 * "<!DOCTYPE...declaration...>"
		 *           [<------------->] $doctype_content
		 *
		 * This parser does not need to consider parsing rules for ">" which terminate the doctype
		 * token as this has already been handled and ">" should never appear in the content.
		 * This parser can assume ">" has been reached at the end of the content.
		 *
		 * Parsing effectively begins in "Before DOCTYPE name state", ignore whitespace and proceed to the next state.
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#before-doctype-name-state
		 */
		$at  = strspn( $doctype_content, " \t\n\f\r" );
		$end = strlen( $doctype_content );

		if ( $at >= $end ) {
			$this->force_quirks_flag = true;
			return;
		}
		$name_length = strcspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
		$this->name  = strtolower( substr( $doctype_content, $at, $name_length ) );

		$at += $name_length;
		$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
		if ( $at >= $end ) {
			return;
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
			$this->force_quirks_flag = true;
			return;
		}

		if ( 0 === substr_compare( $doctype_content, 'PUBLIC', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				$this->force_quirks_flag = true;
				return;
			}
			goto parse_doctype_public_identifier;
		}
		if ( 0 === substr_compare( $doctype_content, 'SYSTEM', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				$this->force_quirks_flag = true;
				return;
			}
			goto parse_doctype_system_identifier;
		}

		$this->force_quirks_flag = true;
		return;

		parse_doctype_public_identifier:
		/*
		 * The parser should enter "DOCTYPE public identifier (double-quoted) state" or
		 * "DOCTYPE public identifier (single-quoted) state" by finding one of the valid quotes.
		 * Anything else forces quirks mode and ignores the rest of the contents.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-public-identifier-(double-quoted)-state
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#doctype-public-identifier-(single-quoted)-state
		 */
		$closer_quote = $doctype_content[ $at ];
		if ( '"' !== $closer_quote && "'" !== $closer_quote ) {
			$this->force_quirks_flag = true;
			return;
		}
		++$at;

		$identifier_length       = strcspn( $doctype_content, $closer_quote, $at, $end - $at );
		$this->public_identifier = substr( $doctype_content, $at, $identifier_length );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_content[ $at ] ) {
			$this->force_quirks_flag = true;
			return;
		}
		++$at;

		/*
		 * "Between DOCTYPE public and system identifiers state"
		 *
		 * Advance through whitespace between public and system identifiers.
		 *
		 * @see https://html.spec.whatwg.org/multipage/parsing.html#between-doctype-public-and-system-identifiers-state
		 */
		$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
		if ( $at >= $end ) {
			return;
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
		$closer_quote = $doctype_content[ $at ];
		if ( '"' !== $closer_quote && "'" !== $closer_quote ) {
			$this->force_quirks_flag = true;
			return;
		}
		++$at;

		$identifier_length       = strcspn( $doctype_content, $closer_quote, $at, $end - $at );
		$this->system_identifier = substr( $doctype_content, $at, $identifier_length );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_content[ $at ] ) {
			$this->force_quirks_flag = true;
			return;
		}
	}

	/**
	 * Gets the name of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string The name of the DOCTYPE.
	 */
	public function get_name(): string {
		return $this->name ?? '';
	}

	/**
	 * Gets the public identifier of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string The public identifier of the DOCTYPE.
	 */
	public function get_public_identifier(): string {
		return $this->public_identifier ?? '';
	}

	/**
	 * Gets the system identifier of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string The system identifier of the DOCTYPE.
	 */
	public function get_system_identifier(): string {
		return $this->system_identifier ?? '';
	}

	/**
	 * Determines the resulting document compatibility mode for this DOCTYPE.
	 *
	 * When a DOCTYPE appears in the appropriate place in a document, its contents determine
	 * the compatibility mode of the document. This implements an algorithm described in the
	 * HTML standard for handling a DOCTYPE token in the "initial" insertion mode.
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#the-initial-insertion-mode
	 *
	 * @since 6.7.0
	 *
	 * @return string A string indicating the resulting quirks mode. One of "quirks",
	 *                "limited-quirks", or "no-quirks".
	 */
	public function get_compatibility_mode(): string {
		/*
		 * > A system identifier whose value is the empty string is not considered missing for the
		 * > purposes of the conditions above.
		 */
		$system_identifier_is_missing = null === $this->system_identifier;

		/*
		 * > The system identifier and public identifier strings must be compared to the values
		 * > given in the lists above in an ASCII case-insensitive manner. A system identifier whose
		 * > value is the empty string is not considered missing for the purposes of the conditions above.
		 */
		$public_identifier = null === $this->public_identifier ? '' : strtolower( $this->public_identifier );
		$system_identifier = null === $this->system_identifier ? '' : strtolower( $this->system_identifier );

		/*
		 * > [If] the DOCTYPE token matches one of the conditions in the following list, then set
		 * > the Document to quirks mode:
		 */

		// > The force-quirks flag is set to on.
		if ( $this->force_quirks_flag ) {
			return 'quirks';
		}

		// > The name is not "html".
		if ( 'html' !== $this->name ) {
			return 'quirks';
		}

		// > The public identifier is set to…
		if (
			'-//w3o//dtd w3 html strict 3.0//en//' === $public_identifier ||
			'-/w3c/dtd html 4.0 transitional/en' === $public_identifier ||
			'html' === $public_identifier
		) {
			return 'quirks';
		}

		// > The system identifier is set to…
		if ( 'http://www.ibm.com/data/dtd/v11/ibmxhtml1-transitional.dtd' === $system_identifier ) {
			return 'quirks';
		}

		/*
		 * All of the following conditions depend on matching the public identifier.
		 * If the public identifier is falsy, none of the following conditions will match.
		 */
		if ( '' === $public_identifier ) {
			return 'no-quirks';
		}

		// > The public identifier starts with…
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
			return 'quirks';
		}

		// > The system identifier is missing and the public identifier starts with…
		if (
			$system_identifier_is_missing && (
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 frameset//' ) ||
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 transitional//' )
			)
		) {
			return 'quirks';
		}

		/*
		 * > Otherwise, [if] the DOCTYPE token matches one of the conditions in
		 * > the following list, then set the Document to limited-quirks mode.
		 */

		// > The public identifier starts with…
		if (
			str_starts_with( $public_identifier, '-//w3c//dtd xhtml 1.0 frameset//' ) ||
			str_starts_with( $public_identifier, '-//w3c//dtd xhtml 1.0 transitional//' )
		) {
			return 'limited-quirks';
		}

		// > The system identifier is not missing and the public identifier starts with…
		if (
			! $system_identifier_is_missing && (
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 frameset//' ) ||
				str_starts_with( $public_identifier, '-//w3c//dtd html 4.01 transitional//' )
			)
		) {
			return 'limited-quirks';
		}

		return 'no-quirks';
	}
}
