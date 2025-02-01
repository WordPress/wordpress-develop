<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Method WP_Automatic_Updater\\:\\:update\\(\\) should return WP_Error\\|null but returns false\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_Direct\\:\\:group\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_Direct\\:\\:owner\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_FTPext\\:\\:parselisting\\(\\) should return array but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Filesystem_FTPext\\:\\:\\$link \\(resource\\) does not accept FTP\\\\Connection\\|false\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:group\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:owner\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Filesystem_SSH2\\:\\:\\$link \\(resource\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$comment_status \\(bool\\) of method WP_Post_Comments_List_Table\\:\\:get_per_page\\(\\) should be compatible with parameter \\$comment_status \\(string\\) of method WP_Comments_List_Table\\:\\:get_per_page\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-post-comments-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Screen\\:\\:get_help_tab\\(\\) should return array but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Screen\\:\\:get_option\\(\\) should return string but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Screen\\:\\:get_screen_reader_text\\(\\) should return string but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Screen\\:\\:\\$columns \\(int\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'preview\' does not exist on array\\{activate\\: non\\-falsy\\-string\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-themes-list-table.php',
];
$ignoreErrors[] = [
	'message' => '#^Function WP_Filesystem\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	'message' => '#^Function iis7_save_url_rewrite_rules\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Function save_mod_rewrite_rules\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_get_nav_menu_to_edit\\(\\) should return string\\|WP_Error but returns WP_Term\\|false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Function delete_plugins\\(\\) should return bool\\|WP_Error\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_create_category\\(\\) should return int\\|WP_Error but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Function convert_to_screen\\(\\) should return WP_Screen but returns object\\{id\\: string, base\\: string\\}&stdClass\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function delete_theme\\(\\) should return bool\\|WP_Error\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_the_author_posts\\(\\) should return int but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/author-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block_Template\\:\\:\\$author \\(int\\|null\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_the_block_template_html\\(\\) should return string but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function filter_block_kses\\(\\) should return array but returns ArrayAccess&WP_Block_Parser_Block\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'host\' does not exist on array\\{path\\: list\\<string\\>\\|string\\|null\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'port\' does not exist on array\\{path\\: list\\<string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'query\' does not exist on array\\{path\\: list\\<string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'query\' does not exist on array\\{path\\: list\\<string\\>\\|string\\|null\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'scheme\' does not exist on array\\{path\\: list\\<string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	'message' => '#^Function default_topic_count_scale\\(\\) should return int but returns float\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_category_by_path\\(\\) should return array\\|WP_Error\\|WP_Term\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_lvl\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_lvl\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_el\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_el\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Walker_Nav_Menu\\:\\:\\$tree_type \\(string\\) does not accept default value of type array\\<int, string\\>\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Pattern_Categories_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Patterns_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Styles_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-styles-registry.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Block_Type\\:\\:__get\\(\\) should return array\\<string\\>\\|string\\|void\\|null but returns array\\<array\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset int\\<0, max\\> does not exist on WP_Block_List\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Block\\:\\:\\$inner_blocks \\(WP_Block_List\\) does not accept default value of type array\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment\\:\\:\\$comment_karma \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment\\:\\:\\$comment_parent \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment\\:\\:\\$comment_post_ID \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Comment\\:\\:\\$user_id \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$theme_supports \\(array\\<mixed\\>\\) does not accept default value of type string\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$default \\(string\\) does not accept stdClass\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_js_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$validate_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Dependencies\\:\\:\\$all_queued_deps \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	'message' => '#^WpOrg\\\\Requests\\\\Cookie\\\\Jar does not accept WpOrg\\\\Requests\\\\Cookie\\.$#',
	'identifier' => 'offsetAssign.valueType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but returns float\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:write_image\\(\\) should return WP_Error\\|true but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Image_Editor_Imagick\\:\\:\\$image \\(Imagick\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	'message' => '#^Argument of an invalid type stdClass supplied for foreach, only iterables are supported\\.$#',
	'identifier' => 'foreach.nonIterable',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$comment_count \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_author \\(string\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Query\\:\\:setup_postdata\\(\\) should return true but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Query\\:\\:\\$queried_object_id \\(int\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Rewrite\\:\\:\\$rules \\(array\\<string\\>\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Site_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Tax_Query\\:\\:get_sql_for_clause\\(\\) should return array but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:clean_query\\(\\) expects array, WP_Error given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:transform_query\\(\\) expects array, WP_Error given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$query by\\-ref type of method WP_Tax_Query\\:\\:transform_query\\(\\) expects array, array\\<int\\|string\\|WP_Term\\>\\|string given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Tax_Query\\:\\:\\$no_results \\(string\\) does not accept default value of type array\\<string, list\\<string\\>\\>\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Term_Query\\:\\:get_terms\\(\\) should return array\\<int\\|string\\|WP_Term\\>\\|string but returns int\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term_Query\\:\\:\\$terms \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Term\\:\\:\\$term_group \\(int\\) does not accept default value of type string\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$blocks \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$core \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$i18n_schema \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$theme \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user_custom_post_type_id \\(int\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$block_template_folders \\(array\\<string\\>\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$block_theme \\(bool\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$errors \\(WP_Error\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$headers_sanitized \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$name_translated \\(string\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$parent \\(WP_Theme\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$template \\(string\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$textdomain_loaded \\(bool\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Theme\\:\\:\\$theme_root_uri \\(string\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme\\:\\:\\$cache_expiration \\(bool\\) does not accept default value of type int\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Theme\\:\\:\\$cache_expiration \\(bool\\) does not accept int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$matched_token_byte_length by\\-ref type of method WP_Token_Map\\:\\:read_token\\(\\) expects int\\|null, \\(float\\|int\\) given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-token-map.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'identifier' => 'property.defaultValue',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_User\\:\\:\\$roles \\(array\\<string\\>\\) does not accept array\\<string, bool\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	'message' => '#^Method wp_xmlrpc_server\\:\\:wp_newTerm\\(\\) should return int\\|IXR_Error but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$col_info \\(array\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Property wpdb\\:\\:\\$last_query \\(string\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_comment_reply_link\\(\\) should return string\\|false\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset 0 on WP_Post\\.$#',
	'identifier' => 'offsetAccess.nonOffsetAccessible',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _close_comments_for_old_posts\\(\\) should return array but returns WP_Post\\.$#',
	'identifier' => 'return.type',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_page_of_comment\\(\\) should return int\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^Function separate_comments\\(\\) should return array\\<WP_Comment\\> but returns array\\<string, list\\<WP_Comment\\>\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Item_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Post\\:\\:\\$post_author \\(string\\) does not accept int\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Return type \\(void\\|null\\) of method WP_Customize_Nav_Menu_Item_Setting\\:\\:update\\(\\) should be compatible with return type \\(bool\\) of method WP_Customize_Setting\\:\\:update\\(\\)$#',
	'identifier' => 'method.childReturnType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Return type \\(void\\|null\\) of method WP_Customize_Nav_Menu_Setting\\:\\:update\\(\\) should be compatible with return type \\(bool\\) of method WP_Customize_Setting\\:\\:update\\(\\)$#',
	'identifier' => 'method.childReturnType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _oembed_rest_pre_serve_request\\(\\) should return true but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _oembed_rest_pre_serve_request\\(\\) should return true but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_filter_oembed_result\\(\\) should return string but returns false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'basedir\' does not exist on string\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'baseurl\' does not exist on string\\.$#',
	'identifier' => 'offsetAccess.notFound',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_HTML_Decoder\\:\\:read_character_reference\\(\\) should return string\\|false but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 7,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_HTML_Tag_Processor\\:\\:\\$is_closing_tag \\(bool\\) does not accept null\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_dropdown_languages\\(\\) should return string but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Translation_Controller\\:\\:get_entries\\(\\) should return array\\<string, string\\> but returns array\\<string, array\\<string\\>\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_Translation_File\\:\\:entries\\(\\) should return array\\<string, array\\<string\\>\\> but returns array\\<string, string\\>\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Translation_File\\:\\:\\$entries \\(array\\<string, string\\>\\) does not accept array\\<string, array\\<string\\>\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_edit_post_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_edit_term_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_preview_post_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function update_meta_cache\\(\\) should return array\\|false but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_update_nav_menu_item\\(\\) should return int\\|WP_Error but returns WP_Term\\|false\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_set_all_user_settings\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_post_revision_title\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_post_revision_title_expanded\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	'message' => '#^Function get_page_by_path\\(\\) should return array\\|WP_Post\\|null but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_set_post_categories\\(\\) should return array\\|WP_Error\\|false but returns true\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_trash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_untrash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_update_attachment_metadata\\(\\) should return int\\|false but returns bool\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter &\\$result by\\-ref type of function _page_traverse_name\\(\\) expects array\\<string\\>, array given\\.$#',
	'identifier' => 'parameterByRef.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Attachments_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Controller\\:\\:get_object_type\\(\\) should return string but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Font_Faces_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-faces-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc type false of property WP_REST_Font_Families_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'identifier' => 'property.phpDocType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$id \\(int\\) of method WP_REST_Global_Styles_Controller\\:\\:prepare_links\\(\\) should be compatible with parameter \\$post \\(WP_Post\\) of method WP_REST_Posts_Controller\\:\\:prepare_links\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Method WP_REST_Template_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$parent_template_id \\(string\\) of method WP_REST_Template_Revisions_Controller\\:\\:get_parent\\(\\) should be compatible with parameter \\$parent_post_id \\(int\\) of method WP_REST_Revisions_Controller\\:\\:get_parent\\(\\)$#',
	'identifier' => 'method.childParameterType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-revisions-controller.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_preview_post_thumbnail_filter\\(\\) should return array\\|null but returns string\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_delete_post_revision\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Function wp_restore_post_revision\\(\\) should return int\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	'message' => '#^Property WP_Taxonomy\\:\\:\\$labels \\(stdClass\\) does not accept array\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _remove_theme_support\\(\\) should return bool but empty return statement found\\.$#',
	'identifier' => 'return.empty',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns array\\|float\\|int\\|string\\|false\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns null\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property WP_Widget_Media\\:\\:\\$l10n_defaults \\(array\\<string\\>\\) does not accept array\\<string, array\\|string\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/widgets/class-wp-widget-media.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
