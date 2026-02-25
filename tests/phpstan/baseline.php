<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/about.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/credits.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/edit-tags.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method inline_edit\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/erase-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method embed_scripts\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method process_bulk_action\\(\\) on WP_List_Table\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/export-personal-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to protected property WP_List_Table\\:\\:\\$screen\\.$#',
	'identifier' => 'property.protected',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_email\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_url\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$user_id\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:display_rows\\(\\) invoked with 2 parameters, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 2 parameters, 1 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:single_row\\(\\) invoked with 3 parameters, 1 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:get_name_for_update\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-language-pack-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$language_update\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_plugin_data\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:plugin_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_active\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$plugin_info\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-plugin-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader\\:\\:\\$new_theme_data\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-installer-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method WP_Upgrader\\:\\:theme_info\\(\\)\\.$#',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$api\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Upgrader_Skin\\:\\:\\$theme_info\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Upgrader_Skin\\:\\:before\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-theme-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$menu_item_parent\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$object_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$target\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$xfn\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_status \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$current on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$response on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Automatic_Updater\\:\\:update\\(\\) should return WP_Error\\|null but returns false\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$author\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$parent_theme\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$version\\.$#',
	'identifier' => 'property.notFound',
	'count' => 5,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Theme\\:\\:\\$stylesheet\\.$#',
	'identifier' => 'property.private',
	'count' => 20,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Theme\\:\\:\\$template\\.$#',
	'identifier' => 'property.private',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with bool will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'new_version\' on bool\\.$#',
	'identifier' => 'offsetAccess.nonOffsetAccessible',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between Imagick and Imagick will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\+" between non\\-empty\\-string and non\\-empty\\-string results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-base.php',
];
$ignoreErrors[] = [
	'message' => '#^Constructor of class WP_Filesystem_Direct has an unused parameter \\$arg\\.$#',
	'identifier' => 'constructor.unusedParameter',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_Direct\\:\\:group\\(\\) should return string\\|false but returns int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_Direct\\:\\:owner\\(\\) should return string\\|false but returns int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_FTPext\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Filesystem_FTPext\\:\\:\\$link has unknown class FTP\\\\Connection as its type\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_ftpsockets\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ftpsockets.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$opt \\(string\\) of method WP_Filesystem_SSH2\\:\\:__construct\\(\\) is incompatible with type array\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:group\\(\\) should return string\\|false but returns int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:owner\\(\\) should return string\\|false but returns int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Filesystem_SSH2\\:\\:\\$link \\(resource\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset mixed on array\\{\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Static method WP_Internal_Pointers\\:\\:print_js\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-plugin-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_status \\(bool\\) of method WP_Post_Comments_List_Table\\:\\:get_per_page\\(\\) should be compatible with parameter \\$comment_status \\(string\\) of method WP_Comments_List_Table\\:\\:get_per_page\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-post-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Screen\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 8,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$info on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'preview\' does not exist on array\\{activate\\: non\\-falsy\\-string\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-themes-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'ParagonIE_Sodium…\' and \'runtime_speed_test\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _crop_image_resource\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _flip_image_resource\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _rotate_image_resource\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$img of function _crop_image_resource\\(\\) has invalid type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$img of function _flip_image_resource\\(\\) has invalid type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$img of function _rotate_image_resource\\(\\) has invalid type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_read_data\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'iptcparse\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$_wp_attachment_image_alt on array\\|WP_Post\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Function load_image_to_edit\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$menu_order on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'created_timestamp\' on array\\{\\}\\|array\\{lossless\\?\\: mixed, bitrate\\?\\: int, bitrate_mode\\?\\: mixed, filesize\\?\\: int, mime_type\\?\\: mixed, length\\?\\: int, length_formatted\\?\\: mixed, width\\?\\: int, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Part \\$form_fields\\[\'_final\'\\] \\(non\\-empty\\-array\\<string, mixed\\>\\) of encapsed string cannot be cast to string\\.$#',
	'identifier' => 'encapsedStringPart.nonString',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_List_Table\\:\\:display\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$privacy_policy_page\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$front_or_home on array\\|WP_Post\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\<int\\|WP_Post\\>\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_get_nav_menu_to_edit\\(\\) should return string\\|WP_Error\\|null but returns WP_Term\\|false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$author on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$downloaded on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$homepage on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$requires on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$sections on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 5,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$slug on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$tested on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with float\\|int\\|numeric\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$meta_key on object\\|true\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_id on object\\|true\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$posts on class\\-string\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Taxonomy\\:\\:\\$meta_box_sanitize_cb \\(callable\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/schema.php',
];
$ignoreErrors[] = [
	'message' => '#^Function convert_to_screen\\(\\) should return WP_Screen but returns object\\{id\\: string, base\\: string\\}&stdClass\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_preferred_from_update_core\\(\\) never returns array so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/menu-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/my-sites.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Term\\:\\:\\$truncated_name\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \\(float\\|int\\) on array\\<mixed\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'update\\-selected\' and mixed~\\(\'activate\'\\|\'activate\\-selected\'\\|\'deactivate\'\\|\'deactivate\\-selected\'\\|\'delete\\-selected\'\\|\'disable\\-auto\\-update\'\\|\'disable\\-auto\\-update\\-selected\'\\|\'enable\\-auto\\-update\'\\|\'enable\\-auto\\-update\\-selected\'\\|\'error_scrape\'\\|\'resume\'\\|\'update\\-selected\'\\) will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to method html\\(\\) on an unknown class WP_Press_This_Plugin\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/press-this.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/profile.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$parent_file in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Theme\\:\\:\\$version\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/update-core.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'new_version\' on bool\\.$#',
	'identifier' => 'offsetAccess.nonOffsetAccessible',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/update-core.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$name on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$version on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-admin/update.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$comment_shortcuts on WP_User\\|false\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/user-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(int The height and width avatar dimension in pixels\\. Default 60\\.\\)\\: Unexpected token "The", expected variable at offset 135 on line 6$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/author.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/author.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/author.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/category.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/category.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(int The height and width avatar dimensions in pixels\\. Default 65\\.\\)\\: Unexpected token "The", expected variable at offset 121 on line 6$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/content-status.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_HEIGHT not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_WIDTH not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_TEXTCOLOR not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_WIDTH not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/header.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(int The width for the image attachment size in pixels\\. Default 848\\.\\)\\: Unexpected token "The", expected variable at offset 270 on line 8$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/inc/theme-options.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/inc/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/inc/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_WIDTH not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/showcase.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/showcase.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/showcase.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(string The default tag description\\.\\)\\: Unexpected token "The", expected variable at offset 139 on line 6$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyeleven/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/inc/customizer.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfifteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/author.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/author.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/category.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/inc/customizer.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/inc/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/inc/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/taxonomy-post_format.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyfourteen/taxonomy-post_format.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/comments.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/header.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/inc/customizer.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/inc/icon-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/inc/icon-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/inc/template-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentynineteen/template-parts/footer/footer-widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/front-page.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/inc/icon-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentyseventeen_edit_link invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/template-parts/page/content-front-page-panels.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/template-parts/page/content-front-page-panels.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/template-parts/page/content-front-page-panels.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentyseventeen_edit_link invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/template-parts/page/content-front-page.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentyseventeen_edit_link invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyseventeen/template-parts/page/content-page.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/inc/customizer.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentysixteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_HEIGHT not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_WIDTH not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant HEADER_IMAGE_WIDTH not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/header.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/loop-attachment.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/loop-page.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentyten/loop-single.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/author.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/author.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/category.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/taxonomy-post_format.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentythirteen/taxonomy-post_format.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/author.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/author.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/category.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$selective_refresh \\(WP_Customize_Selective_Refresh\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/search.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwelve/tag.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "/" between string and 2 results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentytwenty_get_color_for_area\\(\\) should return string but returns false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentytwenty_generate_css\\(\\) should return string but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/inc/custom-css.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$classes\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/inc/template-tags.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/inc/template-tags.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/singular.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/template-parts/modal-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwenty/templates/template-cover.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/archive.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param has invalid value \\(string The list of classes\\. Default empty string\\.\\)\\: Unexpected token "The", expected variable at offset 116 on line 6$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/inc/menu-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/index.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/search.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-content/themes/twentytwentyone/search.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$prompt of function wp_ai_client_prompt\\(\\) has invalid type Message\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/ai-client.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$prompt of function wp_ai_client_prompt\\(\\) has invalid type MessagePart\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/ai-client.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Type\\:\\:\\$editor_style_handles \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 1 on array\\{list\\<string\\>, list\\<non\\-empty\\-string\\>\\} on left side of \\?\\? always exists and is not nullable\\.$#',
	'identifier' => 'nullCoalesce.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-supports/block-style-variations.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'sticky\' and \'sticky\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-supports/typography.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Template\\:\\:\\$author \\(int\\|null\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/block-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function filter_block_kses\\(\\) should return array but returns ArrayAccess&WP_Block_Parser_Block\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Type\\:\\:\\$render_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$addl_path in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post_Type\\:\\:\\$capabilities\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_category_by_path\\(\\) should return array\\|WP_Error\\|WP_Term\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$current\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_lvl\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_lvl\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_el\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_el\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Walker_Nav_Menu\\:\\:\\$tree_type \\(string\\) does not accept default value of type array\\<int, string\\>\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$namespace in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Processor\\:\\:extract_full_block_and_advance\\(\\) should return array\\<array\\>\\|null but returns array\\<string, array\\|string\\|null\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Processor\\:\\:extract_full_block_and_advance\\(\\) should return array\\<array\\>\\|null but returns array\\<string, list\\<string\\|null\\>\\|string\\|null\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param for parameter \\$block_type with type array\\<string\\> is incompatible with native type string\\.$#',
	'identifier' => 'parameter.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Processor\\:\\:\\$last_error \\(string\\|null\\) is never assigned string so it can be removed from the property type\\.$#',
	'identifier' => 'property.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$block_type in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-templates-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Type\\:\\:__get\\(\\) should return array\\<string\\>\\|string\\|void\\|null but returns array\\<array\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block\\:\\:\\$inner_blocks \\(WP_Block_List\\) does not accept default value of type array\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:group_by_parent_id\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Classic_To_Block_Menu_Converter\\:\\:to_blocks\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-classic-to-block-menu-converter.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$themes on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_post_id \\(int\\|false\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_uuid \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_post_values \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$widgets \\(WP_Customize_Widgets\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$theme_supports \\(array\\<mixed\\>\\) does not accept default value of type string\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$_previewed_blog_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$default \\(string\\) does not accept stdClass\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_js_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$validate_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Widgets\\:\\:\\$selective_refreshable_widgets \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-customize-widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param for parameter \\$type contains unresolvable type\\.$#',
	'identifier' => 'parameter.unresolvableType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$path \\(string\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$port \\(int\\|string\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$value \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Http\\:\\:_dispatch_request\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	'message' => '#^WpOrg\\\\Requests\\\\Cookie\\\\Jar does not accept WpOrg\\\\Requests\\\\Cookie\\.$#',
	'identifier' => 'offsetAssign.valueType',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#1 \\$width \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Default value of the parameter \\#2 \\$height \\(false\\) of method WP_Image_Editor_GD\\:\\:update_size\\(\\) is incompatible with type int\\.$#',
	'identifier' => 'parameter.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_GD\\:\\:_resize\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$image of method WP_Image_Editor_GD\\:\\:_save\\(\\) has invalid type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Image_Editor_GD\\:\\:\\$image has unknown class GdImage as its type\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'Imagick\' and \'setIteratorIndex\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but returns float\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:write_image\\(\\) should return WP_Error\\|true but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Image_Editor_Imagick\\:\\:\\$image \\(Imagick\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_read_data\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-image-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Locale\\:\\:\\$word_count_type \\(string\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-locale.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_classic_menu_fallback\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:create_default_fallback\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_default_fallback_blocks\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_fallback_classic_menu\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_created_nav_menu\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_most_recently_published_navigation\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_at_primary_location\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Navigation_Fallback\\:\\:get_nav_menu_with_primary_slug\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_code\\(\\) on object\\|false\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$loader in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	'message' => '#^Argument of an invalid type stdClass supplied for foreach, only iterables are supported\\.$#',
	'identifier' => 'foreach.nonIterable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var above assignment does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 9,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$ID \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$posts \\(array\\<int\\|WP_Post\\>\\|null\\) does not accept array\\<int, stdClass\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$search in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$status_type_clauses in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Recovery_Mode_Cookie_Service\\:\\:recovery_mode_hash\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-recovery-mode-cookie-service.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$rules \\(array\\<string\\>\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and int\\|string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Script_Modules\\:\\:get_marked_for_enqueue\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-script-modules.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$stored_results by\\-ref type of method WP_Scripts\\:\\:get_highest_fetchpriority_with_dependents\\(\\) expects array\\<string, string\\>, array\\<string, mixed\\> given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:clean_query\\(\\) expects array, WP_Error given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:transform_query\\(\\) expects array, WP_Error given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:transform_query\\(\\) expects array, array\\<int\\|string\\|WP_Term\\>\\|string given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$object_id on array\\|WP_Error\\|WP_Term\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Term_Query\\:\\:get_terms\\(\\) should return array\\<int\\|string\\|WP_Term\\>\\|string but returns int\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term_Query\\:\\:\\$terms \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term\\:\\:\\$term_group \\(int\\) does not accept default value of type string\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-term.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$blocks \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$core \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$i18n_schema \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$theme \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user_custom_post_type_id \\(int\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_style_variation_files\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:inject_variations_from_block_styles_registry\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:recursively_iterate_json\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:remove_json_comments\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON_Resolver\\:\\:style_variation_has_scope\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\<\\=" between 0 and int\\<0, max\\>\\|false is always true\\.$#',
	'identifier' => 'smallerOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:compute_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:get_block_nodes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:merge_spacing_sizes\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:process_pseudo_selectors\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:remove_indirect_properties\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:resolve_custom_css_format\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:unwrap_shared_block_style_variations\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:update_paragraph_text_indent_selector\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Theme_JSON\\:\\:update_separator_declarations\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Static method WP_Theme\\:\\:_check_headers_property_has_correct_type\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme\\:\\:\\$persistently_cache \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$matched_token_byte_length by\\-ref type of method WP_Token_Map\\:\\:read_token\\(\\) expects int\\|null, \\(float\\|int\\) given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-token-map.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User\\:\\:\\$roles \\(array\\<string\\>\\) does not accept array\\<string, bool\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_User\\:\\:\\$back_compat_keys \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Widget\\:\\:\\$alt_option_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Method wp_xmlrpc_server\\:\\:wp_newTerm\\(\\) should return int\\|IXR_Error but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'404\' and \'404\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$base_prefix \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$col_info \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$last_query \\(string\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Binary operation "\\+" between array\\|int\\<min, \\-1\\>\\|int\\<1, max\\> and 1 results in an error\\.$#',
	'identifier' => 'binaryOp.invalid',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_scan_utf8\\(\\) never assigns null to &\\$has_noncharacters so it can be removed from the by\\-ref type\\.$#',
	'identifier' => 'parameterByRef.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/compat-utf8.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_utf8_codepoint_span\\(\\) never assigns null to &\\$found_code_points so it can be removed from the by\\-ref type\\.$#',
	'identifier' => 'parameterByRef.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/compat-utf8.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$has_noncharacters by\\-ref type of function _wp_scan_utf8\\(\\) expects bool\\|null, int given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/compat-utf8.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on string\\|WP_Customize_Setting\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$attr_title\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$db_id\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$description\\.$#',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type\\.$#',
	'identifier' => 'property.notFound',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$type_label\\.$#',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Post\\:\\:\\$url\\.$#',
	'identifier' => 'property.notFound',
	'count' => 5,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Item_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_author \\(string\\) does not accept int\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Customize_Nav_Menu_Setting\\:\\:filter_wp_get_nav_menu_object\\(\\) should return object\\|null but returns false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$render_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$settings \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always false\\.$#',
	'identifier' => 'while.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/feed-rdf.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'SimplePie_Cache\' and \'register\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'basedir\' does not exist on string\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'baseurl\' does not exist on string\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:convert_font_face_properties\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:maybe_parse_name_from_comma_separated_list\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:parse_settings\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_kebab_case\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe call to private method WP_Font_Face_Resolver\\:\\:to_theme_file_uri\\(\\) through static\\:\\:\\.$#',
	'identifier' => 'staticClassAccess.privateMethod',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-face-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<70400, 80500\\> and 70300 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<70400, 80500\\> and 70400 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<2592000, 31535999\\> and 2592000 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<31536000, max\\> and 31536000 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<3600, 86399\\> and 3600 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<60, 3599\\> and 60 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<604800, 2591999\\> and 604800 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<86400, 604799\\> and 86400 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 0 on array\\{0\\: non\\-empty\\-string, non_cdata_followed_by_cdata\\: \'\', 1\\: \'\', 2\\: \'\', cdata\\: \'\', 3\\: \'\', 4\\: \'\', non_cdata\\: string, \\.\\.\\.\\}\\|array\\{0\\: non\\-empty\\-string, non_cdata_followed_by_cdata\\: string, 1\\: string, 2\\: string, cdata\\: non\\-falsy\\-string, 3\\: non\\-falsy\\-string, 4\\: non\\-falsy\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_imagetype\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset int\\<1, max\\> on non\\-empty\\-list\\<array\\{continent\\: \'\'\\|\'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\', city\\: string, subcity\\: string, t_continent\\: mixed, t_city\\: mixed, t_subcity\\: mixed\\}\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$key$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$url$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$value$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @phpstan\\-return has invalid value \\(\\( \\$args\\[\'exit\'\\] is false \\? void \\: never \\)\\)\\: Unexpected token "\\[", expected type \\("is"\\) at offset 3167 on line 50$#',
	'identifier' => 'phpDoc.parseError',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'Etc\' and \'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Function single_cat_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function single_tag_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-doctype-info.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Processor\\:\\:bookmark_token\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 17,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_rawtext\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_script_data\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Tag_Processor\\:\\:\\$is_closing_tag \\(bool\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Tag_Processor\\:\\:\\$skip_newline_at \\(int\\|null\\) is never assigned int so it can be removed from the property type\\.$#',
	'identifier' => 'property.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Text_Replacement\\:\\:\\$text \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always true\\.$#',
	'identifier' => 'booleanAnd.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_COMPLETE\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_INCOMPLETE…\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_MATCHED_TAG\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'STATE_INCOMPLETE…\' and \'STATE_READY\' will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_bind_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_class_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_context_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_each_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_interactive_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_router_region_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_style_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_text_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_dropdown_languages\\(\\) should return string but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Translation_Controller\\:\\:get_entries\\(\\) should return array\\<string, string\\> but returns array\\<string, array\\<string\\>\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/l10n/class-wp-translation-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Translation_File\\:\\:entries\\(\\) should return array\\<string, array\\<string\\>\\> but returns array\\<string, string\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Translation_File\\:\\:\\$entries \\(array\\<string, string\\>\\) does not accept array\\<string, array\\<string\\>\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$link_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with bool will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_imagecreatetruecolor\\(\\) has invalid return type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_imagecreatetruecolor\\(\\) never returns GdImage so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 2 on array\\{string, non\\-empty\\-string, non\\-empty\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\$image of function is_gd_image\\(\\) has invalid type GdImage\\.$#',
	'identifier' => 'class.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and non\\-empty\\-string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Function update_meta_cache\\(\\) should return array\\|false but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/ms-default-constants.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<mixed\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_update_nav_menu_item\\(\\) should return int\\|WP_Error but returns WP_Term\\|false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term\\:\\:\\$term_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between 0 and int\\<min, \\-1\\>\\|int\\<1, max\\> will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between float\\|int\\|numeric\\-string and \'bottom\'\\|\'footer\'\\|\'header\'\\|\'main\'\\|\'menu\\-1\'\\|\'menu\\-2\'\\|\'navigation\'\\|\'primary\'\\|\'secondary\'\\|\'social\'\\|\'subsidiary\'\\|\'top\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between false and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 3000000000 and 2147483647 will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$deprecated in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_post_revision_title\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_post_revision_title_expanded\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-list\\{0\\?\\: string, 1\\?\\: non\\-falsy\\-string&numeric\\-string, 2\\?\\: numeric\\-string, 3\\?\\: numeric\\-string\\} will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$ID on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_set_post_categories\\(\\) should return array\\|WP_Error\\|false but returns true\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_trash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_untrash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$result by\\-ref type of function _page_traverse_name\\(\\) expects array\\<string\\>, array given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset mixed on array\\{\\} in isset\\(\\) does not exist\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset mixed on array\\{\\} on left side of \\?\\? does not exist\\.$#',
	'identifier' => 'nullCoalesce.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Attachments_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$schema in empty\\(\\) is never defined\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$plugins on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$uses_context\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to private property WP_Block_Type\\:\\:\\$variations\\.$#',
	'identifier' => 'property.private',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Controller\\:\\:get_object_type\\(\\) should return string but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Font_Faces_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-faces-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-faces-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Font_Families_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_title \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$id \\(int\\) of method WP_REST_Global_Styles_Controller\\:\\:prepare_links\\(\\) should be compatible with parameter \\$post \\(WP_Post\\) of method WP_REST_Posts_Controller\\:\\:prepare_links\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @param references unknown parameter\\: \\$_request$#',
	'identifier' => 'parameter.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-icons-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$auto_add on WP_Term\\|false\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$download_link on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$language_packs on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method add_data\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method get_error_message\\(\\) on array\\|object\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post_Type\\:\\:\\$template \\(array\\<array\\>\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Template_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$parent_post_type is never read, only written\\.$#',
	'identifier' => 'property.onlyWritten',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$parent_template_id \\(string\\) of method WP_REST_Template_Revisions_Controller\\:\\:get_parent\\(\\) should be compatible with parameter \\$parent_post_id \\(int\\) of method WP_REST_Revisions_Controller\\:\\:get_parent\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-revisions-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and mixed will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_content on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_excerpt on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$post_title on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _set_preview\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_preview_post_thumbnail_filter\\(\\) should return array\\|null but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_delete_post_revision\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_restore_post_revision\\(\\) should return int\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$max_num_pages \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$parent on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$template_name on array\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access property \\$term_id on array\\|object\\.$#',
	'identifier' => 'property.nonObject',
	'count' => 4,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_term_to_edit\\(\\) never returns int so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Taxonomy\\:\\:\\$labels \\(stdClass\\) does not accept array\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$the_parent in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$s in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/theme-compat/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Function remove_theme_support\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns array\\|float\\|int\\|string\\|false\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 3,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_list_users\\(\\) should return string\\|null but return statement is missing\\.$#',
	'identifier' => 'return.missing',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User\\:\\:\\$ID \\(int\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Widget_Media\\:\\:\\$l10n_defaults \\(array\\<string\\>\\) does not accept array\\<string, array\\|string\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/widgets/class-wp-widget-media.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-login.php',
];
$ignoreErrors[] = [
	'message' => '#^Function validate_another_blog_signup\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-signup.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @var does not specify variable name\\.$#',
	'identifier' => 'varTag.noVariable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/xmlrpc.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
