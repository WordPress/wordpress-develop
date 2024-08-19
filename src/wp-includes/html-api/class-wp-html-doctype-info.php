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
 * This class is an important for the HTML API when parsing full HTML documents. It is unlikely
 * to be of interest outside of the HTML API implementation except for very particular cases
 * such as constructing a lossless HTML tree representation where the DOCTYPE is essential.
 *
 * DOCTYPEs in HTML will cause the document to be handled in "no-quirks", "limited-quirks",
 * or "quirks" mode. DOCTYPEs in HTML can contain additional information, but there is little
 * reason to use anything other than the HTML5 doctype: `<!DOCTYPE html>`. It's recommended
 * to always use that DOCTYPE when authoring HTML.
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
	 * The compatibility (quirks) mode of the document that results from parsing this DOCTYPE.
	 *
	 * @var string
	 */
	private $compatibility_mode;

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
			$this->compatibility_mode = 'quirks';
			return;
		}
		$name_length = strcspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
		$this->name  = strtolower( substr( $doctype_content, $at, $name_length ) );

		$at += $name_length;
		$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
		if ( $at >= $end ) {
			$this->compatibility_mode = self::apply_quirks_mode_algorithm( $this->name, $this->public_identifier, $this->system_identifier );
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
			$this->compatibility_mode = 'quirks';
			return;
		}

		if ( 0 === substr_compare( $doctype_content, 'PUBLIC', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				$this->compatibility_mode = 'quirks';
				return;
			}
			goto parse_doctype_public_identifier;
		}
		if ( 0 === substr_compare( $doctype_content, 'SYSTEM', $at, 6, true ) ) {
			$at += 6;
			$at += strspn( $doctype_content, " \t\n\f\r", $at, $end - $at );
			if ( $at >= $end ) {
				$this->compatibility_mode = 'quirks';
				return;
			}
			goto parse_doctype_system_identifier;
		}

		$this->compatibility_mode = 'quirks';
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
			$this->compatibility_mode = 'quirks';
			return;
		}
		++$at;

		$identifier_length       = strcspn( $doctype_content, $closer_quote, $at, $end - $at );
		$this->public_identifier = substr( $doctype_content, $at, $identifier_length );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_content[ $at ] ) {
			$this->compatibility_mode = 'quirks';
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
			$this->compatibility_mode = self::apply_quirks_mode_algorithm( $this->name, $this->public_identifier, $this->system_identifier );
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
			$this->compatibility_mode = 'quirks';
			return;
		}
		++$at;

		$identifier_length       = strcspn( $doctype_content, $closer_quote, $at, $end - $at );
		$this->system_identifier = substr( $doctype_content, $at, $identifier_length );

		$at += $identifier_length;
		if ( $at >= $end || $closer_quote !== $doctype_content[ $at ] ) {
			$this->compatibility_mode = 'quirks';
			return;
		}

		$this->compatibility_mode = self::apply_quirks_mode_algorithm( $this->name, $this->public_identifier, $this->system_identifier );
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
	 * Gets the document compatibility mode resulting from this DOCTYPE.
	 *
	 * When a DOCTYPE is encountered in the "initial" insertion mode, the DOCTYPE is used
	 * to determine the document's compatibility or "quirks" mode.
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#the-initial-insertion-mode
	 *
	 * @since 6.7.0
	 *
	 * @return string The compatibility mode "no-quirks", "limited-quirks", or "no-quirks".
	 */
	public function get_compatibility_mode(): string {
		return $this->compatibility_mode;
	}


	/**
	 * Determines the resulting document compatibility mode for this DOCTYPE.
	 *
	 * A DOCTYPE in the appropriate place in a document will determine the
	 * compatibility (quirks) mode of the document. The algorithm for determining quirks mode
	 * is described in the HTML standard for handling a DOCTYPE token in the "initial"
	 * insertion mode.
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#the-initial-insertion-mode
	 *
	 * This method does not accept a "force-quirks flag" argument. If the DOCTYPE token has
	 * a "force-quirks flag" set, then the compatibility mode should be "quirks" and there
	 * is no need to run this algorithm.
	 *
	 * @since 6.7.0
	 *
	 * @param string|null $name The DOCTYPE token name or null if omitted.
	 * @param string|null $public_identifier The DOCTYPE public identifier or null if omitted.
	 * @param string|null $system_identifier The DOCTYPE system identifier or null if omitted.
	 *
	 * @return string A string indicating the resulting quirks mode. One of "quirks",
	 *                "limited-quirks", or "no-quirks".
	 */
	private static function apply_quirks_mode_algorithm(
		?string $name,
		?string $public_identifier,
		?string $system_identifier
	): string {
		/*
		 * > A system identifier whose value is the empty string is not considered missing for the
		 * > purposes of the conditions above.
		 */
		$system_identifier_is_missing = null === $system_identifier;

		/*
		 * > The system identifier and public identifier strings must be compared to the values
		 * > given in the lists above in an ASCII case-insensitive manner. A system identifier whose
		 * > value is the empty string is not considered missing for the purposes of the conditions above.
		 */
		$public_identifier = null === $public_identifier ? '' : strtolower( $public_identifier );
		$system_identifier = null === $system_identifier ? '' : strtolower( $system_identifier );

		/*
		 * > [If] the DOCTYPE token matches one of the conditions in the following list, then set
		 * > the Document to quirks mode:
		 */

		/*
		 * > The force-quirks flag is set to on.
		 *
		 * The force-quirks flag should be handled by calling code and is not accounted for
		 * in this method.
		 */

		/*
		 * > The name is not "html".
		 */
		if ( 'html' !== $name ) {
			return 'quirks';
		}

		/*
		 * > The public identifier is set to…
		 */
		if (
			'-//w3o//dtd w3 html strict 3.0//en//' === $public_identifier ||
			'-/w3c/dtd html 4.0 transitional/en' === $public_identifier ||
			'html' === $public_identifier
		) {
			return 'quirks';
		}

		/*
		 * > The system identifier is set to…
		 */
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
			return 'quirks';
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
			return 'quirks';
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
			return 'limited-quirks';
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
			return 'limited-quirks';
		}

		return 'no-quirks';
	}
}
