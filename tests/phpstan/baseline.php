<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Variable \\$class in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/class-wp-posts-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$_POST in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/includes/media.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$parent_file in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-admin/themes.php',
];
$ignoreErrors[] = [
	'message' => '#^Function twentyseventeen_edit_link invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
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
	'message' => '#^Function wp_cache_switch_to_blog_fallback invoked with 1 parameter, 0 required\\.$#',
	'identifier' => 'arguments.count',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/cache-compat.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$addl_path in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$namespace in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-parser.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$block_type in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-block-supports.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$loader in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/class-wp-oembed.php',
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
	'message' => '#^Variable \\$deprecated in empty\\(\\) always exists and is always falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/pluggable.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$schema in empty\\(\\) is never defined\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$the_parent in empty\\(\\) always exists and is not falsy\\.$#',
	'identifier' => 'empty.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$s in isset\\(\\) is never defined\\.$#',
	'identifier' => 'isset.variable',
	'count' => 1,
	'path' => __DIR__ . '/../../src/wp-includes/template.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
