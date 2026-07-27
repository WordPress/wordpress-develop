<?php
/**
 * Upgrader API: WP_Ajax_Upgrader_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Upgrader Skin for Ajax WordPress upgrades.
 *
 * This skin is designed to be used for Ajax updates.
 *
 * @since 4.6.0
 *
 * @see Automatic_Upgrader_Skin
 */
class WP_Ajax_Upgrader_Skin extends Automatic_Upgrader_Skin {

	/**
	 * Plugin info.
	 *
	 * The Plugin_Upgrader::bulk_upgrade() method will fill this in
	 * with info retrieved from the get_plugin_data() function.
	 *
	 * @var array Plugin data. Values will be empty if not supplied by the plugin.
	 */
	public $plugin_info = array();

	/**
	 * Theme info.
	 *
	 * The Theme_Upgrader::bulk_upgrade() method will fill this in
	 * with info retrieved from the Theme_Upgrader::theme_info() method,
	 * which in turn calls the wp_get_theme() function.
	 *
	 * @var WP_Theme|false The theme's info object, or false.
	 */
	public $theme_info = false;

	/**
	 * Holds the WP_Error object.
	 *
	 * @since 4.6.0
	 *
	 * @var null|WP_Error
	 */
	protected $errors = null;

	/**
	 * Constructor.
	 *
	 * Sets up the WordPress Ajax upgrader skin.
	 *
	 * @since 4.6.0
	 *
	 * @see WP_Upgrader_Skin::__construct()
	 *
	 * @param array $args Optional. The WordPress Ajax upgrader skin arguments to
	 *                    override default options. See WP_Upgrader_Skin::__construct().
	 *                    Default empty array.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );

		$this->errors = new WP_Error();
	}

	/**
	 * Retrieves the list of errors.
	 *
	 * @since 4.6.0
	 *
	 * @return WP_Error Errors during an upgrade.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Retrieves a string for error messages.
	 *
	 * @since 4.6.0
	 *
	 * @return string Error messages during an upgrade.
	 */
	public function get_error_messages() {
		$messages = array();

		foreach ( $this->errors->get_error_codes() as $error_code ) {
			$error_data = $this->errors->get_error_data( $error_code );

			if ( $error_data && is_string( $error_data ) ) {
				$messages[] = $this->errors->get_error_message( $error_code ) . ' ' . esc_html( strip_tags( $error_data ) );
			} else {
				$messages[] = $this->errors->get_error_message( $error_code );
			}
		}

		return implode( ', ', $messages );
	}

	/**
	 * Checks if the plugin can be overwritten and outputs an array of changes for overwriting a plugin on upload.
	 *
	 * @since 6.9.0
	 *
	 * @return bool|array Whether the plugin can be overwritten and an array of changes returned.
	 */
	public function can_overwrite_plugin() {
		if ( ! is_wp_error( $this->result ) || 'folder_exists' !== $this->result->get_error_code() ) {
			return false;
		}

		$folder = $this->result->get_error_data( 'folder_exists' );
		$folder = ltrim( substr( $folder, strlen( WP_PLUGIN_DIR ) ), '/' );

		$current_plugin_data = false;
		$all_plugins         = get_plugins();

		foreach ( $all_plugins as $plugin => $plugin_data ) {
			if ( strrpos( $plugin, $folder ) !== 0 ) {
				continue;
			}

			$current_plugin_data = $plugin_data;
		}

		$new_plugin_data = $this->upgrader->new_plugin_data;

		if ( ! $current_plugin_data || ! $new_plugin_data ) {
			return false;
		}

		$rows = array(
			'Downgrade' => version_compare( $current_plugin_data['Version'], $new_plugin_data['Version'], '>' ),
		);

		$fields = array( 'Name', 'Version', 'Author', 'RequiresWP', 'RequiresPHP' );

		$is_same_plugin = true; // Let's consider only these rows.

		foreach ( $fields as $field ) {
			$old_value = ! empty( $current_plugin_data[ $field ] ) ? (string) $current_plugin_data[ $field ] : '-';
			$new_value = ! empty( $new_plugin_data[ $field ] ) ? (string) $new_plugin_data[ $field ] : '-';

			$is_same_plugin = $is_same_plugin && ( $old_value === $new_value );

			$rows[ $field ] = array( wp_strip_all_tags( $old_value ), wp_strip_all_tags( $new_value ) );

		}

		return $rows;
	}

	/**
	 * Stores an error message about the upgrade.
	 *
	 * @since 4.6.0
	 * @since 5.3.0 Formalized the existing `...$args` parameter by adding it
	 *              to the function signature.
	 *
	 * @param string|WP_Error $errors  Errors.
	 * @param mixed           ...$args Optional text replacements.
	 */
	public function error( $errors, ...$args ) {
		if ( is_string( $errors ) ) {
			$string = $errors;
			if ( ! empty( $this->upgrader->strings[ $string ] ) ) {
				$string = $this->upgrader->strings[ $string ];
			}

			if ( str_contains( $string, '%' ) ) {
				if ( ! empty( $args ) ) {
					$string = vsprintf( $string, $args );
				}
			}

			// Count existing errors to generate a unique error code.
			$errors_count = count( $this->errors->get_error_codes() );
			$this->errors->add( 'unknown_upgrade_error_' . ( $errors_count + 1 ), $string );
		} elseif ( is_wp_error( $errors ) ) {
			foreach ( $errors->get_error_codes() as $error_code ) {
				$this->errors->add( $error_code, $errors->get_error_message( $error_code ), $errors->get_error_data( $error_code ) );
			}
		}

		parent::error( $errors, ...$args );
	}

	/**
	 * Stores a message about the upgrade.
	 *
	 * @since 4.6.0
	 * @since 5.3.0 Formalized the existing `...$args` parameter by adding it
	 *              to the function signature.
	 * @since 5.9.0 Renamed `$data` to `$feedback` for PHP 8 named parameter support.
	 *
	 * @param string|array|WP_Error $feedback Message data.
	 * @param mixed                 ...$args  Optional text replacements.
	 */
	public function feedback( $feedback, ...$args ) {
		if ( is_wp_error( $feedback ) ) {
			foreach ( $feedback->get_error_codes() as $error_code ) {
				$this->errors->add( $error_code, $feedback->get_error_message( $error_code ), $feedback->get_error_data( $error_code ) );
			}
		}

		parent::feedback( $feedback, ...$args );
	}
}
