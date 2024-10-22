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
	'message' => '#^Variable \\$transient in isset\\(\\) is never defined\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	// identifier: constructor.unusedParameter
	'message' => '#^Constructor of class WP_Filesystem_Direct has an unused parameter \\$arg\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	// identifier: isset.variable
	'message' => '#^Variable \\$children_pages in isset\\(\\) is never defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$theme in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$connection_type in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$callback in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$load in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/load-scripts.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$load in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/load-styles.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$parent_file in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$inner_blocks in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/navigation.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$addl_path in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$object_subtype in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$PopArray in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-pop3.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$banner in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-pop3.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$block_type in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$q_values in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$status_clauses in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
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
	'message' => '#^Variable \\$inner in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$modes_str in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$posts in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$attachment in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$deprecated in empty\\(\\) always exists and is always falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$tempheaders in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$last_error_code in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/class-wp-rest-server.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$schema in empty\\(\\) is never defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$prepared_term in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$prepared_term in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$object_terms in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$the_parent in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
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
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$control_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$form_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$output_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	// identifier: empty.variable
	'message' => '#^Variable \\$update_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
