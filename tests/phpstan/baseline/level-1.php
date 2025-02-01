<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_email\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$comment_author_url\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function compact\\(\\) contains possibly undefined variable \\$user_id\\.$#',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$results in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/ajax-actions.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$oitar in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-custom-image-header.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$transient in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-debug-data.php',
];
$ignoreErrors[] = [
	'message' => '#^Constructor of class WP_Filesystem_Direct has an unused parameter \\$arg\\.$#',
	'identifier' => 'constructor.unusedParameter',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$children_pages in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$theme in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-theme-install-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$connection_type in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$callback in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$load in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/load-scripts.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$load in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/load-styles.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$parent_file in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$area in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$addl_path in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$object_subtype in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/capabilities.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$block in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-list.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$namespace in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$category_name in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$pattern_name in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$block_type in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$q_values in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$status_clauses in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$root in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$value in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-date-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Constructor of class WP_Feed_Cache_Transient has an unused parameter \\$location\\.$#',
	'identifier' => 'constructor.unusedParameter',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	'message' => '#^Constructor of class WP_Feed_Cache_Transient has an unused parameter \\$type\\.$#',
	'identifier' => 'constructor.unusedParameter',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-feed-cache-transient.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$loader in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-oembed.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$q in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$search in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$status_type_clauses in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$inner in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$modes_str in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$posts in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$newrow in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/general-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$character_reference in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$replacement in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$attachment in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$file_info in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$user_already_exists in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/ms-functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$deprecated in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$tempheaders in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$wp_actions in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$wp_current_filter in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$wp_filters in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$last_error_code in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/class-wp-rest-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$schema in empty\\(\\) is never defined\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$prepared_term in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$prepared_term in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$default in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$object_terms in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$the_parent in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$s in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$old_user_data in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$control_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$form_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$output_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$update_callback in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
