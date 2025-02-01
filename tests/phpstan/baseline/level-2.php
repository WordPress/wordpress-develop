<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/_index.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-tags.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:display_rows\\(\\) invoked with 2 parameters, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 2 parameters, 1 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 3 parameters, 1 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$admin_header_callback \\(\'\'\\) of method Custom_Background\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$admin_image_div_callback \\(\'\'\\) of method Custom_Background\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$admin_image_div_callback \\(\'\'\\) of method Custom_Image_Header\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:get_name_for_update\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$language_update\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_plugin_data\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_active\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_info\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_theme_data\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$api\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$theme_info\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$current on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$response on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$author\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$parent_theme\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$version\\.$#',
	'identifier' => 'property.notFound',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Theme\\:\\:\\$stylesheet\\.$#',
	'identifier' => 'property.private',
	'count' => 20,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Theme\\:\\:\\$template\\.$#',
	'identifier' => 'property.private',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\+" between non\\-empty\\-string and non\\-empty\\-string results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-base.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_FTPext\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_ftpsockets\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpsockets.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_SSH2\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\." between \'http\\://\' and list\\<string\\>\\|null results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\<\\=" between \\(array\\|float\\|int\\) and 0 results in an error\\.$#',
	'identifier' => 'smallerOrEqual.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between array\\|float\\|int and 0 results in an error\\.$#',
	'identifier' => 'greater.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$_wp_attachment_image_alt on array\\|WP_Post\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$menu_order on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between 1 and array\\<int\\|WP_Comment\\>\\|int results in an error\\.$#',
	'identifier' => 'greater.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:display\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$privacy_policy_page\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$front_or_home on array\\|WP_Post\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\<int\\|WP_Post\\>\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$author on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$downloaded on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$homepage on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$requires on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$sections on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$slug on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$tested on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\*" between string and 1\\.0E\\-5 results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_comments_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_dashboard_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_links_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_management_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_media_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_menu_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_options_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_pages_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_plugins_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_posts_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_theme_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_users_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#6 \\$callback \\(\'\'\\) of function add_submenu_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$meta_key on object\\|true\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_id on object\\|true\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$posts on class\\-string\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$post_id \\(string\\) of function redirect_post\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$post_id \\(string\\) of function wp_create_categories\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Term\\:\\:\\$truncated_name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to method html\\(\\) on an unknown class WP_Press_This_Plugin\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/profile.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$comment_shortcuts on WP_User\\|false\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/user-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post_Type\\:\\:\\$capabilities\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$post_id \\(false\\) of function get_the_category\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#3 \\$post_id \\(false\\) of function get_the_category_list\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#3 \\$post_id \\(false\\) of function the_category\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_category_by_path\\(\\) should return array\\|WP_Error\\|WP_Term\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$current\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:group_by_parent_id\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:to_blocks\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "/" between string and 255 results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param for parameter \\$type contains unresolvable type\\.$#',
	'identifier' => 'parameter.unresolvableType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$width \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$height \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$meta_query \\(false\\) of method WP_Meta_Query\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-meta-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_classic_menu_fallback\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_default_fallback\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_default_fallback_blocks\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_fallback_classic_menu\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_created_nav_menu\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_published_navigation\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_at_primary_location\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_with_primary_slug\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_code\\(\\) on object\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var above assignment does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 9,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$object_id on array\\|WP_Error\\|WP_Term\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_style_variation_files\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_styles_registry\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:recursively_iterate_json\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:remove_json_comments\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:style_variation_has_scope\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:compute_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:get_block_nodes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:merge_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:remove_indirect_properties\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:resolve_custom_css_format\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:unwrap_shared_block_style_variations\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:update_separator_declarations\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$blog_id \\(string\\) of method WP_User\\:\\:for_blog\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$site_id \\(string\\) of method WP_User\\:\\:for_site\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$site_id \\(string\\) of method WP_User\\:\\:init\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#3 \\$site_id \\(string\\) of method WP_User\\:\\:__construct\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$zero \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$one \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#3 \\$more \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\+" between array\\|int\\<min, \\-1\\>\\|int\\<1, max\\> and 1 results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\=\\=" between 0 and array\\|int results in an error\\.$#',
	'identifier' => 'equal.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on string\\|WP_Customize_Setting\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$db_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:convert_font_face_properties\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:maybe_parse_name_from_comma_separated_list\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:parse_settings\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_kebab_case\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_theme_file_uri\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$key$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$url$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$value$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$link_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$post \\(false\\) of function _get_page_link\\(\\) is incompatible with type int\\|WP_Post\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$post \\(false\\) of function get_page_link\\(\\) is incompatible with type int\\|WP_Post\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$post_id \\(string\\) of function post_comments_feed_link\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$force \\(string\\) of function force_ssl_content\\(\\) is incompatible with type bool\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#3 \\$deprecated \\(\'\'\\) of function unregister_setting\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method has_param\\(\\) on array\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/class-wp-rest-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(WP_REST_Controller \\$this             The current instance of the controller\\.\\)\\: Unexpected token "\\$this", expected variable at offset 401 on line 9$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$auto_add on WP_Term\\|false\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$language_packs on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_excerpt on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$object_id on array\\|int\\|string\\|WP_Term\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$template_name on array\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between int\\|string\\|WP_Term and 0 results in an error\\.$#',
	'identifier' => 'greater.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_list_users\\(\\) should return string\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/xmlrpc.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
