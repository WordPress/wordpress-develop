<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/about.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/credits.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$post_status \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset mixed on array\\{\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Static method WP_Internal_Pointers\\:\\:print_js\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Screen\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysTrue
	'message' => '#^While loop condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function WP_Filesystem\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'created_timestamp\' on array\\{\\}\\|array\\{lossless\\?\\: mixed, bitrate\\?\\: int, bitrate_mode\\?\\: mixed, filesize\\?\\: int, mime_type\\?\\: mixed, length\\?\\: int, length_formatted\\?\\: mixed, width\\?\\: int, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function iis7_save_url_rewrite_rules\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function save_mod_rewrite_rules\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysTrue
	'message' => '#^While loop condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function delete_plugins\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Taxonomy\\:\\:\\$meta_box_sanitize_cb \\(callable\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function delete_theme\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu-header.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/my-sites.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/sites.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'update\\-selected\' and mixed~\\(\'activate\'\\|\'activate\\-selected\'\\|\'deactivate\'\\|\'deactivate\\-selected\'\\|\'delete\\-selected\'\\|\'disable\\-auto\\-update\'\\|\'disable\\-auto\\-update\\-selected\'\\|\'enable\\-auto\\-update\'\\|\'enable\\-auto\\-update\\-selected\'\\|\'error_scrape\'\\|\'resume\'\\|\'update\\-selected\'\\) will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between mixed~\\(\'edit\\.php\\?post_type\\=wp_navigation\'\\|\'site\\-editor\\.php\'\\|\'theme\\-editor\\.php\'\\|\'themes\\.php\'\\) and \'themes\\.php\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Block_Type\\:\\:\\$editor_style_handles \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	// identifier: nullCoalesce.offset
	'message' => '#^Offset 1 on array\\{array\\<int, string\\>, array\\<int, non\\-empty\\-string\\>\\} on left side of \\?\\? always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/block-style-variations.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysTrue
	'message' => '#^While loop condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'port\' on array\\{path\\: mixed, host\\?\\: mixed\\} in empty\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'query\' on array\\{path\\: array\\|string\\|null\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'query\' on array\\{path\\: mixed, host\\?\\: mixed\\} in empty\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyWritten
	'message' => '#^Property WP_Block_Bindings_Registry\\:\\:\\$supported_blocks is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-templates-registry.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_post_id \\(int\\|false\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_uuid \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_post_values \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$widgets \\(WP_Customize_Widgets\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysTrue
	'message' => '#^Right side of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$_previewed_blog_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Widgets\\:\\:\\$selective_refreshable_widgets \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-widgets.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with mixed will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Dependencies\\:\\:\\$all_queued_deps \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	// identifier: nullCoalesce.property
	'message' => '#^Property _WP_Dependency\\:\\:\\$ver \\(bool\\|string\\) on left side of \\?\\? is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_block_names \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyRead
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_block_names is never written, only read\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_presets \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyRead
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_presets is never written, only read\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$path \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$port \\(int\\|string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$value \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Http\\:\\:_dispatch_request\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$ID \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Query\\:\\:\\$queried_object_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Query\\:\\:\\$query \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Query\\:\\:\\$stopwords \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Method WP_Recovery_Mode_Cookie_Service\\:\\:recovery_mode_hash\\(\\) never returns false so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-recovery-mode-cookie-service.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$author_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$comment_feed_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$date_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$feed_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$page_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Rewrite\\:\\:\\$search_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property _WP_Dependency\\:\\:\\$args \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property _WP_Dependency\\:\\:\\$translations_path \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property _WP_Dependency\\:\\:\\$args \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	// identifier: smallerOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\<\\=" between 0 and int\\<0, max\\>\\|false is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$block_template_folders \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$block_theme \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$headers_sanitized \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$name_translated \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$parent \\(WP_Theme\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$textdomain_loaded \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Theme\\:\\:\\$theme_root_uri \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Static method WP_Theme\\:\\:_check_headers_property_has_correct_type\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Static property WP_Theme\\:\\:\\$persistently_cache \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Static property WP_User\\:\\:\\$back_compat_keys \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Widget\\:\\:\\$alt_option_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property wpdb\\:\\:\\$base_prefix \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: instanceof.alwaysFalse
	'message' => '#^Instanceof between mixed and ResourceBundle will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	// identifier: instanceof.alwaysFalse
	'message' => '#^Instanceof between mixed and SimpleXMLElement will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$render_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$settings \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysFalse
	'message' => '#^While loop condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed-rdf.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<2592000, 31535999\\> and 2592000 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<31536000, max\\> and 31536000 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<3600, 86399\\> and 3600 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<60, 3599\\> and 60 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<604800, 2591999\\> and 604800 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: greaterOrEqual.alwaysTrue
	'message' => '#^Comparison operation "\\>\\=" between int\\<86400, 604799\\> and 86400 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset 0 on array\\{0\\: string, non_cdata_followed_by_cdata\\: \'\', 1\\: \'\', 2\\: \'\', cdata\\: \'\', 3\\: \'\', 4\\: \'\', non_cdata\\: string, \\.\\.\\.\\}\\|array\\{0\\: string, non_cdata_followed_by_cdata\\: string, 1\\: string, 2\\: string, cdata\\: non\\-falsy\\-string, 3\\: non\\-falsy\\-string, 4\\: non\\-falsy\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset int on non\\-empty\\-array\\<int, array\\{continent\\: \'\'\\|\'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\', city\\: string, subcity\\: string, t_continent\\: mixed, t_city\\: mixed, t_subcity\\: mixed\\}\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function single_cat_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function single_tag_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-doctype-info.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_rawtext\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_script_data\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_HTML_Text_Replacement\\:\\:\\$text \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysTrue
	'message' => '#^Result of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'STATE_INCOMPLETE…\' and \'STATE_READY\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_bind_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_class_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_context_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_each_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_interactive_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_router_region_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_style_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_text_processor\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_imagecreatetruecolor\\(\\) never returns GdImage so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset 2 on array\\{string, non\\-empty\\-string, non\\-empty\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Term\\:\\:\\$term_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_set_all_user_settings\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 3000000000 and 2147483647 will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset mixed on array\\{\\} in isset\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$post_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$post_title \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	// identifier: nullCoalesce.property
	'message' => '#^Property WP_Post_Type\\:\\:\\$template \\(array\\<array\\>\\) on left side of \\?\\? is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyWritten
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$parent_post_type is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and mixed will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function _set_preview\\(\\) never returns false so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rewrite.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Query\\:\\:\\$max_num_pages \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_term_to_edit\\(\\) never returns int so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysTrue
	'message' => '#^While loop condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/embed.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function remove_theme_support\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_User\\:\\:\\$ID \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function validate_another_blog_signup\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-signup.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
