<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress\Sniffs\WP;

use WordPressCS\WordPress\AbstractFunctionRestrictionsSniff;

/**
 * Restricts the use of various deprecated WordPress functions and suggests alternatives.
 *
 * This sniff will throw an error when usage of deprecated functions is detected
 * if the function was deprecated before the minimum supported WP version;
 * a warning otherwise.
 * By default, it is set to presume that a project will support the current
 * WP version and up to three releases before.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.11.0
 * @since   0.13.0 Class name changed: this class is now namespaced.
 * @since   0.14.0 Now has the ability to handle minimum supported WP version
 *                 being provided via the command-line or as as <config> value
 *                 in a custom ruleset.
 *
 * @uses    \WordPressCS\WordPress\Sniff::$minimum_supported_version
 */
class DeprecatedFunctionsSniff extends AbstractFunctionRestrictionsSniff {

	/**
	 * List of deprecated functions with alternative when available.
	 *
	 * To be updated after every major release.
	 * Last updated for WordPress 4.8.
	 *
	 * Version numbers should be fully qualified.
	 * Replacement functions should have parentheses.
	 *
	 * To retrieve a function list for comparison, the following tool is available:
	 * https://github.com/JDGrimes/wp-deprecated-code-scanner
	 *
	 * @var array
	 */
	private $deprecated_functions = array(

		// WP 0.71.
		'the_category_head' => array(
			'alt'     => 'get_the_category_by_ID()',
			'version' => '0.71',
		),
		'the_category_ID' => array(
			'alt'     => 'get_the_category()',
			'version' => '0.71',
		),

		// WP 1.2.0.
		'permalink_link' => array(
			'alt'     => 'the_permalink()',
			'version' => '1.2.0',
		),

		// WP 1.5.0.
		'start_wp' => array(
			// Verified correct alternative.
			'alt'     => 'the Loop',
			'version' => '1.5.0',
		),

		// WP 1.5.1.
		'get_postdata' => array(
			'alt'     => 'get_post()',
			'version' => '1.5.1',
		),

		// WP 2.0.0.
		'create_user' => array(
			'alt'     => 'wp_create_user()',
			'version' => '2.0.0',
		),
		'next_post' => array(
			'alt'     => 'next_post_link()',
			'version' => '2.0.0',
		),
		'previous_post' => array(
			'alt'     => 'previous_post_link()',
			'version' => '2.0.0',
		),
		'user_can_create_draft' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_create_post' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_delete_post' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_delete_post_comments' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_edit_post' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_edit_post_comments' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_edit_post_date' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_edit_user' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),
		'user_can_set_post_date' => array(
			'alt'     => 'current_user_can()',
			'version' => '2.0.0',
		),

		// WP 2.1.0.
		'dropdown_cats' => array(
			'alt'     => 'wp_dropdown_categories()',
			'version' => '2.1.0',
		),
		'get_archives' => array(
			'alt'     => 'wp_get_archives()',
			'version' => '2.1.0',
		),
		'get_author_link' => array(
			'alt'     => 'get_author_posts_url()',
			'version' => '2.1.0',
		),
		'get_autotoggle' => array(
			'alt'     => '',
			'version' => '2.1.0',
		),
		'get_link' => array(
			'alt'     => 'get_bookmark()',
			'version' => '2.1.0',
		),
		'get_linkcatname' => array(
			'alt'     => 'get_category()',
			'version' => '2.1.0',
		),
		'get_linkobjects' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_linkobjectsbyname' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_linkrating' => array(
			'alt'     => 'sanitize_bookmark_field()',
			'version' => '2.1.0',
		),
		'get_links' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_links_list' => array(
			'alt'     => 'wp_list_bookmarks()',
			'version' => '2.1.0',
		),
		'get_links_withrating' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_linksbyname' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_linksbyname_withrating' => array(
			'alt'     => 'get_bookmarks()',
			'version' => '2.1.0',
		),
		'get_settings' => array(
			'alt'     => 'get_option()',
			'version' => '2.1.0',
		),
		'link_pages' => array(
			'alt'     => 'wp_link_pages()',
			'version' => '2.1.0',
		),
		'links_popup_script' => array(
			'alt'     => '',
			'version' => '2.1.0',
		),
		'list_authors' => array(
			'alt'     => 'wp_list_authors()',
			'version' => '2.1.0',
		),
		'list_cats' => array(
			'alt'     => 'wp_list_categories()',
			'version' => '2.1.0',
		),
		'tinymce_include' => array(
			'alt'     => 'wp_editor()',
			'version' => '2.1.0',
		),
		'wp_get_links' => array(
			'alt'     => 'wp_list_bookmarks()',
			'version' => '2.1.0',
		),
		'wp_get_linksbyname' => array(
			'alt'     => 'wp_list_bookmarks()',
			'version' => '2.1.0',
		),
		'wp_get_post_cats' => array(
			'alt'     => 'wp_get_post_categories()',
			'version' => '2.1.0',
		),
		'wp_list_cats' => array(
			'alt'     => 'wp_list_categories()',
			'version' => '2.1.0',
		),
		'wp_set_post_cats' => array(
			'alt'     => 'wp_set_post_categories()',
			'version' => '2.1.0',
		),

		// WP 2.2.0.
		'comments_rss' => array(
			'alt'     => 'get_post_comments_feed_link()',
			'version' => '2.2.0',
		),

		// WP 2.3.0.
		'permalink_single_rss' => array(
			'alt'     => 'the_permalink_rss()',
			'version' => '2.3.0',
		),

		// WP 2.5.0.
		'comments_rss_link' => array(
			'alt'     => 'post_comments_feed_link()',
			'version' => '2.5.0',
		),
		'documentation_link' => array(
			'alt'     => '',
			'version' => '2.5.0',
		),
		'get_attachment_icon' => array(
			'alt'     => 'wp_get_attachment_image()',
			'version' => '2.5.0',
		),
		'get_attachment_icon_src' => array(
			'alt'     => 'wp_get_attachment_image_src()',
			'version' => '2.5.0',
		),
		'get_attachment_innerHTML' => array(
			'alt'     => 'wp_get_attachment_image()',
			'version' => '2.5.0',
		),
		'get_author_rss_link' => array(
			'alt'     => 'get_author_feed_link()',
			'version' => '2.5.0',
		),
		'get_category_rss_link' => array(
			'alt'     => 'get_category_feed_link()',
			'version' => '2.5.0',
		),
		'get_the_attachment_link' => array(
			'alt'     => 'wp_get_attachment_link()',
			'version' => '2.5.0',
		),
		'gzip_compression' => array(
			'alt'     => '',
			'version' => '2.5.0',
		),
		'wp_clearcookie' => array(
			'alt'     => 'wp_clear_auth_cookie()',
			'version' => '2.5.0',
		),
		'wp_get_cookie_login' => array(
			'alt'     => '',
			'version' => '2.5.0',
		),
		'wp_login' => array(
			'alt'     => 'wp_signon()',
			'version' => '2.5.0',
		),
		'wp_setcookie' => array(
			'alt'     => 'wp_set_auth_cookie()',
			'version' => '2.5.0',
		),

		// WP 2.6.0.
		'dropdown_categories' => array(
			'alt'     => 'wp_category_checklist()',
			'version' => '2.6.0',
		),
		'dropdown_link_categories' => array(
			'alt'     => 'wp_link_category_checklist()',
			'version' => '2.6.0',
		),

		// WP 2.7.0.
		'get_commentdata' => array(
			'alt'     => 'get_comment()',
			'version' => '2.7.0',
		),
		// This is a method i.e. WP_Filesystem_Base::find_base_dir() See #731.
		'find_base_dir' => array(
			'alt'     => 'WP_Filesystem::abspath()',
			'version' => '2.7.0',
		),
		// This is a method i.e. WP_Filesystem_Base::get_base_dir() See #731.
		'get_base_dir' => array(
			'alt'     => 'WP_Filesystem::abspath()',
			'version' => '2.7.0',
		),

		// WP 2.8.0.
		'__ngettext' => array(
			'alt'     => '_n()',
			'version' => '2.8.0',
		),
		'__ngettext_noop' => array(
			'alt'     => '_n_noop()',
			'version' => '2.8.0',
		),
		'attribute_escape' => array(
			'alt'     => 'esc_attr()',
			'version' => '2.8.0',
		),
		'get_author_name' => array(
			'alt'     => 'get_the_author_meta(\'display_name\')',
			'version' => '2.8.0',
		),
		'get_category_children' => array(
			'alt'     => 'get_term_children()',
			'version' => '2.8.0',
		),
		'get_catname' => array(
			'alt'     => 'get_cat_name()',
			'version' => '2.8.0',
		),
		'get_the_author_aim' => array(
			'alt'     => 'get_the_author_meta(\'aim\')',
			'version' => '2.8.0',
		),
		'get_the_author_description' => array(
			'alt'     => 'get_the_author_meta(\'description\')',
			'version' => '2.8.0',
		),
		'get_the_author_email' => array(
			'alt'     => 'get_the_author_meta(\'email\')',
			'version' => '2.8.0',
		),
		'get_the_author_firstname' => array(
			'alt'     => 'get_the_author_meta(\'first_name\')',
			'version' => '2.8.0',
		),
		'get_the_author_icq' => array(
			'alt'     => 'get_the_author_meta(\'icq\')',
			'version' => '2.8.0',
		),
		'get_the_author_ID' => array(
			'alt'     => 'get_the_author_meta(\'ID\')',
			'version' => '2.8.0',
		),
		'get_the_author_lastname' => array(
			'alt'     => 'get_the_author_meta(\'last_name\')',
			'version' => '2.8.0',
		),
		'get_the_author_login' => array(
			'alt'     => 'get_the_author_meta(\'login\')',
			'version' => '2.8.0',
		),
		'get_the_author_msn' => array(
			'alt'     => 'get_the_author_meta(\'msn\')',
			'version' => '2.8.0',
		),
		'get_the_author_nickname' => array(
			'alt'     => 'get_the_author_meta(\'nickname\')',
			'version' => '2.8.0',
		),
		'get_the_author_url' => array(
			'alt'     => 'get_the_author_meta(\'url\')',
			'version' => '2.8.0',
		),
		'get_the_author_yim' => array(
			'alt'     => 'get_the_author_meta(\'yim\')',
			'version' => '2.8.0',
		),
		'js_escape' => array(
			'alt'     => 'esc_js()',
			'version' => '2.8.0',
		),
		'register_sidebar_widget' => array(
			'alt'     => 'wp_register_sidebar_widget()',
			'version' => '2.8.0',
		),
		'register_widget_control' => array(
			'alt'     => 'wp_register_widget_control()',
			'version' => '2.8.0',
		),
		'sanitize_url' => array(
			'alt'     => 'esc_url_raw()',
			'version' => '2.8.0',
		),
		'the_author_aim' => array(
			'alt'     => 'the_author_meta(\'aim\')',
			'version' => '2.8.0',
		),
		'the_author_description' => array(
			'alt'     => 'the_author_meta(\'description\')',
			'version' => '2.8.0',
		),
		'the_author_email' => array(
			'alt'     => 'the_author_meta(\'email\')',
			'version' => '2.8.0',
		),
		'the_author_firstname' => array(
			'alt'     => 'the_author_meta(\'first_name\')',
			'version' => '2.8.0',
		),
		'the_author_icq' => array(
			'alt'     => 'the_author_meta(\'icq\')',
			'version' => '2.8.0',
		),
		'the_author_ID' => array(
			'alt'     => 'the_author_meta(\'ID\')',
			'version' => '2.8.0',
		),
		'the_author_lastname' => array(
			'alt'     => 'the_author_meta(\'last_name\')',
			'version' => '2.8.0',
		),
		'the_author_login' => array(
			'alt'     => 'the_author_meta(\'login\')',
			'version' => '2.8.0',
		),
		'the_author_msn' => array(
			'alt'     => 'the_author_meta(\'msn\')',
			'version' => '2.8.0',
		),
		'the_author_nickname' => array(
			'alt'     => 'the_author_meta(\'nickname\')',
			'version' => '2.8.0',
		),
		'the_author_url' => array(
			'alt'     => 'the_author_meta(\'url\')',
			'version' => '2.8.0',
		),
		'the_author_yim' => array(
			'alt'     => 'the_author_meta(\'yim\')',
			'version' => '2.8.0',
		),
		'unregister_sidebar_widget' => array(
			'alt'     => 'wp_unregister_sidebar_widget()',
			'version' => '2.8.0',
		),
		'unregister_widget_control' => array(
			'alt'     => 'wp_unregister_widget_control()',
			'version' => '2.8.0',
		),
		'wp_specialchars' => array(
			'alt'     => 'esc_html()',
			'version' => '2.8.0',
		),

		// WP 2.9.0.
		'_c' => array(
			'alt'     => '_x()',
			'version' => '2.9.0',
		),
		'_nc' => array(
			'alt'     => '_nx()',
			'version' => '2.9.0',
		),
		'get_real_file_to_edit' => array(
			'alt'     => '',
			'version' => '2.9.0',
		),
		'make_url_footnote' => array(
			'alt'     => '',
			'version' => '2.9.0',
		),
		'the_content_rss' => array(
			'alt'     => 'the_content_feed()',
			'version' => '2.9.0',
		),
		'translate_with_context' => array(
			'alt'     => '_x()',
			'version' => '2.9.0',
		),

		// WP 3.0.0.
		'activate_sitewide_plugin' => array(
			'alt'     => 'activate_plugin()',
			'version' => '3.0.0',
		),
		'add_option_update_handler' => array(
			'alt'     => 'register_setting()',
			'version' => '3.0.0',
		),
		'automatic_feed_links' => array(
			'alt'     => 'add_theme_support( \'automatic-feed-links\' )',
			'version' => '3.0.0',
		),
		'clean_url' => array(
			'alt'     => 'esc_url()',
			'version' => '3.0.0',
		),
		'clear_global_post_cache' => array(
			'alt'     => 'clean_post_cache()',
			'version' => '3.0.0',
		),
		'codepress_footer_js' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'codepress_get_lang' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'deactivate_sitewide_plugin' => array(
			'alt'     => 'deactivate_plugin()',
			'version' => '3.0.0',
		),
		'delete_usermeta' => array(
			'alt'     => 'delete_user_meta()',
			'version' => '3.0.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'funky_javascript_callback' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'funky_javascript_fix' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'generate_random_password' => array(
			'alt'     => 'wp_generate_password()',
			'version' => '3.0.0',
		),
		'get_alloptions' => array(
			'alt'     => 'wp_load_alloptions()',
			'version' => '3.0.0',
		),
		'get_blog_list' => array(
			'alt'     => 'wp_get_sites()',
			'version' => '3.0.0',
		),
		'get_most_active_blogs' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'get_profile' => array(
			'alt'     => 'get_the_author_meta()',
			'version' => '3.0.0',
		),
		'get_user_details' => array(
			'alt'     => 'get_user_by()',
			'version' => '3.0.0',
		),
		'get_usermeta' => array(
			'alt'     => 'get_user_meta()',
			'version' => '3.0.0',
		),
		'get_usernumposts' => array(
			'alt'     => 'count_user_posts()',
			'version' => '3.0.0',
		),
		'graceful_fail' => array(
			'alt'     => 'wp_die()',
			'version' => '3.0.0',
		),
		// Verified version & alternative.
		'install_blog_defaults' => array(
			'alt'     => 'wp_install_defaults',
			'version' => '3.0.0',
		),
		'is_main_blog' => array(
			'alt'     => 'is_main_site()',
			'version' => '3.0.0',
		),
		'is_site_admin' => array(
			'alt'     => 'is_super_admin()',
			'version' => '3.0.0',
		),
		'is_taxonomy' => array(
			'alt'     => 'taxonomy_exists()',
			'version' => '3.0.0',
		),
		'is_term' => array(
			'alt'     => 'term_exists()',
			'version' => '3.0.0',
		),
		'is_wpmu_sitewide_plugin' => array(
			'alt'     => 'is_network_only_plugin()',
			'version' => '3.0.0',
		),
		'mu_options' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'remove_option_update_handler' => array(
			'alt'     => 'unregister_setting()',
			'version' => '3.0.0',
		),
		'set_current_user' => array(
			'alt'     => 'wp_set_current_user()',
			'version' => '3.0.0',
		),
		'update_usermeta' => array(
			'alt'     => 'update_user_meta()',
			'version' => '3.0.0',
		),
		'use_codepress' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),
		'validate_email' => array(
			'alt'     => 'is_email()',
			'version' => '3.0.0',
		),
		'wp_dropdown_cats' => array(
			'alt'     => 'wp_dropdown_categories()',
			'version' => '3.0.0',
		),
		'wp_shrink_dimensions' => array(
			'alt'     => 'wp_constrain_dimensions()',
			'version' => '3.0.0',
		),
		'wpmu_checkAvailableSpace' => array(
			'alt'     => 'is_upload_space_available()',
			'version' => '3.0.0',
		),
		'wpmu_menu' => array(
			'alt'     => '',
			'version' => '3.0.0',
		),

		// WP 3.1.0.
		'get_author_user_ids' => array(
			'alt'     => 'get_users()',
			'version' => '3.1.0',
		),
		'get_dashboard_blog' => array(
			'alt'     => 'get_site()',
			'version' => '3.1.0',
		),
		'get_editable_authors' => array(
			'alt'     => 'get_users()',
			'version' => '3.1.0',
		),
		'get_editable_user_ids' => array(
			'alt'     => 'get_users()',
			'version' => '3.1.0',
		),
		'get_nonauthor_user_ids' => array(
			'alt'     => 'get_users()',
			'version' => '3.1.0',
		),
		'get_others_drafts' => array(
			'alt'     => '',
			'version' => '3.1.0',
		),
		'get_others_pending' => array(
			'alt'     => '',
			'version' => '3.1.0',
		),
		'get_others_unpublished_posts' => array(
			'alt'     => '',
			'version' => '3.1.0',
		),
		'get_users_of_blog' => array(
			'alt'     => 'get_users()',
			'version' => '3.1.0',
		),
		'install_themes_feature_list' => array(
			'alt'     => 'get_theme_feature_list()',
			'version' => '3.1.0',
		),
		'is_plugin_page' => array(
			// Verified correct alternative.
			'alt'     => 'global $plugin_page and/or get_plugin_page_hookname() hooks',
			'version' => '3.1.0',
		),
		'update_category_cache' => array(
			'alt'     => '',
			'version' => '3.1.0',
		),

		// WP 3.2.0.
		'favorite_actions' => array(
			'alt'     => 'WP_Admin_Bar',
			'version' => '3.2.0',
		),
		'wp_dashboard_quick_press_output' => array(
			'alt'     => 'wp_dashboard_quick_press()',
			'version' => '3.2.0',
		),
		'wp_timezone_supported' => array(
			'alt'     => '',
			'version' => '3.2.0',
		),

		// WP 3.3.0.
		'add_contextual_help' => array(
			'alt'     => 'get_current_screen()->add_help_tab()',
			'version' => '3.3.0',
		),
		'get_boundary_post_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'get_index_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'get_parent_post_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'get_user_by_email' => array(
			'alt'     => 'get_user_by(\'email\')',
			'version' => '3.3.0',
		),
		'get_user_metavalues' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'get_userdatabylogin' => array(
			'alt'     => 'get_user_by(\'login\')',
			'version' => '3.3.0',
		),
		'index_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'is_blog_user' => array(
			'alt'     => 'is_user_member_of_blog()',
			'version' => '3.3.0',
		),
		'media_upload_audio' => array(
			'alt'     => 'wp_media_upload_handler()',
			'version' => '3.3.0',
		),
		'media_upload_file' => array(
			'alt'     => 'wp_media_upload_handler()',
			'version' => '3.3.0',
		),
		'media_upload_image' => array(
			'alt'     => 'wp_media_upload_handler()',
			'version' => '3.3.0',
		),
		'media_upload_video' => array(
			'alt'     => 'wp_media_upload_handler()',
			'version' => '3.3.0',
		),
		'parent_post_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'sanitize_user_object' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'screen_layout' => array(
			'alt'     => '$current_screen->render_screen_layout()',
			'version' => '3.3.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'screen_meta' => array(
			'alt'     => '$current_screen->render_screen_meta()',
			'version' => '3.3.0',
		),
		'screen_options' => array(
			'alt'     => '$current_screen->render_per_page_options()',
			'version' => '3.3.0',
		),
		'start_post_rel_link' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'the_editor' => array(
			'alt'     => 'wp_editor()',
			'version' => '3.3.0',
		),
		'type_url_form_audio' => array(
			'alt'     => 'wp_media_insert_url_form(\'audio\')',
			'version' => '3.3.0',
		),
		'type_url_form_file' => array(
			'alt'     => 'wp_media_insert_url_form(\'file\')',
			'version' => '3.3.0',
		),
		'type_url_form_image' => array(
			'alt'     => 'wp_media_insert_url_form(\'image\')',
			'version' => '3.3.0',
		),
		'type_url_form_video' => array(
			'alt'     => 'wp_media_insert_url_form(\'video\')',
			'version' => '3.3.0',
		),
		'wp_admin_bar_dashboard_view_site_menu' => array(
			'alt'     => '',
			'version' => '3.3.0',
		),
		'wp_preload_dialogs' => array(
			'alt'     => 'wp_editor()',
			'version' => '3.3.0',
		),
		'wp_print_editor_js' => array(
			'alt'     => 'wp_editor()',
			'version' => '3.3.0',
		),
		'wp_quicktags' => array(
			'alt'     => 'wp_editor()',
			'version' => '3.3.0',
		),
		'wp_tiny_mce' => array(
			'alt'     => 'wp_editor()',
			'version' => '3.3.0',
		),
		'wpmu_admin_do_redirect' => array(
			'alt'     => 'wp_redirect()',
			'version' => '3.3.0',
		),
		'wpmu_admin_redirect_add_updated_param' => array(
			'alt'     => 'add_query_arg()',
			'version' => '3.3.0',
		),

		// WP 3.4.0.
		'add_custom_background' => array(
			'alt'     => 'add_theme_support( \'custom-background\', $args )',
			'version' => '3.4.0',
		),
		'add_custom_image_header' => array(
			'alt'     => 'add_theme_support( \'custom-header\', $args )',
			'version' => '3.4.0',
		),
		'clean_page_cache' => array(
			'alt'     => 'clean_post_cache()',
			'version' => '3.4.0',
		),
		'clean_pre' => array(
			'alt'     => '',
			'version' => '3.4.0',
		),
		'current_theme_info' => array(
			'alt'     => 'wp_get_theme()',
			'version' => '3.4.0',
		),
		'debug_fclose' => array(
			'alt'     => 'error_log()',
			'version' => '3.4.0',
		),
		'debug_fopen' => array(
			'alt'     => 'error_log()',
			'version' => '3.4.0',
		),
		'debug_fwrite' => array(
			'alt'     => 'error_log()',
			'version' => '3.4.0',
		),
		'display_theme' => array(
			'alt'     => '',
			'version' => '3.4.0',
		),
		'get_allowed_themes' => array(
			'alt'     => 'wp_get_themes( array( \'allowed\' => true ) )',
			'version' => '3.4.0',
		),
		'get_broken_themes' => array(
			'alt'     => 'wp_get_themes( array( \'errors\' => true )',
			'version' => '3.4.0',
		),
		'get_current_theme' => array(
			'alt'     => 'wp_get_theme()',
			'version' => '3.4.0',
		),
		'get_site_allowed_themes' => array(
			'alt'     => 'WP_Theme::get_allowed_on_network()',
			'version' => '3.4.0',
		),
		'get_theme' => array(
			'alt'     => 'wp_get_theme( $stylesheet )',
			'version' => '3.4.0',
		),
		'get_theme_data' => array(
			'alt'     => 'wp_get_theme()',
			'version' => '3.4.0',
		),
		'get_themes' => array(
			'alt'     => 'wp_get_themes()',
			'version' => '3.4.0',
		),
		'logIO' => array(
			'alt'     => 'error_log()',
			'version' => '3.4.0',
		),
		'remove_custom_background' => array(
			'alt'     => 'remove_theme_support( \'custom-background\' )',
			'version' => '3.4.0',
		),
		'remove_custom_image_header' => array(
			'alt'     => 'remove_theme_support( \'custom-header\' )',
			'version' => '3.4.0',
		),
		'update_page_cache' => array(
			'alt'     => 'update_post_cache()',
			'version' => '3.4.0',
		),
		'wpmu_get_blog_allowedthemes' => array(
			'alt'     => 'WP_Theme::get_allowed_on_site()',
			'version' => '3.4.0',
		),

		// WP 3.4.1.
		'wp_explain_nonce' => array(
			'alt'     => 'wp_nonce_ays()',
			'version' => '3.4.1',
		),

		// WP 3.5.0.
		'_flip_image_resource' => array(
			'alt'     => 'WP_Image_Editor::flip()',
			'version' => '3.5.0',
		),
		'_get_post_ancestors' => array(
			'alt'     => '',
			'version' => '3.5.0',
		),
		'_insert_into_post_button' => array(
			'alt'     => '',
			'version' => '3.5.0',
		),
		'_media_button' => array(
			'alt'     => '',
			'version' => '3.5.0',
		),
		'_rotate_image_resource' => array(
			'alt'     => 'WP_Image_Editor::rotate()',
			'version' => '3.5.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'_save_post_hook' => array(
			'alt'     => '',
			'version' => '3.5.0',
		),
		'gd_edit_image_support' => array(
			'alt'     => 'wp_image_editor_supports()',
			'version' => '3.5.0',
		),
		'get_default_page_to_edit' => array(
			'alt'     => 'get_default_post_to_edit( \'page\' )',
			'version' => '3.5.0',
		),
		'get_post_to_edit' => array(
			'alt'     => 'get_post()',
			'version' => '3.5.0',
		),
		'get_udims' => array(
			'alt'     => 'wp_constrain_dimensions()',
			'version' => '3.5.0',
		),
		'image_resize' => array(
			'alt'     => 'wp_get_image_editor()',
			'version' => '3.5.0',
		),
		'sticky_class' => array(
			'alt'     => 'post_class()',
			'version' => '3.5.0',
		),
		'user_pass_ok' => array(
			'alt'     => 'wp_authenticate()',
			'version' => '3.5.0',
		),
		'wp_cache_reset' => array(
			'alt'     => 'WP_Object_Cache::reset()',
			'version' => '3.5.0',
		),
		'wp_create_thumbnail' => array(
			'alt'     => 'image_resize()',
			'version' => '3.5.0',
		),
		'wp_get_single_post' => array(
			'alt'     => 'get_post()',
			'version' => '3.5.0',
		),
		'wp_load_image' => array(
			'alt'     => 'wp_get_image_editor()',
			'version' => '3.5.0',
		),

		// WP 3.6.0.
		'get_user_id_from_string' => array(
			'alt'     => 'get_user_by()',
			'version' => '3.6.0',
		),
		'wp_convert_bytes_to_hr' => array(
			'alt'     => 'size_format()',
			'version' => '3.6.0',
		),
		'wp_nav_menu_locations_meta_box' => array(
			'alt'     => '',
			'version' => '3.6.0',
		),

		// WP 3.7.0.
		'_search_terms_tidy' => array(
			'alt'     => '',
			'version' => '3.7.0',
		),
		'get_blogaddress_by_domain' => array(
			'alt'     => '',
			'version' => '3.7.0',
		),
		'the_attachment_links' => array(
			'alt'     => '',
			'version' => '3.7.0',
		),
		'wp_update_core' => array(
			'alt'     => 'new Core_Upgrader();',
			'version' => '3.7.0',
		),
		'wp_update_plugin' => array(
			'alt'     => 'new Plugin_Upgrader();',
			'version' => '3.7.0',
		),
		'wp_update_theme' => array(
			'alt'     => 'new Theme_Upgrader();',
			'version' => '3.7.0',
		),

		// WP 3.8.0.
		'get_screen_icon' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		'screen_icon' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_incoming_links' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_incoming_links_control' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_incoming_links_output' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_plugins' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_primary_control' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_recent_comments_control' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_secondary' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_secondary_control' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_dashboard_secondary_output' => array(
			'alt'     => '',
			'version' => '3.8.0',
		),

		// WP 3.9.0.
		'_relocate_children' => array(
			'alt'     => '',
			'version' => '3.9.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'default_topic_count_text' => array(
			'alt'     => '',
			'version' => '3.9.0',
		),
		'format_to_post' => array(
			'alt'     => '',
			'version' => '3.9.0',
		),
		'get_current_site_name' => array(
			'alt'     => 'get_current_site()',
			'version' => '3.9.0',
		),
		'rich_edit_exists' => array(
			'alt'     => '',
			'version' => '3.9.0',
		),
		'wpmu_current_site' => array(
			'alt'     => '',
			'version' => '3.9.0',
		),

		// WP 4.0.0.
		'get_all_category_ids' => array(
			'alt'     => 'get_terms()',
			'version' => '4.0.0',
		),
		'like_escape' => array(
			'alt'     => 'wpdb::esc_like()',
			'version' => '4.0.0',
		),
		'url_is_accessable_via_ssl' => array(
			'alt'     => '',
			'version' => '4.0.0',
		),

		// WP 4.1.0.
		// This is a method from the WP_Customize_Image_Control class. See #731.
		'add_tab' => array(
			'alt'     => '',
			'version' => '4.1.0',
		),
		// This is a method from the WP_Customize_Image_Control class. See #731.
		'prepare_control' => array(
			'alt'     => '',
			'version' => '4.1.0',
		),
		// This is a method from the WP_Customize_Image_Control class. See #731.
		'print_tab_image' => array(
			'alt'     => '',
			'version' => '4.1.0',
		),
		// This is a method from the WP_Customize_Image_Control class. See #731.
		'remove_tab' => array(
			'alt'     => '',
			'version' => '4.1.0',
		),

		// WP 4.2.0.
		// This is a method from the WP_Customize_Widgets class. See #731.
		'prepreview_added_sidebars_widgets' => array(
			'alt'     => 'the \'customize_dynamic_setting_args\' filter',
			'version' => '4.2.0',
		),
		// This is a method from the WP_Customize_Widgets class. See #731.
		'prepreview_added_widget_instance' => array(
			'alt'     => 'the \'customize_dynamic_setting_args\' filter',
			'version' => '4.2.0',
		),
		// This is a method from the WP_Customize_Widgets class. See #731.
		'remove_prepreview_filters' => array(
			'alt'     => 'the \'customize_dynamic_setting_args\' filter',
			'version' => '4.2.0',
		),
		// This is a method from the WP_Customize_Widgets class. See #731.
		'setup_widget_addition_previews' => array(
			'alt'     => 'the \'customize_dynamic_setting_args\' filter',
			'version' => '4.2.0',
		),

		// WP 4.3.0.
		'_preview_theme_stylesheet_filter' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		'_preview_theme_template_filter' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		'preview_theme' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		'preview_theme_ob_filter' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		'preview_theme_ob_filter_callback' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		// Verified; see https://core.trac.wordpress.org/ticket/41121, patch 3.
		'wp_ajax_wp_fullscreen_save_post' => array(
			'alt'     => '',
			'version' => '4.3.0',
		),
		'wp_htmledit_pre' => array(
			'alt'     => 'format_for_editor()',
			'version' => '4.3.0',
		),
		'wp_richedit_pre' => array(
			'alt'     => 'format_for_editor()',
			'version' => '4.3.0',
		),

		// WP 4.4.0.
		'create_empty_blog' => array(
			'alt'     => '',
			'version' => '4.4.0',
		),
		'force_ssl_login' => array(
			'alt'     => 'force_ssl_admin()',
			'version' => '4.4.0',
		),
		'get_admin_users_for_domain' => array(
			'alt'     => '',
			'version' => '4.4.0',
		),
		'post_permalink' => array(
			'alt'     => 'get_permalink()',
			'version' => '4.4.0',
		),
		'wp_get_http' => array(
			'alt'     => 'the WP_Http class',
			'version' => '4.4.0',
		),
		// This is a method i.e. WP_Widget_Recent_Comments::flush_widget_cache() See #731.
		'flush_widget_cache' => array(
			'alt'     => '',
			'version' => '4.4.0',
		),

		// WP 4.5.0.
		'add_object_page' => array(
			'alt'     => 'add_menu_page()',
			'version' => '4.5.0',
		),
		'add_utility_page' => array(
			'alt'     => 'add_menu_page()',
			'version' => '4.5.0',
		),
		'comments_popup_script' => array(
			'alt'     => '',
			'version' => '4.5.0',
		),
		'get_comments_popup_template' => array(
			'alt'     => '',
			'version' => '4.5.0',
		),
		'get_currentuserinfo' => array(
			'alt'     => 'wp_get_current_user()',
			'version' => '4.5.0',
		),
		'is_comments_popup' => array(
			'alt'     => '',
			'version' => '4.5.0',
		),
		'popuplinks' => array(
			'alt'     => '',
			'version' => '4.5.0',
		),

		// WP 4.6.0.
		'post_form_autocomplete_off' => array(
			'alt'     => '',
			'version' => '4.6.0',
		),
		'wp_embed_handler_googlevideo' => array(
			'alt'     => '',
			'version' => '4.6.0',
		),
		'wp_get_sites' => array(
			'alt'     => 'get_sites()',
			'version' => '4.6.0',
		),

		// WP 4.7.0.
		'_sort_nav_menu_items' => array(
			'alt'     => 'wp_list_sort()',
			'version' => '4.7.0',
		),
		'_usort_terms_by_ID' => array(
			'alt'     => 'wp_list_sort()',
			'version' => '4.7.0',
		),
		'_usort_terms_by_name' => array(
			'alt'     => 'wp_list_sort()',
			'version' => '4.7.0',
		),
		'get_paged_template' => array(
			'alt'     => '',
			'version' => '4.7.0',
		),
		'wp_get_network' => array(
			'alt'     => 'get_network()',
			'version' => '4.7.0',
		),
		'wp_kses_js_entities' => array(
			'alt'     => '',
			'version' => '4.7.0',
		),

		// WP 4.8.0.
		'wp_dashboard_plugins_output' => array(
			'alt'     => '',
			'version' => '4.8.0',
		),

		// WP 4.9.0.
		'get_shortcut_link' => array(
			'alt'     => '',
			'version' => '4.9.0',
		),
		'is_user_option_local' => array(
			'alt'     => '',
			'version' => '4.9.0',
		),
		'wp_ajax_press_this_add_category' => array(
			'alt'     => '',
			'version' => '4.9.0',
		),
		'wp_ajax_press_this_save_post' => array(
			'alt'     => '',
			'version' => '4.9.0',
		),

		// WP 5.1.0.
		'insert_blog' => array(
			'alt'     => 'wp_insert_site()',
			'version' => '5.1.0',
		),
		'install_blog' => array(
			'alt'     => '',
			'version' => '5.1.0',
		),

		// WP 5.3.0.
		'_wp_json_prepare_data' => array(
			'alt'     => '',
			'version' => '5.3.0',
		),
		'_wp_privacy_requests_screen_options' => array(
			'alt'     => '',
			'version' => '5.3.0',
		),
		'update_user_status' => array(
			'alt'     => 'wp_update_user()',
			'version' => '5.3.0',
		),

		// WP 5.4.0.
		'wp_get_user_request_data' => array(
			'alt'     => 'wp_get_user_request()',
			'version' => '5.4.0',
		),
	);

	/**
	 * Groups of functions to restrict.
	 *
	 * @return array
	 */
	public function getGroups() {
		// Make sure all array keys are lowercase.
		$this->deprecated_functions = array_change_key_case( $this->deprecated_functions, CASE_LOWER );

		return array(
			'deprecated_functions' => array(
				'functions' => array_keys( $this->deprecated_functions ),
			),
		);
	}

	/**
	 * Process a matched token.
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param string $group_name      The name of the group which was matched. Will
	 *                                always be 'deprecated_functions'.
	 * @param string $matched_content The token content (function name) which was matched.
	 *
	 * @return void
	 */
	public function process_matched_token( $stackPtr, $group_name, $matched_content ) {

		$this->get_wp_version_from_cl();

		$function_name = strtolower( $matched_content );

		$message = '%s() has been deprecated since WordPress version %s.';
		$data    = array(
			$matched_content,
			$this->deprecated_functions[ $function_name ]['version'],
		);

		if ( ! empty( $this->deprecated_functions[ $function_name ]['alt'] ) ) {
			$message .= ' Use %s instead.';
			$data[]   = $this->deprecated_functions[ $function_name ]['alt'];
		}

		$this->addMessage(
			$message,
			$stackPtr,
			( version_compare( $this->deprecated_functions[ $function_name ]['version'], $this->minimum_supported_version, '<' ) ),
			$this->string_to_errorcode( $matched_content . 'Found' ),
			$data
		);
	}

}
