<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/about.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-post.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between int\\<51, max\\> and 50 is always true\\.$#',
	'identifier' => 'greater.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always false\\.$#',
	'identifier' => 'elseif.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always true\\.$#',
	'identifier' => 'booleanAnd.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/credits.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-form-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<int\\<0, max\\>, non\\-falsy\\-string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with bool\\|WP_Error will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between true and non\\-empty\\-array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<int\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/bookmark.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-language-pack-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-checklist.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_status \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-walker-nav-menu-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always false\\.$#',
	'identifier' => 'elseif.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 12,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-community-events.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-community-events.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with false will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always false\\.$#',
	'identifier' => 'elseif.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between Imagick and Imagick will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpsockets.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset string on array\\{\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Static method WP_Internal_Pointers\\:\\:print_js\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-internal-pointers.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'all\' and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-links-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_List_Table\\:\\:\\$_column_headers \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<array\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-ms-themes-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<object\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-ms-users-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugins-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'dropins\' and \'dropins\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugins-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-requests-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_bool\\(\\) with bool will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Screen\\:\\:\\$_screen_settings is never written, only read\\.$#',
	'identifier' => 'property.onlyRead',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Screen\\:\\:\\$_show_screen_options is never written, only read\\.$#',
	'identifier' => 'property.onlyRead',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Screen\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between null and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-themes-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with WP_Upgrader will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader-skin.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with WP_Upgrader_Skin and \'hide_process_failed\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_User and WP_User will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-users-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with callable\\(\\)\\: mixed will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with int\\|false will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<WP_Term\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/export.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'ParagonIE_Sodium…\' and \'runtime_speed_test\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Function WP_Filesystem\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'\' and non\\-falsy\\-string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Image_Editor and WP_Image_Editor will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'height\' on non\\-empty\\-array in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'width\' on non\\-empty\\-array in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_bool\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_read_data\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'iptcparse\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always true\\.$#',
	'identifier' => 'elseif.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with WP_Post will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with WP_Post will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with array\\|null will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'created_timestamp\' on array\\{\\}\\|array\\{lossless\\?\\: mixed, bitrate\\?\\: int, bitrate_mode\\?\\: mixed, filesize\\?\\: int, mime_type\\?\\: mixed, length\\?\\: int, length_formatted\\?\\: mixed, width\\?\\: int, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Function iis7_save_url_rewrite_rules\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Function save_mod_rewrite_rules\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ms.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ms.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with float\\|int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Function delete_plugins\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'user_ID\' on non\\-empty\\-array in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Taxonomy\\:\\:\\$meta_box_sanitize_cb \\(callable\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/privacy-tools.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/privacy-tools.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with WP_Screen will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function delete_theme\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Theme and WP_Theme will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with mixed will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset mixed on array\\<array\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update-core.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update-core.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and literal\\-string&lowercase\\-string&non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update-core.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<array\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'novalue\' and int\\|null will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install-helper.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/link.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/link.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/my-sites.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \\(float\\|int\\) on array\\<mixed\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 0 and array\\|string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/sites.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/options-general.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Type\\:\\:\\$editor_style_handles \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/block-style-variations.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 1 on array\\{list\\<string\\>, list\\<non\\-empty\\-string\\>\\} on left side of \\?\\? always exists and is not nullable\\.$#',
	'identifier' => 'nullCoalesce.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/block-style-variations.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/border.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/colors.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/elements.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'sticky\' and \'sticky\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_float\\(\\) with float will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/typography.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/typography.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/typography.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'wp_template\' and \'wp_template\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'port\' on array\\{path\\: list\\<string\\>\\|string\\|null, host\\?\\: string\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'query\' on array\\{path\\: list\\<string\\>\\|string\\|null, host\\?\\: string\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'query\' on array\\{path\\: list\\<string\\>\\|string\\|null\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Bindings_Source and WP_Block_Bindings_Source will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Bindings_Registry\\:\\:\\$supported_blocks is never read, only written\\.$#',
	'identifier' => 'property.onlyWritten',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-list.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'closer\' on array\\{0\\: array\\{string, int\\<\\-1, max\\>\\}, closer\\: array\\{\'\'\\|\'/\', int\\<\\-1, max\\>\\}, 1\\: array\\{\'\'\\|\'/\', int\\<\\-1, max\\>\\}, namespace\\: array\\{string, int\\<\\-1, max\\>\\}, 2\\: array\\{string, int\\<\\-1, max\\>\\}, name\\: array\\{non\\-falsy\\-string, int\\<\\-1, max\\>\\}, 3\\: array\\{non\\-falsy\\-string, int\\<\\-1, max\\>\\}, attrs\\?\\: array\\{string, int\\<\\-1, max\\>\\}, \\.\\.\\.\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-styles-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-styles-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-templates-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-templates-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with callable\\(\\)\\: mixed will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with WP_Block_Type will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_post_id \\(int\\|false\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_changeset_uuid \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$_post_values \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$widgets \\(WP_Customize_Widgets\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always true\\.$#',
	'identifier' => 'booleanAnd.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between false and string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$_previewed_blog_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Widgets\\:\\:\\$selective_refreshable_widgets \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Dependencies\\:\\:\\$all_queued_deps \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Property _WP_Dependency\\:\\:\\$ver \\(bool\\|string\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependency.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependency.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Block_Type and WP_Block_Type will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_block_names \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_block_names is never written, only read\\.$#',
	'identifier' => 'property.onlyRead',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_presets \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Duotone\\:\\:\\$global_styles_presets is never written, only read\\.$#',
	'identifier' => 'property.onlyRead',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$path \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$port \\(int\\|string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Http_Cookie\\:\\:\\$value \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-curl.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-streams.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Http\\:\\:_dispatch_request\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with array\\{Imagick, \'getImageProfiles\'\\} will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with array\\{Imagick, \'sampleImage\'\\} will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with array\\{Imagick, \'setImageOrientation\'\\} will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'Imagick\' and \'setIteratorIndex\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with Imagick and \'getInterlaceScheme\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with Imagick and \'setInterlaceScheme\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between Imagick and Imagick will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between bool and 0 will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_read_data\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-locale.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-meta-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-meta-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between int\\<min, \\-1\\>\\|int\\<1, max\\>\\|WP_Error and WP_Post will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-network-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-object-cache.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with object will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-plugin-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<array\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-plugin-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-plugin-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-plugin-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<int\\|WP_Post\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$queried_object_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$query \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$stopwords \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Recovery_Mode_Cookie_Service\\:\\:recovery_mode_hash\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-recovery-mode-cookie-service.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'identifier' => 'notIdentical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-recovery-mode-cookie-service.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$author_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$comment_feed_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$date_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$feed_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$page_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$search_structure \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and int\\|string will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between null and string\\|false will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with array\\<mixed, mixed\\> will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Property _WP_Dependency\\:\\:\\$translations_path \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 1 and array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and \\*NEVER\\* will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_bool\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	'message' => '#^Property _WP_Dependency\\:\\:\\$args \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<int\\|object\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Theme and WP_Theme will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Theme_JSON and WP_Theme_JSON will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with array\\<string\\> will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\<\\=" between 0 and int\\<0, max\\>\\|false is always true\\.$#',
	'identifier' => 'smallerOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'block_styles\' and \\*NEVER\\* will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'css_variables\' and \\*NEVER\\* will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with mixed will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Theme and WP_Theme will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Theme\\:\\:parent\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$block_template_folders \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$block_theme \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$headers_sanitized \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$name_translated \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$parent \\(WP_Theme\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$textdomain_loaded \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$theme_root_uri \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Static method WP_Theme\\:\\:_check_headers_property_has_correct_type\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme\\:\\:\\$persistently_cache \\(bool\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'both\' and bool will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'leading\' and bool will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'trailing\' and bool will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<bool\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_User\\:\\:\\$back_compat_keys \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between array and ArrayIterator will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between array and ArrayObject will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Widget\\:\\:\\$alt_option_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'404\' and \'404\' will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_float\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between mysqli and mysqli will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$base_prefix \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string\\|WP_Error will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<WP_Comment\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between mixed and ResourceBundle will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between mixed and SimpleXMLElement will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between null and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<array\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 0 on non\\-empty\\-list\\<\\(int\\|string\\)\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \\*NEVER\\* and int will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always false\\.$#',
	'identifier' => 'elseif.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$render_callback \\(callable\\) in empty\\(\\) is not falsy\\.$#',
	'identifier' => 'empty.property',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Partial\\:\\:\\$settings \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-selective-refresh.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/default-constants.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always false\\.$#',
	'identifier' => 'while.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed-rdf.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function method_exists\\(\\) with \'SimplePie_Cache\' and \'register\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 0 on non\\-empty\\-list\\<string\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between null and callable\\(\\)\\: mixed will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<2592000, 31535999\\> and 2592000 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<31536000, max\\> and 31536000 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<3600, 86399\\> and 3600 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<60, 3599\\> and 60 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<604800, 2591999\\> and 604800 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>\\=" between int\\<86400, 604799\\> and 86400 is always true\\.$#',
	'identifier' => 'greaterOrEqual.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 0 on array\\{0\\: string, non_cdata_followed_by_cdata\\: \'\', 1\\: \'\', 2\\: \'\', cdata\\: \'\', 3\\: \'\', 4\\: \'\', non_cdata\\: string, \\.\\.\\.\\}\\|array\\{0\\: string, non_cdata_followed_by_cdata\\: string, 1\\: string, 2\\: string, cdata\\: non\\-falsy\\-string, 3\\: non\\-falsy\\-string, 4\\: non\\-falsy\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 7,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\{scheme\\?\\: string, host\\?\\: string, port\\?\\: int\\<0, 65535\\>, user\\?\\: string, pass\\?\\: string, path\\?\\: string, query\\?\\: string, fragment\\?\\: string\\} will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with \'exif_imagetype\' will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with callable\\(\\)\\: mixed will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with int will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 0 on non\\-empty\\-list\\<string\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset int\\<1, max\\> on non\\-empty\\-list\\<array\\{continent\\: \'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\', city\\: string, subcity\\: string, t_continent\\: string, t_city\\: string, t_subcity\\: string\\}\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'https\\://wordpress…\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'Etc\' and \'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always true\\.$#',
	'identifier' => 'ternary.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function single_cat_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function single_tag_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and false will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/global-styles-and-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string\\|null will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-doctype-info.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_rawtext\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Tag_Processor\\:\\:skip_script_data\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Tag_Processor\\:\\:\\$skip_newline_at \\(int\\|null\\) is never assigned int so it can be removed from the property type\\.$#',
	'identifier' => 'property.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Text_Replacement\\:\\:\\$text \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always true\\.$#',
	'identifier' => 'booleanAnd.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_COMPLETE\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_INCOMPLETE…\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'STATE_MATCHED_TAG\' and \'STATE_READY\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between false and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'STATE_INCOMPLETE…\' and \'STATE_READY\' will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/http.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_bind_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_class_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_context_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_each_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_interactive_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_router_region_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_style_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Interactivity_API\\:\\:data_wp_text_processor\\(\\) is unused\\.$#',
	'identifier' => 'method.unused',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/interactivity-api/class-wp-interactivity-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between true and array will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int\\<min, \\-1\\>\\|int\\<1, max\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file-mo.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 11,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function in_array\\(\\) with arguments \'\', array\\{\'true\', \'1\'\\} and true will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with false will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 10 and bool will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_imagecreatetruecolor\\(\\) never returns resource so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 2 on array\\{string, non\\-empty\\-string, non\\-empty\\-string\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with non\\-empty\\-array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and null will always evaluate to false\\.$#',
	'identifier' => 'notIdentical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	'message' => '#^Elseif condition is always false\\.$#',
	'identifier' => 'elseif.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-files.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-files.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array\\<object\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<mixed\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with WP_User will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'%%siteurl%%\' and \'\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-load.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with int will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term\\:\\:\\$term_id \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \\(int\\|numeric\\-string\\) and \'bottom\'\\|\'footer\'\\|\'header\'\\|\'main\'\\|\'menu\\-1\'\\|\'menu\\-2\'\\|\'navigation\'\\|\'primary\'\\|\'secondary\'\\|\'social\'\\|\'subsidiary\'\\|\'top\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between 0 and int\\<min, \\-1\\>\\|int\\<1, max\\> will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between int\\|numeric\\-string and \'bottom\'\\|\'footer\'\\|\'header\'\\|\'main\'\\|\'menu\\-1\'\\|\'menu\\-2\'\\|\'navigation\'\\|\'primary\'\\|\'secondary\'\\|\'social\'\\|\'subsidiary\'\\|\'top\' will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with int\\<min, \\-1\\>\\|int\\<1, max\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_set_all_user_settings\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and callable\\(\\)\\: mixed will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'identifier' => 'notIdentical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between false and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and null will always evaluate to false\\.$#',
	'identifier' => 'notIdentical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 3 and 3 will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 3000000000 and 2147483647 will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_bool\\(\\) with int\\|WP_Post\\|null will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-falsy\\-string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_numeric\\(\\) with array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with WP_Post_Type will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with stdClass will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Post and WP_Post will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'\' and int will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 0 and int\\<1, max\\> will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-falsy\\-string will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with object will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset string on array\\{\\} in isset\\(\\) does not exist\\.$#',
	'identifier' => 'isset.offset',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'OPTIONS\' and \'GET\' will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between 2 and \\*NEVER\\* will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between 1 and 0 is always true\\.$#',
	'identifier' => 'greater.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_REST_Response and WP_REST_Response will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_title \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_null\\(\\) with bool\\|WP_Error will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post_Type\\:\\:\\$template \\(array\\<array\\>\\) on left side of \\?\\? is not nullable\\.$#',
	'identifier' => 'nullCoalesce.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Comparison operation "\\>" between 1 and 0 is always true\\.$#',
	'identifier' => 'greater.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$parent_post_type is never read, only written\\.$#',
	'identifier' => 'property.onlyWritten',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-url-details-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-url-details-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between \'false\' and true will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and true will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with \\*NEVER\\* will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_object\\(\\) with WP_Post will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _set_preview\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between true and true will always evaluate to true\\.$#',
	'identifier' => 'identical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and int will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'identifier' => 'booleanOr.alwaysTrue',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	'message' => '#^Function strip_shortcode_tag\\(\\) never returns false so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/shortcodes.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always false\\.$#',
	'identifier' => 'booleanNot.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps/class-wp-sitemaps-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$max_num_pages \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-css-rules-store.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_Style_Engine_CSS_Rules_Store and WP_Style_Engine_CSS_Rules_Store will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<string\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-empty\\-array will always evaluate to false\\.$#',
	'identifier' => 'function.impossibleType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always true\\.$#',
	'identifier' => 'booleanAnd.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_term_to_edit\\(\\) never returns int so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'template\' on array\\{0\\: false, label\\: string\\|WP_Taxonomy, args\\: array\\{\\}\\}\\|array\\{name\\: string, label\\: string\\|WP_Taxonomy, labels\\: stdClass, description\\: string, public\\: bool, publicly_queryable\\: bool, hierarchical\\: bool, show_ui\\: bool, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'term_template\' on array\\{0\\: false, label\\: string\\|WP_Taxonomy, args\\: array\\{\\}, template\\: mixed\\}\\|array\\{name\\: string, label\\: string\\|WP_Taxonomy, labels\\: stdClass, description\\: string, public\\: bool, publicly_queryable\\: bool, hierarchical\\: bool, show_ui\\: bool, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'identifier' => 'empty.offset',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between null and mixed will always evaluate to true\\.$#',
	'identifier' => 'notIdentical.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between null and int\\|string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^While loop condition is always true\\.$#',
	'identifier' => 'while.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme-compat/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Function remove_theme_support\\(\\) never returns void so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always true\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'identifier' => 'booleanOr.rightAlwaysFalse',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/update.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with non\\-empty\\-array\\<int\\> will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_string\\(\\) with non\\-falsy\\-string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between WP_User and WP_User will always evaluate to true\\.$#',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Negated boolean expression is always true\\.$#',
	'identifier' => 'booleanNot.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site\\:\\:\\$domain \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User\\:\\:\\$ID \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'identifier' => 'isset.property',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always true\\.$#',
	'identifier' => 'booleanAnd.rightAlwaysTrue',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_array\\(\\) with array will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_callable\\(\\) with callable\\(\\)\\: mixed will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with int\\|string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function is_scalar\\(\\) with string will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always false\\.$#',
	'identifier' => 'if.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'identifier' => 'notIdentical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	'message' => '#^If condition is always true\\.$#',
	'identifier' => 'if.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Left side of && is always false\\.$#',
	'identifier' => 'booleanAnd.leftAlwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Result of && is always false\\.$#',
	'identifier' => 'booleanAnd.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'identifier' => 'deadCode.unreachable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	'message' => '#^Function validate_another_blog_signup\\(\\) never returns null so it can be removed from the return type\\.$#',
	'identifier' => 'return.unusedType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-signup.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
