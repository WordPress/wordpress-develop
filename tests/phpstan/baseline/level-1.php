<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_email\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_url\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: variable.undefined
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$user_id\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$oitar in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	// identifier: constructor.unusedParameter
	'message' => '#^Constructor of class WP_Filesystem_Direct has an unused parameter \\$arg\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$parent_file in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$area in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$addl_path in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$namespace in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$block_type in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	// identifier: constructor.unusedParameter
	'message' => '#^Constructor of class WP_Feed_Cache_Transient has an unused parameter \\$location\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	// identifier: constructor.unusedParameter
	'message' => '#^Constructor of class WP_Feed_Cache_Transient has an unused parameter \\$type\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$loader in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$q in isset\\(\\) is never defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$search in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$status_type_clauses in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$modes_str in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$newrow in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$user_already_exists in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$deprecated in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$schema in empty\\(\\) is never defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$default in isset\\(\\) always exists and is not nullable\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$s in isset\\(\\) is never defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/template.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$old_user_data in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
