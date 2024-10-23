<?php
/**
 * Media Library administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'upload_files' ) ) {
	wp_die( __( 'Sorry, you are not allowed to upload files.' ) );
}

$message = '';
if ( ! empty( $_GET['posted'] ) ) {
	$message = __( 'Media file updated.' );

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'posted' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['posted'] );
}

if ( ! empty( $_GET['attached'] ) && absint( $_GET['attached'] ) ) {
	$attached = absint( $_GET['attached'] );

	if ( 1 === $attached ) {
		$message = __( 'Media file attached.' );
	} else {
		$message = sprintf(
			/* translators: %s: Number of media files. */
			_n( '%s media file attached.', '%s media files attached.', $attached ),
			number_format_i18n( $attached )
		);
	}

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'detach', 'attached' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['detach'], $_GET['attached'] );
}

if ( ! empty( $_GET['detach'] ) && absint( $_GET['detach'] ) ) {
	$detached = absint( $_GET['detach'] );

	if ( 1 === $detached ) {
		$message = __( 'Media file detached.' );
	} else {
		$message = sprintf(
			/* translators: %s: Number of media files. */
			_n( '%s media file detached.', '%s media files detached.', $detached ),
			number_format_i18n( $detached )
		);
	}

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'detach', 'attached' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['detach'], $_GET['attached'] );
}

if ( ! empty( $_GET['deleted'] ) && absint( $_GET['deleted'] ) ) {
	$deleted = absint( $_GET['deleted'] );

	if ( 1 === $deleted ) {
		$message = __( 'Media file permanently deleted.' );
	} else {
		$message = sprintf(
			/* translators: %s: Number of media files. */
			_n( '%s media file permanently deleted.', '%s media files permanently deleted.', $deleted ),
			number_format_i18n( $deleted )
		);
	}

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'deleted' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['deleted'] );
}

if ( ! empty( $_GET['trashed'] ) && absint( $_GET['trashed'] ) ) {
	$trashed = absint( $_GET['trashed'] );

	if ( 1 === $trashed ) {
		$message = __( 'Media file moved to the Trash.' );
	} else {
		$message = sprintf(
			/* translators: %s: Number of media files. */
			_n( '%s media file moved to the Trash.', '%s media files moved to the Trash.', $trashed ),
			number_format_i18n( $trashed )
		);
	}

	$message .= sprintf(
		' <a href="%1$s">%2$s</a>',
		esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids=' . ( isset( $_GET['ids'] ) ? $_GET['ids'] : '' ), 'bulk-media' ) ),
		__( 'Undo' )
	);

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'trashed' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['trashed'] );
}

if ( ! empty( $_GET['untrashed'] ) && absint( $_GET['untrashed'] ) ) {
	$untrashed = absint( $_GET['untrashed'] );

	if ( 1 === $untrashed ) {
		$message = __( 'Media file restored from the Trash.' );
	} else {
		$message = sprintf(
			/* translators: %s: Number of media files. */
			_n( '%s media file restored from the Trash.', '%s media files restored from the Trash.', $untrashed ),
			number_format_i18n( $untrashed )
		);
	}

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'untrashed' ), $_SERVER['REQUEST_URI'] );
	unset( $_GET['untrashed'] );
}

$messages[1] = __( 'Media file updated.' );
$messages[2] = __( 'Media file permanently deleted.' );
$messages[3] = __( 'Error saving media file.' );
$messages[4] = __( 'Media file moved to the Trash.' ) . sprintf(
	' <a href="%1$s">%2$s</a>',
	esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids=' . ( isset( $_GET['ids'] ) ? $_GET['ids'] : '' ), 'bulk-media' ) ),
	__( 'Undo' )
);
$messages[5] = __( 'Media file restored from the Trash.' );

if ( ! empty( $_GET['message'] ) && isset( $messages[ $_GET['message'] ] ) ) {
	$message = $messages[ $_GET['message'] ];

	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
}

$modes = array( 'grid', 'list' );

if ( isset( $_GET['mode'] ) && in_array( $_GET['mode'], $modes, true ) ) {
	$mode = $_GET['mode'];
	update_user_option( get_current_user_id(), 'media_library_mode', $mode );
} else {
	$mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';
}

if ( 'grid' === $mode ) {
	wp_enqueue_media();
	wp_enqueue_script( 'media-grid' );
	wp_enqueue_script( 'media' );

	// Remove the error parameter added by deprecation of wp-admin/media.php.
	add_filter(
		'removable_query_args',
		function () {
			return array( 'error' );
		},
		10,
		0
	);

	$query_string = $_GET;
	// Let JS handle this.
	unset( $query_string['s'] );
	$query_vars = wp_edit_attachments_query_vars( $query_string );
	$ignore     = array( 'mode', 'post_type', 'post_status', 'posts_per_page' );
	foreach ( $query_vars as $key => $value ) {
		if ( ! $value || in_array( $key, $ignore, true ) ) {
			unset( $query_vars[ $key ] );
		}
	}

	wp_localize_script(
		'media-grid',
		'_wpMediaGridSettings',
		array(
			'adminUrl'  => parse_url( self_admin_url(), PHP_URL_PATH ),
			'queryVars' => (object) $query_vars,
		)
	);

	get_current_screen()->add_help_tab(
		array(
			'id'      => 'overview',
			'title'   => __( 'Overview' ),
			'content' =>
				'<p>' . __( 'All the files you&#8217;ve uploaded are listed in the Media Library, with the most recent uploads listed first.' ) . '</p>' .
				'<p>' . __( 'You can view your media in a simple visual grid or a list with columns. Switch between these views using the icons to the left above the media.' ) . '</p>' .
				'<p>' . __( 'To delete media items, click the Bulk Select button at the top of the screen. Select any items you wish to delete, then click the Delete Selected button. Clicking the Cancel Selection button takes you back to viewing your media.' ) . '</p>',
		)
	);

	get_current_screen()->add_help_tab(
		array(
			'id'      => 'attachment-details',
			'title'   => __( 'Attachment Details' ),
			'content' =>
				'<p>' . __( 'Clicking an item will display an Attachment Details dialog, which allows you to preview media and make quick edits. Any changes you make to the attachment details will be automatically saved.' ) . '</p>' .
				'<p>' . __( 'Use the arrow buttons at the top of the dialog, or the left and right arrow keys on your keyboard, to navigate between media items quickly.' ) . '</p>' .
				'<p>' . __( 'You can also delete individual items and access the extended edit screen from the details dialog.' ) . '</p>',
		)
	);

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
		'<p>' . __( '<a href="https://wordpress.org/documentation/article/media-library-screen/">Documentation on Media Library</a>' ) . '</p>' .
		'<p>' . __( '<a href="https://wordpress.org/support/forums/">Support forums</a>' ) . '</p>'
	);

	// Used in the HTML title tag.
	$title       = __( 'Media Library' );
	$parent_file = 'upload.php';

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap" id="wp-media-grid" data-search="<?php _admin_search_query(); ?>">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>

		<?php
		if ( current_user_can( 'upload_files' ) ) {
			?>
			<a href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>" class="page-title-action aria-button-if-js"><?php echo esc_html__( 'Add New Media File' ); ?></a>
			<?php
		}
		?>

		<hr class="wp-header-end">

		<?php
		if ( ! empty( $message ) ) {
			wp_admin_notice(
				$message,
				array(
					'id'                 => 'message',
					'additional_classes' => array( 'updated' ),
					'dismissible'        => true,
				)
			);
		}

		$js_required_message = sprintf(
			/* translators: %s: List view URL. */
			__( 'The grid view for the Media Library requires JavaScript. <a href="%s">Switch to the list view</a>.' ),
			'upload.php?mode=list'
		);
		wp_admin_notice(
			$js_required_message,
			array(
				'additional_classes' => array( 'error', 'hide-if-js' ),
			)
		);
		?>
	</div>
	<?php
	require_once ABSPATH . 'wp-admin/admin-footer.php';
	exit;
}

$wp_list_table = _get_list_table( 'WP_Media_List_Table' );
$pagenum       = $wp_list_table->get_pagenum();

// Handle bulk actions.
$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer( 'bulk-media' );

	$post_ids = array();

	if ( 'delete_all' === $doaction ) {
		$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type='attachment' AND post_status = 'trash'" );
		$doaction = 'delete';
	} elseif ( isset( $_REQUEST['media'] ) ) {
		$post_ids = $_REQUEST['media'];
	} elseif ( isset( $_REQUEST['ids'] ) ) {
		$post_ids = explode( ',', $_REQUEST['ids'] );
	}
	$post_ids = array_map( 'intval', (array) $post_ids );

	$location = 'upload.php';
	$referer  = wp_get_referer();
	if ( $referer ) {
		if ( str_contains( $referer, 'upload.php' ) ) {
			$location = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ), $referer );
		}
	}

	switch ( $doaction ) {
		case 'detach':
			wp_media_attach_action( $_REQUEST['parent_post_id'], 'detach' );
			break;

		case 'attach':
			wp_media_attach_action( $_REQUEST['found_post_id'] );
			break;

		case 'trash':
			if ( empty( $post_ids ) ) {
				break;
			}
			foreach ( $post_ids as $post_id ) {
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					wp_die( __( 'Sorry, you are not allowed to move this item to the Trash.' ) );
				}

				if ( ! wp_trash_post( $post_id ) ) {
					wp_die( __( 'Error in moving the item to Trash.' ) );
				}
			}
			$location = add_query_arg(
				array(
					'trashed' => count( $post_ids ),
					'ids'     => implode( ',', $post_ids ),
				),
				$location
			);
			break;
		case 'untrash':
			if ( empty( $post_ids ) ) {
				break;
			}
			foreach ( $post_ids as $post_id ) {
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					wp_die( __( 'Sorry, you are not allowed to restore this item from the Trash.' ) );
				}

				if ( ! wp_untrash_post( $post_id ) ) {
					wp_die( __( 'Error in restoring the item from Trash.' ) );
				}
			}
			$location = add_query_arg( 'untrashed', count( $post_ids ), $location );
			break;
		case 'delete':
			if ( empty( $post_ids ) ) {
				break;
			}
			foreach ( $post_ids as $post_id_del ) {
				if ( ! current_user_can( 'delete_post', $post_id_del ) ) {
					wp_die( __( 'Sorry, you are not allowed to delete this item.' ) );
				}

				if ( ! wp_delete_attachment( $post_id_del ) ) {
					wp_die( __( 'Error in deleting the attachment.' ) );
				}
			}
			$location = add_query_arg( 'deleted', count( $post_ids ), $location );
			break;
		default:
			$screen = get_current_screen()->id;

			/** This action is documented in wp-admin/edit.php */
			$location = apply_filters( "handle_bulk_actions-{$screen}", $location, $doaction, $post_ids ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	wp_redirect( $location );
	exit;
} elseif ( ! empty( $_GET['_wp_http_referer'] ) ) {
	wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	exit;
}

$wp_list_table->prepare_items();

// Used in the HTML title tag.
$title       = __( 'Media Library' );
$parent_file = 'upload.php';

wp_enqueue_script( 'media' );

add_screen_option( 'per_page' );

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
				'<p>' . __( 'All the files you&#8217;ve uploaded are listed in the Media Library, with the most recent uploads listed first. You can use the Screen Options tab to customize the display of this screen.' ) . '</p>' .
				'<p>' . __( 'You can narrow the list by file type/status or by date using the dropdown menus above the media table.' ) . '</p>' .
				'<p>' . __( 'You can view your media in a simple visual grid or a list with columns. Switch between these views using the icons to the left above the media.' ) . '</p>',
	)
);
get_current_screen()->add_help_tab(
	array(
		'id'      => 'actions-links',
		'title'   => __( 'Available Actions' ),
		'content' =>
				'<p>' . __( 'Hovering over a row reveals action links that allow you to manage media items. You can perform the following actions:' ) . '</p>' .
				'<ul>' .
					'<li>' . __( '<strong>Edit</strong> takes you to a simple screen to edit that individual file&#8217;s metadata. You can also reach that screen by clicking on the media file name or thumbnail.' ) . '</li>' .
					'<li>' . __( '<strong>Delete Permanently</strong> will delete the file from the media library (as well as from any posts to which it is currently attached).' ) . '</li>' .
					'<li>' . __( '<strong>View</strong> will take you to a public display page for that file.' ) . '</li>' .
					'<li>' . __( '<strong>Copy URL</strong> copies the URL for the media file to your clipboard.' ) . '</li>' .
					'<li>' . __( '<strong>Download file</strong> downloads the original media file to your device.' ) . '</li>' .
				'</ul>',
	)
);
get_current_screen()->add_help_tab(
	array(
		'id'      => 'attaching-files',
		'title'   => __( 'Attaching Files' ),
		'content' =>
				'<p>' . __( 'If a media file has not been attached to any content, you will see that in the Uploaded To column, and can click on Attach to launch a small popup that will allow you to search for existing content and attach the file.' ) . '</p>',
	)
);

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://wordpress.org/documentation/article/media-library-screen/">Documentation on Media Library</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/forums/">Support forums</a>' ) . '</p>'
);

get_current_screen()->set_screen_reader_content(
	array(
		'heading_views'      => __( 'Filter media items list' ),
		'heading_pagination' => __( 'Media items list navigation' ),
		'heading_list'       => __( 'Media items list' ),
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>

<?php
if ( current_user_can( 'upload_files' ) ) {
	?>
	<a href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New Media File' ); ?></a>
						<?php
}

if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	echo '<span class="subtitle">';
	printf(
		/* translators: %s: Search query. */
		__( 'Search results for: %s' ),
		'<strong>' . get_search_query() . '</strong>'
	);
	echo '</span>';
}
?>

<hr class="wp-header-end">

<?php
if ( ! empty( $message ) ) {
	wp_admin_notice(
		$message,
		array(
			'id'                 => 'message',
			'additional_classes' => array( 'updated' ),
			'dismissible'        => true,
		)
	);
}
?>

<form id="posts-filter" method="get">

<?php $wp_list_table->views(); ?>

<?php $wp_list_table->display(); ?>

<div id="ajax-response"></div>
<?php find_posts_div(); ?>
</form>
</div>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
