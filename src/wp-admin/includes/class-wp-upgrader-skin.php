<?php
/**
 * Upgrader API: WP_Upgrader_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Generic Skin for the WordPress Upgrader classes. This skin is designed to be extended for specific purposes.
 *
 * @since 2.8.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader-skins.php.
 */
#[AllowDynamicProperties]
class WP_Upgrader_Skin {

	/**
	 * Holds the upgrader data.
	 *
	 * @since 2.8.0
	 * @var WP_Upgrader
	 */
	public $upgrader;

	/**
	 * Whether header is done.
	 *
	 * @since 2.8.0
	 * @var bool
	 */
	public $done_header = false;

	/**
	 * Whether footer is done.
	 *
	 * @since 2.8.0
	 * @var bool
	 */
	public $done_footer = false;

	/**
	 * Holds the result of an upgrade.
	 *
	 * @since 2.8.0
	 * @var string|bool|WP_Error
	 */
	public $result = false;

	/**
	 * Holds the options of an upgrade.
	 *
	 * @since 2.8.0
	 * @var array
	 */
	public $options = array();

	/**
	 * Constructor.
	 *
	 * Sets up the generic skin for the WordPress Upgrader classes.
	 *
	 * @since 2.8.0
	 *
	 * @param array $args Optional. The WordPress upgrader skin arguments to
	 *                    override default options. Default empty array.
	 */
	public function __construct( $args = array() ) {
		$defaults      = array(
			'url'     => '',
			'nonce'   => '',
			'title'   => '',
			'context' => false,
		);
		$this->options = wp_parse_args( $args, $defaults );
	}

	/**
	 * Sets the relationship between the skin being used and the upgrader.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Upgrader $upgrader
	 */
	public function set_upgrader( &$upgrader ) {
		if ( is_object( $upgrader ) ) {
			$this->upgrader =& $upgrader;
		}
		$this->add_strings();
	}

	/**
	 * Sets up the strings used in the update process.
	 *
	 * @since 3.0.0
	 */
	public function add_strings() {
	}

	/**
	 * Sets the result of an upgrade.
	 *
	 * @since 2.8.0
	 *
	 * @param string|bool|WP_Error $result The result of an upgrade.
	 */
	public function set_result( $result ) {
		$this->result = $result;
	}

	/**
	 * Builds the file changes summary for the overwrite screen.
	 *
	 * @since 7.3.0
	 *
	 * @return string The summary HTML, or an empty string if there is nothing to compare.
	 */
	protected function get_overwrite_file_changes() {
		if ( ! $this->upgrader instanceof WP_Upgrader || ! $this->upgrader->package_file_changes ) {
			return '';
		}

		$file_changes = $this->upgrader->package_file_changes;

		$count_labels = array(
			/* translators: %s: Number of files. */
			'added'   => __( 'Added: %s' ),
			/* translators: %s: Number of files. */
			'removed' => __( 'Removed: %s' ),
			/* translators: %s: Number of files. */
			'changed' => __( 'Changed: %s' ),
		);

		$headings = array(
			'added'   => _x( 'Added', 'files' ),
			'removed' => _x( 'Removed', 'files' ),
			'changed' => _x( 'Changed', 'files' ),
		);

		$counts = '';
		$lists  = '';

		foreach ( $count_labels as $type => $label ) {
			$files = $file_changes[ $type ];
			$count = number_format_i18n( count( $files ) );

			$counts .= '<li class="' . $type . '">' . esc_html( sprintf( $label, $count ) ) . '</li>';

			if ( ! $files ) {
				continue;
			}

			$lists .= '<h4 class="' . $type . '">' . esc_html( $headings[ $type ] ) . '</h4><ul>';

			foreach ( $files as $file ) {
				$lists .= '<li><code>' . esc_html( $file ) . '</code></li>';
			}

			$lists .= '</ul>';
		}

		/* translators: %s: Number of files. */
		$unchanged = sprintf( __( 'Unchanged: %s' ), number_format_i18n( $file_changes['unchanged'] ) );
		$counts   .= '<li class="unchanged">' . esc_html( $unchanged ) . '</li>';

		$output  = '<div class="update-from-upload-file-changes">';
		$output .= '<h3>' . esc_html__( 'File changes' ) . '</h3>';

		if ( $lists ) {
			$output .= '<ul class="update-from-upload-file-counts">' . $counts . '</ul>';
			$output .= '<details><summary>' . esc_html__( 'View file list' ) . '</summary>';
			$output .= '<div class="update-from-upload-file-list">' . $lists . '</div></details>';
		} else {
			// Same files on both sides, e.g. the same zip uploaded twice.
			$output .= '<p>' . esc_html__( 'The uploaded files are identical to the installed files.' ) . '</p>';
		}

		$output .= '</div>';

		/**
		 * Filters the file changes summary on the overwrite screen.
		 *
		 * Return an empty string to hide it.
		 *
		 * @since 7.3.0
		 *
		 * @param string           $output       The summary HTML.
		 * @param array            $file_changes Added, removed and changed file paths, plus the unchanged count.
		 *                                       See WP_Upgrader::compare_package_files().
		 * @param WP_Upgrader_Skin $skin         The skin showing the screen.
		 */
		return apply_filters( 'upgrader_overwrite_file_changes', $output, $file_changes, $this );
	}

	/**
	 * Displays a form to the user to request for their FTP/SSH details in order
	 * to connect to the filesystem.
	 *
	 * @since 2.8.0
	 * @since 4.6.0 The `$context` parameter default changed from `false` to an empty string.
	 *
	 * @see request_filesystem_credentials()
	 *
	 * @param bool|WP_Error $error                        Optional. Whether the current request has failed to connect,
	 *                                                    or an error object. Default false.
	 * @param string        $context                      Optional. Full path to the directory that is tested
	 *                                                    for being writable. Default empty.
	 * @param bool          $allow_relaxed_file_ownership Optional. Whether to allow Group/World writable. Default false.
	 * @return bool True on success, false on failure.
	 */
	public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
		$url = $this->options['url'];
		if ( ! $context ) {
			$context = $this->options['context'];
		}
		if ( ! empty( $this->options['nonce'] ) ) {
			$url = wp_nonce_url( $url, $this->options['nonce'] );
		}

		$extra_fields = array();

		return request_filesystem_credentials( $url, '', $error, $context, $extra_fields, $allow_relaxed_file_ownership );
	}

	/**
	 * Displays the header before the update process.
	 *
	 * @since 2.8.0
	 */
	public function header() {
		if ( $this->done_header ) {
			return;
		}
		$this->done_header = true;
		echo '<div class="wrap">';
		echo '<h1>' . $this->options['title'] . '</h1>';
	}

	/**
	 * Displays the footer following the update process.
	 *
	 * @since 2.8.0
	 */
	public function footer() {
		if ( $this->done_footer ) {
			return;
		}
		$this->done_footer = true;
		echo '</div>';
	}

	/**
	 * Displays an error message about the update.
	 *
	 * @since 2.8.0
	 *
	 * @param string|WP_Error $errors Errors.
	 */
	public function error( $errors ) {
		if ( ! $this->done_header ) {
			$this->header();
		}
		if ( is_string( $errors ) ) {
			$this->feedback( $errors );
		} elseif ( is_wp_error( $errors ) && $errors->has_errors() ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() && is_string( $errors->get_error_data() ) ) {
					$this->feedback( $message . ' ' . esc_html( strip_tags( $errors->get_error_data() ) ) );
				} else {
					$this->feedback( $message );
				}
			}
		}
	}

	/**
	 * Displays a message about the update.
	 *
	 * @since 2.8.0
	 * @since 5.9.0 Renamed `$string` (a PHP reserved keyword) to `$feedback` for PHP 8 named parameter support.
	 *
	 * @param string $feedback Message data.
	 * @param mixed  ...$args  Optional text replacements.
	 */
	public function feedback( $feedback, ...$args ) {
		if ( isset( $this->upgrader->strings[ $feedback ] ) ) {
			$feedback = $this->upgrader->strings[ $feedback ];
		}

		if ( str_contains( $feedback, '%' ) ) {
			if ( $args ) {
				$args     = array_map( 'strip_tags', $args );
				$args     = array_map( 'esc_html', $args );
				$feedback = vsprintf( $feedback, $args );
			}
		}
		if ( empty( $feedback ) ) {
			return;
		}
		show_message( $feedback );
	}

	/**
	 * Performs an action before an update.
	 *
	 * @since 2.8.0
	 */
	public function before() {}

	/**
	 * Performs an action following an update.
	 *
	 * @since 2.8.0
	 */
	public function after() {}

	/**
	 * Outputs JavaScript that calls function to decrement the update counts.
	 *
	 * @since 3.9.0
	 * @since 7.2.0 Prints the script through wp_print_inline_script_tag() so it can carry
	 *              attributes, such as a per-request nonce, added via wp_inline_script_attributes.
	 *
	 * @param string $type Type of update count to decrement. Likely values include 'plugin',
	 *                     'theme', 'translation', etc.
	 */
	protected function decrement_update_count( $type ) {
		if ( ! $this->result || is_wp_error( $this->result ) || 'up_to_date' === $this->result ) {
			return;
		}

		if ( defined( 'IFRAME_REQUEST' ) ) {
			// language=JavaScript
			$js_function = <<<'JAVASCRIPT'
				( upgradeType ) => {
					window.parent.postMessage(
						JSON.stringify( {
							action: "decrementUpdateCount",
							upgradeType
						} ),
						window.location.protocol + "//" + window.location.hostname
							+ ( "" !== window.location.port ? ":" + window.location.port : "" )
					);
				}
				JAVASCRIPT;
		} else {
			$js_function = <<<'JS'
				( upgradeType ) => {
					const wp = window.wp;
					if ( wp && wp.updates && wp.updates.decrementCount ) {
						wp.updates.decrementCount( upgradeType );
					}
				}
				JS;
		}

		wp_print_inline_script_tag(
			sprintf(
				'( %s )( %s );',
				$js_function,
				wp_json_encode( $type, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			)
		);
	}

	/**
	 * Displays the header before the bulk update process.
	 *
	 * @since 3.0.0
	 */
	public function bulk_header() {}

	/**
	 * Displays the footer following the bulk update process.
	 *
	 * @since 3.0.0
	 */
	public function bulk_footer() {}

	/**
	 * Hides the `process_failed` error message when updating by uploading a zip file.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_Error $wp_error WP_Error object.
	 * @return bool True if the error should be hidden, false otherwise.
	 */
	public function hide_process_failed( $wp_error ) {
		return false;
	}
}
