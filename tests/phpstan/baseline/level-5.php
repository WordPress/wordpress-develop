<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$key of function remove_query_arg expects array\\<string\\>\\|string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-activate.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$subject of function str_replace expects array\\<string\\>\\|string, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_edit_post_link expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post_status expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_the_title expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/customize.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$position of function wp_comment_reply expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$screen of function do_meta_boxes expects string\\|WP_Screen, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-form-advanced.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$screen of function do_meta_boxes expects string\\|WP_Screen, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-form-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$screen of function do_meta_boxes expects string\\|WP_Screen, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-link-form.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$name of function submit_button expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-tag-form.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_edit_post_link expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post_type expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'print_emoji_styles\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/admin-filters.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$attachment of function wp_get_attachment_id3_keys expects WP_Post, stdClass given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function _wp_ajax_delete_comment_response expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 111,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_diff expects an array of values castable to string, array\\<int, array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$compare_from of function wp_get_revision_ui_diff expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$compare_to of function wp_get_revision_ui_diff expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$allowed_html of function wp_kses expects array\\<array\\>\\|string, array\\<string, array\\<string, bool\\>\\|true\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-automatic-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-bulk-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_js expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-bulk-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function submit_button expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, \\(float\\|int\\) given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function submit_button expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$language_updates of method Language_Pack_Upgrader\\:\\:bulk_upgrade\\(\\) expects array\\<object\\>, list\\<string\\>\\|string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function md5 expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_comment_meta expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function post_password_required expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$post of function get_comment_class expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_chdir expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_chmod expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_close expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_delete expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_fget expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_fput expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_mdtm expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_mkdir expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_nlist expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_pwd expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_rawlist expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_rename expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_rmdir expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_site expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_size expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ftp of function ftp_systype expects FTP\\\\Connection, resource given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$minute of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$month of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#5 \\$day of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function uasort expects callable\\(WP_Theme, WP_Theme\\)\\: int, array\\{\\$this\\(WP_MS_Themes_List_Table\\), \'_order_callback\'\\} given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-ms-themes-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-ms-users-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$number of function _nx expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugins-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<stdClass\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$keys of function array_fill_keys expects an array of values castable to string, array\\<stdClass\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-requests-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-requests-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$version of function get_core_checksums expects string, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health-auto-updates.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$bytes of function size_format expects int\\|string, float\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$allowed_html of function wp_kses expects array\\<array\\>\\|string, array\\<string, array\\<string, bool\\>\\|true\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$allowed_html of function wp_kses expects array\\<array\\>\\|string, array\\<string, true\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$args of function WP_Filesystem expects array\\|false, true given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function _draft_or_post_title expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_the_permalink expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function post_password_required expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$name of function submit_button expects string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_filter expects callable\\(\\)\\: mixed, \'wxr_filter_postmeta\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/export.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function md5 expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$image of function is_gd_image expects GdImage\\|resource\\|false, WP_Image_Editor given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$width of function wp_imagecreatetruecolor expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$height of function wp_imagecreatetruecolor expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#5 \\$src_x of function imagecopy expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#6 \\$src_y of function imagecopy expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#7 \\$src_width of function imagecopy expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#8 \\$src_height of function imagecopy expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array \\(non\\-empty\\-list\\<string\\>\\) of array_values is already a list, call has no effect\\.$#',
	'identifier' => 'arrayValues.list',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function wp_delete_attachment expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/import.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$number of function number_format_i18n expects float, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function get_media_items expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$result of function wp_parse_str expects array, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ms.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$value of function update_blog_status expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ms.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_intersect expects an array of values castable to string, array\\<WP_Term\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$tags of function wp_generate_tag_cloud expects array\\<WP_Term\\>, array\\<object\\{link\\: string, name\\: mixed, slug\\: mixed, id\\: string, count\\: mixed\\}&stdClass\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$name of function submit_button expects string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$number of function _nx expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$allowed_html of function wp_kses expects array\\<array\\>\\|string, array\\<string, array\\<string, bool\\>\\|true\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$fallback_title of function sanitize_title expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$request_id of function wp_send_user_request expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/privacy-tools.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$request_id of function wp_send_user_request expects string, int\\|WP_Error given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/privacy-tools.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$user_id of function get_the_author_meta expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function md5 expects string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/schema.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$times of function str_repeat expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$title of function add_meta_box expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$callback of function add_meta_box expects callable\\(\\)\\: mixed, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$update of method Language_Pack_Upgrader\\:\\:upgrade\\(\\) expects string\\|false, stdClass given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$timestamp of function wp_schedule_event expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$deprecated of function add_option expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user of function wp_get_user_contact_methods expects WP_User\\|null, stdClass given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$is_public of function wp_install expects bool, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$menu_data of function wp_save_nav_menu_items expects array\\<array\\>, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function can_edit_network expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-info.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-info.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function can_edit_network expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function can_edit_network expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function can_edit_network expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-users.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-users.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/network/sites.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/options-discussion.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/options-general.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<0, 6\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/options-general.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_diff expects an array of values castable to string, array\\<int, array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function array_filter expects \\(callable\\(mixed\\)\\: bool\\)\\|null, \'validate_file\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/user-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/users.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$userid of function count_user_posts expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/author-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'print_emoji_styles\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$separator of function explode expects non\\-empty\\-string, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$block of function filter_block_kses expects WP_Block_Parser_Block, array given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$block_context of function filter_block_kses_value expects array\\|null, WP_Block_Parser_Block given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$year of function get_day_link expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$year of function get_month_link expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_intersect expects an array of values castable to string, array\\<string\\|WP_Post_Type\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function preg_replace_callback expects callable\\(array\\<string\\>\\)\\: string, \'lowercase_octets\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$month of function get_day_link expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$month of function get_month_link expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$day of function get_day_link expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$blog_id of function get_home_url expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'wp_admin_bar_header\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$name of class WP_Block_Parser_Block constructor expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$child_id of method WP_Comment\\:\\:get_child\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ids of function _prime_post_caches expects array\\<int\\>, list\\<string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ajax_message of method WP_Customize_Manager\\:\\:wp_die\\(\\) expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function array_diff expects an array of values castable to string, array\\<stdClass\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$month of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$day of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$year of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_html expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$parent_query of method WP_Date_Query\\:\\:get_sql_for_clause\\(\\) expects array, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$timestamp of function gmdate expects int\\|null, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_Date_Query\\:\\:build_value\\(\\) expects array\\|string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_Date_Query\\:\\:build_value\\(\\) expects array\\|string, int\\|null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$value of static method WP_Duotone\\:\\:colord_parse_hue\\(\\) expects float, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$priority of function _wp_filter_build_unique_id expects int, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-hook.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$value of function curl_setopt expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-curl.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$enable of function stream_set_blocking expects bool, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-streams.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$limit of static method Imagick\\:\\:setResourceLimit\\(\\) expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method Imagick\\:\\:setOption\\(\\) expects string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method Imagick\\:\\:setOption\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method Imagick\\:\\:setOption\\(\\) expects string, true given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_intersect expects an array of values castable to string, array\\<string\\|WP_Taxonomy\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$new_blog_id of function switch_to_blog expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, array given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$metadata of method WP_Theme_JSON\\:\\:get_feature_declarations_for_node\\(\\) expects object, array given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$styles of static method WP_Theme_JSON\\:\\:compute_style_properties\\(\\) expects array, object given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$data of method WP_Theme\\:\\:cache_add\\(\\) expects array\\|string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$translate of method WP_Theme\\:\\:display\\(\\) expects bool, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$translate of method WP_Theme\\:\\:markup_header\\(\\) expects string, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$expire of function wp_cache_add expects int, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function strtoupper expects string, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-token-map.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$level of method WP_User\\:\\:translate_level_to_cap\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$number of method WP_Widget\\:\\:_set\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_the_title expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$term_id of method wp_xmlrpc_server\\:\\:get_term_custom_fields\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_intersect expects an array of values castable to string, array\\<string\\|WP_Post_Type\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$error_level of function error_reporting expects int\\|null, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_page_of_comment expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_permalink expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function post_password_required expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment of function get_comment_link expects int\\|WP_Comment\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function add_comment_meta expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function delete_comment_meta expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_comment_meta expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_comment_text expects int\\|WP_Comment, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_page_of_comment expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$content of function pingback expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$ids of function clean_comment_cache expects array\\|int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function wp_update_comment_count expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$object_ids of function update_meta_cache expects array\\<int\\>\\|string, list\\<string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$gmt_time of function spawn_cron expects int, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$container_context of method WP_Customize_Partial\\:\\:render\\(\\) expects array, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-selective-refresh.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'print_embed_styles\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/default-filters.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'print_emoji_styles\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/default-filters.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function add_action expects callable\\(\\)\\: mixed, \'the_block_template…\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/default-filters.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$response of method WP_REST_Server\\:\\:response_to_data\\(\\) expects WP_REST_Response, WP_HTTP_Response given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function get_post_comments_feed_link expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/feed-atom-comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function get_post_comments_feed_link expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed-rss2.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_the_guid expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$number of function zeroise expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function preg_replace_callback expects callable\\(array\\<string\\>\\)\\: string, \'_links_add_base\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function preg_replace_callback expects callable\\(array\\<string\\>\\)\\: string, \'_links_add_target\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$options of function debug_backtrace expects int, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$prefix of function uniqid expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$fallback_url of function wp_validate_redirect expects string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$number of function _n expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$month of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#5 \\$day of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#6 \\$year of function mktime expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, float given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$replace of function str_replace expects array\\<string\\>\\|string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$replace of function str_replace expects array\\<string\\>\\|string, int\\<min, 0\\>\\|int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$url of function wp_admin_css_color expects string, false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$blog_id of function get_admin_url expects int\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$replace of function str_replace expects array\\<string\\>\\|string, array\\<int, mixed\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function md5 expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$subject of function str_replace expects array\\<string\\>\\|string, array\\<string, int\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int\\<1, 9\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_html expects string, int\\<1, 9\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function array_unique expects an array of values castable to string, array\\<string\\|WP_Taxonomy\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#5 \\$text of function wp_get_attachment_link expects string\\|false, bool given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$value of function update_blog_status expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of static method WP_Network\\:\\:get_instance\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-load.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$month of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$day of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$object_id of function delete_metadata expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$year of function wp_checkdate expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array \\(array\\<int\\<0, max\\>, int\\<min, \\-1\\>\\|int\\<1, max\\>\\>\\) to function array_filter does not contain falsy values, the array will always stay the same\\.$#',
	'identifier' => 'arrayFilter.same',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post_id of function wp_delete_post expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function add_network_option expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function delete_network_option expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function get_network_option expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function update_network_option expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$network_id of function wp_prime_network_option_caches expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of function setcookie expects string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$engine of class Text_Diff constructor expects string, list\\<string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$message of function wp_die expects string\\|WP_Error, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_permalink expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user of function user_can expects int\\|WP_User, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$user_id of function get_userdata expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$url of function user_trailingslashit expects string, int\\<min, 0\\>\\|int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$fallback of function sanitize_html_class expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$replace of function str_replace expects array\\<string\\>\\|string, int\\<1, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$user_id of function get_the_author_meta expects int\\|false, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$keys of function array_fill_keys expects an array of values castable to string, array\\<stdClass\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function strlen expects string, int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<string\\|WP_Post_Type\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$arrays of function array_diff expects an array of values castable to string, array\\<stdClass\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$object_id of function delete_metadata expects int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$data_object of method WP_REST_Controller\\:\\:update_additional_fields_for_object\\(\\) expects object, array given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-application-passwords-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function get_comment_type expects int\\|WP_Comment, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function wp_delete_comment expects int\\|WP_Comment, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_id of function wp_trash_comment expects int\\|WP_Comment, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$object_id of method WP_REST_Meta_Fields\\:\\:get_value\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_post expects int\\|WP_Post\\|null, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$comment_id of method WP_REST_Comments_Controller\\:\\:handle_status_param\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$object_id of method WP_REST_Meta_Fields\\:\\:update_value\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, array\\|int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-collections-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-collections-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-revisions-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-search-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$args of function get_taxonomies expects array, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-taxonomies-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$id of function get_block_template expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-templates-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$id of method WP_REST_Templates_Controller\\:\\:prepare_links\\(\\) expects int, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-templates-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-themes-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int\\<0, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-themes-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$value of method WP_HTTP_Response\\:\\:header\\(\\) expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function array_diff expects an array of values castable to string, list\\<string\\|WP_Post_Type\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/search/class-wp-rest-post-search-handler.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function usort contains unresolvable type\\.$#',
	'identifier' => 'argument.unresolvableType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$callback of function usort contains unresolvable type\\.$#',
	'identifier' => 'argument.unresolvableType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$src of method WP_Dependencies\\:\\:add\\(\\) expects string\\|false, true given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$terms of function wp_update_term_count expects array\\|int, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function implode expects array\\<string\\>, array\\<array\\|string\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$fallback_title of function sanitize_title expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$fallback_title of function sanitize_title expects string, int\\|WP_Error given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$taxonomy of function wp_update_term_count expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$replacement of function _deprecated_file expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$replacement of function _deprecated_file expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/footer.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$replacement of function _deprecated_file expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/header.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$replacement of function _deprecated_file expects string, null given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/sidebar.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function strlen expects string, int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-templates.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function mb_strlen expects string, int\\<2, max\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$control_callback of function wp_register_widget_control expects callable\\(\\)\\: mixed, \'\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$output_callback of function wp_register_sidebar_widget expects callable\\(\\)\\: mixed, \'\' given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_attr expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-nav-menu-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$post of function get_the_title expects int\\|WP_Post, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-recent-comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$request_id of function wp_validate_user_request_key expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$text of function esc_html expects string, int given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-mail.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
