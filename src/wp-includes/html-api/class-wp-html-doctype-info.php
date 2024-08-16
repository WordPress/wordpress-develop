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
	 * @var string|null
	 */
	private $name;

	/**
	 * The public identifier of the DOCTYPE.
	 *
	 * @var string|null
	 */
	private $public_identifier;

	/**
	 * The system identifier of the DOCTYPE.
	 *
	 * @var string|null
	 */
	private $system_identifier;

	/**
	 * Whether the DOCTYPE token force-quirks flag is set.
	 *
	 * @var bool
	 */
	private $force_quirks_flag;

	/**
	 * Constructor.
	 *
	 * The arguments to this constructor correspond to the "DOCTYPE token" as defined in the
	 * HTML specification.
	 *
	 * > DOCTYPE tokens have a name, a public identifier, a system identifier,
	 * > and a force-quirks flag.
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
	public function __construct(
		?string $name,
		?string $public_identifier,
		?string $system_identifier,
		bool $force_quirks_flag
	) {
		$this->name              = $name;
		$this->public_identifier = $public_identifier;
		$this->system_identifier = $system_identifier;
		$this->force_quirks_flag = $force_quirks_flag;
	}

	/**
	 * Gets the name of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string|null The name of the DOCTYPE.
	 */
	public function get_name(): ?string {
		return $this->name;
	}

	/**
	 * Gets the public identifier of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string|null The public identifier of the DOCTYPE.
	 */
	public function get_public_identifier(): ?string {
		return $this->public_identifier;
	}

	/**
	 * Gets the system identifier of the DOCTYPE.
	 *
	 * @since 6.7.0
	 *
	 * @return string|null The system identifier of the DOCTYPE.
	 */
	public function get_system_identifier(): ?string {
		return $this->system_identifier;
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
