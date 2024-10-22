<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Automatic_Updater\\:\\:update\\(\\) should return WP_Error\\|null but returns false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-automatic-updater.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Filesystem_Direct\\:\\:group\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Filesystem_Direct\\:\\:owner\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-direct.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Filesystem_FTPext\\:\\:parselisting\\(\\) should return array but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Filesystem_FTPext\\:\\:\\$link \\(resource\\) does not accept FTP\\\\Connection\\|false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ftpext.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:group\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Filesystem_SSH2\\:\\:owner\\(\\) should return string\\|false but returns int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Filesystem_SSH2\\:\\:\\$link \\(resource\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-filesystem-ssh2.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#1 \\$comment_status \\(bool\\) of method WP_Post_Comments_List_Table\\:\\:get_per_page\\(\\) should be compatible with parameter \\$comment_status \\(string\\) of method WP_Comments_List_Table\\:\\:get_per_page\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-post-comments-list-table.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Screen\\:\\:get_help_tab\\(\\) should return array but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Screen\\:\\:get_option\\(\\) should return string but returns null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Screen\\:\\:get_screen_reader_text\\(\\) should return string but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Screen\\:\\:\\$columns \\(int\\) does not accept string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-screen.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'preview\' does not exist on array\\{activate\\: non\\-falsy\\-string\\}\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/class-wp-themes-list-table.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function WP_Filesystem\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/file.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function iis7_save_url_rewrite_rules\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function save_mod_rewrite_rules\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/misc.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_get_nav_menu_to_edit\\(\\) should return string\\|WP_Error but returns WP_Term\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function delete_plugins\\(\\) should return bool\\|WP_Error\\|null but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/plugin.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_create_category\\(\\) should return int\\|WP_Error but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function convert_to_screen\\(\\) should return WP_Screen but returns object\\{id\\: string, base\\: string\\}&stdClass\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function delete_theme\\(\\) should return bool\\|WP_Error\\|null but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-admin/includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function get_the_author_posts\\(\\) should return int but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/author-template.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Block_Template\\:\\:\\$author \\(int\\|null\\) does not accept string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template-utils.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_the_block_template_html\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/block-template.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function filter_block_kses\\(\\) should return array but returns ArrayAccess&WP_Block_Parser_Block\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_comment_reply_link\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/comment-reply-link.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_comment_template\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_comments_pagination\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/comments-pagination.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_comments_title\\(\\) should return string but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/comments-title.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_footnotes\\(\\) should return string but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/footnotes.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function block_core_navigation_get_classic_menu_fallback_blocks\\(\\) should return array but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/navigation.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function block_core_navigation_get_menu_items_at_location\\(\\) should return array but empty return statement found\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/navigation.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function block_core_navigation_maybe_use_classic_menu_fallback\\(\\) should return array but empty return statement found\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/navigation.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function block_core_navigation_maybe_use_classic_menu_fallback\\(\\) should return array but returns WP_Post\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/navigation.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function block_core_page_list_nest_pages\\(\\) should return array but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/page-list.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function block_core_page_list_render_nested_page_list\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/page-list.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_page_list\\(\\) should return string but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/page-list.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_post_comments_form\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/post-comments-form.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function block_core_query_disable_enhanced_pagination\\(\\) should return string but returns array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/query.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_site_tagline\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/site-tagline.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function render_block_core_site_title\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/blocks/site-title.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'host\' does not exist on array\\{path\\: array\\<int, string\\>\\|string\\|null\\}\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'port\' does not exist on array\\{path\\: array\\<int, string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'query\' does not exist on array\\{path\\: array\\<int, string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'query\' does not exist on array\\{path\\: array\\<int, string\\>\\|string\\|null\\}\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'scheme\' does not exist on array\\{path\\: array\\<int, string\\>\\|string\\|null, host\\?\\: string\\}\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/canonical.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function default_topic_count_scale\\(\\) should return int but returns float\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category-template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_category_by_path\\(\\) should return array\\|WP_Error\\|WP_Term\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/category.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function Avifinfo\\\\read\\(\\) should return Avifinfo\\\\binary but returns string\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Box\\:\\:parse\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 11,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Features\\:\\:get_item_features\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Features\\:\\:get_primary_item_features\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Parser\\:\\:parse_ipco\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 23,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Parser\\:\\:parse_iprp\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 9,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Parser\\:\\:parse_iref\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 7,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Avifinfo\\\\Parser\\:\\:parse_meta\\(\\) should return Avifinfo\\\\Status but returns int\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/../../../src/wp-includes/class-avif-info.php',
];
$ignoreErrors[] = [
	// identifier: return.void
	'message' => '#^Method POP3\\:\\:__construct\\(\\) with return type void returns true but should not return anything\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-pop3.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_lvl\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#3 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_lvl\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_lvl\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:end_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:end_el\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#4 \\$args \\(stdClass\\) of method Walker_Nav_Menu\\:\\:start_el\\(\\) should be compatible with parameter \\$args \\(array\\) of method Walker\\:\\:start_el\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property Walker_Nav_Menu\\:\\:\\$tree_type \\(string\\) does not accept default value of type array\\<int, string\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-walker-nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Block_Pattern_Categories_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-pattern-categories-registry.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Block_Patterns_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-patterns-registry.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Block_Styles_Registry\\:\\:get_registered\\(\\) should return array but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-styles-registry.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Block_Type\\:\\:__get\\(\\) should return array\\<string\\>\\|string\\|void\\|null but returns array\\<array\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block-type.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset int\\<0, max\\> does not exist on WP_Block_List\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Block\\:\\:\\$inner_blocks \\(WP_Block_List\\) does not accept default value of type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-block.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment\\:\\:\\$comment_karma \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment\\:\\:\\$comment_parent \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment\\:\\:\\$comment_post_ID \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Comment\\:\\:\\$user_id \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-comment.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Control\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Customize_Control\\:\\:\\$settings \\(array\\) does not accept string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-control.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Panel\\:\\:\\$theme_supports \\(array\\) does not accept default value of type string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-panel.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Section\\:\\:\\$active_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-section.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$default \\(string\\) does not accept stdClass\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$sanitize_js_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Customize_Setting\\:\\:\\$validate_callback \\(callable\\(\\)\\: mixed\\) does not accept default value of type \'\'\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-customize-setting.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Dependencies\\:\\:\\$all_queued_deps \\(array\\) does not accept null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-dependencies.php',
];
$ignoreErrors[] = [
	// identifier: offsetAssign.valueType
	'message' => '#^WpOrg\\\\Requests\\\\Cookie\\\\Jar does not accept WpOrg\\\\Requests\\\\Cookie\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-http.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:set_imagick_time_limit\\(\\) should return int\\|null but returns float\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Image_Editor_Imagick\\:\\:write_image\\(\\) should return WP_Error\\|true but returns bool\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Image_Editor_Imagick\\:\\:\\$image \\(Imagick\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-image-editor-imagick.php',
];
$ignoreErrors[] = [
	// identifier: foreach.nonIterable
	'message' => '#^Argument of an invalid type stdClass supplied for foreach, only iterables are supported\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post-type.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Post\\:\\:\\$comment_count \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Post\\:\\:\\$post_author \\(string\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-post.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Method WP_Query\\:\\:setup_postdata\\(\\) should return true but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Query\\:\\:\\$queried_object_id \\(int\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-query.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Rewrite\\:\\:\\$rules \\(array\\<string\\>\\) does not accept string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-rewrite.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Site_Query\\:\\:\\$date_query \\(WP_Date_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Site_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-site-query.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Tax_Query\\:\\:get_sql_for_clause\\(\\) should return array but returns string\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Static property WP_Tax_Query\\:\\:\\$no_results \\(string\\) does not accept default value of type array\\<string, array\\<int, string\\>\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-tax-query.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Term_Query\\:\\:get_terms\\(\\) should return array\\<int\\|string\\|WP_Term\\>\\|string but returns int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Term_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Term_Query\\:\\:\\$terms \\(array\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term-query.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_Term\\:\\:\\$term_group \\(int\\) does not accept default value of type string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-term.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$blocks \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$core \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$i18n_schema \\(array\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$theme \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user \\(WP_Theme_JSON\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme_JSON_Resolver\\:\\:\\$user_custom_post_type_id \\(int\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme-json-resolver.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$block_template_folders \\(array\\<string\\>\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$block_theme \\(bool\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$errors \\(WP_Error\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$headers_sanitized \\(array\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$name_translated \\(string\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$parent \\(WP_Theme\\) does not accept null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$template \\(string\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$textdomain_loaded \\(bool\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Theme\\:\\:\\$theme_root_uri \\(string\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Static property WP_Theme\\:\\:\\$cache_expiration \\(bool\\) does not accept default value of type int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Static property WP_Theme\\:\\:\\$cache_expiration \\(bool\\) does not accept int\\<min, \\-1\\>\\|int\\<1, max\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-theme.php',
];
$ignoreErrors[] = [
	// identifier: property.defaultValue
	'message' => '#^Property WP_User_Query\\:\\:\\$meta_query \\(WP_Meta_Query\\) does not accept default value of type false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user-query.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_User\\:\\:\\$roles \\(array\\<string\\>\\) does not accept array\\<string, bool\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-user.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method wp_xmlrpc_server\\:\\:wp_newTerm\\(\\) should return int\\|IXR_Error but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wp-xmlrpc-server.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property wpdb\\:\\:\\$col_info \\(array\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property wpdb\\:\\:\\$last_query \\(string\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/class-wpdb.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_comment_reply_link\\(\\) should return string\\|false\\|null but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/comment-template.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.nonOffsetAccessible
	'message' => '#^Cannot access offset 0 on WP_Post\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _close_comments_for_old_posts\\(\\) should return array but returns WP_Post\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_page_of_comment\\(\\) should return int\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function separate_comments\\(\\) should return array\\<WP_Comment\\> but returns array\\<string, array\\<int, WP_Comment\\>\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/comment.php',
];
$ignoreErrors[] = [
	// identifier: property.phpDocType
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Item_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Post\\:\\:\\$post_author \\(string\\) does not accept int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: method.childReturnType
	'message' => '#^Return type \\(void\\|null\\) of method WP_Customize_Nav_Menu_Item_Setting\\:\\:update\\(\\) should be compatible with return type \\(bool\\) of method WP_Customize_Setting\\:\\:update\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
];
$ignoreErrors[] = [
	// identifier: property.phpDocType
	'message' => '#^PHPDoc type array of property WP_Customize_Nav_Menu_Setting\\:\\:\\$default is not covariant with PHPDoc type string of overridden property WP_Customize_Setting\\:\\:\\$default\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: method.childReturnType
	'message' => '#^Return type \\(void\\|null\\) of method WP_Customize_Nav_Menu_Setting\\:\\:update\\(\\) should be compatible with return type \\(bool\\) of method WP_Customize_Setting\\:\\:update\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/customize/class-wp-customize-nav-menu-setting.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _oembed_rest_pre_serve_request\\(\\) should return true but returns bool\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _oembed_rest_pre_serve_request\\(\\) should return true but returns string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_filter_oembed_result\\(\\) should return string but returns false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/embed.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'basedir\' does not exist on string\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	// identifier: offsetAccess.notFound
	'message' => '#^Offset \'baseurl\' does not exist on string\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/fonts.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_HTML_Decoder\\:\\:read_character_reference\\(\\) should return string\\|false but returns null\\.$#',
	'count' => 7,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-decoder.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_HTML_Tag_Processor\\:\\:\\$is_closing_tag \\(bool\\) does not accept null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/html-api/class-wp-html-tag-processor.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function wp_dropdown_languages\\(\\) should return string but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Translation_Controller\\:\\:get_entries\\(\\) should return array\\<string, string\\> but returns array\\<string, array\\<string\\>\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_Translation_File\\:\\:entries\\(\\) should return array\\<string, array\\<string\\>\\> but returns array\\<string, string\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Translation_File\\:\\:\\$entries \\(array\\<string, string\\>\\) does not accept array\\<string, array\\<string\\>\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/l10n/class-wp-translation-file.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_edit_post_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_edit_term_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_preview_post_link\\(\\) should return string\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/link-template.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function update_meta_cache\\(\\) should return array\\|false but returns bool\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/meta.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_update_nav_menu_item\\(\\) should return int\\|WP_Error but returns WP_Term\\|false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/nav-menu.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function wp_set_all_user_settings\\(\\) should return bool\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/option.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_post_revision_title\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_post_revision_title_expanded\\(\\) should return string\\|false but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post-template.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function get_page_by_path\\(\\) should return array\\|WP_Post\\|null but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_set_post_categories\\(\\) should return array\\|WP_Error\\|false but returns true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_trash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_untrash_post\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_update_attachment_metadata\\(\\) should return int\\|false but returns bool\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/post.php',
];
$ignoreErrors[] = [
	// identifier: property.phpDocType
	'message' => '#^PHPDoc type false of property WP_REST_Attachments_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_REST_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_REST_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_REST_Controller\\:\\:get_object_type\\(\\) should return string but returns null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.phpDocType
	'message' => '#^PHPDoc type false of property WP_REST_Font_Faces_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-faces-controller.php',
];
$ignoreErrors[] = [
	// identifier: property.phpDocType
	'message' => '#^PHPDoc type false of property WP_REST_Font_Families_Controller\\:\\:\\$allow_batch is not covariant with PHPDoc type array of overridden property WP_REST_Posts_Controller\\:\\:\\$allow_batch\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#1 \\$id \\(int\\) of method WP_REST_Global_Styles_Controller\\:\\:prepare_links\\(\\) should be compatible with parameter \\$post \\(WP_Post\\) of method WP_REST_Posts_Controller\\:\\:prepare_links\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method WP_REST_Template_Autosaves_Controller\\:\\:get_item\\(\\) should return WP_Error\\|WP_Post but returns WP_REST_Response\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_REST_Template_Autosaves_Controller\\:\\:\\$revisions_controller \\(WP_REST_Revisions_Controller\\) does not accept WP_REST_Controller\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
];
$ignoreErrors[] = [
	// identifier: method.childParameterType
	'message' => '#^Parameter \\#1 \\$parent_template_id \\(string\\) of method WP_REST_Template_Revisions_Controller\\:\\:get_parent\\(\\) should be compatible with parameter \\$parent_post_id \\(int\\) of method WP_REST_Revisions_Controller\\:\\:get_parent\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/rest-api/endpoints/class-wp-rest-template-revisions-controller.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _wp_preview_post_thumbnail_filter\\(\\) should return array\\|null but returns string\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_delete_post_revision\\(\\) should return WP_Post\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function wp_restore_post_revision\\(\\) should return int\\|false\\|null but returns array\\{\\}\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/revision.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property WP_Taxonomy\\:\\:\\$labels \\(stdClass\\) does not accept array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/taxonomy.php',
];
$ignoreErrors[] = [
	// identifier: return.empty
	'message' => '#^Function _remove_theme_support\\(\\) should return bool but empty return statement found\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../../../src/wp-includes/theme.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns array\\|float\\|int\\|string\\|false\\|null\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Function _wp_get_current_user\\(\\) should return WP_User but returns null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../../../src/wp-includes/user.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
