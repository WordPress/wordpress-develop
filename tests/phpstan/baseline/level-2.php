<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/_index.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-tags.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	// identifier: property.protected
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: property.protected
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	// identifier: property.protected
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_List_Table\\:\\:display_rows\\(\\) invoked with 2 parameters, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 2 parameters, 1 required\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 3 parameters, 1 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$new_bundled\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-core-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$packages\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-core-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$partial_version\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-core-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$version\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-core-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$admin_header_callback \\(\'\'\\) of method Custom_Background\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$admin_image_div_callback \\(\'\'\\) of method Custom_Background\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$admin_image_div_callback \\(\'\'\\) of method Custom_Image_Header\\:\\:__construct\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:get_name_for_update\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader-skin.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$language_update\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_plugin_data\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader-skin.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_active\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_info\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_theme_data\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader-skin.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$api\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$theme_info\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$current on array\\|object\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$response on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$author\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$parent_theme\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$version\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Theme\\:\\:\\$stylesheet\\.$#',
	'count' => 20,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Theme\\:\\:\\$template\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\+" between non\\-empty\\-string and non\\-empty\\-string results in an error\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-base.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_FTPext\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_ftpsockets\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpsockets.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_SSH2\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\." between \'http\\://\' and array\\<int, string\\>\\|null results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$slug\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$upgrade\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: smallerOrEqual.invalid
	'message' => '#^Comparison operation "\\<\\=" between \\(array\\|float\\|int\\) and 0 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: greater.invalid
	'message' => '#^Comparison operation "\\>" between array\\|float\\|int and 0 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$angle\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$axis\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$sel\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$type\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$_wp_attachment_image_alt on array\\|WP_Post\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$menu_order on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$link_url\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: greater.invalid
	'message' => '#^Comparison operation "\\>" between 1 and array\\<int\\|WP_Comment\\>\\|int results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_List_Table\\:\\:display\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$privacy_policy_page\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$_default_query\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "/" between string\\|WP_Error and 50 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$front_or_home on array\\|WP_Post\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\<int\\|WP_Post\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$num_ratings\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$slug\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$author on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$downloaded on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$homepage on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$requires on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$sections on array\\|object\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$slug on array\\|object\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$tested on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\*" between string and 1\\.0E\\-5 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_comments_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_dashboard_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_links_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_management_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_media_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_menu_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_options_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_pages_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_plugins_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_posts_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_theme_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#5 \\$callback \\(\'\'\\) of function add_users_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#6 \\$callback \\(\'\'\\) of function add_submenu_page\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$meta_key on object\\|true\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_id on object\\|true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$posts on class\\-string\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$post_id \\(string\\) of function redirect_post\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$post_id \\(string\\) of function wp_create_categories\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$current\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$new_version\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 10,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$user_firstname\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$user_icq\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$user_lastname\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$user_login\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$user_nickname\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\+" between int\\<1, max\\> and string results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_Dependencies\\:\\:get_etag\\(\\) invoked with 2 parameters, 1 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/load-styles.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Term\\:\\:\\$truncated_name\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	// identifier: class.notFound
	'message' => '#^Call to method html\\(\\) on an unknown class WP_Press_This_Plugin\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/profile.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$current\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update-core.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$comment_shortcuts on WP_User\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/user-edit.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-cron.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$term_id\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$taxonomy\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post_Type\\:\\:\\$capabilities\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$post_id \\(false\\) of function get_the_category\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#3 \\$post_id \\(false\\) of function get_the_category_list\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#3 \\$post_id \\(false\\) of function the_category\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Function get_category_by_path\\(\\) should return array\\|WP_Error\\|WP_Term\\|null but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$current\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$id\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	// identifier: arguments.count
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:get_tag\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:group_by_parent_id\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:to_blocks\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\+" between string and 1 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "/" between string and 255 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: parameter.unresolvableType
	'message' => '#^PHPDoc tag @param for parameter \\$type contains unresolvable type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$width \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$height \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$meta_query \\(false\\) of method WP_Meta_Query\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-meta-query.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_classic_menu_fallback\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_default_fallback\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_default_fallback_blocks\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_fallback_classic_menu\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_created_nav_menu\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_published_navigation\\(\\) through static\\:\\:\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_at_primary_location\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_with_primary_slug\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method get_error_code\\(\\) on object\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var above assignment does not specify variable name\\.$#',
	'count' => 9,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$object_id on array\\|WP_Error\\|WP_Term\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_style_variation_files\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_styles_registry\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:recursively_iterate_json\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:remove_json_comments\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:style_variation_has_scope\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:compute_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:get_block_nodes\\(\\) through static\\:\\:\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:merge_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:remove_indirect_properties\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:resolve_custom_css_format\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:unwrap_shared_block_style_variations\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:update_separator_declarations\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$blog_id \\(string\\) of method WP_User\\:\\:for_blog\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$site_id \\(string\\) of method WP_User\\:\\:for_site\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$site_id \\(string\\) of method WP_User\\:\\:init\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#3 \\$site_id \\(string\\) of method WP_User\\:\\:__construct\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$comments_by_type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$max_num_comment_pages\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$zero \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$one \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#3 \\$more \\(false\\) of function get_comments_number_text\\(\\) is incompatible with type string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\+" between array\\|int and 1 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: equal.invalid
	'message' => '#^Comparison operation "\\=\\=" between 0 and array\\|int results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Function array_key_first\\(\\) should return int\\|string\\|null but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$term_id on string\\|WP_Customize_Setting\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-control.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$db_id\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:convert_font_face_properties\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:maybe_parse_name_from_comma_separated_list\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:parse_settings\\(\\) through static\\:\\:\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_kebab_case\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	// identifier: staticClassAccess.privateMethod
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_theme_file_uri\\(\\) through static\\:\\:\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assignOp.invalid
	'message' => '#^Binary operation "\\+\\=" between string and 7 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_meridiem\\(\\)\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_month\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_month_abbrev\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_weekday\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_weekday_abbrev\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: parameter.notFound
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$key$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: parameter.notFound
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$url$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: parameter.notFound
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$value$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\-" between string and int results in an error\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: phpDoc.parseError
	'message' => '#^PHPDoc tag @var has invalid value \\(\\[string, array\\]\\|null\\)\\: Unexpected token "\\]", expected \'\\(\' at offset 202$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-processor-state.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$link_id on array\\|object\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$post \\(false\\) of function _get_page_link\\(\\) is incompatible with type int\\|WP_Post\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$post \\(false\\) of function get_page_link\\(\\) is incompatible with type int\\|WP_Post\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#2 \\$post_id \\(string\\) of function post_comments_feed_link\\(\\) is incompatible with type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$path\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$title\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#1 \\$force \\(string\\) of function force_ssl_content\\(\\) is incompatible with type bool\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ancestors\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$object_id\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$term_id\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 13,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$_invalid\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$attr_title\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$classes\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$db_id\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$description\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$menu_item_parent\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$object\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$object_id\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$parent\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$post_content\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$post_excerpt\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$post_parent\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$post_title\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$target\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$term_id\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$title\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$type\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$type_label\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$url\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$xfn\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: greater.invalid
	'message' => '#^Comparison operation "\\>" between 0\\|array\\<int, string\\>\\|string\\|null and int results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: parameter.defaultValue
	'message' => '#^Default value of the parameter \\#3 \\$deprecated \\(\'\'\\) of function unregister_setting\\(\\) is incompatible with type callable\\(\\)\\: mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: assignOp.invalid
	'message' => '#^Binary operation "\\+\\=" between string and 3600 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-formats.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$slug\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post-formats.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$slug\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$taxonomy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method has_param\\(\\) on array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/class-wp-rest-server.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$type\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$auto_add on WP_Term\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$language_packs on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	// identifier: method.nonObject
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Method WP_REST_Posts_Controller\\:\\:handle_terms\\(\\) should return WP_Error\\|null but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.private
	'message' => '#^Access to private property WP_User_Query\\:\\:\\$results\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_excerpt on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method object\\:\\:get_data\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "/" between string\\|WP_Error and int results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps/providers/class-wp-sitemaps-taxonomies.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$taxonomy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$template_name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$term_id\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$object_id on array\\|int\\|string\\|WP_Term\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$template_name on array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.nonObject
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: greater.invalid
	'message' => '#^Comparison operation "\\>" between int\\|string\\|WP_Term and 0 results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: property.notFound
	'message' => '#^Access to an undefined property object\\:\\:\\$ID\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: binaryOp.invalid
	'message' => '#^Binary operation "\\-" between string and int\\<1, max\\> results in an error\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: return.missing
	'message' => '#^Function wp_list_users\\(\\) should return string\\|null but return statement is missing\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: varTag.noVariable
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/xmlrpc.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
