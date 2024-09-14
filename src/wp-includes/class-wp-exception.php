<?php
/**
 * WordPress Exception.
 *
 * @package WordPress
 */

/**
 * WordPress Exception class.
 *
 * @since 6.3
 */
class WP_Exception extends Exception {
	/**
	 * Error code, compatible with WP_Error.
	 *
	 * @since 6.3
	 *
	 * @var int|string Error code.
	 */
	protected $error_code = '';

	/**
	 * Error data, compatible with WP_Error.
	 *
	 * @since 6.3
	 *
	 * @var mixed Data.
	 */
	protected $error_data = [];

	/**
	 * Constructor.
	 *
	 * @since 6.3
	 *
	 * @param int|string $error_code    Error code.
	 * @param string     $error_message Error message.
	 * @param mixed      $error_data    Error data.
	 */
	public function __construct( $error_code = '', $error_message = '', $error_data = [] ) {
		$this->error_code = $error_code;
		$this->error_data = $error_data;

		parent::__construct( $error_message );
	}

	/**
	 * Get error code.
	 *
	 * @since 6.3
	 *
	 * @return int|string
	 */
	public function get_error_code() {
		return $this->error_code;
	}

	/**
	 * Get error message.
	 *
	 * @since 6.3
	 *
	 * @return string
	 */
	public function get_error_message() {
		return parent::getMessage();
	}

	/**
	 * Get error data.
	 *
	 * @since 6.3
	 *
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->error_data;
	}

	/**
	 * Convert a WP_Error into this Exception.
	 *
	 * @since 6.3
	 *
	 * @param WP_Error $wp_error WP_Error object.
	 *
	 * @return $this
	 */
	public function from_wp_error( $wp_error ) {
		$this->error_code = $wp_error->get_error_code();
		$this->message    = $wp_error->get_error_message();
		$this->error_data = $wp_error->get_error_data();

		return $this;
	}

	/**
	 * Convert this exception into a WP_Error.
	 *
	 * @since 6.3
	 *
	 * @return WP_Error
	 */
	public function to_wp_error() {
		return new WP_Error(
			$this->get_error_code(),
			$this->get_error_message(),
			$this->get_error_data()
		);
	}
}
