<?php
/**
 * Option API: WP_Option_Autoload class
 *
 * @package WordPress
 * @subpackage Option
 * @since 7.3.0
 */

/**
 * Holds the values stored in the `autoload` column of the options table.
 *
 * Functions such as add_option() and update_option() accept a boolean for the
 * `$autoload` parameter. These constants name the strings that are stored in
 * the database, so core code does not repeat them as literals.
 *
 * @since 7.3.0
 */
final class WP_Option_Autoload {

	/**
	 * The option is always autoloaded.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const ON = 'on';

	/**
	 * The option is never autoloaded.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const OFF = 'off';

	/**
	 * No explicit value was given, and the default behavior applies.
	 *
	 * Options with this value are autoloaded.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const AUTO = 'auto';

	/**
	 * No explicit value was given, and the `wp_default_autoload_value` filter
	 * decided to autoload the option.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const AUTO_ON = 'auto-on';

	/**
	 * No explicit value was given, and the `wp_default_autoload_value` filter
	 * decided not to autoload the option, for example because its value is large.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const AUTO_OFF = 'auto-off';

	/**
	 * Legacy value for an autoloaded option.
	 *
	 * Deprecated since 6.7.0, but still accepted and read for backward
	 * compatibility. Use ON for new code.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const LEGACY_YES = 'yes';

	/**
	 * Legacy value for an option that is not autoloaded.
	 *
	 * Deprecated since 6.7.0, but still accepted and read for backward
	 * compatibility. Use OFF for new code.
	 *
	 * @since 7.3.0
	 * @var string
	 */
	const LEGACY_NO = 'no';
}
