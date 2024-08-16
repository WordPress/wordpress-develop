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
	public function get_quirks_mode(): string {
	}
}
