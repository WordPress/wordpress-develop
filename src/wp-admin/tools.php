<?php
/**
 * Tools Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( isset( $_GET['page'] ) && ! empty( $_POST ) ) {
	// Ensure POST-ing to `tools.php?page=export_personal_data` and `tools.php?page=remove_personal_data`
	// continues to work after creating the new files for exporting and erasing of personal data.
	if ( 'export_personal_data' === $_GET['page'] ) {
		require_once ABSPATH . 'wp-admin/export-personal-data.php';
		return;
	} elseif ( 'remove_personal_data' === $_GET['page'] ) {
		require_once ABSPATH . 'wp-admin/erase-personal-data.php';
		return;
	}
}

// The privacy policy guide used to be outputted from here. Since WP 5.3 it is in wp-admin/privacy-policy-guide.php.
if ( isset( $_GET['wp-privacy-policy-guide'] ) ) {
	require_once dirname( __DIR__ ) . '/wp-load.php';
	wp_redirect( admin_url( 'options-privacy.php?tab=policyguide' ), 301 );
	exit;
} elseif ( isset( $_GET['page'] ) ) {
	// These were also moved to files in WP 5.3.
	if ( 'export_personal_data' === $_GET['page'] ) {
		require_once dirname( __DIR__ ) . '/wp-load.php';
		wp_redirect( admin_url( 'export-personal-data.php' ), 301 );
		exit;
	} elseif ( 'remove_personal_data' === $_GET['page'] ) {
		require_once dirname( __DIR__ ) . '/wp-load.php';
		wp_redirect( admin_url( 'erase-personal-data.php' ), 301 );
		exit;
	}
}

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

// Used in the HTML title tag.
$title = __( 'Tools' );

get_current_screen()->add_help_tab(
	array(
		'id'      => 'converter',
		'title'   => __( 'Categories and Tags Converter' ),
		'content' => '<p>' . __( 'Categories have hierarchy, meaning that you can nest sub-categories. Tags do not have hierarchy and cannot be nested. Sometimes people start out using one on their posts, then later realize that the other would work better for their content.' ) . '</p>' .
		'<p>' . __( 'The Categories and Tags Converter link on this screen will take you to the Import screen, where that Converter is one of the plugins you can install. Once that plugin is installed, the Activate Plugin &amp; Run Importer link will take you to a screen where you can choose to convert tags into categories or vice versa.' ) . '</p>',
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'import',
		'title'   => __( 'Import' ),
		'content' => '<p>' . __( 'In previous versions of WordPress, all importers were built-in. They have been turned into plugins since most people only use them once or infrequently.' ) . '</p>',
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'export',
		'title'   => __( 'Export' ),
		'content' => '<p>' . __( 'You can export a file of your site&#8217;s content in order to import it into another installation or platform. The export file will be an XML file format called WXR. Posts, pages, comments, custom fields, categories, and tags can be included. You can choose for the WXR file to include only certain posts or pages by setting the dropdown filters to limit the export by category, author, date range by month, or publishing status.' ) . '</p>',
	)
);

get_current_screen()->add_help_tab(
	array(
		'id'      => 'code-editing',
		'title'   => __( 'Code Editing Tools' ),
		'content' => '<p>' . __( 'You can use the theme file editor to edit the individual CSS and PHP files which make up your theme.' ) . '</p>' .
		'<p>' . __( 'You can use the plugin file editor to make changes to any of your plugins&#8217; individual PHP files. Be aware that if you make changes, plugins updates will overwrite your customizations.' ) . '</p>',
	)
);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://wordpress.org/documentation/article/tools-screen/">Documentation on Tools</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/forums/">Support forums</a>' ) . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>
<?php

/*
 * Tracks whether anything at all was output on this screen, so that users whose
 * role gives them access to none of the tools are shown an explanation instead
 * of an empty page.
 */
$tools_screen_has_content = false;

if ( current_user_can( 'import' ) ) :
	$cats = get_taxonomy( 'category' );
	$tags = get_taxonomy( 'post_tag' );
	if ( current_user_can( $cats->cap->manage_terms ) || current_user_can( $tags->cap->manage_terms ) ) :
		$tools_screen_has_content = true;
		?>
		<div class="card">
			<h2 class="title"><?php _e( 'Categories and Tags Converter' ); ?></h2>
			<p>
			<?php
				printf(
					/* translators: %s: URL to Import screen. */
					__( 'If you want to convert your categories to tags (or vice versa), use the <a href="%s">Categories and Tags Converter</a> available from the Import screen.' ),
					'import.php'
				);
			?>
			</p>
		</div>
		<?php
	endif;

	$import_options = array();

	/*
	 * The importers are plugins, so the list is only meaningful to a user who is
	 * able to install them. This mirrors the check made on the Import screen.
	 */
	if ( current_user_can( 'install_plugins' ) ) {
		foreach ( wp_get_popular_importers() as $import_slug => $importer ) {
			$import_options[ $import_slug ] = array(
				'label'       => $importer['name'],
				'description' => $importer['description'],
			);
		}
	}

	/**
	 * Filters the list of import options described on the Tools screen.
	 *
	 * Defaults to the popular importers returned by wp_get_popular_importers(),
	 * and is empty for users who cannot install plugins. The list is informational:
	 * the importers themselves are installed and run from the Import screen.
	 *
	 * @since 7.2.0
	 *
	 * @param array[] $import_options {
	 *     Array of import options, keyed by importer slug.
	 *
	 *     @type array ...$0 {
	 *         @type string $label       Name of the importer.
	 *         @type string $description Short description of what the importer imports.
	 *     }
	 * }
	 */
	$import_options = apply_filters( 'tools_screen_import_options', $import_options );

	$tools_screen_has_content = true;
	?>
	<div class="card">
		<h2 class="title"><?php _e( 'Import Tools' ); ?></h2>
		<?php if ( ! empty( $import_options ) ) : ?>
			<ul class="ul-disc">
				<?php
				foreach ( $import_options as $import_option ) :
					$import_option = wp_parse_args(
						$import_option,
						array(
							'label'       => '',
							'description' => '',
						)
					);

					if ( '' === $import_option['label'] ) {
						continue;
					}
					?>
					<li>
						<strong><?php echo esc_html( $import_option['label'] ); ?></strong>
						<?php if ( '' !== $import_option['description'] ) : ?>
							<span class="description"><?php echo wp_kses_post( $import_option['description'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'import.php' ) ); ?>"><?php _e( 'Import' ); ?></a>
		</p>
	</div>
	<?php
endif;

if ( current_user_can( 'export' ) ) :
	$export_options = array(
		'all'        => __( 'All content' ),
		'posts'      => __( 'Posts' ),
		'pages'      => __( 'Pages' ),
		'attachment' => __( 'Media' ),
	);

	$exportable_post_types = get_post_types(
		array(
			'_builtin'   => false,
			'can_export' => true,
		),
		'objects'
	);

	foreach ( $exportable_post_types as $exportable_post_type ) {
		$export_options[ $exportable_post_type->name ] = $exportable_post_type->label;
	}

	/**
	 * Filters the list of export options described on the Tools screen.
	 *
	 * By default this mirrors the choices offered on the Export screen, including
	 * any custom post type registered with 'can_export' support.
	 *
	 * @since 7.2.0
	 *
	 * @param string[] $export_options Array of export option labels, keyed by the value of the
	 *                                 matching `content` parameter on the Export screen.
	 */
	$export_options = apply_filters( 'tools_screen_export_options', $export_options );

	if ( ! empty( $export_options ) ) :
		$tools_screen_has_content = true;
		?>
		<div class="card">
			<h2 class="title"><?php _e( 'Export Tools' ); ?></h2>
			<ul class="ul-disc">
				<?php foreach ( $export_options as $export_option ) : ?>
					<li><?php echo esc_html( $export_option ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'export.php' ) ); ?>"><?php _e( 'Export' ); ?></a>
			</p>
		</div>
		<?php
	endif;
endif;

$code_editing_options = array();

/*
 * The file editors only belong to the Tools menu for block themes. With a classic
 * theme the Theme File Editor lives under Appearance and the Plugin File Editor
 * under Plugins, and on multisite neither is added to a site's menu at all. This
 * mirrors _add_themes_utility_last() and _add_plugin_file_editor_to_tools() in
 * wp-admin/menu.php.
 */
if ( wp_is_block_theme() && ! is_multisite() ) {
	if ( current_user_can( 'edit_themes' ) ) {
		$code_editing_options['theme-editor'] = array(
			'label'       => __( 'Theme File Editor' ),
			'url'         => admin_url( 'theme-editor.php' ),
			'description' => __( 'You can use the theme file editor to edit the individual CSS and PHP files which make up your theme.' ),
		);
	}

	if ( current_user_can( 'edit_plugins' ) ) {
		$code_editing_options['plugin-editor'] = array(
			'label'       => __( 'Plugin File Editor' ),
			'url'         => admin_url( 'plugin-editor.php' ),
			'description' => __( 'You can use the plugin file editor to make changes to any of your plugins&#8217; individual PHP files. Be aware that if you make changes, plugins updates will overwrite your customizations.' ),
		);
	}
}

/**
 * Filters the list of code editing tools shown on the Tools screen.
 *
 * Entries are only added by default when the current user is allowed to use
 * them, so this filter runs after the capability checks. Any entry added here
 * is displayed as-is.
 *
 * @since 7.2.0
 *
 * @param array[] $code_editing_options {
 *     Array of code editing tools, keyed by tool slug.
 *
 *     @type array ...$0 {
 *         @type string $label       Name of the tool, used as the link text.
 *         @type string $url         URL of the screen the tool lives on.
 *         @type string $description Short description of what the tool does.
 *     }
 * }
 */
$code_editing_options = apply_filters( 'tools_screen_code_editing_options', $code_editing_options );

if ( ! empty( $code_editing_options ) ) :
	$tools_screen_has_content = true;
	?>
	<div class="card">
		<h2 class="title"><?php _e( 'Code Editing Tools' ); ?></h2>
		<ul class="ul-disc">
			<?php
			foreach ( $code_editing_options as $code_editing_option ) :
				$code_editing_option = wp_parse_args(
					$code_editing_option,
					array(
						'label'       => '',
						'url'         => '',
						'description' => '',
					)
				);

				if ( '' === $code_editing_option['label'] || '' === $code_editing_option['url'] ) {
					continue;
				}
				?>
				<li>
					<a href="<?php echo esc_url( $code_editing_option['url'] ); ?>"><?php echo esc_html( $code_editing_option['label'] ); ?></a>
					<?php if ( '' !== $code_editing_option['description'] ) : ?>
						<span class="description"><?php echo wp_kses_post( $code_editing_option['description'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
endif;

/*
 * The output is buffered so that anything added by a plugin counts as content,
 * and the "no tools" message below is not shown alongside it.
 */
ob_start();

/**
 * Fires at the end of the Tools Administration screen.
 *
 * @since 2.8.0
 */
do_action( 'tool_box' );

$tool_box = ob_get_clean();

if ( '' !== trim( $tool_box ) ) {
	$tools_screen_has_content = true;
}

echo $tool_box;

if ( ! $tools_screen_has_content ) {
	echo '<p>' . __( 'No tools are available for your account.' ) . '</p>';
}

?>
</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
