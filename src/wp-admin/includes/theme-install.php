<?php
/**
 * WordPress Theme Installation Administration API
 *
 * @package WordPress
 * @subpackage Administration
 */

$themes_allowedtags = array(
	'a'       => array(
		'href'   => array(),
		'title'  => array(),
		'target' => array(),
	),
	'abbr'    => array( 'title' => array() ),
	'acronym' => array( 'title' => array() ),
	'code'    => array(),
	'pre'     => array(),
	'em'      => array(),
	'strong'  => array(),
	'div'     => array(),
	'p'       => array(),
	'ul'      => array(),
	'ol'      => array(),
	'li'      => array(),
	'h1'      => array(),
	'h2'      => array(),
	'h3'      => array(),
	'h4'      => array(),
	'h5'      => array(),
	'h6'      => array(),
	'img'     => array(
		'src'   => array(),
		'class' => array(),
		'alt'   => array(),
	),
);

$theme_field_defaults = array(
	'description'  => true,
	'sections'     => false,
	'tested'       => true,
	'requires'     => true,
	'rating'       => true,
	'downloaded'   => true,
	'downloadlink' => true,
	'last_updated' => true,
	'homepage'     => true,
	'tags'         => true,
	'num_ratings'  => true,
);

/**
 * Retrieves the list of WordPress theme features (aka theme tags).
 *
 * @since 2.8.0
 *
 * @deprecated 3.1.0 Use get_theme_feature_list() instead.
 *
 * @return array
 */
function install_themes_feature_list() {
	_deprecated_function( __FUNCTION__, '3.1.0', 'get_theme_feature_list()' );

	$cache = get_transient( 'wporg_theme_feature_list' );
	if ( ! $cache ) {
		set_transient( 'wporg_theme_feature_list', array(), 3 * HOUR_IN_SECONDS );
	}

	if ( $cache ) {
		return $cache;
	}

	$feature_list = themes_api( 'feature_list', array() );
	if ( is_wp_error( $feature_list ) ) {
		return array();
	}

	set_transient( 'wporg_theme_feature_list', $feature_list, 3 * HOUR_IN_SECONDS );

	return $feature_list;
}

/**
 * Displays search form for searching themes.
 *
 * @since 2.8.0
 *
 * @param bool $type_selector
 */
function install_theme_search_form( $type_selector = true ) {
	$type = isset( $_REQUEST['type'] ) ? wp_unslash( $_REQUEST['type'] ) : 'term';
	$term = isset( $_REQUEST['s'] ) ? wp_unslash( $_REQUEST['s'] ) : '';
	if ( ! $type_selector ) {
		echo '<p class="install-help">' . __( 'Search for themes by keyword.' ) . '</p>';
	}
	?>
<form id="search-themes" method="get">
	<input type="hidden" name="tab" value="search" />
	<?php if ( $type_selector ) : ?>
	<label class="screen-reader-text" for="typeselector">
		<?php
		/* translators: Hidden accessibility text. */
		_e( 'Type of search' );
		?>
	</label>
	<select	name="type" id="typeselector">
	<option value="term" <?php selected( 'term', $type ); ?>><?php _e( 'Keyword' ); ?></option>
	<option value="author" <?php selected( 'author', $type ); ?>><?php _e( 'Author' ); ?></option>
	<option value="tag" <?php selected( 'tag', $type ); ?>><?php _ex( 'Tag', 'Theme Installer' ); ?></option>
	</select>
	<label class="screen-reader-text" for="s">
		<?php
		switch ( $type ) {
			case 'term':
				/* translators: Hidden accessibility text. */
				_e( 'Search by keyword' );
				break;
			case 'author':
				/* translators: Hidden accessibility text. */
				_e( 'Search by author' );
				break;
			case 'tag':
				/* translators: Hidden accessibility text. */
				_e( 'Search by tag' );
				break;
		}
		?>
	</label>
	<?php else : ?>
	<label class="screen-reader-text" for="s">
		<?php
		/* translators: Hidden accessibility text. */
		_e( 'Search by keyword' );
		?>
	</label>
	<?php endif; ?>
	<input type="search" name="s" id="s" size="30" value="<?php echo esc_attr( $term ); ?>" autofocus="autofocus" />
	<?php submit_button( __( 'Search' ), '', 'search', false ); ?>
</form>
	<?php
}

/**
 * Displays tags filter for themes.
 *
 * @since 2.8.0
 */
function install_themes_dashboard() {
	install_theme_search_form( false );
	?>
<h4><?php _e( 'Feature Filter' ); ?></h4>
<p class="install-help"><?php _e( 'Find a theme based on specific features.' ); ?></p>

<form method="get">
	<input type="hidden" name="tab" value="search" />
	<?php
	$feature_list = get_theme_feature_list();
	echo '<div class="feature-filter">';

	foreach ( (array) $feature_list as $feature_name => $features ) {
		$feature_name = esc_html( $feature_name );
		echo '<div class="feature-name">' . $feature_name . '</div>';

		echo '<ol class="feature-group">';
		foreach ( $features as $feature => $feature_name ) {
			$feature_name = esc_html( $feature_name );
			$feature      = esc_attr( $feature );
			?>

<li>
	<input type="checkbox" name="features[]" id="feature-id-<?php echo $feature; ?>" value="<?php echo $feature; ?>" />
	<label for="feature-id-<?php echo $feature; ?>"><?php echo $feature_name; ?></label>
</li>

<?php	} ?>
</ol>
<br class="clear" />
		<?php
	}
	?>

</div>
<br class="clear" />
	<?php submit_button( __( 'Find Themes' ), '', 'search' ); ?>
</form>
	<?php
}

/**
 * Displays a form to upload themes from zip files.
 *
 * @since 2.8.0
 */
function install_themes_upload() {
	?>
<p class="install-help"><?php _e( 'If you have a theme in a .zip format, you may install or update it by uploading it here.' ); ?></p>
<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?php echo esc_url( self_admin_url( 'update.php?action=upload-theme' ) ); ?>">
	<?php wp_nonce_field( 'theme-upload' ); ?>
	<label class="screen-reader-text" for="themezip">
		<?php
		/* translators: Hidden accessibility text. */
		_e( 'Theme zip file' );
		?>
	</label>
	<input type="file" id="themezip" name="themezip" accept=".zip" />
	<?php submit_button( _x( 'Install Now', 'theme' ), '', 'install-theme-submit', false ); ?>
</form>
	<?php
}

/**
 * Prints a theme on the Install Themes pages.
 *
 * @deprecated 3.4.0
 *
 * @global WP_Theme_Install_List_Table $wp_list_table
 *
 * @param object $theme
 */
function display_theme( $theme ) {
	_deprecated_function( __FUNCTION__, '3.4.0' );
	global $wp_list_table;
	if ( ! isset( $wp_list_table ) ) {
		$wp_list_table = _get_list_table( 'WP_Theme_Install_List_Table' );
	}
	$wp_list_table->prepare_items();
	$wp_list_table->single_row( $theme );
}

/**
 * Displays theme content based on theme list.
 *
 * @since 2.8.0
 *
 * @global WP_Theme_Install_List_Table $wp_list_table
 */
function display_themes() {
	global $wp_list_table;

	if ( ! isset( $wp_list_table ) ) {
		$wp_list_table = _get_list_table( 'WP_Theme_Install_List_Table' );
	}
	$wp_list_table->prepare_items();
	$wp_list_table->display();
}

/**
 * Displays theme information in dialog box form.
 *
 * @since 2.8.0
 *
 * @global WP_Theme_Install_List_Table $wp_list_table
 *
 * @return never
 */
function install_theme_information() {
	global $wp_list_table;

	$theme = themes_api( 'theme_information', array( 'slug' => wp_unslash( $_REQUEST['theme'] ) ) );

	$is_closed = false;
	if ( is_object( $theme ) && (
		( isset( $theme->error ) && 'closed' === $theme->error )
		|| ! empty( $theme->closed )
		|| ! empty( $theme->is_closed )
		|| ! empty( $theme->is_suspended )
		|| ( isset( $theme->status ) && in_array( $theme->status, array( 'closed', 'suspend', 'disabled' ), true ) )
	) ) {
		$is_closed = true;
	} elseif ( is_wp_error( $theme ) && 'closed' === $theme->get_error_code() ) {
		$is_closed  = true;
		$error_data = $theme->get_error_data();
		if ( is_array( $error_data ) || is_object( $error_data ) ) {
			$theme = (object) $error_data;
		} else {
			$theme = (object) array(
				'error'       => 'closed',
				'name'        => sanitize_text_field( wp_unslash( $_REQUEST['theme'] ) ),
				'slug'        => sanitize_text_field( wp_unslash( $_REQUEST['theme'] ) ),
				'description' => $theme->get_error_message(),
			);
		}
	}

	if ( $is_closed ) {
		iframe_header( __( 'Theme Installation' ) );

		$themes_allowedtags = array(
			'a'       => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
			),
			'abbr'    => array( 'title' => array() ),
			'acronym' => array( 'title' => array() ),
			'code'    => array(),
			'pre'     => array(),
			'em'      => array(),
			'strong'  => array(),
			'div'     => array( 'class' => array() ),
			'span'    => array( 'class' => array() ),
			'p'       => array(),
			'br'      => array(),
			'ul'      => array(),
			'ol'      => array(),
			'li'      => array(),
			'h1'      => array(),
			'h2'      => array(),
			'h3'      => array(),
			'h4'      => array(),
			'h5'      => array(),
			'h6'      => array(),
		);

		$theme_name  = ! empty( $theme->name ) ? wp_kses( $theme->name, $themes_allowedtags ) : sanitize_text_field( wp_unslash( $_REQUEST['theme'] ) );
		$is_security = ! empty( $theme->is_security ) || 'security-issue' === ( $theme->reason ?? '' ) || 'security-issue' === ( $theme->closed_reason ?? '' );
		$closed_date = '';
		if ( ! empty( $theme->closed_date ) ) {
			$closed_timestamp = strtotime( $theme->closed_date );
			$closed_date      = $closed_timestamp ? wp_date( get_option( 'date_format' ), $closed_timestamp ) : $theme->closed_date;
		}
		$reason      = $theme->reason_text ?? ( $theme->closed_reason ?? ( $theme->reason ?? '' ) );
		$description = ! empty( $theme->description ) ? wp_kses( $theme->description, $themes_allowedtags ) : '';

		echo '<div id="theme-information" class="theme-information-closed-panel" style="padding: 20px;">';
		echo '<h2>' . esc_html( $theme_name ) . '</h2>';

		if ( $is_security ) {
			if ( $closed_date ) {
				/* translators: %s: Theme closure date. */
				$message = sprintf( __( 'Warning: This theme was closed on %s due to a security issue and is no longer available for download. It should be uninstalled or replaced immediately.' ), esc_html( $closed_date ) );
			} else {
				$message = __( 'Warning: This theme was closed due to a security issue and is no longer available for download. It should be uninstalled or replaced immediately.' );
			}
			wp_admin_notice(
				$message,
				array(
					'type'               => 'error',
					'additional_classes' => array( 'notice-alt' ),
					'paragraph_wrap'     => true,
				)
			);
		} else {
			if ( $closed_date && $reason ) {
				/* translators: 1: Theme closure date, 2: Theme closure reason. */
				$message = sprintf( __( 'Notice: This theme was closed on %1$s (%2$s) and is no longer available for download.' ), esc_html( $closed_date ), esc_html( $reason ) );
			} elseif ( $closed_date ) {
				/* translators: %s: Theme closure date. */
				$message = sprintf( __( 'Notice: This theme was closed on %s and is no longer available for download.' ), esc_html( $closed_date ) );
			} elseif ( $reason ) {
				/* translators: %s: Theme closure reason. */
				$message = sprintf( __( 'Notice: This theme was closed (%s) and is no longer available for download.' ), esc_html( $reason ) );
			} else {
				$message = __( 'Notice: This theme was closed and is no longer available for download.' );
			}
			wp_admin_notice(
				$message,
				array(
					'type'               => 'warning',
					'additional_classes' => array( 'notice-alt' ),
					'paragraph_wrap'     => true,
				)
			);
		}

		if ( ! empty( $theme->is_outdated ) ) {
			$outdated_msg = ! empty( $theme->outdated_notice ) ? $theme->outdated_notice : __( 'This theme has not been updated in over 2 years and may no longer be maintained.' );
			wp_admin_notice(
				$outdated_msg,
				array(
					'type'               => 'warning',
					'additional_classes' => array( 'notice-alt' ),
					'paragraph_wrap'     => true,
				)
			);
		}

		if ( $description ) {
			echo '<div class="theme-closure-description" style="margin-top: 20px;">' . $description . '</div>';
		}

		echo '<ul class="theme-closure-meta" style="margin-top: 20px; list-style: disc; padding-left: 20px;">';
		if ( $closed_date ) {
			/* translators: %s: Theme closure date. */
			echo '<li>' . sprintf( __( 'Closed Date: %s' ), '<strong>' . esc_html( $closed_date ) . '</strong>' ) . '</li>';
		}
		if ( $reason ) {
			/* translators: %s: Theme closure reason. */
			echo '<li>' . sprintf( __( 'Reason: %s' ), '<strong>' . esc_html( $reason ) . '</strong>' ) . '</li>';
		}
		if ( ! empty( $theme->slug ) ) {
			/* translators: %s: Theme slug. */
			echo '<li>' . sprintf( __( 'Slug: %s' ), '<code>' . esc_html( $theme->slug ) . '</code>' ) . '</li>';
		}
		echo '</ul>';

		echo '</div>';

		iframe_footer();
		exit;
	}

	if ( is_wp_error( $theme ) ) {
		wp_die( $theme );
	}

	iframe_header( __( 'Theme Installation' ) );
	if ( ! isset( $wp_list_table ) ) {
		$wp_list_table = _get_list_table( 'WP_Theme_Install_List_Table' );
	}
	$wp_list_table->theme_installer_single( $theme );
	iframe_footer();
	exit;
}
