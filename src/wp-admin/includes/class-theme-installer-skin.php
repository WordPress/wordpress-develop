<?php
/**
 * Upgrader API: Theme_Installer_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Theme Installer Skin for the WordPress Theme Installer.
 *
 * @since 2.8.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader-skins.php.
 *
 * @see WP_Upgrader_Skin
 */
class Theme_Installer_Skin extends WP_Upgrader_Skin {
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
			'theme'     => '',
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
				$this->upgrader->strings['process_success_specific'],
				$this->api->name,
				$this->api->version
			);
		}
	}

	/**
	 */
	public function after() {
		$compare_table = $this->compare_table();

		if ( $compare_table ) {
			$this->feedback( 'compare_before_overwrite' );
			echo $compare_table;

			$overwrite = $this->is_downgrading ? 'downgrade-theme' : 'update-theme';
			$label     = $this->is_downgrading ? __( 'Remove current and install the old version' ) : __( 'Remove current and install the uploaded version' );

			$install_actions = array(
				'themes_page'    => sprintf(
					'<a href="%s" target="_parent">%s</a>',
					self_admin_url( 'theme-install.php' ),
					__( 'Cancel and go back' )
				),
				'ovewrite_theme' => sprintf(
					'<a class="ovewrite-uploaded-theme" href="%s" target="_parent">%s</a>',
					wp_nonce_url( add_query_arg( 'overwrite', $overwrite, $this->url ), 'theme-upload' ),
					$label
				),
			);

			/**
			 * Filters the list of action links available following a single theme installation failed but ovewrite is allowed.
			 *
			 * @since 5.5.0
			 *
			 * @param string[] $install_actions Array of theme action links.
			 * @param object   $api             Object containing WordPress.org API theme data.
			 * @param array    $new_theme_data  Array with uploaded theme data.
			 */
			$install_actions = apply_filters( 'install_theme_ovewrite_actions', $install_actions, $this->api, $this->upgrader->new_theme_data );
			if ( ! empty( $install_actions ) ) {
				$this->feedback( implode( ' | ', (array) $install_actions ) );
			}

			return;
		}

		if ( empty( $this->upgrader->result['destination_name'] ) ) {
			return;
		}

		$theme_info = $this->upgrader->theme_info();
		if ( empty( $theme_info ) ) {
			return;
		}

		$name       = $theme_info->display( 'Name' );
		$stylesheet = $this->upgrader->result['destination_name'];
		$template   = $theme_info->get_template();

		$activate_link = add_query_arg(
			array(
				'action'     => 'activate',
				'template'   => urlencode( $template ),
				'stylesheet' => urlencode( $stylesheet ),
			),
			admin_url( 'themes.php' )
		);
		$activate_link = wp_nonce_url( $activate_link, 'switch-theme_' . $stylesheet );

		$install_actions = array();

		if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
			$customize_url = add_query_arg(
				array(
					'theme'  => urlencode( $stylesheet ),
					'return' => urlencode( admin_url( 'web' === $this->type ? 'theme-install.php' : 'themes.php' ) ),
				),
				admin_url( 'customize.php' )
			);

			$install_actions['preview'] = sprintf(
				'<a href="%s" class="hide-if-no-customize load-customize">' .
				'<span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
				esc_url( $customize_url ),
				__( 'Live Preview' ),
				/* translators: %s: Theme name. */
				sprintf( __( 'Live Preview &#8220;%s&#8221;' ), $name )
			);
		}

		$install_actions['activate'] = sprintf(
			'<a href="%s" class="activatelink">' .
			'<span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
			esc_url( $activate_link ),
			__( 'Activate' ),
			/* translators: %s: Theme name. */
			sprintf( __( 'Activate &#8220;%s&#8221;' ), $name )
		);

		if ( is_network_admin() && current_user_can( 'manage_network_themes' ) ) {
			$install_actions['network_enable'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				esc_url( wp_nonce_url( 'themes.php?action=enable&amp;theme=' . urlencode( $stylesheet ), 'enable-theme_' . $stylesheet ) ),
				__( 'Network Enable' )
			);
		}

		if ( 'web' === $this->type ) {
			$install_actions['themes_page'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				self_admin_url( 'theme-install.php' ),
				__( 'Return to Theme Installer' )
			);
		} elseif ( current_user_can( 'switch_themes' ) || current_user_can( 'edit_theme_options' ) ) {
			$install_actions['themes_page'] = sprintf(
				'<a href="%s" target="_parent">%s</a>',
				self_admin_url( 'themes.php' ),
				__( 'Return to Themes page' )
			);
		}

		if ( ! $this->result || is_wp_error( $this->result ) || is_network_admin() || ! current_user_can( 'switch_themes' ) ) {
			unset( $install_actions['activate'], $install_actions['preview'] );
		}

		/**
		 * Filters the list of action links available following a single theme installation.
		 *
		 * @since 2.8.0
		 *
		 * @param string[] $install_actions Array of theme action links.
		 * @param object   $api             Object containing WordPress.org API theme data.
		 * @param string   $stylesheet      Theme directory name.
		 * @param WP_Theme $theme_info      Theme object.
		 */
		$install_actions = apply_filters( 'install_theme_complete_actions', $install_actions, $this->api, $stylesheet, $theme_info );
		if ( ! empty( $install_actions ) ) {
			$this->feedback( implode( ' | ', (array) $install_actions ) );
		}
	}

	/**
	 * Create the compare table to show user information about overwrite theme on upload.
	 *
	 * @since 5.5.0
	 *
	 * @return string   $table   The table output.
	 */
	private function compare_table() {
		if ( 'upload' !== $this->type || ! is_wp_error( $this->result ) || $this->result->get_error_code() !== 'folder_exists' ) {
			return '';
		}

		$folder = $this->result->get_error_data( 'folder_exists' );
		$folder = rtrim( $folder, '/' );

		$current_theme_data = false;
		foreach ( wp_get_themes() as $theme ) {
			if ( $folder !== rtrim( $theme->get_stylesheet_directory(), '/' ) ) {
				continue;
			}

			$current_theme_data = $theme;
		}

		if ( empty( $current_theme_data ) || empty( $this->upgrader->new_theme_data ) ) {
			return '';
		}

		$this->is_downgrading = version_compare( $current_theme_data['Version'], $this->upgrader->new_theme_data['Version'], '>' );

		$rows = array(
			'Name'        => __( 'Theme Name' ),
			'Version'     => __( 'Version' ),
			'Author'      => __( 'Author' ),
			'RequiresWP'  => __( 'Requires at least' ),
			'RequiresPHP' => __( 'Requires PHP' ),
			'Template'    => __( 'Parent Theme' ),
		);

		$table  = '<table class="compare-themes-table"><tbody>';
		$table .= '<tr><th></th><th>' . esc_html( __( 'Current' ) ) . '</th><th>' . esc_html( __( 'Uploaded' ) ) . '</th></tr>';

		$is_same_theme = true; // Let's consider only these rows
		foreach ( $rows as $field => $label ) {
			$old_value = $current_theme_data->display( $field, false );
			$old_value = $old_value ? $old_value : '-';

			$new_value = ! empty( $this->upgrader->new_theme_data[ $field ] ) ? $this->upgrader->new_theme_data[ $field ] : '-';

			if ( $old_value === $new_value && $new_value === '-' && $field === 'Template' ) {
				continue;
			}

			$is_same_theme = $is_same_theme && ( $old_value === $new_value );

			$diff_field   = ( $field !== 'Version' && $new_value !== $old_value );
			$diff_version = ( $field === 'Version' && $this->is_downgrading );

			$table .= ( $diff_field || $diff_version ) ? '<tr class="warning">' : '<tr>';
			$table .= '<td>' . $label . '</td><td>' . esc_html( $old_value ) . '</td><td>' . esc_html( $new_value ) . '</td></tr>';
		}

		$table .= '</tbody></table>';

		if ( $is_same_theme ) {
			$this->feedback( 'reuploading_theme' );
		}

		/**
		 * Filters the compare table output for overwrite a theme package on upload.
		 *
		 * @since 5.5.0
		 *
		 * @param string   $table               The output table with Name, Version, Author, RequiresWP and RequiresPHP info.
		 * @param array    $current_theme_data  Array with current theme data.
		 * @param array    $new_theme_data      Array with uploaded theme data.
		 */
		return apply_filters( 'install_theme_compare_table_ovewrite', $table, $current_theme_data, $this->upgrader->new_theme_data );
	}
}
