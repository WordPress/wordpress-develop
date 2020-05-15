<?php
/**
 * Upgrader API: Plugin_Installer_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Plugin Installer Skin for WordPress Plugin Installer.
 *
 * @since 2.8.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader-skins.php.
 *
 * @see WP_Upgrader_Skin
 */
class Plugin_Installer_Skin extends WP_Upgrader_Skin {
	public $api;
	public $type;
	public $url;
	public $overwrite;

	private $is_downgrading = false;

	/**
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'type'      => 'web',
			'url'       => '',
			'plugin'    => '',
			'nonce'     => '',
			'title'     => '',
			'overwrite' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$this->type      = $args['type'];
		$this->url       = $args['url'];
		$this->api       = isset( $args['api'] ) ? $args['api'] : array();
		$this->overwrite = $args['overwrite'];

		parent::__construct( $args );
	}

	/**
	 */
	public function before() {
		if ( ! empty( $this->api ) ) {
			$this->upgrader->strings['process_success'] = sprintf(
				/* translators: 1: Plugin name, 2: Plugin version. */
				__( 'Successfully installed the plugin <strong>%1$s %2$s</strong>.' ),
				$this->api->name,
				$this->api->version
			);
		}
	}

	/**
	 */
	public function after() {
		$compare_table = $this->compare_table();

		$plugin_file = $this->upgrader->plugin_info();

		$install_actions = array();

		$from = isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : 'plugins';

		if ( 'import' == $from ) {
			$install_actions['activate_plugin'] = sprintf(
				'<a class="button button-primary" href="%s" target="_parent">%s</a>',
				wp_nonce_url( 'plugins.php?action=activate&amp;from=import&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ),
				__( 'Activate Plugin &amp; Run Importer' )
			);
		} elseif ( 'press-this' == $from ) {
			$install_actions['activate_plugin'] = sprintf(
				'<a class="button button-primary" href="%s" target="_parent">%s</a>',
				wp_nonce_url( 'plugins.php?action=activate&amp;from=press-this&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ),
				__( 'Activate Plugin &amp; Return to Press This' )
			);
		} else {
			$install_actions['activate_plugin'] = sprintf(
				'<a class="button button-primary" href="%s" target="_parent">%s</a>',
				wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ),
				__( 'Activate Plugin' )
			);
		}

		if ( is_multisite() && current_user_can( 'manage_network_plugins' ) ) {
			$install_actions['network_activate'] = sprintf(
				'<a class="button button-primary" href="%s" target="_parent">%s</a>',
				wp_nonce_url( 'plugins.php?action=activate&amp;networkwide=1&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ),
				__( 'Network Activate' )
			);
			unset( $install_actions['activate_plugin'] );
		}

		if ( 'import' === $from ) {
			$install_actions['importers_page'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				admin_url( 'import.php' ),
				__( 'Return to Importers' )
			);
		} elseif ( 'web' === $this->type ) {
			$install_actions['plugins_page'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				self_admin_url( 'plugin-install.php' ),
				__( 'Return to Plugin Installer' )
			);
		} elseif ( 'upload' === $this->type && 'plugins' === $from ) {
			$install_actions['plugins_page'] = sprintf(
				'<a href="%s">%s</a>',
				self_admin_url( 'plugin-install.php' ),
				$compare_table ? __( 'Cancel and go back' ) : __( 'Return to Plugin Installer' )
			);
		} else {
			$install_actions['plugins_page'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				self_admin_url( 'plugins.php' ),
				__( 'Return to Plugins page' )
			);
		}

		if ( $compare_table ) {
			$this->feedback( 'compare_before_overwrite' );
			echo $compare_table;

			$overwrite = $this->is_downgrading ? 'downgrade-plugin' : 'update-plugin';
			$label     = $this->is_downgrading ? __( 'Remove current and install the old version' ) : __( 'Remove current and install the uploaded version' );

			$install_actions['ovewrite_plugin'] = sprintf(
				'<a class="ovewrite-uploaded-plugin" href="%s" target="_parent">%s</a>',
				wp_nonce_url( add_query_arg( 'overwrite', $overwrite, $this->url ), 'plugin-upload' ),
				$label
			);
		}

		if ( ! $this->result || is_wp_error( $this->result ) ) {
			unset( $install_actions['activate_plugin'], $install_actions['network_activate'] );
		} elseif ( $this->overwrite || ! current_user_can( 'activate_plugin', $plugin_file ) ) {
			unset( $install_actions['activate_plugin'] );
		}

		/**
		 * Filters the list of action links available following a single plugin installation.
		 *
		 * @since 2.7.0
		 *
		 * @param string[] $install_actions Array of plugin action links.
		 * @param object   $api             Object containing WordPress.org API plugin data. Empty
		 *                                  for non-API installs, such as when a plugin is installed
		 *                                  via upload.
		 * @param string   $plugin_file     Path to the plugin file relative to the plugins directory.
		 */
		$install_actions = apply_filters( 'install_plugin_complete_actions', $install_actions, $this->api, $plugin_file );

		if ( ! empty( $install_actions ) ) {
			$this->feedback( implode( ' ', (array) $install_actions ) );
		}
	}

	/**
	 * Create the compare table to show user information about overwrite plugin on upload.
	 *
	 * @since 5.5.0
	 *
	 * @return string   $table   The table output.
	 */
	private function compare_table() {
		if ( 'upload' !== $this->type || ! is_wp_error( $this->result ) || $this->result->get_error_code() !== 'folder_exists' ) {
			return '';
		}

		$folder    = $this->result->get_error_data( 'folder_exists' );
		$directory = ltrim( substr( $folder, strlen( WP_PLUGIN_DIR ) ), '/' );

		$current_plugin_data = false;
		foreach ( get_plugins() as $plugin => $plugin_data ) {
			if ( strrpos( $plugin, $directory ) !== 0 ) {
				continue;
			}

			$current_plugin_data = $plugin_data;
		}

		if ( empty( $current_plugin_data ) || empty( $this->upgrader->new_plugin_data ) ) {
			return '';
		}

		$this->is_downgrading = version_compare( $current_plugin_data['Version'], $this->upgrader->new_plugin_data['Version'], '>' );

		$rows = array(
			'Name'        => __( 'Plugin Name' ),
			'Version'     => __( 'Version' ),
			'Author'      => __( 'Author' ),
			'RequiresWP'  => __( 'Requires at least' ),
			'RequiresPHP' => __( 'Requires PHP' ),
		);

		$table  = '<table class="compare-plugins-table"><tbody>';
		$table .= '<tr><th></th><th>' . esc_html( __( 'Current' ) ) . '</th><th>' . esc_html( __( 'Uploaded' ) ) . '</th></tr>';

		$is_same_plugin = true; // Let's consider only these rows
		foreach ( $rows as $field => $label ) {
			$old_value = ! empty( $current_plugin_data[ $field ] ) ? $current_plugin_data[ $field ] : '-';
			$new_value = ! empty( $this->upgrader->new_plugin_data[ $field ] ) ? $this->upgrader->new_plugin_data[ $field ] : '-';

			$is_same_plugin = $is_same_plugin && ( $old_value === $new_value );

			$diff_field   = ( $field !== 'Version' && $new_value !== $old_value );
			$diff_version = ( $field === 'Version' && $this->is_downgrading );

			$table .= ( $diff_field || $diff_version ) ? '<tr class="warning">' : '<tr>';
			$table .= '<td>' . $label . '</td><td>' . esc_html( $old_value ) . '</td><td>' . esc_html( $new_value ) . '</td></tr>';
		}

		$table .= '</tbody></table>';

		if ( $is_same_plugin ) {
			$this->feedback( 'reuploading_plugin' );
		}

		/**
		 * Filters the compare table output for overwrite a plugin package on upload.
		 *
		 * @since 5.5
		 *
		 * @param string   $table                The output table with Name, Version, Author, RequiresWP and RequiresPHP info.
		 * @param array    $current_plugin_data  Array with current plugin data.
		 * @param array    $new_plugin_data      Array with uploaded plugin data.
		 */
		return apply_filters( 'install_plugin_compare_table_ovewrite', $table, $current_plugin_data, $this->upgrader->new_plugin_data );
	}
}
