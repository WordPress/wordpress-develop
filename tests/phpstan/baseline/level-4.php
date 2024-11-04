<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/about.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-header.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin-header.php',
];
$ignoreErrors[] = [
	// identifier: greater.alwaysTrue
	'message' => '#^Comparison operation "\\>" between int\\<51, max\\> and 50 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	// identifier: elseif.alwaysFalse
	'message' => '#^Elseif condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysTrue
	'message' => '#^Result of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/admin.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/credits.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/edit-form-comment.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with bool\\|WP_Error will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between true and non\\-empty\\-array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-background.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.leftAlwaysTrue
	'message' => '#^Left side of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
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
	// identifier: elseif.alwaysFalse
	'message' => '#^Elseif condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-comments-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-community-events.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-community-events.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with false will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: elseif.alwaysFalse
	'message' => '#^Elseif condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpsockets.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-importer.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset string on array\\{\\} in empty\\(\\) does not exist\\.$#',
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
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_List_Table\\:\\:\\$_column_headers \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-media-list-table.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-ms-themes-list-table.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-plugins-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-privacy-requests-table.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyRead
	'message' => '#^Property WP_Screen\\:\\:\\$_screen_settings is never written, only read\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyRead
	'message' => '#^Property WP_Screen\\:\\:\\$_show_screen_options is never written, only read\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Screen\\:\\:\\$post_type \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between null and string will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-site-health.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-terms-list-table.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-upgrader.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_dashboard_quota\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/dashboard.php',
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
	// identifier: return.unusedType
	'message' => '#^Function validate_file_to_edit\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
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
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'\' and non\\-falsy\\-string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: elseif.unreachable
	'message' => '#^Elseif branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset \'height\' on array in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset \'width\' on array in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image-edit.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_bool\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/image.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function register_importer\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/import.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with WP_Post will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_int\\(\\) with WP_Post will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with array\\|null will always evaluate to false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'created_timestamp\' on array\\{\\}\\|array\\{lossless\\?\\: mixed, bitrate\\?\\: int, bitrate_mode\\?\\: mixed, filesize\\?\\: int, mime_type\\?\\: mixed, length\\?\\: int, length_formatted\\?\\: mixed, width\\?\\: int, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/meta-boxes.php',
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
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function site_admin_notice\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ms.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysTrue
	'message' => '#^While loop condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin-install.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function delete_plugins\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function uninstall_plugin\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function _fix_attachment_links\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function write_post\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset \'user_ID\' on non\\-empty\\-array in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Taxonomy\\:\\:\\$meta_box_sanitize_cb \\(callable\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/post.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/privacy-tools.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with WP_Screen will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/screen.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function parent_dropdown\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
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
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_object\\(\\) with mixed will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset mixed on array\\<array\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/translation-install.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update-core.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update-core.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function maintenance_nag\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function update_nag\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_plugin_update_row\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_theme_update_row\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/update.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function make_site_theme_from_default\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/upgrade.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'novalue\' and int\\|null will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/user.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/install.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/link.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/link.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu-header.php',
];
$ignoreErrors[] = [
	// identifier: ternary.elseUnreachable
	'message' => '#^Else branch is unreachable because ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu.php',
];
$ignoreErrors[] = [
	// identifier: elseif.unreachable
	'message' => '#^Elseif branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/menu.php',
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
	// identifier: isset.offset
	'message' => '#^Offset \\(float\\|int\\) on array\\<int\\|string, mixed\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/nav-menus.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 0 and array\\|string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/site-settings.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/network/sites.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/options-general.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/plugins.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/post.php',
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
	// identifier: return.unusedType
	'message' => '#^Function wp_list_authors\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/author-template.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Block_Type\\:\\:\\$editor_style_handles \\(array\\<string\\>\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-editor.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
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
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/elements.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/layout.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/position.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-supports/typography.php',
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
	'message' => '#^Offset \'port\' on array\\{path\\: array\\<int, string\\>\\|string\\|null, host\\?\\: string\\} in empty\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'query\' on array\\{path\\: array\\<int, string\\>\\|string\\|null, host\\?\\: string\\} in empty\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'query\' on array\\{path\\: array\\<int, string\\>\\|string\\|null\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function add_role\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function the_terms\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-comment.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_object\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-admin-bar.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyWritten
	'message' => '#^Property WP_Block_Bindings_Registry\\:\\:\\$supported_blocks is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-bindings-registry.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-list.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset \'closer\' on array\\{0\\: array\\{string, int\\<\\-1, max\\>\\}, closer\\: array\\{\'\'\\|\'/\', int\\<\\-1, max\\>\\}, 1\\: array\\{\'\'\\|\'/\', int\\<\\-1, max\\>\\}, namespace\\: array\\{string, int\\<\\-1, max\\>\\}, 2\\: array\\{string, int\\<\\-1, max\\>\\}, name\\: array\\{non\\-falsy\\-string, int\\<\\-1, max\\>\\}, 3\\: array\\{non\\-falsy\\-string, int\\<\\-1, max\\>\\}, attrs\\?\\: array\\{string, int\\<\\-1, max\\>\\}, \\.\\.\\.\\} in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-styles-registry.php',
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
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type-registry.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with WP_Block_Type will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
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
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-manager.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 2,
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
	// identifier: booleanAnd.alwaysTrue
	'message' => '#^Result of && is always true\\.$#',
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
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
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
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-duotone.php',
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
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-editor.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-cookie.php',
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
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-curl.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http-streams.php',
];
$ignoreErrors[] = [
	// identifier: method.unused
	'message' => '#^Method WP_Http\\:\\:_dispatch_request\\(\\) is unused\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-gd.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between bool and 0 will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-locale.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_int\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-meta-query.php',
];
$ignoreErrors[] = [
	// identifier: instanceof.alwaysFalse
	'message' => '#^Instanceof between int\\<min, \\-1\\>\\|int\\<1, max\\>\\|WP_Error and WP_Post will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-navigation-fallback.php',
];
$ignoreErrors[] = [
	// identifier: ternary.elseUnreachable
	'message' => '#^Else branch is unreachable because ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-object-cache.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-plugin-dependencies.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
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
	// identifier: notIdentical.alwaysFalse
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-recovery-mode-cookie-service.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
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
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between null and string\\|false will always evaluate to false\\.$#',
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
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with array will always evaluate to false\\.$#',
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
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 1 and array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and \\*NEVER\\* will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-scripts.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_bool\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property _WP_Dependency\\:\\:\\$args \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-styles.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with array\\<string\\> will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
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
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'block_styles\' and \\*NEVER\\* will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'css_variables\' and \\*NEVER\\* will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with mixed will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
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
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
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
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'both\' and bool will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'leading\' and bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'trailing\' and bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Static property WP_User\\:\\:\\$back_compat_keys \\(array\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-walker.php',
];
$ignoreErrors[] = [
	// identifier: instanceof.alwaysFalse
	'message' => '#^Instanceof between array and ArrayIterator will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	// identifier: instanceof.alwaysFalse
	'message' => '#^Instanceof between array and ArrayObject will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Widget\\:\\:\\$alt_option_name \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-widget.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Method wp_xmlrpc_server\\:\\:_toggle_sticky\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_float\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property wpdb\\:\\:\\$base_prefix \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string\\|WP_Error will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function comment_class\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function trackback_url\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function do_trackbacks\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_update_comment_count\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
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
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between null and string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/compat.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_cron\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/cron.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_int\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: empty.property
	'message' => '#^Property WP_Customize_Manager\\:\\:\\$nav_menus \\(WP_Customize_Nav_Menus\\) in empty\\(\\) is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \\*NEVER\\* and int will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and array will always evaluate to false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: elseif.alwaysFalse
	'message' => '#^Elseif condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-partial.php',
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
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-selective-refresh.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/default-constants.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	// identifier: while.alwaysFalse
	'message' => '#^While loop condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed-rdf.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/feed.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between null and callable\\(\\)\\: mixed will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts/class-wp-font-utils.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_object\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
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
	// identifier: return.unusedType
	'message' => '#^Function sanitize_hex_color\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
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
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/formatting.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with int will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: elseif.unreachable
	'message' => '#^Elseif branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function do_enclose\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_ext2type\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_update_php_annotation\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset 0 on non\\-empty\\-array\\<int, string\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset int on non\\-empty\\-array\\<int, array\\{continent\\: \'Africa\'\\|\'America\'\\|\'Antarctica\'\\|\'Arctic\'\\|\'Asia\'\\|\'Atlantic\'\\|\'Australia\'\\|\'Europe\'\\|\'Indian\'\\|\'Pacific\', city\\: string, subcity\\: string, t_continent\\: string, t_city\\: string, t_subcity\\: string\\}\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysTrue
	'message' => '#^Ternary operator condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_footer\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_header\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_sidebar\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_template_part\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function post_type_archive_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function single_cat_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function single_month_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function single_post_title\\(\\) never returns void so it can be removed from the return type\\.$#',
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
	// identifier: return.unusedType
	'message' => '#^Function single_term_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_get_archives\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_login_form\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
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
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string\\|null will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
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
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between true and array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/kses.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file-mo.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function edit_term_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_next_comments_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_next_posts_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_next_posts_page_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_previous_comments_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_previous_posts_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_previous_posts_page_link\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function next_posts\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function paginate_comments_links\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function previous_posts\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function in_array\\(\\) with arguments \'\', array\\{\'true\', \'1\'\\} and true will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with false will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/load.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 10 and bool will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_imagecreatetruecolor\\(\\) never returns resource so it can be removed from the return type\\.$#',
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
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_object\\(\\) with non\\-empty\\-array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	// identifier: notIdentical.alwaysFalse
	'message' => '#^Strict comparison using \\!\\=\\= between null and null will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-blogs.php',
];
$ignoreErrors[] = [
	// identifier: elseif.alwaysFalse
	'message' => '#^Elseif condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-files.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-files.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function add_existing_user_to_blog\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_active_blog_for_user\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_object\\(\\) with int will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-site.php',
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
	// identifier: booleanOr.alwaysFalse
	'message' => '#^Result of \\|\\| is always false\\.$#',
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
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 5,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: notIdentical.alwaysFalse
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: notIdentical.alwaysFalse
	'message' => '#^Strict comparison using \\!\\=\\= between null and null will always evaluate to false\\.$#',
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
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_bool\\(\\) with int\\|WP_Post\\|null will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function the_title\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function the_title_attribute\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with non\\-falsy\\-string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_numeric\\(\\) with array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_post_custom_keys\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_untrash_post_comments\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Post\\:\\:\\$filter \\(string\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'\' and int will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 0 and int\\<1, max\\> will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_array\\(\\) with non\\-falsy\\-string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: isset.offset
	'message' => '#^Offset string on array\\{\\} in isset\\(\\) does not exist\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'OPTIONS\' and \'GET\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between 2 and \\*NEVER\\* will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: greater.alwaysTrue
	'message' => '#^Comparison operation "\\>" between 1 and 0 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
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
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_null\\(\\) with bool\\|WP_Error will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
];
$ignoreErrors[] = [
	// identifier: nullCoalesce.property
	'message' => '#^Property WP_Post_Type\\:\\:\\$template \\(array\\<array\\>\\) on left side of \\?\\? is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
];
$ignoreErrors[] = [
	// identifier: greater.alwaysTrue
	'message' => '#^Comparison operation "\\>" between 1 and 0 is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.onlyWritten
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$parent_post_type is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-url-details-controller.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between \'false\' and true will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and true will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
];
$ignoreErrors[] = [
	// identifier: else.unreachable
	'message' => '#^Else branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: elseif.unreachable
	'message' => '#^Elseif branch is unreachable because previous condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function _set_preview\\(\\) never returns false so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_save_post_revision\\(\\) never returns void so it can be removed from the return type\\.$#',
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
	// identifier: return.unusedType
	'message' => '#^Function print_late_styles\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.alwaysTrue
	'message' => '#^Result of \\|\\| is always true\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: ternary.alwaysFalse
	'message' => '#^Ternary operator condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-loader.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/script-modules.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function strip_shortcode_tag\\(\\) never returns false so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/shortcodes.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysFalse
	'message' => '#^Negated boolean expression is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps.php',
];
$ignoreErrors[] = [
	// identifier: isset.property
	'message' => '#^Property WP_Query\\:\\:\\$max_num_pages \\(int\\) in isset\\(\\) is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysFalse
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine-processor.php',
];
$ignoreErrors[] = [
	// identifier: function.impossibleType
	'message' => '#^Call to function is_string\\(\\) with non\\-empty\\-array will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysTrue
	'message' => '#^Result of && is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/style-engine/class-wp-style-engine.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function get_term_to_edit\\(\\) never returns int so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function update_object_term_cache\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'template\' on array\\{0\\: false, label\\: string\\|WP_Taxonomy, args\\: array\\{\\}\\}\\|array\\{name\\: string, label\\: string\\|WP_Taxonomy, labels\\: stdClass, description\\: string, public\\: bool, publicly_queryable\\: bool, hierarchical\\: bool, show_ui\\: bool, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: empty.offset
	'message' => '#^Offset \'term_template\' on array\\{0\\: false, label\\: string\\|WP_Taxonomy, args\\: array\\{\\}, template\\: mixed\\}\\|array\\{name\\: string, label\\: string\\|WP_Taxonomy, labels\\: stdClass, description\\: string, public\\: bool, publicly_queryable\\: bool, hierarchical\\: bool, show_ui\\: bool, \\.\\.\\.\\} in empty\\(\\) does not exist\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.rightAlwaysTrue
	'message' => '#^Right side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between null and int\\|string will always evaluate to false\\.$#',
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
	'message' => '#^Function add_theme_support\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function remove_theme_support\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function unregister_default_headers\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysTrue
	'message' => '#^Left side of && is always true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: identical.alwaysFalse
	'message' => '#^Strict comparison using \\=\\=\\= between false and string will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: booleanOr.rightAlwaysFalse
	'message' => '#^Right side of \\|\\| is always false\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/update.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function update_user_caches\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: booleanNot.alwaysTrue
	'message' => '#^Negated boolean expression is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
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
	// identifier: return.unusedType
	'message' => '#^Function wp_sidebar_description\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function wp_widget_description\\(\\) never returns void so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysFalse
	'message' => '#^If condition is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	// identifier: notIdentical.alwaysFalse
	'message' => '#^Strict comparison using \\!\\=\\= between \'\' and \'\' will always evaluate to false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-login.php',
];
$ignoreErrors[] = [
	// identifier: if.alwaysTrue
	'message' => '#^If condition is always true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.leftAlwaysFalse
	'message' => '#^Left side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	// identifier: booleanAnd.alwaysFalse
	'message' => '#^Result of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	// identifier: deadCode.unreachable
	'message' => '#^Unreachable statement \\- code above always terminates\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-settings.php',
];
$ignoreErrors[] = [
	// identifier: return.unusedType
	'message' => '#^Function validate_another_blog_signup\\(\\) never returns null so it can be removed from the return type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-signup.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
