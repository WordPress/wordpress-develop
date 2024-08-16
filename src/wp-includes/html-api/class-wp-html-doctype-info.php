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
	 * The name of the DOCTYPE, e.g. "html" or "svg".
	 *
	 * @var string|null
	 */
	public $name;

	/**
	 * The public identifier of the DOCTYPE, e.g. "-//W3C//DTD XHTML 1.0 Strict//EN".
	 *
	 * @var string|null
	 */
	public $public_identifier;

	/**
	 * The system identifier of the DOCTYPE, e.g. "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd".
	 *
	 * @var string|null
	 */
	public $system_identifier;

	/**
	 * Whether the DOCTYPE is in quirks mode.
	 *
	 * @var bool
	 */
	public $force_quirks_flag;

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
}
