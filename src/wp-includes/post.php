<?php
/**
 * Core Post API
 *
 * @package WordPress
 * @subpackage Post
 */

//
// Post Type registration.
//

/**
 * Creates the initial post types when 'init' action is fired.
 *
 * See {@see 'init'}.
 *
 * @since 2.9.0
 */
function create_initial_post_types() {
	WP_Post_Type::reset_default_labels();

	register_post_type(
		'post',
		array(
			'labels'                => array(
				'name_admin_bar' => _x( 'Post', 'add new from admin bar' ),
			),
			'public'                => true,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-admin-post',
			'hierarchical'          => false,
			'rewrite'               => false,
			'query_var'             => false,
			'delete_with_user'      => true,
			'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' ),
			'show_in_rest'          => true,
			'rest_base'             => 'posts',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		)
	);

	register_post_type(
		'page',
		array(
			'labels'                => array(
				'name_admin_bar' => _x( 'Page', 'add new from admin bar' ),
			),
			'public'                => true,
			'publicly_queryable'    => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
			'capability_type'       => 'page',
			'map_meta_cap'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-admin-page',
			'hierarchical'          => true,
			'rewrite'               => false,
			'query_var'             => false,
			'delete_with_user'      => true,
			'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments', 'revisions' ),
			'show_in_rest'          => true,
			'rest_base'             => 'pages',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		)
	);

	register_post_type(
		'attachment',
		array(
			'labels'                => array(
				'name'           => _x( 'Media', 'post type general name' ),
				'name_admin_bar' => _x( 'Media', 'add new from admin bar' ),
				'add_new'        => __( 'Add New Media File' ),
				'edit_item'      => __( 'Edit Media' ),
				'view_item'      => ( '1' === get_option( 'wp_attachment_pages_enabled' ) ) ? __( 'View Attachment Page' ) : __( 'View Media File' ),
				'attributes'     => __( 'Attachment Attributes' ),
			),
			'public'                => true,
			'show_ui'               => true,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
			'capability_type'       => 'post',
			'capabilities'          => array(
				'create_posts' => 'upload_files',
			),
			'map_meta_cap'          => true,
			'menu_icon'             => 'dashicons-admin-media',
			'hierarchical'          => false,
			'rewrite'               => false,
			'query_var'             => false,
			'show_in_nav_menus'     => false,
			'delete_with_user'      => true,
			'supports'              => array( 'title', 'author', 'comments' ),
			'show_in_rest'          => true,
			'rest_base'             => 'media',
			'rest_controller_class' => 'WP_REST_Attachments_Controller',
		)
	);
	add_post_type_support( 'attachment:audio', 'thumbnail' );
	add_post_type_support( 'attachment:video', 'thumbnail' );

	register_post_type(
		'revision',
		array(
			'labels'           => array(
				'name'          => __( 'Revisions' ),
				'singular_name' => __( 'Revision' ),
			),
			'public'           => false,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'       => 'revision.php?revision=%d', /* internal use only. don't use this when registering your own post type. */
			'capability_type'  => 'post',
			'map_meta_cap'     => true,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'can_export'       => false,
			'delete_with_user' => true,
			'supports'         => array( 'author' ),
		)
	);

	register_post_type(
		'nav_menu_item',
		array(
			'labels'                => array(
				'name'          => __( 'Navigation Menu Items' ),
				'singular_name' => __( 'Navigation Menu Item' ),
			),
			'public'                => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'hierarchical'          => false,
			'rewrite'               => false,
			'delete_with_user'      => false,
			'query_var'             => false,
			'map_meta_cap'          => true,
			'capability_type'       => array( 'edit_theme_options', 'edit_theme_options' ),
			'capabilities'          => array(
				// Meta Capabilities.
				'edit_post'              => 'edit_post',
				'read_post'              => 'read_post',
				'delete_post'            => 'delete_post',
				// Primitive Capabilities.
				'edit_posts'             => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
				'read'                   => 'read',
				'delete_private_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'edit_private_posts'     => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
			),
			'show_in_rest'          => true,
			'rest_base'             => 'menu-items',
			'rest_controller_class' => 'WP_REST_Menu_Items_Controller',
		)
	);

	register_post_type(
		'custom_css',
		array(
			'labels'           => array(
				'name'          => __( 'Custom CSS' ),
				'singular_name' => __( 'Custom CSS' ),
			),
			'public'           => false,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'delete_with_user' => false,
			'can_export'       => true,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'supports'         => array( 'title', 'revisions' ),
			'capabilities'     => array(
				'delete_posts'           => 'edit_theme_options',
				'delete_post'            => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'delete_private_posts'   => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'edit_post'              => 'edit_css',
				'edit_posts'             => 'edit_css',
				'edit_others_posts'      => 'edit_css',
				'edit_published_posts'   => 'edit_css',
				'read_post'              => 'read',
				'read_private_posts'     => 'read',
				'publish_posts'          => 'edit_theme_options',
			),
		)
	);

	register_post_type(
		'customize_changeset',
		array(
			'labels'           => array(
				'name'               => _x( 'Changesets', 'post type general name' ),
				'singular_name'      => _x( 'Changeset', 'post type singular name' ),
				'add_new'            => __( 'Add New Changeset' ),
				'add_new_item'       => __( 'Add New Changeset' ),
				'new_item'           => __( 'New Changeset' ),
				'edit_item'          => __( 'Edit Changeset' ),
				'view_item'          => __( 'View Changeset' ),
				'all_items'          => __( 'All Changesets' ),
				'search_items'       => __( 'Search Changesets' ),
				'not_found'          => __( 'No changesets found.' ),
				'not_found_in_trash' => __( 'No changesets found in Trash.' ),
			),
			'public'           => false,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'map_meta_cap'     => true,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'can_export'       => false,
			'delete_with_user' => false,
			'supports'         => array( 'title', 'author' ),
			'capability_type'  => 'customize_changeset',
			'capabilities'     => array(
				'create_posts'           => 'customize',
				'delete_others_posts'    => 'customize',
				'delete_post'            => 'customize',
				'delete_posts'           => 'customize',
				'delete_private_posts'   => 'customize',
				'delete_published_posts' => 'customize',
				'edit_others_posts'      => 'customize',
				'edit_post'              => 'customize',
				'edit_posts'             => 'customize',
				'edit_private_posts'     => 'customize',
				'edit_published_posts'   => 'do_not_allow',
				'publish_posts'          => 'customize',
				'read'                   => 'read',
				'read_post'              => 'customize',
				'read_private_posts'     => 'customize',
			),
		)
	);

	register_post_type(
		'oembed_cache',
		array(
			'labels'           => array(
				'name'          => __( 'oEmbed Responses' ),
				'singular_name' => __( 'oEmbed Response' ),
			),
			'public'           => false,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'delete_with_user' => false,
			'can_export'       => false,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'supports'         => array(),
		)
	);

	register_post_type(
		'user_request',
		array(
			'labels'           => array(
				'name'          => __( 'User Requests' ),
				'singular_name' => __( 'User Request' ),
			),
			'public'           => false,
			'_builtin'         => true, /* internal use only. don't use this when registering your own post type. */
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'can_export'       => false,
			'delete_with_user' => false,
			'supports'         => array(),
		)
	);

	register_post_type(
		'wp_block',
		array(
			'labels'                => array(
				'name'                     => _x( 'Patterns', 'post type general name' ),
				'singular_name'            => _x( 'Pattern', 'post type singular name' ),
				'add_new'                  => __( 'Add New Pattern' ),
				'add_new_item'             => __( 'Add New Pattern' ),
				'new_item'                 => __( 'New Pattern' ),
				'edit_item'                => __( 'Edit Block Pattern' ),
				'view_item'                => __( 'View Pattern' ),
				'view_items'               => __( 'View Patterns' ),
				'all_items'                => __( 'All Patterns' ),
				'search_items'             => __( 'Search Patterns' ),
				'not_found'                => __( 'No patterns found.' ),
				'not_found_in_trash'       => __( 'No patterns found in Trash.' ),
				'filter_items_list'        => __( 'Filter patterns list' ),
				'items_list_navigation'    => __( 'Patterns list navigation' ),
				'items_list'               => __( 'Patterns list' ),
				'item_published'           => __( 'Pattern published.' ),
				'item_published_privately' => __( 'Pattern published privately.' ),
				'item_reverted_to_draft'   => __( 'Pattern reverted to draft.' ),
				'item_scheduled'           => __( 'Pattern scheduled.' ),
				'item_updated'             => __( 'Pattern updated.' ),
			),
			'public'                => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'show_ui'               => true,
			'show_in_menu'          => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => 'blocks',
			'rest_controller_class' => 'WP_REST_Blocks_Controller',
			'capability_type'       => 'block',
			'capabilities'          => array(
				// You need to be able to edit posts, in order to read blocks in their raw form.
				'read'                   => 'edit_posts',
				// You need to be able to publish posts, in order to create blocks.
				'create_posts'           => 'publish_posts',
				'edit_posts'             => 'edit_posts',
				'edit_published_posts'   => 'edit_published_posts',
				'delete_published_posts' => 'delete_published_posts',
				// Enables trashing draft posts as well.
				'delete_posts'           => 'delete_posts',
				'edit_others_posts'      => 'edit_others_posts',
				'delete_others_posts'    => 'delete_others_posts',
			),
			'map_meta_cap'          => true,
			'supports'              => array(
				'title',
				'excerpt',
				'editor',
				'revisions',
				'custom-fields',
			),
		)
	);

	$template_edit_link = 'site-editor.php?' . build_query(
		array(
			'postType' => '%s',
			'postId'   => '%s',
			'canvas'   => 'edit',
		)
	);

	register_post_type(
		'wp_template',
		array(
			'labels'                          => array(
				'name'                  => _x( 'Templates', 'post type general name' ),
				'singular_name'         => _x( 'Template', 'post type singular name' ),
				'add_new'               => __( 'Add New Template' ),
				'add_new_item'          => __( 'Add New Template' ),
				'new_item'              => __( 'New Template' ),
				'edit_item'             => __( 'Edit Template' ),
				'view_item'             => __( 'View Template' ),
				'all_items'             => __( 'Templates' ),
				'search_items'          => __( 'Search Templates' ),
				'parent_item_colon'     => __( 'Parent Template:' ),
				'not_found'             => __( 'No templates found.' ),
				'not_found_in_trash'    => __( 'No templates found in Trash.' ),
				'archives'              => __( 'Template archives' ),
				'insert_into_item'      => __( 'Insert into template' ),
				'uploaded_to_this_item' => __( 'Uploaded to this template' ),
				'filter_items_list'     => __( 'Filter templates list' ),
				'items_list_navigation' => __( 'Templates list navigation' ),
				'items_list'            => __( 'Templates list' ),
				'item_updated'          => __( 'Template updated.' ),
			),
			'description'                     => __( 'Templates to include in your theme.' ),
			'public'                          => false,
			'_builtin'                        => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'                      => $template_edit_link, /* internal use only. don't use this when registering your own post type. */
			'has_archive'                     => false,
			'show_ui'                         => false,
			'show_in_menu'                    => false,
			'show_in_rest'                    => true,
			'rewrite'                         => false,
			'rest_base'                       => 'templates',
			'rest_controller_class'           => 'WP_REST_Templates_Controller',
			'autosave_rest_controller_class'  => 'WP_REST_Template_Autosaves_Controller',
			'revisions_rest_controller_class' => 'WP_REST_Template_Revisions_Controller',
			'late_route_registration'         => true,
			'capability_type'                 => array( 'template', 'templates' ),
			'capabilities'                    => array(
				'create_posts'           => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'delete_private_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'edit_private_posts'     => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'read'                   => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
			),
			'map_meta_cap'                    => true,
			'supports'                        => array(
				'title',
				'slug',
				'excerpt',
				'editor',
				'revisions',
				'author',
			),
		)
	);

	register_post_type(
		'wp_template_part',
		array(
			'labels'                          => array(
				'name'                  => _x( 'Template Parts', 'post type general name' ),
				'singular_name'         => _x( 'Template Part', 'post type singular name' ),
				'add_new'               => __( 'Add New Template Part' ),
				'add_new_item'          => __( 'Add New Template Part' ),
				'new_item'              => __( 'New Template Part' ),
				'edit_item'             => __( 'Edit Template Part' ),
				'view_item'             => __( 'View Template Part' ),
				'all_items'             => __( 'Template Parts' ),
				'search_items'          => __( 'Search Template Parts' ),
				'parent_item_colon'     => __( 'Parent Template Part:' ),
				'not_found'             => __( 'No template parts found.' ),
				'not_found_in_trash'    => __( 'No template parts found in Trash.' ),
				'archives'              => __( 'Template part archives' ),
				'insert_into_item'      => __( 'Insert into template part' ),
				'uploaded_to_this_item' => __( 'Uploaded to this template part' ),
				'filter_items_list'     => __( 'Filter template parts list' ),
				'items_list_navigation' => __( 'Template parts list navigation' ),
				'items_list'            => __( 'Template parts list' ),
				'item_updated'          => __( 'Template part updated.' ),
			),
			'description'                     => __( 'Template parts to include in your templates.' ),
			'public'                          => false,
			'_builtin'                        => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'                      => $template_edit_link, /* internal use only. don't use this when registering your own post type. */
			'has_archive'                     => false,
			'show_ui'                         => false,
			'show_in_menu'                    => false,
			'show_in_rest'                    => true,
			'rewrite'                         => false,
			'rest_base'                       => 'template-parts',
			'rest_controller_class'           => 'WP_REST_Templates_Controller',
			'autosave_rest_controller_class'  => 'WP_REST_Template_Autosaves_Controller',
			'revisions_rest_controller_class' => 'WP_REST_Template_Revisions_Controller',
			'late_route_registration'         => true,
			'map_meta_cap'                    => true,
			'capabilities'                    => array(
				'create_posts'           => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'delete_private_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'edit_private_posts'     => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'read'                   => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
			),
			'supports'                        => array(
				'title',
				'slug',
				'excerpt',
				'editor',
				'revisions',
				'author',
			),
		)
	);

	register_post_type(
		'wp_global_styles',
		array(
			'label'                           => _x( 'Global Styles', 'post type general name' ),
			'description'                     => __( 'Global styles to include in themes.' ),
			'public'                          => false,
			'_builtin'                        => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'                      => '/site-editor.php?canvas=edit', /* internal use only. don't use this when registering your own post type. */
			'show_ui'                         => false,
			'show_in_rest'                    => true,
			'rewrite'                         => false,
			'rest_base'                       => 'global-styles',
			'rest_controller_class'           => 'WP_REST_Global_Styles_Controller',
			'revisions_rest_controller_class' => 'WP_REST_Global_Styles_Revisions_Controller',
			'late_route_registration'         => true,
			'capabilities'                    => array(
				'read'                   => 'edit_posts',
				'create_posts'           => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
			),
			'map_meta_cap'                    => true,
			'supports'                        => array(
				'title',
				'editor',
				'revisions',
			),
		)
	);
	// Disable autosave endpoints for global styles.
	remove_post_type_support( 'wp_global_styles', 'autosave' );

	$navigation_post_edit_link = 'site-editor.php?' . build_query(
		array(
			'postId'   => '%s',
			'postType' => 'wp_navigation',
			'canvas'   => 'edit',
		)
	);

	register_post_type(
		'wp_navigation',
		array(
			'labels'                => array(
				'name'                  => _x( 'Navigation Menus', 'post type general name' ),
				'singular_name'         => _x( 'Navigation Menu', 'post type singular name' ),
				'add_new'               => __( 'Add New Navigation Menu' ),
				'add_new_item'          => __( 'Add New Navigation Menu' ),
				'new_item'              => __( 'New Navigation Menu' ),
				'edit_item'             => __( 'Edit Navigation Menu' ),
				'view_item'             => __( 'View Navigation Menu' ),
				'all_items'             => __( 'Navigation Menus' ),
				'search_items'          => __( 'Search Navigation Menus' ),
				'parent_item_colon'     => __( 'Parent Navigation Menu:' ),
				'not_found'             => __( 'No Navigation Menu found.' ),
				'not_found_in_trash'    => __( 'No Navigation Menu found in Trash.' ),
				'archives'              => __( 'Navigation Menu archives' ),
				'insert_into_item'      => __( 'Insert into Navigation Menu' ),
				'uploaded_to_this_item' => __( 'Uploaded to this Navigation Menu' ),
				'filter_items_list'     => __( 'Filter Navigation Menu list' ),
				'items_list_navigation' => __( 'Navigation Menus list navigation' ),
				'items_list'            => __( 'Navigation Menus list' ),
			),
			'description'           => __( 'Navigation menus that can be inserted into your site.' ),
			'public'                => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'_edit_link'            => $navigation_post_edit_link, /* internal use only. don't use this when registering your own post type. */
			'has_archive'           => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_rest'          => true,
			'rewrite'               => false,
			'map_meta_cap'          => true,
			'capabilities'          => array(
				'edit_others_posts'      => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
				'delete_private_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'edit_private_posts'     => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
			),
			'rest_base'             => 'navigation',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'supports'              => array(
				'title',
				'editor',
				'revisions',
			),
		)
	);

	register_post_type(
		'wp_font_family',
		array(
			'labels'                => array(
				'name'          => __( 'Font Families' ),
				'singular_name' => __( 'Font Family' ),
			),
			'public'                => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'hierarchical'          => false,
			'capabilities'          => array(
				'read'                   => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
			),
			'map_meta_cap'          => true,
			'query_var'             => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => 'font-families',
			'rest_controller_class' => 'WP_REST_Font_Families_Controller',
			'supports'              => array( 'title' ),
		)
	);

	register_post_type(
		'wp_font_face',
		array(
			'labels'                => array(
				'name'          => __( 'Font Faces' ),
				'singular_name' => __( 'Font Face' ),
			),
			'public'                => false,
			'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
			'hierarchical'          => false,
			'capabilities'          => array(
				'read'                   => 'edit_theme_options',
				'read_private_posts'     => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'publish_posts'          => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_posts'           => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
			),
			'map_meta_cap'          => true,
			'query_var'             => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => 'font-families/(?P<font_family_id>[\d]+)/font-faces',
			'rest_controller_class' => 'WP_REST_Font_Faces_Controller',
			'supports'              => array( 'title' ),
		)
	);

	register_post_status(
		'publish',
		array(
			'label'       => _x( 'Published', 'post status' ),
			'public'      => true,
			'_builtin'    => true, /* internal use only. */
			/* translators: %s: Number of published posts. */
			'label_count' => _n_noop(
				'Published <span class="count">(%s)</span>',
				'Published <span class="count">(%s)</span>'
			),
		)
	);

	register_post_status(
		'future',
		array(
			'label'       => _x( 'Scheduled', 'post status' ),
			'protected'   => true,
			'_builtin'    => true, /* internal use only. */
			/* translators: %s: Number of scheduled posts. */
			'label_count' => _n_noop(
				'Scheduled <span class="count">(%s)</span>',
				'Scheduled <span class="count">(%s)</span>'
			),
		)
	);

	register_post_status(
		'draft',
		array(
			'label'         => _x( 'Draft', 'post status' ),
			'protected'     => true,
			'_builtin'      => true, /* internal use only. */
			/* translators: %s: Number of draft posts. */
			'label_count'   => _n_noop(
				'Draft <span class="count">(%s)</span>',
				'Drafts <span class="count">(%s)</span>'
			),
			'date_floating' => true,
		)
	);

	register_post_status(
		'pending',
		array(
			'label'         => _x( 'Pending', 'post status' ),
			'protected'     => true,
			'_builtin'      => true, /* internal use only. */
			/* translators: %s: Number of pending posts. */
			'label_count'   => _n_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>'
			),
			'date_floating' => true,
		)
	);

	register_post_status(
		'private',
		array(
			'label'       => _x( 'Private', 'post status' ),
			'private'     => true,
			'_builtin'    => true, /* internal use only. */
			/* translators: %s: Number of private posts. */
			'label_count' => _n_noop(
				'Private <span class="count">(%s)</span>',
				'Private <span class="count">(%s)</span>'
			),
		)
	);

	register_post_status(
		'trash',
		array(
			'label'                     => _x( 'Trash', 'post status' ),
			'internal'                  => true,
			'_builtin'                  => true, /* internal use only. */
			/* translators: %s: Number of trashed posts. */
			'label_count'               => _n_noop(
				'Trash <span class="count">(%s)</span>',
				'Trash <span class="count">(%s)</span>'
			),
			'show_in_admin_status_list' => true,
		)
	);

	register_post_status(
		'auto-draft',
		array(
			'label'         => 'auto-draft',
			'internal'      => true,
			'_builtin'      => true, /* internal use only. */
			'date_floating' => true,
		)
	);

	register_post_status(
		'inherit',
		array(
			'label'               => 'inherit',
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			'exclude_from_search' => false,
		)
	);

	register_post_status(
		'request-pending',
		array(
			'label'               => _x( 'Pending', 'request status' ),
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			/* translators: %s: Number of pending requests. */
			'label_count'         => _n_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>'
			),
			'exclude_from_search' => false,
		)
	);

	register_post_status(
		'request-confirmed',
		array(
			'label'               => _x( 'Confirmed', 'request status' ),
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			/* translators: %s: Number of confirmed requests. */
			'label_count'         => _n_noop(
				'Confirmed <span class="count">(%s)</span>',
				'Confirmed <span class="count">(%s)</span>'
			),
			'exclude_from_search' => false,
		)
	);

	register_post_status(
		'request-failed',
		array(
			'label'               => _x( 'Failed', 'request status' ),
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			/* translators: %s: Number of failed requests. */
			'label_count'         => _n_noop(
				'Failed <span class="count">(%s)</span>',
				'Failed <span class="count">(%s)</span>'
			),
			'exclude_from_search' => false,
		)
	);

	register_post_status(
		'request-completed',
		array(
			'label'               => _x( 'Completed', 'request status' ),
			'internal'            => true,
			'_builtin'            => true, /* internal use only. */
			/* translators: %s: Number of completed requests. */
			'label_count'         => _n_noop(
				'Completed <span class="count">(%s)</span>',
				'Completed <span class="count">(%s)</span>'
			),
			'exclude_from_search' => false,
		)
	);
}

/**
 * Retrieves attached file path based on attachment ID.
 *
 * By default the path will go through the {@see 'get_attached_file'} filter, but
 * passing `true` to the `$unfiltered` argument will return the file path unfiltered.
 *
 * The function works by retrieving the `_wp_attached_file` post meta value.
 * This is a convenience function to prevent looking up the meta name and provide
 * a mechanism for sending the attached filename through a filter.
 *
 * @since 2.0.0
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $unfiltered    Optional. Whether to skip the {@see 'get_attached_file'} filter.
 *                            Default false.
 * @return string|false The file path to where the attached file should be, false otherwise.
 */
function get_attached_file( $attachment_id, $unfiltered = false ) {
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

	// If the file is relative, prepend upload dir.
	if ( $file && ! str_starts_with( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ) {
		$uploads = wp_get_upload_dir();
		if ( false === $uploads['error'] ) {
			$file = $uploads['basedir'] . "/$file";
		}
	}

	if ( $unfiltered ) {
		return $file;
	}

	/**
	 * Filters the attached file based on the given ID.
	 *
	 * @since 2.1.0
	 *
	 * @param string|false $file          The file path to where the attached file should be, false otherwise.
	 * @param int          $attachment_id Attachment ID.
	 */
	return apply_filters( 'get_attached_file', $file, $attachment_id );
}

/**
 * Updates attachment file path based on attachment ID.
 *
 * Used to update the file path of the attachment, which uses post meta name
 * '_wp_attached_file' to store the path of the attachment.
 *
 * @since 2.1.0
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $file          File path for the attachment.
 * @return bool True on success, false on failure.
 */
function update_attached_file( $attachment_id, $file ) {
	if ( ! get_post( $attachment_id ) ) {
		return false;
	}

	/**
	 * Filters the path to the attached file to update.
	 *
	 * @since 2.1.0
	 *
	 * @param string $file          Path to the attached file to update.
	 * @param int    $attachment_id Attachment ID.
	 */
	$file = apply_filters( 'update_attached_file', $file, $attachment_id );

	$file = _wp_relative_upload_path( $file );
	if ( $file ) {
		return update_post_meta( $attachment_id, '_wp_attached_file', $file );
	} else {
		return delete_post_meta( $attachment_id, '_wp_attached_file' );
	}
}

/**
 * Returns relative path to an uploaded file.
 *
 * The path is relative to the current upload dir.
 *
 * @since 2.9.0
 * @access private
 *
 * @param string $path Full path to the file.
 * @return string Relative path on success, unchanged path on failure.
 */
function _wp_relative_upload_path( $path ) {
	$new_path = $path;

	$uploads = wp_get_upload_dir();
	if ( str_starts_with( $new_path, $uploads['basedir'] ) ) {
			$new_path = str_replace( $uploads['basedir'], '', $new_path );
			$new_path = ltrim( $new_path, '/' );
	}

	/**
	 * Filters the relative path to an uploaded file.
	 *
	 * @since 2.9.0
	 *
	 * @param string $new_path Relative path to the file.
	 * @param string $path     Full path to the file.
	 */
	return apply_filters( '_wp_relative_upload_path', $new_path, $path );
}

/**
 * Retrieves all children of the post parent ID.
 *
 * Normally, without any enhancements, the children would apply to pages. In the
 * context of the inner workings of WordPress, pages, posts, and attachments
 * share the same table, so therefore the functionality could apply to any one
 * of them. It is then noted that while this function does not work on posts, it
 * does not mean that it won't work on posts. It is recommended that you know
 * what context you wish to retrieve the children of.
 *
 * Attachments may also be made the child of a post, so if that is an accurate
 * statement (which needs to be verified), it would then be possible to get
 * all of the attachments for a post. Attachments have since changed since
 * version 2.5, so this is most likely inaccurate, but serves generally as an
 * example of what is possible.
 *
 * The arguments listed as defaults are for this function and also of the
 * get_posts() function. The arguments are combined with the get_children defaults
 * and are then passed to the get_posts() function, which accepts additional arguments.
 * You can replace the defaults in this function, listed below and the additional
 * arguments listed in the get_posts() function.
 *
 * The 'post_parent' is the most important argument and important attention
 * needs to be paid to the $args parameter. If you pass either an object or an
 * integer (number), then just the 'post_parent' is grabbed and everything else
 * is lost. If you don't specify any arguments, then it is assumed that you are
 * in The Loop and the post parent will be grabbed for from the current post.
 *
 * The 'post_parent' argument is the ID to get the children. The 'numberposts'
 * is the amount of posts to retrieve that has a default of '-1', which is
 * used to get all of the posts. Giving a number higher than 0 will only
 * retrieve that amount of posts.
 *
 * The 'post_type' and 'post_status' arguments can be used to choose what
 * criteria of posts to retrieve. The 'post_type' can be anything, but WordPress
 * post types are 'post', 'pages', and 'attachments'. The 'post_status'
 * argument will accept any post status within the write administration panels.
 *
 * @since 2.0.0
 *
 * @see get_posts()
 * @todo Check validity of description.
 *
 * @global WP_Post $post Global post object.
 *
 * @param mixed  $args   Optional. User defined arguments for replacing the defaults. Default empty.
 * @param string $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                       correspond to a WP_Post object, an associative array, or a numeric array,
 *                       respectively. Default OBJECT.
 * @return WP_Post[]|array[]|int[] Array of post objects, arrays, or IDs, depending on `$output`.
 */
function get_children( $args = '', $output = OBJECT ) {
	$kids = array();
	if ( empty( $args ) ) {
		if ( isset( $GLOBALS['post'] ) ) {
			$args = array( 'post_parent' => (int) $GLOBALS['post']->post_parent );
		} else {
			return $kids;
		}
	} elseif ( is_object( $args ) ) {
		$args = array( 'post_parent' => (int) $args->post_parent );
	} elseif ( is_numeric( $args ) ) {
		$args = array( 'post_parent' => (int) $args );
	}

	$defaults = array(
		'numberposts' => -1,
		'post_type'   => 'any',
		'post_status' => 'any',
		'post_parent' => 0,
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	$children = get_posts( $parsed_args );

	if ( ! $children ) {
		return $kids;
	}

	if ( ! empty( $parsed_args['fields'] ) ) {
		return $children;
	}

	update_post_cache( $children );

	foreach ( $children as $key => $child ) {
		$kids[ $child->ID ] = $children[ $key ];
	}

	if ( OBJECT === $output ) {
		return $kids;
	} elseif ( ARRAY_A === $output ) {
		$weeuns = array();
		foreach ( (array) $kids as $kid ) {
			$weeuns[ $kid->ID ] = get_object_vars( $kids[ $kid->ID ] );
		}
		return $weeuns;
	} elseif ( ARRAY_N === $output ) {
		$babes = array();
		foreach ( (array) $kids as $kid ) {
			$babes[ $kid->ID ] = array_values( get_object_vars( $kids[ $kid->ID ] ) );
		}
		return $babes;
	} else {
		return $kids;
	}
}

/**
 * Gets extended entry info (<!--more-->).
 *
 * There should not be any space after the second dash and before the word
 * 'more'. There can be text or space(s) after the word 'more', but won't be
 * referenced.
 *
 * The returned array has 'main', 'extended', and 'more_text' keys. Main has the text before
 * the `<!--more-->`. The 'extended' key has the content after the
 * `<!--more-->` comment. The 'more_text' key has the custom "Read More" text.
 *
 * @since 1.0.0
 *
 * @param string $post Post content.
 * @return string[] {
 *     Extended entry info.
 *
 *     @type string $main      Content before the more tag.
 *     @type string $extended  Content after the more tag.
 *     @type string $more_text Custom read more text, or empty string.
 * }
 */
function get_extended( $post ) {
	// Match the new style more links.
	if ( preg_match( '/<!--more(.*?)?-->/', $post, $matches ) ) {
		list($main, $extended) = explode( $matches[0], $post, 2 );
		$more_text             = $matches[1];
	} else {
		$main      = $post;
		$extended  = '';
		$more_text = '';
	}

	// Leading and trailing whitespace.
	$main      = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $main );
	$extended  = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $extended );
	$more_text = preg_replace( '/^[\s]*(.*)[\s]*$/', '\\1', $more_text );

	return array(
		'main'      => $main,
		'extended'  => $extended,
		'more_text' => $more_text,
	);
}

/**
 * Retrieves post data given a post ID or post object.
 *
 * See sanitize_post() for optional $filter values. Also, the parameter
 * `$post`, must be given as a variable, since it is passed by reference.
 *
 * @since 1.5.1
 *
 * @global WP_Post $post Global post object.
 *
 * @param int|WP_Post|null $post   Optional. Post ID or post object. `null`, `false`, `0` and other PHP falsey values
 *                                 return the current global post inside the loop. A numerically valid post ID that
 *                                 points to a non-existent post returns `null`. Defaults to global $post.
 * @param string           $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                                 correspond to a WP_Post object, an associative array, or a numeric array,
 *                                 respectively. Default OBJECT.
 * @param string           $filter Optional. Type of filter to apply. Accepts 'raw', 'edit', 'db',
 *                                 or 'display'. Default 'raw'.
 * @return WP_Post|array|null Type corresponding to $output on success or null on failure.
 *                            When $output is OBJECT, a `WP_Post` instance is returned.
 */
function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $post ) && isset( $GLOBALS['post'] ) ) {
		$post = $GLOBALS['post'];
	}

	if ( $post instanceof WP_Post ) {
		$_post = $post;
	} elseif ( is_object( $post ) ) {
		if ( empty( $post->filter ) ) {
			$_post = sanitize_post( $post, 'raw' );
			$_post = new WP_Post( $_post );
		} elseif ( 'raw' === $post->filter ) {
			$_post = new WP_Post( $post );
		} else {
			$_post = WP_Post::get_instance( $post->ID );
		}
	} else {
		$_post = WP_Post::get_instance( $post );
	}

	if ( ! $_post ) {
		return null;
	}

	$_post = $_post->filter( $filter );

	if ( ARRAY_A === $output ) {
		return $_post->to_array();
	} elseif ( ARRAY_N === $output ) {
		return array_values( $_post->to_array() );
	}

	return $_post;
}

/**
 * Retrieves the IDs of the ancestors of a post.
 *
 * @since 2.5.0
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return int[] Array of ancestor IDs or empty array if there are none.
 */
function get_post_ancestors( $post ) {
	$post = get_post( $post );

	if ( ! $post || empty( $post->post_parent ) || $post->post_parent == $post->ID ) {
		return array();
	}

	$ancestors = array();

	$id          = $post->post_parent;
	$ancestors[] = $id;

	while ( $ancestor = get_post( $id ) ) {
		// Loop detection: If the ancestor has been seen before, break.
		if ( empty( $ancestor->post_parent ) || ( $ancestor->post_parent == $post->ID ) || in_array( $ancestor->post_parent, $ancestors, true ) ) {
			break;
		}

		$id          = $ancestor->post_parent;
		$ancestors[] = $id;
	}

	return $ancestors;
}

/**
 * Retrieves data from a post field based on Post ID.
 *
 * Examples of the post field will be, 'post_type', 'post_status', 'post_content',
 * etc and based off of the post object property or key names.
 *
 * The context values are based off of the taxonomy filter functions and
 * supported values are found within those functions.
 *
 * @since 2.3.0
 * @since 4.5.0 The `$post` parameter was made optional.
 *
 * @see sanitize_post_field()
 *
 * @param string      $field   Post field name.
 * @param int|WP_Post $post    Optional. Post ID or post object. Defaults to global $post.
 * @param string      $context Optional. How to filter the field. Accepts 'raw', 'edit', 'db',
 *                             or 'display'. Default 'display'.
 * @return string The value of the post field on success, empty string on failure.
 */
function get_post_field( $field, $post = null, $context = 'display' ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	if ( ! isset( $post->$field ) ) {
		return '';
	}

	return sanitize_post_field( $field, $post->$field, $post->ID, $context );
}

/**
 * Retrieves the mime type of an attachment based on the ID.
 *
 * This function can be used with any post type, but it makes more sense with
 * attachments.
 *
 * @since 2.0.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Defaults to global $post.
 * @return string|false The mime type on success, false on failure.
 */
function get_post_mime_type( $post = null ) {
	$post = get_post( $post );

	if ( is_object( $post ) ) {
		return $post->post_mime_type;
	}

	return false;
}

/**
 * Retrieves the post status based on the post ID.
 *
 * If the post ID is of an attachment, then the parent post status will be given
 * instead.
 *
 * @since 2.0.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Defaults to global $post.
 * @return string|false Post status on success, false on failure.
 */
function get_post_status( $post = null ) {
	// Normalize the post object if necessary, skip normalization if called from get_sample_permalink().
	if ( ! $post instanceof WP_Post || ! isset( $post->filter ) || 'sample' !== $post->filter ) {
		$post = get_post( $post );
	}

	if ( ! is_object( $post ) ) {
		return false;
	}

	$post_status = $post->post_status;

	if (
		'attachment' === $post->post_type &&
		'inherit' === $post_status
	) {
		if (
			0 === $post->post_parent ||
			! get_post( $post->post_parent ) ||
			$post->ID === $post->post_parent
		) {
			// Unattached attachments with inherit status are assumed to be published.
			$post_status = 'publish';
		} elseif ( 'trash' === get_post_status( $post->post_parent ) ) {
			// Get parent status prior to trashing.
			$post_status = get_post_meta( $post->post_parent, '_wp_trash_meta_status', true );

			if ( ! $post_status ) {
				// Assume publish as above.
				$post_status = 'publish';
			}
		} else {
			$post_status = get_post_status( $post->post_parent );
		}
	} elseif (
		'attachment' === $post->post_type &&
		! in_array( $post_status, array( 'private', 'trash', 'auto-draft' ), true )
	) {
		/*
		 * Ensure uninherited attachments have a permitted status either 'private', 'trash', 'auto-draft'.
		 * This is to match the logic in wp_insert_post().
		 *
		 * Note: 'inherit' is excluded from this check as it is resolved to the parent post's
		 * status in the logic block above.
		 */
		$post_status = 'publish';
	}

	/**
	 * Filters the post status.
	 *
	 * @since 4.4.0
	 * @since 5.7.0 The attachment post type is now passed through this filter.
	 *
	 * @param string  $post_status The post status.
	 * @param WP_Post $post        The post object.
	 */
	return apply_filters( 'get_post_status', $post_status, $post );
}

/**
 * Retrieves all of the WordPress supported post statuses.
 *
 * Posts have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return string[] Array of post status labels keyed by their status.
 */
function get_post_statuses() {
	$status = array(
		'draft'   => __( 'Draft' ),
		'pending' => __( 'Pending Review' ),
		'private' => __( 'Private' ),
		'publish' => __( 'Published' ),
	);

	return $status;
}

/**
 * Retrieves all of the WordPress support page statuses.
 *
 * Pages have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return string[] Array of page status labels keyed by their status.
 */
function get_page_statuses() {
	$status = array(
		'draft'   => __( 'Draft' ),
		'private' => __( 'Private' ),
		'publish' => __( 'Published' ),
	);

	return $status;
}

/**
 * Returns statuses for privacy requests.
 *
 * @since 4.9.6
 * @access private
 *
 * @return string[] Array of privacy request status labels keyed by their status.
 */
function _wp_privacy_statuses() {
	return array(
		'request-pending'   => _x( 'Pending', 'request status' ),      // Pending confirmation from user.
		'request-confirmed' => _x( 'Confirmed', 'request status' ),    // User has confirmed the action.
		'request-failed'    => _x( 'Failed', 'request status' ),       // User failed to confirm the action.
		'request-completed' => _x( 'Completed', 'request status' ),    // Admin has handled the request.
	);
}

/**
 * Registers a post status. Do not use before init.
 *
 * A simple function for creating or modifying a post status based on the
 * parameters given. The function will accept an array (second optional
 * parameter), along with a string for the post status name.
 *
 * Arguments prefixed with an _underscore shouldn't be used by plugins and themes.
 *
 * @since 3.0.0
 *
 * @global stdClass[] $wp_post_statuses Inserts new post status object into the list
 *
 * @param string       $post_status Name of the post status.
 * @param array|string $args {
 *     Optional. Array or string of post status arguments.
 *
 *     @type bool|string $label                     A descriptive name for the post status marked
 *                                                  for translation. Defaults to value of $post_status.
 *     @type array|false $label_count               Nooped plural text from _n_noop() to provide the singular
 *                                                  and plural forms of the label for counts. Default false
 *                                                  which means the `$label` argument will be used for both
 *                                                  the singular and plural forms of this label.
 *     @type bool        $exclude_from_search       Whether to exclude posts with this post status
 *                                                  from search results. Default is value of $internal.
 *     @type bool        $_builtin                  Whether the status is built-in. Core-use only.
 *                                                  Default false.
 *     @type bool        $public                    Whether posts of this status should be shown
 *                                                  in the front end of the site. Default false.
 *     @type bool        $internal                  Whether the status is for internal use only.
 *                                                  Default false.
 *     @type bool        $protected                 Whether posts with this status should be protected.
 *                                                  Default false.
 *     @type bool        $private                   Whether posts with this status should be private.
 *                                                  Default false.
 *     @type bool        $publicly_queryable        Whether posts with this status should be publicly-
 *                                                  queryable. Default is value of $public.
 *     @type bool        $show_in_admin_all_list    Whether to include posts in the edit listing for
 *                                                  their post type. Default is the opposite value
 *                                                  of $internal.
 *     @type bool        $show_in_admin_status_list Show in the list of statuses with post counts at
 *                                                  the top of the edit listings,
 *                                                  e.g. All (12) | Published (9) | My Custom Status (2)
 *                                                  Default is the opposite value of $internal.
 *     @type bool        $date_floating             Whether the post has a floating creation date.
 *                                                  Default to false.
 * }
 * @return object
 */
function register_post_status( $post_status, $args = array() ) {
	global $wp_post_statuses;

	if ( ! is_array( $wp_post_statuses ) ) {
		$wp_post_statuses = array();
	}

	// Args prefixed with an underscore are reserved for internal use.
	$defaults = array(
		'label'                     => false,
		'label_count'               => false,
		'exclude_from_search'       => null,
		'_builtin'                  => false,
		'public'                    => null,
		'internal'                  => null,
		'protected'                 => null,
		'private'                   => null,
		'publicly_queryable'        => null,
		'show_in_admin_status_list' => null,
		'show_in_admin_all_list'    => null,
		'date_floating'             => null,
	);
	$args     = wp_parse_args( $args, $defaults );
	$args     = (object) $args;

	$post_status = sanitize_key( $post_status );
	$args->name  = $post_status;

	// Set various defaults.
	if ( null === $args->public && null === $args->internal && null === $args->protected && null === $args->private ) {
		$args->internal = true;
	}

	if ( null === $args->public ) {
		$args->public = false;
	}

	if ( null === $args->private ) {
		$args->private = false;
	}

	if ( null === $args->protected ) {
		$args->protected = false;
	}

	if ( null === $args->internal ) {
		$args->internal = false;
	}

	if ( null === $args->publicly_queryable ) {
		$args->publicly_queryable = $args->public;
	}

	if ( null === $args->exclude_from_search ) {
		$args->exclude_from_search = $args->internal;
	}

	if ( null === $args->show_in_admin_all_list ) {
		$args->show_in_admin_all_list = ! $args->internal;
	}

	if ( null === $args->show_in_admin_status_list ) {
		$args->show_in_admin_status_list = ! $args->internal;
	}

	if ( null === $args->date_floating ) {
		$args->date_floating = false;
	}

	if ( false === $args->label ) {
		$args->label = $post_status;
	}

	if ( false === $args->label_count ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingular,WordPress.WP.I18n.NonSingularStringLiteralPlural
		$args->label_count = _n_noop( $args->label, $args->label );
	}

	$wp_post_statuses[ $post_status ] = $args;

	return $args;
}

/**
 * Retrieves a post status object by name.
 *
 * @since 3.0.0
 *
 * @global stdClass[] $wp_post_statuses List of post statuses.
 *
 * @see register_post_status()
 *
 * @param string $post_status The name of a registered post status.
 * @return stdClass|null A post status object.
 */
function get_post_status_object( $post_status ) {
	global $wp_post_statuses;

	if ( empty( $wp_post_statuses[ $post_status ] ) ) {
		return null;
	}

	return $wp_post_statuses[ $post_status ];
}

/**
 * Gets a list of post statuses.
 *
 * @since 3.0.0
 *
 * @global stdClass[] $wp_post_statuses List of post statuses.
 *
 * @see register_post_status()
 *
 * @param array|string $args     Optional. Array or string of post status arguments to compare against
 *                               properties of the global `$wp_post_statuses objects`. Default empty array.
 * @param string       $output   Optional. The type of output to return, either 'names' or 'objects'. Default 'names'.
 * @param string       $operator Optional. The logical operation to perform. 'or' means only one element
 *                               from the array needs to match; 'and' means all elements must match.
 *                               Default 'and'.
 * @return string[]|stdClass[] A list of post status names or objects.
 */
function get_post_stati( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_post_statuses;

	$field = ( 'names' === $output ) ? 'name' : false;

	return wp_filter_object_list( $wp_post_statuses, $args, $operator, $field );
}

/**
 * Determines whether the post type is hierarchical.
 *
 * A false return value might also mean that the post type does not exist.
 *
 * @since 3.0.0
 *
 * @see get_post_type_object()
 *
 * @param string $post_type Post type name
 * @return bool Whether post type is hierarchical.
 */
function is_post_type_hierarchical( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return false;
	}

	$post_type = get_post_type_object( $post_type );
	return $post_type->hierarchical;
}

/**
 * Determines whether a post type is registered.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 3.0.0
 *
 * @see get_post_type_object()
 *
 * @param string $post_type Post type name.
 * @return bool Whether post type is registered.
 */
function post_type_exists( $post_type ) {
	return (bool) get_post_type_object( $post_type );
}

/**
 * Retrieves the post type of the current post or of a given post.
 *
 * @since 2.1.0
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
 * @return string|false          Post type on success, false on failure.
 */
function get_post_type( $post = null ) {
	$post = get_post( $post );
	if ( $post ) {
		return $post->post_type;
	}

	return false;
}

/**
 * Retrieves a post type object by name.
 *
 * @since 3.0.0
 * @since 4.6.0 Object returned is now an instance of `WP_Post_Type`.
 *
 * @global array $wp_post_types List of post types.
 *
 * @see register_post_type()
 *
 * @param string $post_type The name of a registered post type.
 * @return WP_Post_Type|null WP_Post_Type object if it exists, null otherwise.
 */
function get_post_type_object( $post_type ) {
	global $wp_post_types;

	if ( ! is_scalar( $post_type ) || empty( $wp_post_types[ $post_type ] ) ) {
		return null;
	}

	return $wp_post_types[ $post_type ];
}

/**
 * Gets a list of all registered post type objects.
 *
 * @since 2.9.0
 *
 * @global array $wp_post_types List of post types.
 *
 * @see register_post_type() for accepted arguments.
 *
 * @param array|string $args     Optional. An array of key => value arguments to match against
 *                               the post type objects. Default empty array.
 * @param string       $output   Optional. The type of output to return. Either 'names'
 *                               or 'objects'. Default 'names'.
 * @param string       $operator Optional. The logical operation to perform. 'or' means only one
 *                               element from the array needs to match; 'and' means all elements
 *                               must match; 'not' means no elements may match. Default 'and'.
 * @return string[]|WP_Post_Type[] An array of post type names or objects.
 */
function get_post_types( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_post_types;

	$field = ( 'names' === $output ) ? 'name' : false;

	return wp_filter_object_list( $wp_post_types, $args, $operator, $field );
}

/**
 * Registers a post type.
 *
 * Note: Post type registrations should not be hooked before the
 * {@see 'init'} action. Also, any taxonomy connections should be
 * registered via the `$taxonomies` argument to ensure consistency
 * when hooks such as {@see 'parse_query'} or {@see 'pre_get_posts'}
 * are used.
 *
 * Post types can support any number of built-in core features such
 * as meta boxes, custom fields, post thumbnails, post statuses,
 * comments, and more. See the `$supports` argument for a complete
 * list of supported features.
 *
 * @since 2.9.0
 * @since 3.0.0 The `show_ui` argument is now enforced on the new post screen.
 * @since 4.4.0 The `show_ui` argument is now enforced on the post type listing
 *              screen and post editing screen.
 * @since 4.6.0 Post type object returned is now an instance of `WP_Post_Type`.
 * @since 4.7.0 Introduced `show_in_rest`, `rest_base` and `rest_controller_class`
 *              arguments to register the post type in REST API.
 * @since 5.0.0 The `template` and `template_lock` arguments were added.
 * @since 5.3.0 The `supports` argument will now accept an array of arguments for a feature.
 * @since 5.9.0 The `rest_namespace` argument was added.
 *
 * @global array $wp_post_types List of post types.
 *
 * @param string       $post_type Post type key. Must not exceed 20 characters and may only contain
 *                                lowercase alphanumeric characters, dashes, and underscores. See sanitize_key().
 * @param array|string $args {
 *     Array or string of arguments for registering a post type.
 *
 *     @type string       $label                           Name of the post type shown in the menu. Usually plural.
 *                                                         Default is value of $labels['name'].
 *     @type string[]     $labels                          An array of labels for this post type. If not set, post
 *                                                         labels are inherited for non-hierarchical types and page
 *                                                         labels for hierarchical ones. See get_post_type_labels() for a full
 *                                                         list of supported labels.
 *     @type string       $description                     A short descriptive summary of what the post type is.
 *                                                         Default empty.
 *     @type bool         $public                          Whether a post type is intended for use publicly either via
 *                                                         the admin interface or by front-end users. While the default
 *                                                         settings of $exclude_from_search, $publicly_queryable, $show_ui,
 *                                                         and $show_in_nav_menus are inherited from $public, each does not
 *                                                         rely on this relationship and controls a very specific intention.
 *                                                         Default false.
 *     @type bool         $hierarchical                    Whether the post type is hierarchical (e.g. page). Default false.
 *     @type bool         $exclude_from_search             Whether to exclude posts with this post type from front end search
 *                                                         results. Default is the opposite value of $public.
 *     @type bool         $publicly_queryable              Whether queries can be performed on the front end for the post type
 *                                                         as part of parse_request(). Endpoints would include:
 *                                                          * ?post_type={post_type_key}
 *                                                          * ?{post_type_key}={single_post_slug}
 *                                                          * ?{post_type_query_var}={single_post_slug}
 *                                                         If not set, the default is inherited from $public.
 *     @type bool         $show_ui                         Whether to generate and allow a UI for managing this post type in the
 *                                                         admin. Default is value of $public.
 *     @type bool|string  $show_in_menu                    Where to show the post type in the admin menu. To work, $show_ui
 *                                                         must be true. If true, the post type is shown in its own top level
 *                                                         menu. If false, no menu is shown. If a string of an existing top
 *                                                         level menu ('tools.php' or 'edit.php?post_type=page', for example), the
 *                                                         post type will be placed as a sub-menu of that.
 *                                                         Default is value of $show_ui.
 *     @type bool         $show_in_nav_menus               Makes this post type available for selection in navigation menus.
 *                                                         Default is value of $public.
 *     @type bool         $show_in_admin_bar               Makes this post type available via the admin bar. Default is value
 *                                                         of $show_in_menu.
 *     @type bool         $show_in_rest                    Whether to include the post type in the REST API. Set this to true
 *                                                         for the post type to be available in the block editor.
 *     @type string       $rest_base                       To change the base URL of REST API route. Default is $post_type.
 *     @type string       $rest_namespace                  To change the namespace URL of REST API route. Default is wp/v2.
 *     @type string       $rest_controller_class           REST API controller class name. Default is 'WP_REST_Posts_Controller'.
 *     @type string|bool  $autosave_rest_controller_class  REST API controller class name. Default is 'WP_REST_Autosaves_Controller'.
 *     @type string|bool  $revisions_rest_controller_class REST API controller class name. Default is 'WP_REST_Revisions_Controller'.
 *     @type bool         $late_route_registration         A flag to direct the REST API controllers for autosave / revisions
 *                                                         should be registered before/after the post type controller.
 *     @type int          $menu_position                   The position in the menu order the post type should appear. To work,
 *                                                         $show_in_menu must be true. Default null (at the bottom).
 *     @type string       $menu_icon                       The URL to the icon to be used for this menu. Pass a base64-encoded
 *                                                         SVG using a data URI, which will be colored to match the color scheme
 *                                                         -- this should begin with 'data:image/svg+xml;base64,'. Pass the name
 *                                                         of a Dashicons helper class to use a font icon, e.g.
 *                                                        'dashicons-chart-pie'. Pass 'none' to leave div.wp-menu-image empty
 *                                                         so an icon can be added via CSS. Defaults to use the posts icon.
 *     @type string|array $capability_type                 The string to use to build the read, edit, and delete capabilities.
 *                                                         May be passed as an array to allow for alternative plurals when using
 *                                                         this argument as a base to construct the capabilities, e.g.
 *                                                         array('story', 'stories'). Default 'post'.
 *     @type string[]     $capabilities                    Array of capabilities for this post type. $capability_type is used
 *                                                         as a base to construct capabilities by default.
 *                                                         See get_post_type_capabilities().
 *     @type bool         $map_meta_cap                    Whether to use the internal default meta capability handling.
 *                                                         Default false.
 *     @type array|false  $supports                        Core feature(s) the post type supports. Serves as an alias for calling
 *                                                         add_post_type_support() directly. Core features include 'title',
 *                                                         'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt',
 *                                                         'page-attributes', 'thumbnail', 'custom-fields', and 'post-formats'.
 *                                                         Additionally, the 'revisions' feature dictates whether the post type
 *                                                         will store revisions, the 'autosave' feature dictates whether the post type
 *                                                         will be autosaved, and the 'comments' feature dictates whether the
 *                                                         comments count will show on the edit screen. For backward compatibility reasons,
 *                                                         adding 'editor' support implies 'autosave' support too. A feature can also be
 *                                                         specified as an array of arguments to provide additional information
 *                                                         about supporting that feature.
 *                                                         Example: `array( 'my_feature', array( 'field' => 'value' ) )`.
 *                                                         If false, no features will be added.
 *                                                         Default is an array containing 'title' and 'editor'.
 *     @type callable     $register_meta_box_cb            Provide a callback function that sets up the meta boxes for the
 *                                                         edit form. Do remove_meta_box() and add_meta_box() calls in the
 *                                                         callback. Default null.
 *     @type string[]     $taxonomies                      An array of taxonomy identifiers that will be registered for the
 *                                                         post type. Taxonomies can be registered later with register_taxonomy()
 *                                                         or register_taxonomy_for_object_type().
 *                                                         Default empty array.
 *     @type bool|string  $has_archive                     Whether there should be post type archives, or if a string, the
 *                                                         archive slug to use. Will generate the proper rewrite rules if
 *                                                         $rewrite is enabled. Default false.
 *     @type bool|array   $rewrite                         {
 *         Triggers the handling of rewrites for this post type. To prevent rewrite, set to false.
 *         Defaults to true, using $post_type as slug. To specify rewrite rules, an array can be
 *         passed with any of these keys:
 *
 *         @type string $slug       Customize the permastruct slug. Defaults to $post_type key.
 *         @type bool   $with_front Whether the permastruct should be prepended with WP_Rewrite::$front.
 *                                  Default true.
 *         @type bool   $feeds      Whether the feed permastruct should be built for this post type.
 *                                  Default is value of $has_archive.
 *         @type bool   $pages      Whether the permastruct should provide for pagination. Default true.
 *         @type int    $ep_mask    Endpoint mask to assign. If not specified and permalink_epmask is set,
 *                                  inherits from $permalink_epmask. If not specified and permalink_epmask
 *                                  is not set, defaults to EP_PERMALINK.
 *     }
 *     @type string|bool  $query_var                      Sets the query_var key for this post type. Defaults to $post_type
 *                                                        key. If false, a post type cannot be loaded at
 *                                                        ?{query_var}={post_slug}. If specified as a string, the query
 *                                                        ?{query_var_string}={post_slug} will be valid.
 *     @type bool         $can_export                     Whether to allow this post type to be exported. Default true.
 *     @type bool         $delete_with_user               Whether to delete posts of this type when deleting a user.
 *                                                          * If true, posts of this type belonging to the user will be moved
 *                                                            to Trash when the user is deleted.
 *                                                          * If false, posts of this type belonging to the user will *not*
 *                                                            be trashed or deleted.
 *                                                          * If not set (the default), posts are trashed if post type supports
 *                                                            the 'author' feature. Otherwise posts are not trashed or deleted.
 *                                                        Default null.
 *     @type array        $template                       Array of blocks to use as the default initial state for an editor
 *                                                        session. Each item should be an array containing block name and
 *                                                        optional attributes. Default empty array.
 *     @type string|false $template_lock                  Whether the block template should be locked if $template is set.
 *                                                        * If set to 'all', the user is unable to insert new blocks,
 *                                                          move existing blocks and delete blocks.
 *                                                       * If set to 'insert', the user is able to move existing blocks
 *                                                         but is unable to insert new blocks and delete blocks.
 *                                                         Default false.
 *     @type bool         $_builtin                     FOR INTERNAL USE ONLY! True if this post type is a native or
 *                                                      "built-in" post_type. Default false.
 *     @type string       $_edit_link                   FOR INTERNAL USE ONLY! URL segment to use for edit link of
 *                                                      this post type. Default 'post.php?post=%d'.
 * }
 * @return WP_Post_Type|WP_Error The registered post type object on success,
 *                               WP_Error object on failure.
 */
function register_post_type( $post_type, $args = array() ) {
	global $wp_post_types;

	if ( ! is_array( $wp_post_types ) ) {
		$wp_post_types = array();
	}

	// Sanitize post type name.
	$post_type = sanitize_key( $post_type );

	if ( empty( $post_type ) || strlen( $post_type ) > 20 ) {
		_doing_it_wrong( __FUNCTION__, __( 'Post type names must be between 1 and 20 characters in length.' ), '4.2.0' );
		return new WP_Error( 'post_type_length_invalid', __( 'Post type names must be between 1 and 20 characters in length.' ) );
	}

	$post_type_object = new WP_Post_Type( $post_type, $args );
	$post_type_object->add_supports();
	$post_type_object->add_rewrite_rules();
	$post_type_object->register_meta_boxes();

	$wp_post_types[ $post_type ] = $post_type_object;

	$post_type_object->add_hooks();
	$post_type_object->register_taxonomies();

	/**
	 * Fires after a post type is registered.
	 *
	 * @since 3.3.0
	 * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
	 *
	 * @param string       $post_type        Post type.
	 * @param WP_Post_Type $post_type_object Arguments used to register the post type.
	 */
	do_action( 'registered_post_type', $post_type, $post_type_object );

	/**
	 * Fires after a specific post type is registered.
	 *
	 * The dynamic portion of the filter name, `$post_type`, refers to the post type key.
	 *
	 * Possible hook names include:
	 *
	 *  - `registered_post_type_post`
	 *  - `registered_post_type_page`
	 *
	 * @since 6.0.0
	 *
	 * @param string       $post_type        Post type.
	 * @param WP_Post_Type $post_type_object Arguments used to register the post type.
	 */
	do_action( "registered_post_type_{$post_type}", $post_type, $post_type_object );

	return $post_type_object;
}

/**
 * Unregisters a post type.
 *
 * Cannot be used to unregister built-in post types.
 *
 * @since 4.5.0
 *
 * @global array $wp_post_types List of post types.
 *
 * @param string $post_type Post type to unregister.
 * @return true|WP_Error True on success, WP_Error on failure or if the post type doesn't exist.
 */
function unregister_post_type( $post_type ) {
	global $wp_post_types;

	if ( ! post_type_exists( $post_type ) ) {
		return new WP_Error( 'invalid_post_type', __( 'Invalid post type.' ) );
	}

	$post_type_object = get_post_type_object( $post_type );

	// Do not allow unregistering internal post types.
	if ( $post_type_object->_builtin ) {
		return new WP_Error( 'invalid_post_type', __( 'Unregistering a built-in post type is not allowed' ) );
	}

	$post_type_object->remove_supports();
	$post_type_object->remove_rewrite_rules();
	$post_type_object->unregister_meta_boxes();
	$post_type_object->remove_hooks();
	$post_type_object->unregister_taxonomies();

	unset( $wp_post_types[ $post_type ] );

	/**
	 * Fires after a post type was unregistered.
	 *
	 * @since 4.5.0
	 *
	 * @param string $post_type Post type key.
	 */
	do_action( 'unregistered_post_type', $post_type );

	return true;
}

/**
 * Builds an object with all post type capabilities out of a post type object
 *
 * Post type capabilities use the 'capability_type' argument as a base, if the
 * capability is not set in the 'capabilities' argument array or if the
 * 'capabilities' argument is not supplied.
 *
 * The capability_type argument can optionally be registered as an array, with
 * the first value being singular and the second plural, e.g. array('story, 'stories')
 * Otherwise, an 's' will be added to the value for the plural form. After
 * registration, capability_type will always be a string of the singular value.
 *
 * By default, eight keys are accepted as part of the capabilities array:
 *
 * - edit_post, read_post, and delete_post are meta capabilities, which are then
 *   generally mapped to corresponding primitive capabilities depending on the
 *   context, which would be the post being edited/read/deleted and the user or
 *   role being checked. Thus these capabilities would generally not be granted
 *   directly to users or roles.
 *
 * - edit_posts - Controls whether objects of this post type can be edited.
 * - edit_others_posts - Controls whether objects of this type owned by other users
 *   can be edited. If the post type does not support an author, then this will
 *   behave like edit_posts.
 * - delete_posts - Controls whether objects of this post type can be deleted.
 * - publish_posts - Controls publishing objects of this post type.
 * - read_private_posts - Controls whether private objects can be read.
 *
 * These five primitive capabilities are checked in core in various locations.
 * There are also six other primitive capabilities which are not referenced
 * directly in core, except in map_meta_cap(), which takes the three aforementioned
 * meta capabilities and translates them into one or more primitive capabilities
 * that must then be checked against the user or role, depending on the context.
 *
 * - read - Controls whether objects of this post type can be read.
 * - delete_private_posts - Controls whether private objects can be deleted.
 * - delete_published_posts - Controls whether published objects can be deleted.
 * - delete_others_posts - Controls whether objects owned by other users can be
 *   can be deleted. If the post type does not support an author, then this will
 *   behave like delete_posts.
 * - edit_private_posts - Controls whether private objects can be edited.
 * - edit_published_posts - Controls whether published objects can be edited.
 *
 * These additional capabilities are only used in map_meta_cap(). Thus, they are
 * only assigned by default if the post type is registered with the 'map_meta_cap'
 * argument set to true (default is false).
 *
 * @since 3.0.0
 * @since 5.4.0 'delete_posts' is included in default capabilities.
 *
 * @see register_post_type()
 * @see map_meta_cap()
 *
 * @param object $args Post type registration arguments.
 * @return object Object with all the capabilities as member variables.
 */
function get_post_type_capabilities( $args ) {
	if ( ! is_array( $args->capability_type ) ) {
		$args->capability_type = array( $args->capability_type, $args->capability_type . 's' );
	}

	// Singular base for meta capabilities, plural base for primitive capabilities.
	list( $singular_base, $plural_base ) = $args->capability_type;

	$default_capabilities = array(
		// Meta capabilities.
		'edit_post'          => 'edit_' . $singular_base,
		'read_post'          => 'read_' . $singular_base,
		'delete_post'        => 'delete_' . $singular_base,
		// Primitive capabilities used outside of map_meta_cap():
		'edit_posts'         => 'edit_' . $plural_base,
		'edit_others_posts'  => 'edit_others_' . $plural_base,
		'delete_posts'       => 'delete_' . $plural_base,
		'publish_posts'      => 'publish_' . $plural_base,
		'read_private_posts' => 'read_private_' . $plural_base,
	);

	// Primitive capabilities used within map_meta_cap():
	if ( $args->map_meta_cap ) {
		$default_capabilities_for_mapping = array(
			'read'                   => 'read',
			'delete_private_posts'   => 'delete_private_' . $plural_base,
			'delete_published_posts' => 'delete_published_' . $plural_base,
			'delete_others_posts'    => 'delete_others_' . $plural_base,
			'edit_private_posts'     => 'edit_private_' . $plural_base,
			'edit_published_posts'   => 'edit_published_' . $plural_base,
		);
		$default_capabilities             = array_merge( $default_capabilities, $default_capabilities_for_mapping );
	}

	$capabilities = array_merge( $default_capabilities, $args->capabilities );

	// Post creation capability simply maps to edit_posts by default:
	if ( ! isset( $capabilities['create_posts'] ) ) {
		$capabilities['create_posts'] = $capabilities['edit_posts'];
	}

	// Remember meta capabilities for future reference.
	if ( $args->map_meta_cap ) {
		_post_type_meta_capabilities( $capabilities );
	}

	return (object) $capabilities;
}

/**
 * Stores or returns a list of post type meta caps for map_meta_cap().
 *
 * @since 3.1.0
 * @access private
 *
 * @global array $post_type_meta_caps Used to store meta capabilities.
 *
 * @param string[] $capabilities Post type meta capabilities.
 */
function _post_type_meta_capabilities( $capabilities = null ) {
	global $post_type_meta_caps;

	foreach ( $capabilities as $core => $custom ) {
		if ( in_array( $core, array( 'read_post', 'delete_post', 'edit_post' ), true ) ) {
			$post_type_meta_caps[ $custom ] = $core;
		}
	}
}

/**
 * Builds an object with all post type labels out of a post type object.
 *
 * Accepted keys of the label array in the post type object:
 *
 * - `name` - General name for the post type, usually plural. The same and overridden
 *          by `$post_type_object->label`. Default is 'Posts' / 'Pages'.
 * - `singular_name` - Name for one object of this post type. Default is 'Post' / 'Page'.
 * - `add_new` - Label for adding a new item. Default is 'Add New' / 'Add New'.
 * - `add_new_item` - Label for adding a new singular item. Default is 'Add New Post' / 'Add New Page'.
 * - `edit_item` - Label for editing a singular item. Default is 'Edit Post' / 'Edit Page'.
 * - `new_item` - Label for the new item page title. Default is 'New Post' / 'New Page'.
 * - `view_item` - Label for viewing a singular item. Default is 'View Post' / 'View Page'.
 * - `view_items` - Label for viewing post type archives. Default is 'View Posts' / 'View Pages'.
 * - `search_items` - Label for searching plural items. Default is 'Search Posts' / 'Search Pages'.
 * - `not_found` - Label used when no items are found. Default is 'No posts found' / 'No pages found'.
 * - `not_found_in_trash` - Label used when no items are in the Trash. Default is 'No posts found in Trash' /
 *                        'No pages found in Trash'.
 * - `parent_item_colon` - Label used to prefix parents of hierarchical items. Not used on non-hierarchical
 *                       post types. Default is 'Parent Page:'.
 * - `all_items` - Label to signify all items in a submenu link. Default is 'All Posts' / 'All Pages'.
 * - `archives` - Label for archives in nav menus. Default is 'Post Archives' / 'Page Archives'.
 * - `attributes` - Label for the attributes meta box. Default is 'Post Attributes' / 'Page Attributes'.
 * - `insert_into_item` - Label for the media frame button. Default is 'Insert into post' / 'Insert into page'.
 * - `uploaded_to_this_item` - Label for the media frame filter. Default is 'Uploaded to this post' /
 *                           'Uploaded to this page'.
 * - `featured_image` - Label for the featured image meta box title. Default is 'Featured image'.
 * - `set_featured_image` - Label for setting the featured image. Default is 'Set featured image'.
 * - `remove_featured_image` - Label for removing the featured image. Default is 'Remove featured image'.
 * - `use_featured_image` - Label in the media frame for using a featured image. Default is 'Use as featured image'.
 * - `menu_name` - Label for the menu name. Default is the same as `name`.
 * - `filter_items_list` - Label for the table views hidden heading. Default is 'Filter posts list' /
 *                       'Filter pages list'.
 * - `filter_by_date` - Label for the date filter in list tables. Default is 'Filter by date'.
 * - `items_list_navigation` - Label for the table pagination hidden heading. Default is 'Posts list navigation' /
 *                           'Pages list navigation'.
 * - `items_list` - Label for the table hidden heading. Default is 'Posts list' / 'Pages list'.
 * - `item_published` - Label used when an item is published. Default is 'Post published.' / 'Page published.'
 * - `item_published_privately` - Label used when an item is published with private visibility.
 *                              Default is 'Post published privately.' / 'Page published privately.'
 * - `item_reverted_to_draft` - Label used when an item is switched to a draft.
 *                            Default is 'Post reverted to draft.' / 'Page reverted to draft.'
 * - `item_trashed` - Label used when an item is moved to Trash. Default is 'Post trashed.' / 'Page trashed.'
 * - `item_scheduled` - Label used when an item is scheduled for publishing. Default is 'Post scheduled.' /
 *                    'Page scheduled.'
 * - `item_updated` - Label used when an item is updated. Default is 'Post updated.' / 'Page updated.'
 * - `item_link` - Title for a navigation link block variation. Default is 'Post Link' / 'Page Link'.
 * - `item_link_description` - Description for a navigation link block variation. Default is 'A link to a post.' /
 *                             'A link to a page.'
 *
 * Above, the first default value is for non-hierarchical post types (like posts)
 * and the second one is for hierarchical post types (like pages).
 *
 * Note: To set labels used in post type admin notices, see the {@see 'post_updated_messages'} filter.
 *
 * @since 3.0.0
 * @since 4.3.0 Added the `featured_image`, `set_featured_image`, `remove_featured_image`,
 *              and `use_featured_image` labels.
 * @since 4.4.0 Added the `archives`, `insert_into_item`, `uploaded_to_this_item`, `filter_items_list`,
 *              `items_list_navigation`, and `items_list` labels.
 * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 * @since 4.7.0 Added the `view_items` and `attributes` labels.
 * @since 5.0.0 Added the `item_published`, `item_published_privately`, `item_reverted_to_draft`,
 *              `item_scheduled`, and `item_updated` labels.
 * @since 5.7.0 Added the `filter_by_date` label.
 * @since 5.8.0 Added the `item_link` and `item_link_description` labels.
 * @since 6.3.0 Added the `item_trashed` label.
 * @since 6.4.0 Changed default values for the `add_new` label to include the type of content.
 *              This matches `add_new_item` and provides more context for better accessibility.
 * @since 6.6.0 Added the `template_name` label.
 * @since 6.7.0 Restored pre-6.4.0 defaults for the `add_new` label and updated documentation.
 *              Updated core usage to reference `add_new_item`.
 *
 * @access private
 *
 * @param object|WP_Post_Type $post_type_object Post type object.
 * @return object Object with all the labels as member variables.
 */
function get_post_type_labels( $post_type_object ) {
	$nohier_vs_hier_defaults = WP_Post_Type::get_default_labels();

	$nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

	$labels = _get_custom_object_labels( $post_type_object, $nohier_vs_hier_defaults );

	if ( ! isset( $post_type_object->labels->template_name ) && isset( $post_type_object->labels->singular_name ) ) {
			/* translators: %s: Post type name. */
			$labels->template_name = sprintf( __( 'Single item: %s' ), $post_type_object->labels->singular_name );
	}

	$post_type = $post_type_object->name;

	$default_labels = clone $labels;

	/**
	 * Filters the labels of a specific post type.
	 *
	 * The dynamic portion of the hook name, `$post_type`, refers to
	 * the post type slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `post_type_labels_post`
	 *  - `post_type_labels_page`
	 *  - `post_type_labels_attachment`
	 *
	 * @since 3.5.0
	 *
	 * @see get_post_type_labels() for the full list of labels.
	 *
	 * @param object $labels Object with labels for the post type as member variables.
	 */
	$labels = apply_filters( "post_type_labels_{$post_type}", $labels );

	// Ensure that the filtered labels contain all required default values.
	$labels = (object) array_merge( (array) $default_labels, (array) $labels );

	return $labels;
}

/**
 * Builds an object with custom-something object (post type, taxonomy) labels
 * out of a custom-something object
 *
 * @since 3.0.0
 * @access private
 *
 * @param object $data_object             A custom-something object.
 * @param array  $nohier_vs_hier_defaults Hierarchical vs non-hierarchical default labels.
 * @return object Object containing labels for the given custom-something object.
 */
function _get_custom_object_labels( $data_object, $nohier_vs_hier_defaults ) {
	$data_object->labels = (array) $data_object->labels;

	if ( isset( $data_object->label ) && empty( $data_object->labels['name'] ) ) {
		$data_object->labels['name'] = $data_object->label;
	}

	if ( ! isset( $data_object->labels['singular_name'] ) && isset( $data_object->labels['name'] ) ) {
		$data_object->labels['singular_name'] = $data_object->labels['name'];
	}

	if ( ! isset( $data_object->labels['name_admin_bar'] ) ) {
		$data_object->labels['name_admin_bar'] =
			isset( $data_object->labels['singular_name'] )
			? $data_object->labels['singular_name']
			: $data_object->name;
	}

	if ( ! isset( $data_object->labels['menu_name'] ) && isset( $data_object->labels['name'] ) ) {
		$data_object->labels['menu_name'] = $data_object->labels['name'];
	}

	if ( ! isset( $data_object->labels['all_items'] ) && isset( $data_object->labels['menu_name'] ) ) {
		$data_object->labels['all_items'] = $data_object->labels['menu_name'];
	}

	if ( ! isset( $data_object->labels['archives'] ) && isset( $data_object->labels['all_items'] ) ) {
		$data_object->labels['archives'] = $data_object->labels['all_items'];
	}

	$defaults = array();
	foreach ( $nohier_vs_hier_defaults as $key => $value ) {
		$defaults[ $key ] = $data_object->hierarchical ? $value[1] : $value[0];
	}

	$labels              = array_merge( $defaults, $data_object->labels );
	$data_object->labels = (object) $data_object->labels;

	return (object) $labels;
}

/**
 * Adds submenus for post types.
 *
 * @access private
 * @since 3.1.0
 */
function _add_post_type_submenus() {
	foreach ( get_post_types( array( 'show_ui' => true ) ) as $ptype ) {
		$ptype_obj = get_post_type_object( $ptype );
		// Sub-menus only.
		if ( ! $ptype_obj->show_in_menu || true === $ptype_obj->show_in_menu ) {
			continue;
		}
		add_submenu_page( $ptype_obj->show_in_menu, $ptype_obj->labels->name, $ptype_obj->labels->all_items, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype" );
	}
}

/**
 * Registers support of certain features for a post type.
 *
 * All core features are directly associated with a functional area of the edit
 * screen, such as the editor or a meta box. Features include: 'title', 'editor',
 * 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 'page-attributes',
 * 'thumbnail', 'custom-fields', and 'post-formats'.
 *
 * Additionally, the 'revisions' feature dictates whether the post type will
 * store revisions, the 'autosave' feature dictates whether the post type
 * will be autosaved, and the 'comments' feature dictates whether the comments
 * count will show on the edit screen.
 *
 * A third, optional parameter can also be passed along with a feature to provide
 * additional information about supporting that feature.
 *
 * Example usage:
 *
 *     add_post_type_support( 'my_post_type', 'comments' );
 *     add_post_type_support( 'my_post_type', array(
 *         'author', 'excerpt',
 *     ) );
 *     add_post_type_support( 'my_post_type', 'my_feature', array(
 *         'field' => 'value',
 *     ) );
 *
 * @since 3.0.0
 * @since 5.3.0 Formalized the existing and already documented `...$args` parameter
 *              by adding it to the function signature.
 *
 * @global array $_wp_post_type_features
 *
 * @param string       $post_type The post type for which to add the feature.
 * @param string|array $feature   The feature being added, accepts an array of
 *                                feature strings or a single string.
 * @param mixed        ...$args   Optional extra arguments to pass along with certain features.
 */
function add_post_type_support( $post_type, $feature, ...$args ) {
	global $_wp_post_type_features;

	$features = (array) $feature;
	foreach ( $features as $feature ) {
		if ( $args ) {
			$_wp_post_type_features[ $post_type ][ $feature ] = $args;
		} else {
			$_wp_post_type_features[ $post_type ][ $feature ] = true;
		}
	}
}

/**
 * Removes support for a feature from a post type.
 *
 * @since 3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type for which to remove the feature.
 * @param string $feature   The feature being removed.
 */
function remove_post_type_support( $post_type, $feature ) {
	global $_wp_post_type_features;

	unset( $_wp_post_type_features[ $post_type ][ $feature ] );
}

/**
 * Gets all the post type features
 *
 * @since 3.4.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type.
 * @return array Post type supports list.
 */
function get_all_post_type_supports( $post_type ) {
	global $_wp_post_type_features;

	if ( isset( $_wp_post_type_features[ $post_type ] ) ) {
		return $_wp_post_type_features[ $post_type ];
	}

	return array();
}

/**
 * Checks a post type's support for a given feature.
 *
 * @since 3.0.0
 *
 * @global array $_wp_post_type_features
 *
 * @param string $post_type The post type being checked.
 * @param string $feature   The feature being checked.
 * @return bool Whether the post type supports the given feature.
 */
function post_type_supports( $post_type, $feature ) {
	global $_wp_post_type_features;

	return ( isset( $_wp_post_type_features[ $post_type ][ $feature ] ) );
}

/**
 * Retrieves a list of post type names that support a specific feature.
 *
 * @since 4.5.0
 *
 * @global array $_wp_post_type_features Post type features
 *
 * @param array|string $feature  Single feature or an array of features the post types should support.
 * @param string       $operator Optional. The logical operation to perform. 'or' means
 *                               only one element from the array needs to match; 'and'
 *                               means all elements must match; 'not' means no elements may
 *                               match. Default 'and'.
 * @return string[] A list of post type names.
 */
function get_post_types_by_support( $feature, $operator = 'and' ) {
	global $_wp_post_type_features;

	$features = array_fill_keys( (array) $feature, true );

	return array_keys( wp_filter_object_list( $_wp_post_type_features, $features, $operator ) );
}

/**
 * Updates the post type for the post ID.
 *
 * The page or post cache will be cleaned for the post ID.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $post_id   Optional. Post ID to change post type. Default 0.
 * @param string $post_type Optional. Post type. Accepts 'post' or 'page' to
 *                          name a few. Default 'post'.
 * @return int|false Amount of rows changed. Should be 1 for success and 0 for failure.
 */
function set_post_type( $post_id = 0, $post_type = 'post' ) {
	global $wpdb;

	$post_type = sanitize_post_field( 'post_type', $post_type, $post_id, 'db' );
	$return    = $wpdb->update( $wpdb->posts, array( 'post_type' => $post_type ), array( 'ID' => $post_id ) );

	clean_post_cache( $post_id );

	return $return;
}

/**
 * Determines whether a post type is considered "viewable".
 *
 * For built-in post types such as posts and pages, the 'public' value will be evaluated.
 * For all others, the 'publicly_queryable' value will be used.
 *
 * @since 4.4.0
 * @since 4.5.0 Added the ability to pass a post type name in addition to object.
 * @since 4.6.0 Converted the `$post_type` parameter to accept a `WP_Post_Type` object.
 * @since 5.9.0 Added `is_post_type_viewable` hook to filter the result.
 *
 * @param string|WP_Post_Type $post_type Post type name or object.
 * @return bool Whether the post type should be considered viewable.
 */
function is_post_type_viewable( $post_type ) {
	if ( is_scalar( $post_type ) ) {
		$post_type = get_post_type_object( $post_type );

		if ( ! $post_type ) {
			return false;
		}
	}

	if ( ! is_object( $post_type ) ) {
		return false;
	}

	$is_viewable = $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );

	/**
	 * Filters whether a post type is considered "viewable".
	 *
	 * The returned filtered value must be a boolean type to ensure
	 * `is_post_type_viewable()` only returns a boolean. This strictness
	 * is by design to maintain backwards-compatibility and guard against
	 * potential type errors in PHP 8.1+. Non-boolean values (even falsey
	 * and truthy values) will result in the function returning false.
	 *
	 * @since 5.9.0
	 *
	 * @param bool         $is_viewable Whether the post type is "viewable" (strict type).
	 * @param WP_Post_Type $post_type   Post type object.
	 */
	return true === apply_filters( 'is_post_type_viewable', $is_viewable, $post_type );
}

/**
 * Determines whether a post status is considered "viewable".
 *
 * For built-in post statuses such as publish and private, the 'public' value will be evaluated.
 * For all others, the 'publicly_queryable' value will be used.
 *
 * @since 5.7.0
 * @since 5.9.0 Added `is_post_status_viewable` hook to filter the result.
 *
 * @param string|stdClass $post_status Post status name or object.
 * @return bool Whether the post status should be considered viewable.
 */
function is_post_status_viewable( $post_status ) {
	if ( is_scalar( $post_status ) ) {
		$post_status = get_post_status_object( $post_status );

		if ( ! $post_status ) {
			return false;
		}
	}

	if (
		! is_object( $post_status ) ||
		$post_status->internal ||
		$post_status->protected
	) {
		return false;
	}

	$is_viewable = $post_status->publicly_queryable || ( $post_status->_builtin && $post_status->public );

	/**
	 * Filters whether a post status is considered "viewable".
	 *
	 * The returned filtered value must be a boolean type to ensure
	 * `is_post_status_viewable()` only returns a boolean. This strictness
	 * is by design to maintain backwards-compatibility and guard against
	 * potential type errors in PHP 8.1+. Non-boolean values (even falsey
	 * and truthy values) will result in the function returning false.
	 *
	 * @since 5.9.0
	 *
	 * @param bool     $is_viewable Whether the post status is "viewable" (strict type).
	 * @param stdClass $post_status Post status object.
	 */
	return true === apply_filters( 'is_post_status_viewable', $is_viewable, $post_status );
}

/**
 * Determines whether a post is publicly viewable.
 *
 * Posts are considered publicly viewable if both the post status and post type
 * are viewable.
 *
 * @since 5.7.0
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return bool Whether the post is publicly viewable.
 */
function is_post_publicly_viewable( $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$post_type   = get_post_type( $post );
	$post_status = get_post_status( $post );

	return is_post_type_viewable( $post_type ) && is_post_status_viewable( $post_status );
}

/**
 * Retrieves an array of the latest posts, or posts matching the given criteria.
 *
 * For more information on the accepted arguments, see the
 * {@link https://developer.wordpress.org/reference/classes/wp_query/
 * WP_Query} documentation in the Developer Handbook.
 *
 * The `$ignore_sticky_posts` and `$no_found_rows` arguments are ignored by
 * this function and both are set to `true`.
 *
 * The defaults are as follows:
 *
 * @since 1.2.0
 *
 * @see WP_Query
 * @see WP_Query::parse_query()
 *
 * @param array $args {
 *     Optional. Arguments to retrieve posts. See WP_Query::parse_query() for all available arguments.
 *
 *     @type int        $numberposts      Total number of posts to retrieve. Is an alias of `$posts_per_page`
 *                                        in WP_Query. Accepts -1 for all. Default 5.
 *     @type int|string $category         Category ID or comma-separated list of IDs (this or any children).
 *                                        Is an alias of `$cat` in WP_Query. Default 0.
 *     @type int[]      $include          An array of post IDs to retrieve, sticky posts will be included.
 *                                        Is an alias of `$post__in` in WP_Query. Default empty array.
 *     @type int[]      $exclude          An array of post IDs not to retrieve. Default empty array.
 *     @type bool       $suppress_filters Whether to suppress filters. Default true.
 * }
 * @return WP_Post[]|int[] Array of post objects or post IDs.
 */
function get_posts( $args = null ) {
	$defaults = array(
		'numberposts'      => 5,
		'category'         => 0,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'include'          => array(),
		'exclude'          => array(),
		'meta_key'         => '',
		'meta_value'       => '',
		'post_type'        => 'post',
		'suppress_filters' => true,
	);

	$parsed_args = wp_parse_args( $args, $defaults );
	if ( empty( $parsed_args['post_status'] ) ) {
		$parsed_args['post_status'] = ( 'attachment' === $parsed_args['post_type'] ) ? 'inherit' : 'publish';
	}
	if ( ! empty( $parsed_args['numberposts'] ) && empty( $parsed_args['posts_per_page'] ) ) {
		$parsed_args['posts_per_page'] = $parsed_args['numberposts'];
	}
	if ( ! empty( $parsed_args['category'] ) ) {
		$parsed_args['cat'] = $parsed_args['category'];
	}
	if ( ! empty( $parsed_args['include'] ) ) {
		$incposts                      = wp_parse_id_list( $parsed_args['include'] );
		$parsed_args['posts_per_page'] = count( $incposts );  // Only the number of posts included.
		$parsed_args['post__in']       = $incposts;
	} elseif ( ! empty( $parsed_args['exclude'] ) ) {
		$parsed_args['post__not_in'] = wp_parse_id_list( $parsed_args['exclude'] );
	}

	$parsed_args['ignore_sticky_posts'] = true;
	$parsed_args['no_found_rows']       = true;

	$get_posts = new WP_Query();
	return $get_posts->query( $parsed_args );
}

//
// Post meta functions.
//

/**
 * Adds a meta field to the given post.
 *
 * Post meta data is called "Custom Fields" on the Administration Screen.
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool   $unique     Optional. Whether the same key should not be added.
 *                           Default false.
 * @return int|false Meta ID on success, false on failure.
 */
function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
	// Make sure meta is added to the post, not a revision.
	$the_post = wp_is_post_revision( $post_id );
	if ( $the_post ) {
		$post_id = $the_post;
	}

	return add_metadata( 'post', $post_id, $meta_key, $meta_value, $unique );
}

/**
 * Deletes a post meta field for the given post ID.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching the key, if needed.
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value. If provided,
 *                           rows will only be removed that match the value.
 *                           Must be serializable if non-scalar. Default empty.
 * @return bool True on success, false on failure.
 */
function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
	// Make sure meta is deleted from the post, not from a revision.
	$the_post = wp_is_post_revision( $post_id );
	if ( $the_post ) {
		$post_id = $the_post;
	}

	return delete_metadata( 'post', $post_id, $meta_key, $meta_value );
}

/**
 * Retrieves a post meta field for the given post ID.
 *
 * @since 1.5.0
 *
 * @param int    $post_id Post ID.
 * @param string $key     Optional. The meta key to retrieve. By default,
 *                        returns data for all keys. Default empty.
 * @param bool   $single  Optional. Whether to return a single value.
 *                        This parameter has no effect if `$key` is not specified.
 *                        Default false.
 * @return mixed An array of values if `$single` is false.
 *               The value of the meta field if `$single` is true.
 *               False for an invalid `$post_id` (non-numeric, zero, or negative value).
 *               An empty array if a valid but non-existing post ID is passed and `$single` is false.
 *               An empty string if a valid but non-existing post ID is passed and `$single` is true.
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	return get_metadata( 'post', $post_id, $key, $single );
}

/**
 * Updates a post meta field based on the given post ID.
 *
 * Use the `$prev_value` parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added and its ID returned.
 *
 * Can be used in place of add_post_meta().
 *
 * @since 1.5.0
 *
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed  $prev_value Optional. Previous value to check before updating.
 *                           If specified, only update existing metadata entries with
 *                           this value. Otherwise, update all entries. Default empty.
 * @return int|bool Meta ID if the key didn't exist, true on successful update,
 *                  false on failure or if the value passed to the function
 *                  is the same as the one that is already in the database.
 */
function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
	// Make sure meta is updated for the post, not for a revision.
	$the_post = wp_is_post_revision( $post_id );
	if ( $the_post ) {
		$post_id = $the_post;
	}

	return update_metadata( 'post', $post_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Deletes everything from post meta matching the given meta key.
 *
 * @since 2.3.0
 *
 * @param string $post_meta_key Key to search for when deleting.
 * @return bool Whether the post meta key was deleted from the database.
 */
function delete_post_meta_by_key( $post_meta_key ) {
	return delete_metadata( 'post', null, $post_meta_key, '', true );
}

/**
 * Registers a meta key for posts.
 *
 * @since 4.9.8
 *
 * @param string $post_type Post type to register a meta key for. Pass an empty string
 *                          to register the meta key across all existing post types.
 * @param string $meta_key  The meta key to register.
 * @param array  $args      Data used to describe the meta key when registered. See
 *                          {@see register_meta()} for a list of supported arguments.
 * @return bool True if the meta key was successfully registered, false if not.
 */
function register_post_meta( $post_type, $meta_key, array $args ) {
	$args['object_subtype'] = $post_type;

	return register_meta( 'post', $meta_key, $args );
}

/**
 * Unregisters a meta key for posts.
 *
 * @since 4.9.8
 *
 * @param string $post_type Post type the meta key is currently registered for. Pass
 *                          an empty string if the meta key is registered across all
 *                          existing post types.
 * @param string $meta_key  The meta key to unregister.
 * @return bool True on success, false if the meta key was not previously registered.
 */
function unregister_post_meta( $post_type, $meta_key ) {
	return unregister_meta_key( 'post', $meta_key, $post_type );
}

/**
 * Retrieves post meta fields, based on post ID.
 *
 * The post meta fields are retrieved from the cache where possible,
 * so the function is optimized to be called more than once.
 *
 * @since 1.2.0
 *
 * @param int $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return mixed An array of values.
 *               False for an invalid `$post_id` (non-numeric, zero, or negative value).
 *               An empty string if a valid but non-existing post ID is passed.
 */
function get_post_custom( $post_id = 0 ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	return get_post_meta( $post_id );
}

/**
 * Retrieves meta field names for a post.
 *
 * If there are no meta fields, then nothing (null) will be returned.
 *
 * @since 1.2.0
 *
 * @param int $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return array|void Array of the keys, if retrieved.
 */
function get_post_custom_keys( $post_id = 0 ) {
	$custom = get_post_custom( $post_id );

	if ( ! is_array( $custom ) ) {
		return;
	}

	$keys = array_keys( $custom );
	if ( $keys ) {
		return $keys;
	}
}

/**
 * Retrieves values for a custom post field.
 *
 * The parameters must not be considered optional. All of the post meta fields
 * will be retrieved and only the meta field key values returned.
 *
 * @since 1.2.0
 *
 * @param string $key     Optional. Meta field key. Default empty.
 * @param int    $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return array|null Meta field values.
 */
function get_post_custom_values( $key = '', $post_id = 0 ) {
	if ( ! $key ) {
		return null;
	}

	$custom = get_post_custom( $post_id );

	return isset( $custom[ $key ] ) ? $custom[ $key ] : null;
}

/**
 * Determines whether a post is sticky.
 *
 * Sticky posts should remain at the top of The Loop. If the post ID is not
 * given, then The Loop ID for the current post will be used.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.7.0
 *
 * @param int $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return bool Whether post is sticky.
 */
function is_sticky( $post_id = 0 ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$stickies = get_option( 'sticky_posts' );

	if ( is_array( $stickies ) ) {
		$stickies  = array_map( 'intval', $stickies );
		$is_sticky = in_array( $post_id, $stickies, true );
	} else {
		$is_sticky = false;
	}

	/**
	 * Filters whether a post is sticky.
	 *
	 * @since 5.3.0
	 *
	 * @param bool $is_sticky Whether a post is sticky.
	 * @param int  $post_id   Post ID.
	 */
	return apply_filters( 'is_sticky', $is_sticky, $post_id );
}

/**
 * Sanitizes every post field.
 *
 * If the context is 'raw', then the post object or array will get minimal
 * sanitization of the integer fields.
 *
 * @since 2.3.0
 *
 * @see sanitize_post_field()
 *
 * @param object|WP_Post|array $post    The post object or array
 * @param string               $context Optional. How to sanitize post fields.
 *                                      Accepts 'raw', 'edit', 'db', 'display',
 *                                      'attribute', or 'js'. Default 'display'.
 * @return object|WP_Post|array The now sanitized post object or array (will be the
 *                              same type as `$post`).
 */
function sanitize_post( $post, $context = 'display' ) {
	if ( is_object( $post ) ) {
		// Check if post already filtered for this context.
		if ( isset( $post->filter ) && $context == $post->filter ) {
			return $post;
		}
		if ( ! isset( $post->ID ) ) {
			$post->ID = 0;
		}
		foreach ( array_keys( get_object_vars( $post ) ) as $field ) {
			$post->$field = sanitize_post_field( $field, $post->$field, $post->ID, $context );
		}
		$post->filter = $context;
	} elseif ( is_array( $post ) ) {
		// Check if post already filtered for this context.
		if ( isset( $post['filter'] ) && $context == $post['filter'] ) {
			return $post;
		}
		if ( ! isset( $post['ID'] ) ) {
			$post['ID'] = 0;
		}
		foreach ( array_keys( $post ) as $field ) {
			$post[ $field ] = sanitize_post_field( $field, $post[ $field ], $post['ID'], $context );
		}
		$post['filter'] = $context;
	}
	return $post;
}

/**
 * Sanitizes a post field based on context.
 *
 * Possible context values are:  'raw', 'edit', 'db', 'display', 'attribute' and
 * 'js'. The 'display' context is used by default. 'attribute' and 'js' contexts
 * are treated like 'display' when calling filters.
 *
 * @since 2.3.0
 * @since 4.4.0 Like `sanitize_post()`, `$context` defaults to 'display'.
 *
 * @param string $field   The Post Object field name.
 * @param mixed  $value   The Post Object value.
 * @param int    $post_id Post ID.
 * @param string $context Optional. How to sanitize the field. Possible values are 'raw', 'edit',
 *                        'db', 'display', 'attribute' and 'js'. Default 'display'.
 * @return mixed Sanitized value.
 */
function sanitize_post_field( $field, $value, $post_id, $context = 'display' ) {
	$int_fields = array( 'ID', 'post_parent', 'menu_order' );
	if ( in_array( $field, $int_fields, true ) ) {
		$value = (int) $value;
	}

	// Fields which contain arrays of integers.
	$array_int_fields = array( 'ancestors' );
	if ( in_array( $field, $array_int_fields, true ) ) {
		$value = array_map( 'absint', $value );
		return $value;
	}

	if ( 'raw' === $context ) {
		return $value;
	}

	$prefixed = false;
	if ( str_contains( $field, 'post_' ) ) {
		$prefixed        = true;
		$field_no_prefix = str_replace( 'post_', '', $field );
	}

	if ( 'edit' === $context ) {
		$format_to_edit = array( 'post_content', 'post_excerpt', 'post_title', 'post_password' );

		if ( $prefixed ) {

			/**
			 * Filters the value of a specific post field to edit.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the post
			 * field name. Possible filter names include:
			 *
			 *  - `edit_post_author`
			 *  - `edit_post_date`
			 *  - `edit_post_date_gmt`
			 *  - `edit_post_content`
			 *  - `edit_post_title`
			 *  - `edit_post_excerpt`
			 *  - `edit_post_status`
			 *  - `edit_post_password`
			 *  - `edit_post_name`
			 *  - `edit_post_modified`
			 *  - `edit_post_modified_gmt`
			 *  - `edit_post_content_filtered`
			 *  - `edit_post_parent`
			 *  - `edit_post_type`
			 *  - `edit_post_mime_type`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value   Value of the post field.
			 * @param int   $post_id Post ID.
			 */
			$value = apply_filters( "edit_{$field}", $value, $post_id );

			/**
			 * Filters the value of a specific post field to edit.
			 *
			 * Only applied to post fields with a name which is prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
			 * post field name minus the `post_` prefix. Possible filter names include:
			 *
			 *  - `author_edit_pre`
			 *  - `date_edit_pre`
			 *  - `date_gmt_edit_pre`
			 *  - `content_edit_pre`
			 *  - `title_edit_pre`
			 *  - `excerpt_edit_pre`
			 *  - `status_edit_pre`
			 *  - `password_edit_pre`
			 *  - `name_edit_pre`
			 *  - `modified_edit_pre`
			 *  - `modified_gmt_edit_pre`
			 *  - `content_filtered_edit_pre`
			 *  - `parent_edit_pre`
			 *  - `type_edit_pre`
			 *  - `mime_type_edit_pre`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value   Value of the post field.
			 * @param int   $post_id Post ID.
			 */
			$value = apply_filters( "{$field_no_prefix}_edit_pre", $value, $post_id );
		} else {
			/**
			 * Filters the value of a specific post field to edit.
			 *
			 * Only applied to post fields not prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the
			 * post field name. Possible filter names include:
			 *
			 *  - `edit_post_ID`
			 *  - `edit_post_ping_status`
			 *  - `edit_post_pinged`
			 *  - `edit_post_to_ping`
			 *  - `edit_post_comment_count`
			 *  - `edit_post_comment_status`
			 *  - `edit_post_guid`
			 *  - `edit_post_menu_order`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value   Value of the post field.
			 * @param int   $post_id Post ID.
			 */
			$value = apply_filters( "edit_post_{$field}", $value, $post_id );
		}

		if ( in_array( $field, $format_to_edit, true ) ) {
			if ( 'post_content' === $field ) {
				$value = format_to_edit( $value, user_can_richedit() );
			} else {
				$value = format_to_edit( $value );
			}
		} else {
			$value = esc_attr( $value );
		}
	} elseif ( 'db' === $context ) {
		if ( $prefixed ) {

			/**
			 * Filters the value of a specific post field before saving.
			 *
			 * Only applied to post fields with a name which is prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the post
			 * field name. Possible filter names include:
			 *
			 *  - `pre_post_author`
			 *  - `pre_post_date`
			 *  - `pre_post_date_gmt`
			 *  - `pre_post_content`
			 *  - `pre_post_title`
			 *  - `pre_post_excerpt`
			 *  - `pre_post_status`
			 *  - `pre_post_password`
			 *  - `pre_post_name`
			 *  - `pre_post_modified`
			 *  - `pre_post_modified_gmt`
			 *  - `pre_post_content_filtered`
			 *  - `pre_post_parent`
			 *  - `pre_post_type`
			 *  - `pre_post_mime_type`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value Value of the post field.
			 */
			$value = apply_filters( "pre_{$field}", $value );

			/**
			 * Filters the value of a specific field before saving.
			 *
			 * Only applied to post fields with a name which is prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
			 * post field name minus the `post_` prefix. Possible filter names include:
			 *
			 *  - `author_save_pre`
			 *  - `date_save_pre`
			 *  - `date_gmt_save_pre`
			 *  - `content_save_pre`
			 *  - `title_save_pre`
			 *  - `excerpt_save_pre`
			 *  - `status_save_pre`
			 *  - `password_save_pre`
			 *  - `name_save_pre`
			 *  - `modified_save_pre`
			 *  - `modified_gmt_save_pre`
			 *  - `content_filtered_save_pre`
			 *  - `parent_save_pre`
			 *  - `type_save_pre`
			 *  - `mime_type_save_pre`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value Value of the post field.
			 */
			$value = apply_filters( "{$field_no_prefix}_save_pre", $value );
		} else {
			/**
			 * Filters the value of a specific field before saving.
			 *
			 * Only applied to post fields with a name which is prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field_no_prefix`, refers to the
			 * post field name minus the `post_` prefix. Possible filter names include:
			 *
			 *  - `pre_post_ID`
			 *  - `pre_post_comment_status`
			 *  - `pre_post_ping_status`
			 *  - `pre_post_to_ping`
			 *  - `pre_post_pinged`
			 *  - `pre_post_guid`
			 *  - `pre_post_menu_order`
			 *  - `pre_post_comment_count`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value Value of the post field.
			 */
			$value = apply_filters( "pre_post_{$field}", $value );

			/**
			 * Filters the value of a specific post field before saving.
			 *
			 * Only applied to post fields with a name which is *not* prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the post
			 * field name. Possible filter names include:
			 *
			 *  - `ID_pre`
			 *  - `comment_status_pre`
			 *  - `ping_status_pre`
			 *  - `to_ping_pre`
			 *  - `pinged_pre`
			 *  - `guid_pre`
			 *  - `menu_order_pre`
			 *  - `comment_count_pre`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed $value Value of the post field.
			 */
			$value = apply_filters( "{$field}_pre", $value );
		}
	} else {

		// Use display filters by default.
		if ( $prefixed ) {

			/**
			 * Filters the value of a specific post field for display.
			 *
			 * Only applied to post fields with a name which is prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the post
			 * field name. Possible filter names include:
			 *
			 *  - `post_author`
			 *  - `post_date`
			 *  - `post_date_gmt`
			 *  - `post_content`
			 *  - `post_title`
			 *  - `post_excerpt`
			 *  - `post_status`
			 *  - `post_password`
			 *  - `post_name`
			 *  - `post_modified`
			 *  - `post_modified_gmt`
			 *  - `post_content_filtered`
			 *  - `post_parent`
			 *  - `post_type`
			 *  - `post_mime_type`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed  $value   Value of the prefixed post field.
			 * @param int    $post_id Post ID.
			 * @param string $context Context for how to sanitize the field.
			 *                        Accepts 'raw', 'edit', 'db', 'display',
			 *                        'attribute', or 'js'. Default 'display'.
			 */
			$value = apply_filters( "{$field}", $value, $post_id, $context );
		} else {
			/**
			 * Filters the value of a specific post field for display.
			 *
			 * Only applied to post fields name which is *not* prefixed with `post_`.
			 *
			 * The dynamic portion of the hook name, `$field`, refers to the post
			 * field name. Possible filter names include:
			 *
			 *  - `post_ID`
			 *  - `post_comment_status`
			 *  - `post_ping_status`
			 *  - `post_to_ping`
			 *  - `post_pinged`
			 *  - `post_guid`
			 *  - `post_menu_order`
			 *  - `post_comment_count`
			 *
			 * @since 2.3.0
			 *
			 * @param mixed  $value   Value of the unprefixed post field.
			 * @param int    $post_id Post ID
			 * @param string $context Context for how to sanitize the field.
			 *                        Accepts 'raw', 'edit', 'db', 'display',
			 *                        'attribute', or 'js'. Default 'display'.
			 */
			$value = apply_filters( "post_{$field}", $value, $post_id, $context );
		}

		if ( 'attribute' === $context ) {
			$value = esc_attr( $value );
		} elseif ( 'js' === $context ) {
			$value = esc_js( $value );
		}
	}

	// Restore the type for integer fields after esc_attr().
	if ( in_array( $field, $int_fields, true ) ) {
		$value = (int) $value;
	}
	return $value;
}

/**
 * Makes a post sticky.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function stick_post( $post_id ) {
	$post_id  = (int) $post_id;
	$stickies = get_option( 'sticky_posts' );
	$updated  = false;

	if ( ! is_array( $stickies ) ) {
		$stickies = array();
	} else {
		$stickies = array_unique( array_map( 'intval', $stickies ) );
	}

	if ( ! in_array( $post_id, $stickies, true ) ) {
		$stickies[] = $post_id;
		$updated    = update_option( 'sticky_posts', array_values( $stickies ) );
	}

	if ( $updated ) {
		/**
		 * Fires once a post has been added to the sticky list.
		 *
		 * @since 4.6.0
		 *
		 * @param int $post_id ID of the post that was stuck.
		 */
		do_action( 'post_stuck', $post_id );
	}
}

/**
 * Un-sticks a post.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function unstick_post( $post_id ) {
	$post_id  = (int) $post_id;
	$stickies = get_option( 'sticky_posts' );

	if ( ! is_array( $stickies ) ) {
		return;
	}

	$stickies = array_values( array_unique( array_map( 'intval', $stickies ) ) );

	if ( ! in_array( $post_id, $stickies, true ) ) {
		return;
	}

	$offset = array_search( $post_id, $stickies, true );
	if ( false === $offset ) {
		return;
	}

	array_splice( $stickies, $offset, 1 );

	$updated = update_option( 'sticky_posts', $stickies );

	if ( $updated ) {
		/**
		 * Fires once a post has been removed from the sticky list.
		 *
		 * @since 4.6.0
		 *
		 * @param int $post_id ID of the post that was unstuck.
		 */
		do_action( 'post_unstuck', $post_id );
	}
}

/**
 * Returns the cache key for wp_count_posts() based on the passed arguments.
 *
 * @since 3.9.0
 * @access private
 *
 * @param string $type Optional. Post type to retrieve count Default 'post'.
 * @param string $perm Optional. 'readable' or empty. Default empty.
 * @return string The cache key.
 */
function _count_posts_cache_key( $type = 'post', $perm = '' ) {
	$cache_key = 'posts-' . $type;

	if ( 'readable' === $perm && is_user_logged_in() ) {
		$post_type_object = get_post_type_object( $type );

		if ( $post_type_object && ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			$cache_key .= '_' . $perm . '_' . get_current_user_id();
		}
	}

	return $cache_key;
}

/**
 * Counts number of posts of a post type and if user has permissions to view.
 *
 * This function provides an efficient method of finding the amount of post's
 * type a blog has. Another method is to count the amount of items in
 * get_posts(), but that method has a lot of overhead with doing so. Therefore,
 * when developing for 2.5+, use this function instead.
 *
 * The $perm parameter checks for 'readable' value and if the user can read
 * private posts, it will display that for the user that is signed in.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $type Optional. Post type to retrieve count. Default 'post'.
 * @param string $perm Optional. 'readable' or empty. Default empty.
 * @return stdClass An object containing the number of posts for each status,
 *                  or an empty object if the post type does not exist.
 */
function wp_count_posts( $type = 'post', $perm = '' ) {
	global $wpdb;

	if ( ! post_type_exists( $type ) ) {
		return new stdClass();
	}

	$cache_key = _count_posts_cache_key( $type, $perm );

	$counts = wp_cache_get( $cache_key, 'counts' );
	if ( false !== $counts ) {
		// We may have cached this before every status was registered.
		foreach ( get_post_stati() as $status ) {
			if ( ! isset( $counts->{$status} ) ) {
				$counts->{$status} = 0;
			}
		}

		/** This filter is documented in wp-includes/post.php */
		return apply_filters( 'wp_count_posts', $counts, $type, $perm );
	}

	$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";

	if ( 'readable' === $perm && is_user_logged_in() ) {
		$post_type_object = get_post_type_object( $type );
		if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			$query .= $wpdb->prepare(
				" AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
				get_current_user_id()
			);
		}
	}

	$query .= ' GROUP BY post_status';

	$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
	$counts  = array_fill_keys( get_post_stati(), 0 );

	foreach ( $results as $row ) {
		$counts[ $row['post_status'] ] = $row['num_posts'];
	}

	$counts = (object) $counts;
	wp_cache_set( $cache_key, $counts, 'counts' );

	/**
	 * Filters the post counts by status for the current post type.
	 *
	 * @since 3.7.0
	 *
	 * @param stdClass $counts An object containing the current post_type's post
	 *                         counts by status.
	 * @param string   $type   Post type.
	 * @param string   $perm   The permission to determine if the posts are 'readable'
	 *                         by the current user.
	 */
	return apply_filters( 'wp_count_posts', $counts, $type, $perm );
}

/**
 * Counts number of attachments for the mime type(s).
 *
 * If you set the optional mime_type parameter, then an array will still be
 * returned, but will only have the item you are looking for. It does not give
 * you the number of attachments that are children of a post. You can get that
 * by counting the number of children that post has.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|string[] $mime_type Optional. Array or comma-separated list of
 *                                   MIME patterns. Default empty.
 * @return stdClass An object containing the attachment counts by mime type.
 */
function wp_count_attachments( $mime_type = '' ) {
	global $wpdb;

	$cache_key = sprintf(
		'attachments%s',
		! empty( $mime_type ) ? ':' . str_replace( '/', '_', implode( '-', (array) $mime_type ) ) : ''
	);

	$counts = wp_cache_get( $cache_key, 'counts' );
	if ( false == $counts ) {
		$and   = wp_post_mime_type_where( $mime_type );
		$count = $wpdb->get_results( "SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' $and GROUP BY post_mime_type", ARRAY_A );

		$counts = array();
		foreach ( (array) $count as $row ) {
			$counts[ $row['post_mime_type'] ] = $row['num_posts'];
		}
		$counts['trash'] = $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status = 'trash' $and" );

		wp_cache_set( $cache_key, (object) $counts, 'counts' );
	}

	/**
	 * Filters the attachment counts by mime type.
	 *
	 * @since 3.7.0
	 *
	 * @param stdClass        $counts    An object containing the attachment counts by
	 *                                   mime type.
	 * @param string|string[] $mime_type Array or comma-separated list of MIME patterns.
	 */
	return apply_filters( 'wp_count_attachments', (object) $counts, $mime_type );
}

/**
 * Gets default post mime types.
 *
 * @since 2.9.0
 * @since 5.3.0 Added the 'Documents', 'Spreadsheets', and 'Archives' mime type groups.
 *
 * @return array List of post mime types.
 */
function get_post_mime_types() {
	$post_mime_types = array(   // array( adj, noun )
		'image'       => array(
			__( 'Images' ),
			__( 'Manage Images' ),
			/* translators: %s: Number of images. */
			_n_noop(
				'Image <span class="count">(%s)</span>',
				'Images <span class="count">(%s)</span>'
			),
		),
		'audio'       => array(
			_x( 'Audio', 'file type group' ),
			__( 'Manage Audio' ),
			/* translators: %s: Number of audio files. */
			_n_noop(
				'Audio <span class="count">(%s)</span>',
				'Audio <span class="count">(%s)</span>'
			),
		),
		'video'       => array(
			_x( 'Video', 'file type group' ),
			__( 'Manage Video' ),
			/* translators: %s: Number of video files. */
			_n_noop(
				'Video <span class="count">(%s)</span>',
				'Video <span class="count">(%s)</span>'
			),
		),
		'document'    => array(
			__( 'Documents' ),
			__( 'Manage Documents' ),
			/* translators: %s: Number of documents. */
			_n_noop(
				'Document <span class="count">(%s)</span>',
				'Documents <span class="count">(%s)</span>'
			),
		),
		'spreadsheet' => array(
			__( 'Spreadsheets' ),
			__( 'Manage Spreadsheets' ),
			/* translators: %s: Number of spreadsheets. */
			_n_noop(
				'Spreadsheet <span class="count">(%s)</span>',
				'Spreadsheets <span class="count">(%s)</span>'
			),
		),
		'archive'     => array(
			_x( 'Archives', 'file type group' ),
			__( 'Manage Archives' ),
			/* translators: %s: Number of archives. */
			_n_noop(
				'Archive <span class="count">(%s)</span>',
				'Archives <span class="count">(%s)</span>'
			),
		),
	);

	$ext_types  = wp_get_ext_types();
	$mime_types = wp_get_mime_types();

	foreach ( $post_mime_types as $group => $labels ) {
		if ( in_array( $group, array( 'image', 'audio', 'video' ), true ) ) {
			continue;
		}

		if ( ! isset( $ext_types[ $group ] ) ) {
			unset( $post_mime_types[ $group ] );
			continue;
		}

		$group_mime_types = array();
		foreach ( $ext_types[ $group ] as $extension ) {
			foreach ( $mime_types as $exts => $mime ) {
				if ( preg_match( '!^(' . $exts . ')$!i', $extension ) ) {
					$group_mime_types[] = $mime;
					break;
				}
			}
		}
		$group_mime_types = implode( ',', array_unique( $group_mime_types ) );

		$post_mime_types[ $group_mime_types ] = $labels;
		unset( $post_mime_types[ $group ] );
	}

	/**
	 * Filters the default list of post mime types.
	 *
	 * @since 2.5.0
	 *
	 * @param array $post_mime_types Default list of post mime types.
	 */
	return apply_filters( 'post_mime_types', $post_mime_types );
}

/**
 * Checks a MIME-Type against a list.
 *
 * If the `$wildcard_mime_types` parameter is a string, it must be comma separated
 * list. If the `$real_mime_types` is a string, it is also comma separated to
 * create the list.
 *
 * @since 2.5.0
 *
 * @param string|string[] $wildcard_mime_types Mime types, e.g. `audio/mpeg`, `image` (same as `image/*`),
 *                                             or `flash` (same as `*flash*`).
 * @param string|string[] $real_mime_types     Real post mime type values.
 * @return array array(wildcard=>array(real types)).
 */
function wp_match_mime_types( $wildcard_mime_types, $real_mime_types ) {
	$matches = array();
	if ( is_string( $wildcard_mime_types ) ) {
		$wildcard_mime_types = array_map( 'trim', explode( ',', $wildcard_mime_types ) );
	}
	if ( is_string( $real_mime_types ) ) {
		$real_mime_types = array_map( 'trim', explode( ',', $real_mime_types ) );
	}

	$patternses = array();
	$wild       = '[-._a-z0-9]*';

	foreach ( (array) $wildcard_mime_types as $type ) {
		$mimes = array_map( 'trim', explode( ',', $type ) );
		foreach ( $mimes as $mime ) {
			$regex = str_replace( '__wildcard__', $wild, preg_quote( str_replace( '*', '__wildcard__', $mime ) ) );

			$patternses[][ $type ] = "^$regex$";

			if ( ! str_contains( $mime, '/' ) ) {
				$patternses[][ $type ] = "^$regex/";
				$patternses[][ $type ] = $regex;
			}
		}
	}
	asort( $patternses );

	foreach ( $patternses as $patterns ) {
		foreach ( $patterns as $type => $pattern ) {
			foreach ( (array) $real_mime_types as $real ) {
				if ( preg_match( "#$pattern#", $real )
					&& ( empty( $matches[ $type ] ) || false === array_search( $real, $matches[ $type ], true ) )
				) {
					$matches[ $type ][] = $real;
				}
			}
		}
	}

	return $matches;
}

/**
 * Converts MIME types into SQL.
 *
 * @since 2.5.0
 *
 * @param string|string[] $post_mime_types List of mime types or comma separated string
 *                                         of mime types.
 * @param string          $table_alias     Optional. Specify a table alias, if needed.
 *                                         Default empty.
 * @return string The SQL AND clause for mime searching.
 */
function wp_post_mime_type_where( $post_mime_types, $table_alias = '' ) {
	$where     = '';
	$wildcards = array( '', '%', '%/%' );
	if ( is_string( $post_mime_types ) ) {
		$post_mime_types = array_map( 'trim', explode( ',', $post_mime_types ) );
	}

	$where_clauses = array();

	foreach ( (array) $post_mime_types as $mime_type ) {
		$mime_type = preg_replace( '/\s/', '', $mime_type );
		$slashpos  = strpos( $mime_type, '/' );
		if ( false !== $slashpos ) {
			$mime_group    = preg_replace( '/[^-*.a-zA-Z0-9]/', '', substr( $mime_type, 0, $slashpos ) );
			$mime_subgroup = preg_replace( '/[^-*.+a-zA-Z0-9]/', '', substr( $mime_type, $slashpos + 1 ) );
			if ( empty( $mime_subgroup ) ) {
				$mime_subgroup = '*';
			} else {
				$mime_subgroup = str_replace( '/', '', $mime_subgroup );
			}
			$mime_pattern = "$mime_group/$mime_subgroup";
		} else {
			$mime_pattern = preg_replace( '/[^-*.a-zA-Z0-9]/', '', $mime_type );
			if ( ! str_contains( $mime_pattern, '*' ) ) {
				$mime_pattern .= '/*';
			}
		}

		$mime_pattern = preg_replace( '/\*+/', '%', $mime_pattern );

		if ( in_array( $mime_type, $wildcards, true ) ) {
			return '';
		}

		if ( str_contains( $mime_pattern, '%' ) ) {
			$where_clauses[] = empty( $table_alias ) ? "post_mime_type LIKE '$mime_pattern'" : "$table_alias.post_mime_type LIKE '$mime_pattern'";
		} else {
			$where_clauses[] = empty( $table_alias ) ? "post_mime_type = '$mime_pattern'" : "$table_alias.post_mime_type = '$mime_pattern'";
		}
	}

	if ( ! empty( $where_clauses ) ) {
		$where = ' AND (' . implode( ' OR ', $where_clauses ) . ') ';
	}

	return $where;
}

/**
 * Trashes or deletes a post or page.
 *
 * When the post and page is permanently deleted, everything that is tied to
 * it is deleted also. This includes comments, post meta fields, and terms
 * associated with the post.
 *
 * The post or page is moved to Trash instead of permanently deleted unless
 * Trash is disabled, item is already in the Trash, or $force_delete is true.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @see wp_delete_attachment()
 * @see wp_trash_post()
 *
 * @param int  $post_id      Optional. Post ID. Default 0.
 * @param bool $force_delete Optional. Whether to bypass Trash and force deletion.
 *                           Default false.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_delete_post( $post_id = 0, $force_delete = false ) {
	global $wpdb;

	$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) );

	if ( ! $post ) {
		return $post;
	}

	$post = get_post( $post );

	if ( ! $force_delete
		&& ( 'post' === $post->post_type || 'page' === $post->post_type )
		&& 'trash' !== get_post_status( $post_id ) && EMPTY_TRASH_DAYS
	) {
		return wp_trash_post( $post_id );
	}

	if ( 'attachment' === $post->post_type ) {
		return wp_delete_attachment( $post_id, $force_delete );
	}

	/**
	 * Filters whether a post deletion should take place.
	 *
	 * @since 4.4.0
	 *
	 * @param WP_Post|false|null $delete       Whether to go forward with deletion.
	 * @param WP_Post            $post         Post object.
	 * @param bool               $force_delete Whether to bypass the Trash.
	 */
	$check = apply_filters( 'pre_delete_post', null, $post, $force_delete );
	if ( null !== $check ) {
		return $check;
	}

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 *
	 * @since 3.2.0
	 * @since 5.5.0 Added the `$post` parameter.
	 *
	 * @see wp_delete_post()
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'before_delete_post', $post_id, $post );

	delete_post_meta( $post_id, '_wp_trash_meta_status' );
	delete_post_meta( $post_id, '_wp_trash_meta_time' );

	wp_delete_object_term_relationships( $post_id, get_object_taxonomies( $post->post_type ) );

	$parent_data  = array( 'post_parent' => $post->post_parent );
	$parent_where = array( 'post_parent' => $post_id );

	if ( is_post_type_hierarchical( $post->post_type ) ) {
		// Point children of this page to its parent, also clean the cache of affected children.
		$children_query = $wpdb->prepare(
			"SELECT * FROM $wpdb->posts WHERE post_parent = %d AND post_type = %s",
			$post_id,
			$post->post_type
		);

		$children = $wpdb->get_results( $children_query );

		if ( $children ) {
			$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => $post->post_type ) );
		}
	}

	// Do raw query. wp_get_post_revisions() is filtered.
	$revision_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'revision'", $post_id )
	);

	// Use wp_delete_post (via wp_delete_post_revision) again. Ensures any meta/misplaced data gets cleaned up.
	foreach ( $revision_ids as $revision_id ) {
		wp_delete_post_revision( $revision_id );
	}

	// Point all attachments to this post up one level.
	$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => 'attachment' ) );

	wp_defer_comment_counting( true );

	$comment_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d ORDER BY comment_ID DESC", $post_id )
	);

	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}

	wp_defer_comment_counting( false );

	$post_meta_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $post_id )
	);

	foreach ( $post_meta_ids as $mid ) {
		delete_metadata_by_mid( 'post', $mid );
	}

	/**
	 * Fires immediately before a post is deleted from the database.
	 *
	 * The dynamic portion of the hook name, `$post->post_type`, refers to
	 * the post type slug.
	 *
	 * @since 6.6.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( "delete_post_{$post->post_type}", $post_id, $post );

	/**
	 * Fires immediately before a post is deleted from the database.
	 *
	 * @since 1.2.0
	 * @since 5.5.0 Added the `$post` parameter.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'delete_post', $post_id, $post );

	$result = $wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
	if ( ! $result ) {
		return false;
	}

	/**
	 * Fires immediately after a post is deleted from the database.
	 *
	 * The dynamic portion of the hook name, `$post->post_type`, refers to
	 * the post type slug.
	 *
	 * @since 6.6.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( "deleted_post_{$post->post_type}", $post_id, $post );

	/**
	 * Fires immediately after a post is deleted from the database.
	 *
	 * @since 2.2.0
	 * @since 5.5.0 Added the `$post` parameter.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'deleted_post', $post_id, $post );

	clean_post_cache( $post );

	if ( is_post_type_hierarchical( $post->post_type ) && $children ) {
		foreach ( $children as $child ) {
			clean_post_cache( $child );
		}
	}

	wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );

	/**
	 * Fires after a post is deleted, at the conclusion of wp_delete_post().
	 *
	 * @since 3.2.0
	 * @since 5.5.0 Added the `$post` parameter.
	 *
	 * @see wp_delete_post()
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'after_delete_post', $post_id, $post );

	return $post;
}

/**
 * Resets the page_on_front, show_on_front, and page_for_post settings when
 * a linked page is deleted or trashed.
 *
 * Also ensures the post is no longer sticky.
 *
 * @since 3.7.0
 * @access private
 *
 * @param int $post_id Post ID.
 */
function _reset_front_page_settings_for_post( $post_id ) {
	$post = get_post( $post_id );

	if ( 'page' === $post->post_type ) {
		/*
		 * If the page is defined in option page_on_front or post_for_posts,
		 * adjust the corresponding options.
		 */
		if ( get_option( 'page_on_front' ) == $post->ID ) {
			update_option( 'show_on_front', 'posts' );
			update_option( 'page_on_front', 0 );
		}
		if ( get_option( 'page_for_posts' ) == $post->ID ) {
			update_option( 'page_for_posts', 0 );
		}
	}

	unstick_post( $post->ID );
}

/**
 * Moves a post or page to the Trash
 *
 * If Trash is disabled, the post or page is permanently deleted.
 *
 * @since 2.9.0
 *
 * @see wp_delete_post()
 *
 * @param int $post_id Optional. Post ID. Default is the ID of the global `$post`
 *                     if `EMPTY_TRASH_DAYS` equals true.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_trash_post( $post_id = 0 ) {
	if ( ! EMPTY_TRASH_DAYS ) {
		return wp_delete_post( $post_id, true );
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return $post;
	}

	if ( 'trash' === $post->post_status ) {
		return false;
	}

	$previous_status = $post->post_status;

	/**
	 * Filters whether a post trashing should take place.
	 *
	 * @since 4.9.0
	 * @since 6.3.0 Added the `$previous_status` parameter.
	 *
	 * @param bool|null $trash           Whether to go forward with trashing.
	 * @param WP_Post   $post            Post object.
	 * @param string    $previous_status The status of the post about to be trashed.
	 */
	$check = apply_filters( 'pre_trash_post', null, $post, $previous_status );

	if ( null !== $check ) {
		return $check;
	}

	/**
	 * Fires before a post is sent to the Trash.
	 *
	 * @since 3.3.0
	 * @since 6.3.0 Added the `$previous_status` parameter.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status The status of the post about to be trashed.
	 */
	do_action( 'wp_trash_post', $post_id, $previous_status );

	add_post_meta( $post_id, '_wp_trash_meta_status', $previous_status );
	add_post_meta( $post_id, '_wp_trash_meta_time', time() );

	$post_updated = wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'trash',
		)
	);

	if ( ! $post_updated ) {
		return false;
	}

	wp_trash_post_comments( $post_id );

	/**
	 * Fires after a post is sent to the Trash.
	 *
	 * @since 2.9.0
	 * @since 6.3.0 Added the `$previous_status` parameter.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	do_action( 'trashed_post', $post_id, $previous_status );

	return $post;
}

/**
 * Restores a post from the Trash.
 *
 * @since 2.9.0
 * @since 5.6.0 An untrashed post is now returned to 'draft' status by default, except for
 *              attachments which are returned to their original 'inherit' status.
 *
 * @param int $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_untrash_post( $post_id = 0 ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return $post;
	}

	$post_id = $post->ID;

	if ( 'trash' !== $post->post_status ) {
		return false;
	}

	$previous_status = get_post_meta( $post_id, '_wp_trash_meta_status', true );

	/**
	 * Filters whether a post untrashing should take place.
	 *
	 * @since 4.9.0
	 * @since 5.6.0 Added the `$previous_status` parameter.
	 *
	 * @param bool|null $untrash         Whether to go forward with untrashing.
	 * @param WP_Post   $post            Post object.
	 * @param string    $previous_status The status of the post at the point where it was trashed.
	 */
	$check = apply_filters( 'pre_untrash_post', null, $post, $previous_status );
	if ( null !== $check ) {
		return $check;
	}

	/**
	 * Fires before a post is restored from the Trash.
	 *
	 * @since 2.9.0
	 * @since 5.6.0 Added the `$previous_status` parameter.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	do_action( 'untrash_post', $post_id, $previous_status );

	$new_status = ( 'attachment' === $post->post_type ) ? 'inherit' : 'draft';

	/**
	 * Filters the status that a post gets assigned when it is restored from the trash (untrashed).
	 *
	 * By default posts that are restored will be assigned a status of 'draft'. Return the value of `$previous_status`
	 * in order to assign the status that the post had before it was trashed. The `wp_untrash_post_set_previous_status()`
	 * function is available for this.
	 *
	 * Prior to WordPress 5.6.0, restored posts were always assigned their original status.
	 *
	 * @since 5.6.0
	 *
	 * @param string $new_status      The new status of the post being restored.
	 * @param int    $post_id         The ID of the post being restored.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	$post_status = apply_filters( 'wp_untrash_post_status', $new_status, $post_id, $previous_status );

	delete_post_meta( $post_id, '_wp_trash_meta_status' );
	delete_post_meta( $post_id, '_wp_trash_meta_time' );

	$post_updated = wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => $post_status,
		)
	);

	if ( ! $post_updated ) {
		return false;
	}

	wp_untrash_post_comments( $post_id );

	/**
	 * Fires after a post is restored from the Trash.
	 *
	 * @since 2.9.0
	 * @since 5.6.0 Added the `$previous_status` parameter.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	do_action( 'untrashed_post', $post_id, $previous_status );

	return $post;
}

/**
 * Moves comments for a post to the Trash.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return mixed|void False on failure.
 */
function wp_trash_post_comments( $post = null ) {
	global $wpdb;

	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$post_id = $post->ID;

	/**
	 * Fires before comments are sent to the Trash.
	 *
	 * @since 2.9.0
	 *
	 * @param int $post_id Post ID.
	 */
	do_action( 'trash_post_comments', $post_id );

	$comments = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID, comment_approved FROM $wpdb->comments WHERE comment_post_ID = %d", $post_id ) );

	if ( ! $comments ) {
		return;
	}

	// Cache current status for each comment.
	$statuses = array();
	foreach ( $comments as $comment ) {
		$statuses[ $comment->comment_ID ] = $comment->comment_approved;
	}
	add_post_meta( $post_id, '_wp_trash_meta_comments_status', $statuses );

	// Set status for all comments to post-trashed.
	$result = $wpdb->update( $wpdb->comments, array( 'comment_approved' => 'post-trashed' ), array( 'comment_post_ID' => $post_id ) );

	clean_comment_cache( array_keys( $statuses ) );

	/**
	 * Fires after comments are sent to the Trash.
	 *
	 * @since 2.9.0
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $statuses Array of comment statuses.
	 */
	do_action( 'trashed_post_comments', $post_id, $statuses );

	return $result;
}

/**
 * Restores comments for a post from the Trash.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return true|void
 */
function wp_untrash_post_comments( $post = null ) {
	global $wpdb;

	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$post_id = $post->ID;

	$statuses = get_post_meta( $post_id, '_wp_trash_meta_comments_status', true );

	if ( ! $statuses ) {
		return true;
	}

	/**
	 * Fires before comments are restored for a post from the Trash.
	 *
	 * @since 2.9.0
	 *
	 * @param int $post_id Post ID.
	 */
	do_action( 'untrash_post_comments', $post_id );

	// Restore each comment to its original status.
	$group_by_status = array();
	foreach ( $statuses as $comment_id => $comment_status ) {
		$group_by_status[ $comment_status ][] = $comment_id;
	}

	foreach ( $group_by_status as $status => $comments ) {
		// Confidence check. This shouldn't happen.
		if ( 'post-trashed' === $status ) {
			$status = '0';
		}
		$comments_in = implode( ', ', array_map( 'intval', $comments ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->comments SET comment_approved = %s WHERE comment_ID IN ($comments_in)", $status ) );
	}

	clean_comment_cache( array_keys( $statuses ) );

	delete_post_meta( $post_id, '_wp_trash_meta_comments_status' );

	/**
	 * Fires after comments are restored for a post from the Trash.
	 *
	 * @since 2.9.0
	 *
	 * @param int $post_id Post ID.
	 */
	do_action( 'untrashed_post_comments', $post_id );
}

/**
 * Retrieves the list of categories for a post.
 *
 * Compatibility layer for themes and plugins. Also an easy layer of abstraction
 * away from the complexity of the taxonomy layer.
 *
 * @since 2.1.0
 *
 * @see wp_get_object_terms()
 *
 * @param int   $post_id Optional. The Post ID. Does not default to the ID of the
 *                       global $post. Default 0.
 * @param array $args    Optional. Category query parameters. Default empty array.
 *                       See WP_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error List of categories. If the `$fields` argument passed via `$args` is 'all' or
 *                        'all_with_object_id', an array of WP_Term objects will be returned. If `$fields`
 *                        is 'ids', an array of category IDs. If `$fields` is 'names', an array of category names.
 *                        WP_Error object if 'category' taxonomy doesn't exist.
 */
function wp_get_post_categories( $post_id = 0, $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array( 'fields' => 'ids' );
	$args     = wp_parse_args( $args, $defaults );

	$cats = wp_get_object_terms( $post_id, 'category', $args );
	return $cats;
}

/**
 * Retrieves the tags for a post.
 *
 * There is only one default for this function, called 'fields' and by default
 * is set to 'all'. There are other defaults that can be overridden in
 * wp_get_object_terms().
 *
 * @since 2.3.0
 *
 * @param int   $post_id Optional. The Post ID. Does not default to the ID of the
 *                       global $post. Default 0.
 * @param array $args    Optional. Tag query parameters. Default empty array.
 *                       See WP_Term_Query::__construct() for supported arguments.
 * @return array|WP_Error Array of WP_Term objects on success or empty array if no tags were found.
 *                        WP_Error object if 'post_tag' taxonomy doesn't exist.
 */
function wp_get_post_tags( $post_id = 0, $args = array() ) {
	return wp_get_post_terms( $post_id, 'post_tag', $args );
}

/**
 * Retrieves the terms for a post.
 *
 * @since 2.8.0
 *
 * @param int             $post_id  Optional. The Post ID. Does not default to the ID of the
 *                                  global $post. Default 0.
 * @param string|string[] $taxonomy Optional. The taxonomy slug or array of slugs for which
 *                                  to retrieve terms. Default 'post_tag'.
 * @param array           $args     {
 *     Optional. Term query parameters. See WP_Term_Query::__construct() for supported arguments.
 *
 *     @type string $fields Term fields to retrieve. Default 'all'.
 * }
 * @return array|WP_Error Array of WP_Term objects on success or empty array if no terms were found.
 *                        WP_Error object if `$taxonomy` doesn't exist.
 */
function wp_get_post_terms( $post_id = 0, $taxonomy = 'post_tag', $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array( 'fields' => 'all' );
	$args     = wp_parse_args( $args, $defaults );

	$tags = wp_get_object_terms( $post_id, $taxonomy, $args );

	return $tags;
}

/**
 * Retrieves a number of recent posts.
 *
 * @since 1.0.0
 *
 * @see get_posts()
 *
 * @param array  $args   Optional. Arguments to retrieve posts. Default empty array.
 * @param string $output Optional. The required return type. One of OBJECT or ARRAY_A, which
 *                       correspond to a WP_Post object or an associative array, respectively.
 *                       Default ARRAY_A.
 * @return array|false Array of recent posts, where the type of each element is determined
 *                     by the `$output` parameter. Empty array on failure.
 */
function wp_get_recent_posts( $args = array(), $output = ARRAY_A ) {

	if ( is_numeric( $args ) ) {
		_deprecated_argument( __FUNCTION__, '3.1.0', __( 'Passing an integer number of posts is deprecated. Pass an array of arguments instead.' ) );
		$args = array( 'numberposts' => absint( $args ) );
	}

	// Set default arguments.
	$defaults = array(
		'numberposts'      => 10,
		'offset'           => 0,
		'category'         => 0,
		'orderby'          => 'post_date',
		'order'            => 'DESC',
		'include'          => '',
		'exclude'          => '',
		'meta_key'         => '',
		'meta_value'       => '',
		'post_type'        => 'post',
		'post_status'      => 'draft, publish, future, pending, private',
		'suppress_filters' => true,
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	$results = get_posts( $parsed_args );

	// Backward compatibility. Prior to 3.1 expected posts to be returned in array.
	if ( ARRAY_A === $output ) {
		foreach ( $results as $key => $result ) {
			$results[ $key ] = get_object_vars( $result );
		}
		return $results ? $results : array();
	}

	return $results ? $results : false;
}

/**
 * Inserts or update a post.
 *
 * If the $postarr parameter has 'ID' set to a value, then post will be updated.
 *
 * You can set the post date manually, by setting the values for 'post_date'
 * and 'post_date_gmt' keys. You can close the comments or open the comments by
 * setting the value for 'comment_status' key.
 *
 * @since 1.0.0
 * @since 2.6.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 * @since 4.2.0 Support was added for encoding emoji in the post title, content, and excerpt.
 * @since 4.4.0 A 'meta_input' array can now be passed to `$postarr` to add post meta data.
 * @since 5.6.0 Added the `$fire_after_hooks` parameter.
 *
 * @see sanitize_post()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $postarr {
 *     An array of elements that make up a post to update or insert.
 *
 *     @type int    $ID                    The post ID. If equal to something other than 0,
 *                                         the post with that ID will be updated. Default 0.
 *     @type int    $post_author           The ID of the user who added the post. Default is
 *                                         the current user ID.
 *     @type string $post_date             The date of the post. Default is the current time.
 *     @type string $post_date_gmt         The date of the post in the GMT timezone. Default is
 *                                         the value of `$post_date`.
 *     @type string $post_content          The post content. Default empty.
 *     @type string $post_content_filtered The filtered post content. Default empty.
 *     @type string $post_title            The post title. Default empty.
 *     @type string $post_excerpt          The post excerpt. Default empty.
 *     @type string $post_status           The post status. Default 'draft'.
 *     @type string $post_type             The post type. Default 'post'.
 *     @type string $comment_status        Whether the post can accept comments. Accepts 'open' or 'closed'.
 *                                         Default is the value of 'default_comment_status' option.
 *     @type string $ping_status           Whether the post can accept pings. Accepts 'open' or 'closed'.
 *                                         Default is the value of 'default_ping_status' option.
 *     @type string $post_password         The password to access the post. Default empty.
 *     @type string $post_name             The post name. Default is the sanitized post title
 *                                         when creating a new post.
 *     @type string $to_ping               Space or carriage return-separated list of URLs to ping.
 *                                         Default empty.
 *     @type string $pinged                Space or carriage return-separated list of URLs that have
 *                                         been pinged. Default empty.
 *     @type int    $post_parent           Set this for the post it belongs to, if any. Default 0.
 *     @type int    $menu_order            The order the post should be displayed in. Default 0.
 *     @type string $post_mime_type        The mime type of the post. Default empty.
 *     @type string $guid                  Global Unique ID for referencing the post. Default empty.
 *     @type int    $import_id             The post ID to be used when inserting a new post.
 *                                         If specified, must not match any existing post ID. Default 0.
 *     @type int[]  $post_category         Array of category IDs.
 *                                         Defaults to value of the 'default_category' option.
 *     @type array  $tags_input            Array of tag names, slugs, or IDs. Default empty.
 *     @type array  $tax_input             An array of taxonomy terms keyed by their taxonomy name.
 *                                         If the taxonomy is hierarchical, the term list needs to be
 *                                         either an array of term IDs or a comma-separated string of IDs.
 *                                         If the taxonomy is non-hierarchical, the term list can be an array
 *                                         that contains term names or slugs, or a comma-separated string
 *                                         of names or slugs. This is because, in hierarchical taxonomy,
 *                                         child terms can have the same names with different parent terms,
 *                                         so the only way to connect them is using ID. Default empty.
 *     @type array  $meta_input            Array of post meta values keyed by their post meta key. Default empty.
 *     @type string $page_template         Page template to use.
 * }
 * @param bool  $wp_error         Optional. Whether to return a WP_Error on failure. Default false.
 * @param bool  $fire_after_hooks Optional. Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 */
function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
	global $wpdb;

	// Capture original pre-sanitized array for passing into filters.
	$unsanitized_postarr = $postarr;

	$user_id = get_current_user_id();

	$defaults = array(
		'post_author'           => $user_id,
		'post_content'          => '',
		'post_content_filtered' => '',
		'post_title'            => '',
		'post_excerpt'          => '',
		'post_status'           => 'draft',
		'post_type'             => 'post',
		'comment_status'        => '',
		'ping_status'           => '',
		'post_password'         => '',
		'to_ping'               => '',
		'pinged'                => '',
		'post_parent'           => 0,
		'menu_order'            => 0,
		'guid'                  => '',
		'import_id'             => 0,
		'context'               => '',
		'post_date'             => '',
		'post_date_gmt'         => '',
	);

	$postarr = wp_parse_args( $postarr, $defaults );

	unset( $postarr['filter'] );

	$postarr = sanitize_post( $postarr, 'db' );

	// Are we updating or creating?
	$post_id = 0;
	$update  = false;
	$guid    = $postarr['guid'];

	if ( ! empty( $postarr['ID'] ) ) {
		$update = true;

		// Get the post ID and GUID.
		$post_id     = $postarr['ID'];
		$post_before = get_post( $post_id );

		if ( is_null( $post_before ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
			}
			return 0;
		}

		$guid            = get_post_field( 'guid', $post_id );
		$previous_status = get_post_field( 'post_status', $post_id );
	} else {
		$previous_status = 'new';
		$post_before     = null;
	}

	$post_type = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];

	$post_title   = $postarr['post_title'];
	$post_content = $postarr['post_content'];
	$post_excerpt = $postarr['post_excerpt'];

	if ( isset( $postarr['post_name'] ) ) {
		$post_name = $postarr['post_name'];
	} elseif ( $update ) {
		// For an update, don't modify the post_name if it wasn't supplied as an argument.
		$post_name = $post_before->post_name;
	}

	$maybe_empty = 'attachment' !== $post_type
		&& ! $post_content && ! $post_title && ! $post_excerpt
		&& post_type_supports( $post_type, 'editor' )
		&& post_type_supports( $post_type, 'title' )
		&& post_type_supports( $post_type, 'excerpt' );

	/**
	 * Filters whether the post should be considered "empty".
	 *
	 * The post is considered "empty" if both:
	 * 1. The post type supports the title, editor, and excerpt fields
	 * 2. The title, editor, and excerpt fields are all empty
	 *
	 * Returning a truthy value from the filter will effectively short-circuit
	 * the new post being inserted and return 0. If $wp_error is true, a WP_Error
	 * will be returned instead.
	 *
	 * @since 3.3.0
	 *
	 * @param bool  $maybe_empty Whether the post should be considered "empty".
	 * @param array $postarr     Array of post data.
	 */
	if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
		if ( $wp_error ) {
			return new WP_Error( 'empty_content', __( 'Content, title, and excerpt are empty.' ) );
		} else {
			return 0;
		}
	}

	$post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];

	if ( 'attachment' === $post_type && ! in_array( $post_status, array( 'inherit', 'private', 'trash', 'auto-draft' ), true ) ) {
		$post_status = 'inherit';
	}

	if ( ! empty( $postarr['post_category'] ) ) {
		// Filter out empty terms.
		$post_category = array_filter( $postarr['post_category'] );
	} elseif ( $update && ! isset( $postarr['post_category'] ) ) {
		$post_category = $post_before->post_category;
	}

	// Make sure we set a valid category.
	if ( empty( $post_category ) || 0 === count( $post_category ) || ! is_array( $post_category ) ) {
		// 'post' requires at least one category.
		if ( 'post' === $post_type && 'auto-draft' !== $post_status ) {
			$post_category = array( get_option( 'default_category' ) );
		} else {
			$post_category = array();
		}
	}

	/*
	 * Don't allow contributors to set the post slug for pending review posts.
	 *
	 * For new posts check the primitive capability, for updates check the meta capability.
	 */
	if ( 'pending' === $post_status ) {
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $update && $post_type_object && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
			$post_name = '';
		} elseif ( $update && ! current_user_can( 'publish_post', $post_id ) ) {
			$post_name = '';
		}
	}

	/*
	 * Create a valid post name. Drafts and pending posts are allowed to have
	 * an empty post name.
	 */
	if ( empty( $post_name ) ) {
		if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			$post_name = sanitize_title( $post_title );
		} else {
			$post_name = '';
		}
	} else {
		// On updates, we need to check to see if it's using the old, fixed sanitization context.
		$check_name = sanitize_title( $post_name, '', 'old-save' );

		if ( $update
			&& strtolower( urlencode( $post_name ) ) === $check_name
			&& get_post_field( 'post_name', $post_id ) === $check_name
		) {
			$post_name = $check_name;
		} else { // New post, or slug has changed.
			$post_name = sanitize_title( $post_name );
		}
	}

	/*
	 * Resolve the post date from any provided post date or post date GMT strings;
	 * if none are provided, the date will be set to now.
	 */
	$post_date = wp_resolve_post_date( $postarr['post_date'], $postarr['post_date_gmt'] );

	if ( ! $post_date ) {
		if ( $wp_error ) {
			return new WP_Error( 'invalid_date', __( 'Invalid date.' ) );
		} else {
			return 0;
		}
	}

	if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' === $postarr['post_date_gmt'] ) {
		if ( ! in_array( $post_status, get_post_stati( array( 'date_floating' => true ) ), true ) ) {
			$post_date_gmt = get_gmt_from_date( $post_date );
		} else {
			$post_date_gmt = '0000-00-00 00:00:00';
		}
	} else {
		$post_date_gmt = $postarr['post_date_gmt'];
	}

	if ( $update || '0000-00-00 00:00:00' === $post_date ) {
		$post_modified     = current_time( 'mysql' );
		$post_modified_gmt = current_time( 'mysql', 1 );
	} else {
		$post_modified     = $post_date;
		$post_modified_gmt = $post_date_gmt;
	}

	if ( 'attachment' !== $post_type ) {
		$now = gmdate( 'Y-m-d H:i:s' );

		if ( 'publish' === $post_status ) {
			if ( strtotime( $post_date_gmt ) - strtotime( $now ) >= MINUTE_IN_SECONDS ) {
				$post_status = 'future';
			}
		} elseif ( 'future' === $post_status ) {
			if ( strtotime( $post_date_gmt ) - strtotime( $now ) < MINUTE_IN_SECONDS ) {
				$post_status = 'publish';
			}
		}
	}

	// Comment status.
	if ( empty( $postarr['comment_status'] ) ) {
		if ( $update ) {
			$comment_status = 'closed';
		} else {
			$comment_status = get_default_comment_status( $post_type );
		}
	} else {
		$comment_status = $postarr['comment_status'];
	}

	// These variables are needed by compact() later.
	$post_content_filtered = $postarr['post_content_filtered'];
	$post_author           = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
	$ping_status           = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
	$to_ping               = isset( $postarr['to_ping'] ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
	$pinged                = isset( $postarr['pinged'] ) ? $postarr['pinged'] : '';
	$import_id             = isset( $postarr['import_id'] ) ? $postarr['import_id'] : 0;

	/*
	 * The 'wp_insert_post_parent' filter expects all variables to be present.
	 * Previously, these variables would have already been extracted
	 */
	if ( isset( $postarr['menu_order'] ) ) {
		$menu_order = (int) $postarr['menu_order'];
	} else {
		$menu_order = 0;
	}

	$post_password = isset( $postarr['post_password'] ) ? $postarr['post_password'] : '';
	if ( 'private' === $post_status ) {
		$post_password = '';
	}

	if ( isset( $postarr['post_parent'] ) ) {
		$post_parent = (int) $postarr['post_parent'];
	} else {
		$post_parent = 0;
	}

	$new_postarr = array_merge(
		array(
			'ID' => $post_id,
		),
		compact( array_diff( array_keys( $defaults ), array( 'context', 'filter' ) ) )
	);

	/**
	 * Filters the post parent -- used to check for and prevent hierarchy loops.
	 *
	 * @since 3.1.0
	 *
	 * @param int   $post_parent Post parent ID.
	 * @param int   $post_id     Post ID.
	 * @param array $new_postarr Array of parsed post data.
	 * @param array $postarr     Array of sanitized, but otherwise unmodified post data.
	 */
	$post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_id, $new_postarr, $postarr );

	/*
	 * If the post is being untrashed and it has a desired slug stored in post meta,
	 * reassign it.
	 */
	if ( 'trash' === $previous_status && 'trash' !== $post_status ) {
		$desired_post_slug = get_post_meta( $post_id, '_wp_desired_post_slug', true );

		if ( $desired_post_slug ) {
			delete_post_meta( $post_id, '_wp_desired_post_slug' );
			$post_name = $desired_post_slug;
		}
	}

	// If a trashed post has the desired slug, change it and let this post have it.
	if ( 'trash' !== $post_status && $post_name ) {
		/**
		 * Filters whether or not to add a `__trashed` suffix to trashed posts that match the name of the updated post.
		 *
		 * @since 5.4.0
		 *
		 * @param bool   $add_trashed_suffix Whether to attempt to add the suffix.
		 * @param string $post_name          The name of the post being updated.
		 * @param int    $post_id            Post ID.
		 */
		$add_trashed_suffix = apply_filters( 'add_trashed_suffix_to_trashed_posts', true, $post_name, $post_id );

		if ( $add_trashed_suffix ) {
			wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_id );
		}
	}

	// When trashing an existing post, change its slug to allow non-trashed posts to use it.
	if ( 'trash' === $post_status && 'trash' !== $previous_status && 'new' !== $previous_status ) {
		$post_name = wp_add_trashed_suffix_to_post_name_for_post( $post_id );
	}

	$post_name = wp_unique_post_slug( $post_name, $post_id, $post_status, $post_type, $post_parent );

	// Don't unslash.
	$post_mime_type = isset( $postarr['post_mime_type'] ) ? $postarr['post_mime_type'] : '';

	// Expected_slashed (everything!).
	$data = compact(
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_content_filtered',
		'post_title',
		'post_excerpt',
		'post_status',
		'post_type',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_parent',
		'menu_order',
		'post_mime_type',
		'guid'
	);

	$emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );

	foreach ( $emoji_fields as $emoji_field ) {
		if ( isset( $data[ $emoji_field ] ) ) {
			$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );

			if ( 'utf8' === $charset ) {
				$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
			}
		}
	}

	if ( 'attachment' === $post_type ) {
		/**
		 * Filters attachment post data before it is updated in or added to the database.
		 *
		 * @since 3.9.0
		 * @since 5.4.1 The `$unsanitized_postarr` parameter was added.
		 * @since 6.0.0 The `$update` parameter was added.
		 *
		 * @param array $data                An array of slashed, sanitized, and processed attachment post data.
		 * @param array $postarr             An array of slashed and sanitized attachment post data, but not processed.
		 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed attachment post data
		 *                                   as originally passed to wp_insert_post().
		 * @param bool  $update              Whether this is an existing attachment post being updated.
		 */
		$data = apply_filters( 'wp_insert_attachment_data', $data, $postarr, $unsanitized_postarr, $update );
	} else {
		/**
		 * Filters slashed post data just before it is inserted into the database.
		 *
		 * @since 2.7.0
		 * @since 5.4.1 The `$unsanitized_postarr` parameter was added.
		 * @since 6.0.0 The `$update` parameter was added.
		 *
		 * @param array $data                An array of slashed, sanitized, and processed post data.
		 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
		 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as
		 *                                   originally passed to wp_insert_post().
		 * @param bool  $update              Whether this is an existing post being updated.
		 */
		$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $unsanitized_postarr, $update );
	}

	$data  = wp_unslash( $data );
	$where = array( 'ID' => $post_id );

	if ( $update ) {
		/**
		 * Fires immediately before an existing post is updated in the database.
		 *
		 * @since 2.5.0
		 *
		 * @param int   $post_id Post ID.
		 * @param array $data    Array of unslashed post data.
		 */
		do_action( 'pre_post_update', $post_id, $data );

		if ( false === $wpdb->update( $wpdb->posts, $data, $where ) ) {
			if ( $wp_error ) {
				if ( 'attachment' === $post_type ) {
					$message = __( 'Could not update attachment in the database.' );
				} else {
					$message = __( 'Could not update post in the database.' );
				}

				return new WP_Error( 'db_update_error', $message, $wpdb->last_error );
			} else {
				return 0;
			}
		}
	} else {
		// If there is a suggested ID, use it if not already present.
		if ( ! empty( $import_id ) ) {
			$import_id = (int) $import_id;

			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d", $import_id ) ) ) {
				$data['ID'] = $import_id;
			}
		}

		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if ( $wp_error ) {
				if ( 'attachment' === $post_type ) {
					$message = __( 'Could not insert attachment into the database.' );
				} else {
					$message = __( 'Could not insert post into the database.' );
				}

				return new WP_Error( 'db_insert_error', $message, $wpdb->last_error );
			} else {
				return 0;
			}
		}

		$post_id = (int) $wpdb->insert_id;

		// Use the newly generated $post_id.
		$where = array( 'ID' => $post_id );
	}

	if ( empty( $data['post_name'] ) && ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) {
		$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_id ), $post_id, $data['post_status'], $post_type, $post_parent );

		$wpdb->update( $wpdb->posts, array( 'post_name' => $data['post_name'] ), $where );
		clean_post_cache( $post_id );
	}

	if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
		wp_set_post_categories( $post_id, $post_category );
	}

	if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
		wp_set_post_tags( $post_id, $postarr['tags_input'] );
	}

	// Add default term for all associated custom taxonomies.
	if ( 'auto-draft' !== $post_status ) {
		foreach ( get_object_taxonomies( $post_type, 'object' ) as $taxonomy => $tax_object ) {

			if ( ! empty( $tax_object->default_term ) ) {

				// Filter out empty terms.
				if ( isset( $postarr['tax_input'][ $taxonomy ] ) && is_array( $postarr['tax_input'][ $taxonomy ] ) ) {
					$postarr['tax_input'][ $taxonomy ] = array_filter( $postarr['tax_input'][ $taxonomy ] );
				}

				// Passed custom taxonomy list overwrites the existing list if not empty.
				$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
				if ( ! empty( $terms ) && empty( $postarr['tax_input'][ $taxonomy ] ) ) {
					$postarr['tax_input'][ $taxonomy ] = $terms;
				}

				if ( empty( $postarr['tax_input'][ $taxonomy ] ) ) {
					$default_term_id = get_option( 'default_term_' . $taxonomy );
					if ( ! empty( $default_term_id ) ) {
						$postarr['tax_input'][ $taxonomy ] = array( (int) $default_term_id );
					}
				}
			}
		}
	}

	// New-style support for all custom taxonomies.
	if ( ! empty( $postarr['tax_input'] ) ) {
		foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );

			if ( ! $taxonomy_obj ) {
				/* translators: %s: Taxonomy name. */
				_doing_it_wrong( __FUNCTION__, sprintf( __( 'Invalid taxonomy: %s.' ), $taxonomy ), '4.4.0' );
				continue;
			}

			// array = hierarchical, string = non-hierarchical.
			if ( is_array( $tags ) ) {
				$tags = array_filter( $tags );
			}

			if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
				wp_set_post_terms( $post_id, $tags, $taxonomy );
			}
		}
	}

	if ( ! empty( $postarr['meta_input'] ) ) {
		foreach ( $postarr['meta_input'] as $field => $value ) {
			update_post_meta( $post_id, $field, $value );
		}
	}

	$current_guid = get_post_field( 'guid', $post_id );

	// Set GUID.
	if ( ! $update && '' === $current_guid ) {
		$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_id ) ), $where );
	}

	if ( 'attachment' === $postarr['post_type'] ) {
		if ( ! empty( $postarr['file'] ) ) {
			update_attached_file( $post_id, $postarr['file'] );
		}

		if ( ! empty( $postarr['context'] ) ) {
			add_post_meta( $post_id, '_wp_attachment_context', $postarr['context'], true );
		}
	}

	// Set or remove featured image.
	if ( isset( $postarr['_thumbnail_id'] ) ) {
		$thumbnail_support = current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' ) || 'revision' === $post_type;

		if ( ! $thumbnail_support && 'attachment' === $post_type && $post_mime_type ) {
			if ( wp_attachment_is( 'audio', $post_id ) ) {
				$thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
			} elseif ( wp_attachment_is( 'video', $post_id ) ) {
				$thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
			}
		}

		if ( $thumbnail_support ) {
			$thumbnail_id = (int) $postarr['_thumbnail_id'];
			if ( -1 === $thumbnail_id ) {
				delete_post_thumbnail( $post_id );
			} else {
				set_post_thumbnail( $post_id, $thumbnail_id );
			}
		}
	}

	clean_post_cache( $post_id );

	$post = get_post( $post_id );

	if ( ! empty( $postarr['page_template'] ) ) {
		$post->page_template = $postarr['page_template'];
		$page_templates      = wp_get_theme()->get_page_templates( $post );

		if ( 'default' !== $postarr['page_template'] && ! isset( $page_templates[ $postarr['page_template'] ] ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'invalid_page_template', __( 'Invalid page template.' ) );
			}

			update_post_meta( $post_id, '_wp_page_template', 'default' );
		} else {
			update_post_meta( $post_id, '_wp_page_template', $postarr['page_template'] );
		}
	}

	if ( 'attachment' !== $postarr['post_type'] ) {
		wp_transition_post_status( $data['post_status'], $previous_status, $post );
	} else {
		if ( $update ) {
			/**
			 * Fires once an existing attachment has been updated.
			 *
			 * @since 2.0.0
			 *
			 * @param int $post_id Attachment ID.
			 */
			do_action( 'edit_attachment', $post_id );

			$post_after = get_post( $post_id );

			/**
			 * Fires once an existing attachment has been updated.
			 *
			 * @since 4.4.0
			 *
			 * @param int     $post_id      Post ID.
			 * @param WP_Post $post_after   Post object following the update.
			 * @param WP_Post $post_before  Post object before the update.
			 */
			do_action( 'attachment_updated', $post_id, $post_after, $post_before );
		} else {

			/**
			 * Fires once an attachment has been added.
			 *
			 * @since 2.0.0
			 *
			 * @param int $post_id Attachment ID.
			 */
			do_action( 'add_attachment', $post_id );
		}

		return $post_id;
	}

	if ( $update ) {
		/**
		 * Fires once an existing post has been updated.
		 *
		 * The dynamic portion of the hook name, `$post->post_type`, refers to
		 * the post type slug.
		 *
		 * Possible hook names include:
		 *
		 *  - `edit_post_post`
		 *  - `edit_post_page`
		 *
		 * @since 5.1.0
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		do_action( "edit_post_{$post->post_type}", $post_id, $post );

		/**
		 * Fires once an existing post has been updated.
		 *
		 * @since 1.2.0
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		do_action( 'edit_post', $post_id, $post );

		$post_after = get_post( $post_id );

		/**
		 * Fires once an existing post has been updated.
		 *
		 * @since 3.0.0
		 *
		 * @param int     $post_id      Post ID.
		 * @param WP_Post $post_after   Post object following the update.
		 * @param WP_Post $post_before  Post object before the update.
		 */
		do_action( 'post_updated', $post_id, $post_after, $post_before );
	}

	/**
	 * Fires once a post has been saved.
	 *
	 * The dynamic portion of the hook name, `$post->post_type`, refers to
	 * the post type slug.
	 *
	 * Possible hook names include:
	 *
	 *  - `save_post_post`
	 *  - `save_post_page`
	 *
	 * @since 3.7.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	do_action( "save_post_{$post->post_type}", $post_id, $post, $update );

	/**
	 * Fires once a post has been saved.
	 *
	 * @since 1.5.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	do_action( 'save_post', $post_id, $post, $update );

	/**
	 * Fires once a post has been saved.
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	do_action( 'wp_insert_post', $post_id, $post, $update );

	if ( $fire_after_hooks ) {
		wp_after_insert_post( $post, $update, $post_before );
	}

	return $post_id;
}

/**
 * Updates a post with new post data.
 *
 * The date does not have to be set for drafts. You can set the date and it will
 * not be overridden.
 *
 * @since 1.0.0
 * @since 3.5.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 * @since 5.6.0 Added the `$fire_after_hooks` parameter.
 *
 * @param array|object $postarr          Optional. Post data. Arrays are expected to be escaped,
 *                                       objects are not. See wp_insert_post() for accepted arguments.
 *                                       Default array.
 * @param bool         $wp_error         Optional. Whether to return a WP_Error on failure. Default false.
 * @param bool         $fire_after_hooks Optional. Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 */
function wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
	if ( is_object( $postarr ) ) {
		// Non-escaped post was passed.
		$postarr = get_object_vars( $postarr );
		$postarr = wp_slash( $postarr );
	}

	// First, get all of the original fields.
	$post = get_post( $postarr['ID'], ARRAY_A );

	if ( is_null( $post ) ) {
		if ( $wp_error ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
		}
		return 0;
	}

	// Escape data pulled from DB.
	$post = wp_slash( $post );

	// Passed post category list overwrites existing category list if not empty.
	if ( isset( $postarr['post_category'] ) && is_array( $postarr['post_category'] )
		&& count( $postarr['post_category'] ) > 0
	) {
		$post_cats = $postarr['post_category'];
	} else {
		$post_cats = $post['post_category'];
	}

	// Drafts shouldn't be assigned a date unless explicitly done so by the user.
	if ( isset( $post['post_status'] )
		&& in_array( $post['post_status'], array( 'draft', 'pending', 'auto-draft' ), true )
		&& empty( $postarr['edit_date'] ) && ( '0000-00-00 00:00:00' === $post['post_date_gmt'] )
	) {
		$clear_date = true;
	} else {
		$clear_date = false;
	}

	// Merge old and new fields with new fields overwriting old ones.
	$postarr                  = array_merge( $post, $postarr );
	$postarr['post_category'] = $post_cats;
	if ( $clear_date ) {
		$postarr['post_date']     = current_time( 'mysql' );
		$postarr['post_date_gmt'] = '';
	}

	if ( 'attachment' === $postarr['post_type'] ) {
		return wp_insert_attachment( $postarr, false, 0, $wp_error );
	}

	// Discard 'tags_input' parameter if it's the same as existing post tags.
	if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $postarr['post_type'], 'post_tag' ) ) {
		$tags      = get_the_terms( $postarr['ID'], 'post_tag' );
		$tag_names = array();

		if ( $tags && ! is_wp_error( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
		}

		if ( $postarr['tags_input'] === $tag_names ) {
			unset( $postarr['tags_input'] );
		}
	}

	return wp_insert_post( $postarr, $wp_error, $fire_after_hooks );
}

/**
 * Publishes a post by transitioning the post status.
 *
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post $post Post ID or post object.
 */
function wp_publish_post( $post ) {
	global $wpdb;

	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	if ( 'publish' === $post->post_status ) {
		return;
	}

	$post_before = get_post( $post->ID );

	// Ensure at least one term is applied for taxonomies with a default term.
	foreach ( get_object_taxonomies( $post->post_type, 'object' ) as $taxonomy => $tax_object ) {
		// Skip taxonomy if no default term is set.
		if (
			'category' !== $taxonomy &&
			empty( $tax_object->default_term )
		) {
			continue;
		}

		// Do not modify previously set terms.
		if ( ! empty( get_the_terms( $post, $taxonomy ) ) ) {
			continue;
		}

		if ( 'category' === $taxonomy ) {
			$default_term_id = (int) get_option( 'default_category', 0 );
		} else {
			$default_term_id = (int) get_option( 'default_term_' . $taxonomy, 0 );
		}

		if ( ! $default_term_id ) {
			continue;
		}
		wp_set_post_terms( $post->ID, array( $default_term_id ), $taxonomy );
	}

	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post->ID ) );

	clean_post_cache( $post->ID );

	$old_status        = $post->post_status;
	$post->post_status = 'publish';
	wp_transition_post_status( 'publish', $old_status, $post );

	/** This action is documented in wp-includes/post.php */
	do_action( "edit_post_{$post->post_type}", $post->ID, $post );

	/** This action is documented in wp-includes/post.php */
	do_action( 'edit_post', $post->ID, $post );

	/** This action is documented in wp-includes/post.php */
	do_action( "save_post_{$post->post_type}", $post->ID, $post, true );

	/** This action is documented in wp-includes/post.php */
	do_action( 'save_post', $post->ID, $post, true );

	/** This action is documented in wp-includes/post.php */
	do_action( 'wp_insert_post', $post->ID, $post, true );

	wp_after_insert_post( $post, true, $post_before );
}

/**
 * Publishes future post and make sure post ID has future post status.
 *
 * Invoked by cron 'publish_future_post' event. This safeguard prevents cron
 * from publishing drafts, etc.
 *
 * @since 2.5.0
 *
 * @param int|WP_Post $post Post ID or post object.
 */
function check_and_publish_future_post( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	if ( 'future' !== $post->post_status ) {
		return;
	}

	$time = strtotime( $post->post_date_gmt . ' GMT' );

	// Uh oh, someone jumped the gun!
	if ( $time > time() ) {
		wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) ); // Clear anything else in the system.
		wp_schedule_single_event( $time, 'publish_future_post', array( $post->ID ) );
		return;
	}

	// wp_publish_post() returns no meaningful value.
	wp_publish_post( $post->ID );
}

/**
 * Uses wp_checkdate to return a valid Gregorian-calendar value for post_date.
 * If post_date is not provided, this first checks post_date_gmt if provided,
 * then falls back to use the current time.
 *
 * For back-compat purposes in wp_insert_post, an empty post_date and an invalid
 * post_date_gmt will continue to return '1970-01-01 00:00:00' rather than false.
 *
 * @since 5.7.0
 *
 * @param string $post_date     The date in mysql format (`Y-m-d H:i:s`).
 * @param string $post_date_gmt The GMT date in mysql format (`Y-m-d H:i:s`).
 * @return string|false A valid Gregorian-calendar date string, or false on failure.
 */
function wp_resolve_post_date( $post_date = '', $post_date_gmt = '' ) {
	// If the date is empty, set the date to now.
	if ( empty( $post_date ) || '0000-00-00 00:00:00' === $post_date ) {
		if ( empty( $post_date_gmt ) || '0000-00-00 00:00:00' === $post_date_gmt ) {
			$post_date = current_time( 'mysql' );
		} else {
			$post_date = get_date_from_gmt( $post_date_gmt );
		}
	}

	// Validate the date.
	$month = (int) substr( $post_date, 5, 2 );
	$day   = (int) substr( $post_date, 8, 2 );
	$year  = (int) substr( $post_date, 0, 4 );

	$valid_date = wp_checkdate( $month, $day, $year, $post_date );

	if ( ! $valid_date ) {
		return false;
	}
	return $post_date;
}

/**
 * Computes a unique slug for the post, when given the desired slug and some post details.
 *
 * @since 2.8.0
 *
 * @global wpdb       $wpdb       WordPress database abstraction object.
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param string $slug        The desired slug (post_name).
 * @param int    $post_id     Post ID.
 * @param string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param string $post_type   Post type.
 * @param int    $post_parent Post parent ID.
 * @return string Unique slug for the post, based on $post_name (with a -1, -2, etc. suffix)
 */
function wp_unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent ) {
	if ( in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true )
		|| ( 'inherit' === $post_status && 'revision' === $post_type ) || 'user_request' === $post_type
	) {
		return $slug;
	}

	/**
	 * Filters the post slug before it is generated to be unique.
	 *
	 * Returning a non-null value will short-circuit the
	 * unique slug generation, returning the passed value instead.
	 *
	 * @since 5.1.0
	 *
	 * @param string|null $override_slug Short-circuit return value.
	 * @param string      $slug          The desired slug (post_name).
	 * @param int         $post_id       Post ID.
	 * @param string      $post_status   The post status.
	 * @param string      $post_type     Post type.
	 * @param int         $post_parent   Post parent ID.
	 */
	$override_slug = apply_filters( 'pre_wp_unique_post_slug', null, $slug, $post_id, $post_status, $post_type, $post_parent );
	if ( null !== $override_slug ) {
		return $override_slug;
	}

	global $wpdb, $wp_rewrite;

	$original_slug = $slug;

	$feeds = $wp_rewrite->feeds;
	if ( ! is_array( $feeds ) ) {
		$feeds = array();
	}

	if ( 'attachment' === $post_type ) {
		// Attachment slugs must be unique across all types.
		$check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND ID != %d LIMIT 1";
		$slug            = ( 0 === absint( get_option( 'wp_attachment_pages_enabled' ) ) ) ? wp_generate_uuid4() : $slug;
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_id ) );

		/**
		 * Filters whether the post slug would make a bad attachment slug.
		 *
		 * @since 3.1.0
		 *
		 * @param bool   $bad_slug Whether the slug would be bad as an attachment slug.
		 * @param string $slug     The post slug.
		 */
		$is_bad_attachment_slug = apply_filters( 'wp_unique_post_slug_is_bad_attachment_slug', false, $slug );

		if ( $post_name_check
			|| in_array( $slug, $feeds, true ) || 'embed' === $slug
			|| $is_bad_attachment_slug
		) {
			$suffix = 2;
			do {
				$alt_post_name   = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_id ) );
				++$suffix;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} elseif ( is_post_type_hierarchical( $post_type ) ) {
		if ( 'nav_menu_item' === $post_type ) {
			return $slug;
		}

		/*
		 * Page slugs must be unique within their own trees. Pages are in a separate
		 * namespace than posts so page slugs are allowed to overlap post slugs.
		 */
		$check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_id, $post_parent ) );

		/**
		 * Filters whether the post slug would make a bad hierarchical post slug.
		 *
		 * @since 3.1.0
		 *
		 * @param bool   $bad_slug    Whether the post slug would be bad in a hierarchical post context.
		 * @param string $slug        The post slug.
		 * @param string $post_type   Post type.
		 * @param int    $post_parent Post parent ID.
		 */
		$is_bad_hierarchical_slug = apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent );

		if ( $post_name_check
			|| in_array( $slug, $feeds, true ) || 'embed' === $slug
			|| preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug )
			|| $is_bad_hierarchical_slug
		) {
			$suffix = 2;
			do {
				$alt_post_name   = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_id, $post_parent ) );
				++$suffix;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} else {
		// Post slugs must be unique across all posts.
		$check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_id ) );

		$post = get_post( $post_id );

		// Prevent new post slugs that could result in URLs that conflict with date archives.
		$conflicts_with_date_archive = false;
		if ( 'post' === $post_type && ( ! $post || $post->post_name !== $slug ) && preg_match( '/^[0-9]+$/', $slug ) ) {
			$slug_num = (int) $slug;

			if ( $slug_num ) {
				$permastructs   = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) );
				$postname_index = array_search( '%postname%', $permastructs, true );

				/*
				* Potential date clashes are as follows:
				*
				* - Any integer in the first permastruct position could be a year.
				* - An integer between 1 and 12 that follows 'year' conflicts with 'monthnum'.
				* - An integer between 1 and 31 that follows 'monthnum' conflicts with 'day'.
				*/
				if ( 0 === $postname_index ||
					( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && 13 > $slug_num ) ||
					( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && 32 > $slug_num )
				) {
					$conflicts_with_date_archive = true;
				}
			}
		}

		/**
		 * Filters whether the post slug would be bad as a flat slug.
		 *
		 * @since 3.1.0
		 *
		 * @param bool   $bad_slug  Whether the post slug would be bad as a flat slug.
		 * @param string $slug      The post slug.
		 * @param string $post_type Post type.
		 */
		$is_bad_flat_slug = apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type );

		if ( $post_name_check
			|| in_array( $slug, $feeds, true ) || 'embed' === $slug
			|| $conflicts_with_date_archive
			|| $is_bad_flat_slug
		) {
			$suffix = 2;
			do {
				$alt_post_name   = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_id ) );
				++$suffix;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	}

	/**
	 * Filters the unique post slug.
	 *
	 * @since 3.3.0
	 *
	 * @param string $slug          The post slug.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   The post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Post parent ID
	 * @param string $original_slug The original post slug.
	 */
	return apply_filters( 'wp_unique_post_slug', $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug );
}

/**
 * Truncates a post slug.
 *
 * @since 3.6.0
 * @access private
 *
 * @see utf8_uri_encode()
 *
 * @param string $slug   The slug to truncate.
 * @param int    $length Optional. Max length of the slug. Default 200 (characters).
 * @return string The truncated slug.
 */
function _truncate_post_slug( $slug, $length = 200 ) {
	if ( strlen( $slug ) > $length ) {
		$decoded_slug = urldecode( $slug );
		if ( $decoded_slug === $slug ) {
			$slug = substr( $slug, 0, $length );
		} else {
			$slug = utf8_uri_encode( $decoded_slug, $length, true );
		}
	}

	return rtrim( $slug, '-' );
}

/**
 * Adds tags to a post.
 *
 * @see wp_set_post_tags()
 *
 * @since 2.3.0
 *
 * @param int          $post_id Optional. The Post ID. Does not default to the ID of the global $post.
 * @param string|array $tags    Optional. An array of tags to set for the post, or a string of tags
 *                              separated by commas. Default empty.
 * @return array|false|WP_Error Array of affected term IDs. WP_Error or false on failure.
 */
function wp_add_post_tags( $post_id = 0, $tags = '' ) {
	return wp_set_post_tags( $post_id, $tags, true );
}

/**
 * Sets the tags for a post.
 *
 * @since 2.3.0
 *
 * @see wp_set_object_terms()
 *
 * @param int          $post_id Optional. The Post ID. Does not default to the ID of the global $post.
 * @param string|array $tags    Optional. An array of tags to set for the post, or a string of tags
 *                              separated by commas. Default empty.
 * @param bool         $append  Optional. If true, don't delete existing tags, just add on. If false,
 *                              replace the tags with the new tags. Default false.
 * @return array|false|WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
 */
function wp_set_post_tags( $post_id = 0, $tags = '', $append = false ) {
	return wp_set_post_terms( $post_id, $tags, 'post_tag', $append );
}

/**
 * Sets the terms for a post.
 *
 * @since 2.8.0
 *
 * @see wp_set_object_terms()
 *
 * @param int          $post_id  Optional. The Post ID. Does not default to the ID of the global $post.
 * @param string|array $terms    Optional. An array of terms to set for the post, or a string of terms
 *                               separated by commas. Hierarchical taxonomies must always pass IDs rather
 *                               than names so that children with the same names but different parents
 *                               aren't confused. Default empty.
 * @param string       $taxonomy Optional. Taxonomy name. Default 'post_tag'.
 * @param bool         $append   Optional. If true, don't delete existing terms, just add on. If false,
 *                               replace the terms with the new terms. Default false.
 * @return array|false|WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
 */
function wp_set_post_terms( $post_id = 0, $terms = '', $taxonomy = 'post_tag', $append = false ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return false;
	}

	if ( empty( $terms ) ) {
		$terms = array();
	}

	if ( ! is_array( $terms ) ) {
		$comma = _x( ',', 'tag delimiter' );
		if ( ',' !== $comma ) {
			$terms = str_replace( $comma, ',', $terms );
		}
		$terms = explode( ',', trim( $terms, " \n\t\r\0\x0B," ) );
	}

	/*
	 * Hierarchical taxonomies must always pass IDs rather than names so that
	 * children with the same names but different parents aren't confused.
	 */
	if ( is_taxonomy_hierarchical( $taxonomy ) ) {
		$terms = array_unique( array_map( 'intval', $terms ) );
	}

	return wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
}

/**
 * Sets categories for a post.
 *
 * If no categories are provided, the default category is used.
 *
 * @since 2.1.0
 *
 * @param int       $post_id         Optional. The Post ID. Does not default to the ID
 *                                   of the global $post. Default 0.
 * @param int[]|int $post_categories Optional. List of category IDs, or the ID of a single category.
 *                                   Default empty array.
 * @param bool      $append          If true, don't delete existing categories, just add on.
 *                                   If false, replace the categories with the new categories.
 * @return array|false|WP_Error Array of term taxonomy IDs of affected categories. WP_Error or false on failure.
 */
function wp_set_post_categories( $post_id = 0, $post_categories = array(), $append = false ) {
	$post_id     = (int) $post_id;
	$post_type   = get_post_type( $post_id );
	$post_status = get_post_status( $post_id );

	// If $post_categories isn't already an array, make it one.
	$post_categories = (array) $post_categories;

	if ( empty( $post_categories ) ) {
		/**
		 * Filters post types (in addition to 'post') that require a default category.
		 *
		 * @since 5.5.0
		 *
		 * @param string[] $post_types An array of post type names. Default empty array.
		 */
		$default_category_post_types = apply_filters( 'default_category_post_types', array() );

		// Regular posts always require a default category.
		$default_category_post_types = array_merge( $default_category_post_types, array( 'post' ) );

		if ( in_array( $post_type, $default_category_post_types, true )
			&& is_object_in_taxonomy( $post_type, 'category' )
			&& 'auto-draft' !== $post_status
		) {
			$post_categories = array( get_option( 'default_category' ) );
			$append          = false;
		} else {
			$post_categories = array();
		}
	} elseif ( 1 === count( $post_categories ) && '' === reset( $post_categories ) ) {
		return true;
	}

	return wp_set_post_terms( $post_id, $post_categories, 'category', $append );
}

/**
 * Fires actions related to the transitioning of a post's status.
 *
 * When a post is saved, the post status is "transitioned" from one status to another,
 * though this does not always mean the status has actually changed before and after
 * the save. This function fires a number of action hooks related to that transition:
 * the generic {@see 'transition_post_status'} action, as well as the dynamic hooks
 * {@see '$old_status_to_$new_status'} and {@see '$new_status_$post->post_type'}. Note
 * that the function does not transition the post object in the database.
 *
 * For instance: When publishing a post for the first time, the post status may transition
 * from 'draft' – or some other status – to 'publish'. However, if a post is already
 * published and is simply being updated, the "old" and "new" statuses may both be 'publish'
 * before and after the transition.
 *
 * @since 2.3.0
 *
 * @param string  $new_status Transition to this post status.
 * @param string  $old_status Previous post status.
 * @param WP_Post $post Post data.
 */
function wp_transition_post_status( $new_status, $old_status, $post ) {
	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @since 2.3.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	do_action( 'transition_post_status', $new_status, $old_status, $post );

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * The dynamic portions of the hook name, `$new_status` and `$old_status`,
	 * refer to the old and new post statuses, respectively.
	 *
	 * Possible hook names include:
	 *
	 *  - `draft_to_publish`
	 *  - `publish_to_trash`
	 *  - `pending_to_draft`
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Post $post Post object.
	 */
	do_action( "{$old_status}_to_{$new_status}", $post );

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * The dynamic portions of the hook name, `$new_status` and `$post->post_type`,
	 * refer to the new post status and post type, respectively.
	 *
	 * Possible hook names include:
	 *
	 *  - `draft_post`
	 *  - `future_post`
	 *  - `pending_post`
	 *  - `private_post`
	 *  - `publish_post`
	 *  - `trash_post`
	 *  - `draft_page`
	 *  - `future_page`
	 *  - `pending_page`
	 *  - `private_page`
	 *  - `publish_page`
	 *  - `trash_page`
	 *  - `publish_attachment`
	 *  - `trash_attachment`
	 *
	 * Please note: When this action is hooked using a particular post status (like
	 * 'publish', as `publish_{$post->post_type}`), it will fire both when a post is
	 * first transitioned to that status from something else, as well as upon
	 * subsequent post updates (old and new status are both the same).
	 *
	 * Therefore, if you are looking to only fire a callback when a post is first
	 * transitioned to a status, use the {@see 'transition_post_status'} hook instead.
	 *
	 * @since 2.3.0
	 * @since 5.9.0 Added `$old_status` parameter.
	 *
	 * @param int     $post_id    Post ID.
	 * @param WP_Post $post       Post object.
	 * @param string  $old_status Old post status.
	 */
	do_action( "{$new_status}_{$post->post_type}", $post->ID, $post, $old_status );
}

/**
 * Fires actions after a post, its terms and meta data has been saved.
 *
 * @since 5.6.0
 *
 * @param int|WP_Post  $post        The post ID or object that has been saved.
 * @param bool         $update      Whether this is an existing post being updated.
 * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior
 *                                  to the update for updated posts.
 */
function wp_after_insert_post( $post, $update, $post_before ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	$post_id = $post->ID;

	/**
	 * Fires once a post, its terms and meta data has been saved.
	 *
	 * @since 5.6.0
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an existing post being updated.
	 * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior
	 *                                  to the update for updated posts.
	 */
	do_action( 'wp_after_insert_post', $post_id, $post, $update, $post_before );
}

//
// Comment, trackback, and pingback functions.
//

/**
 * Adds a URL to those already pinged.
 *
 * @since 1.5.0
 * @since 4.7.0 `$post` can be a WP_Post object.
 * @since 4.7.0 `$uri` can be an array of URIs.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|WP_Post  $post Post ID or post object.
 * @param string|array $uri  Ping URI or array of URIs.
 * @return int|false How many rows were updated.
 */
function add_ping( $post, $uri ) {
	global $wpdb;

	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$pung = trim( $post->pinged );
	$pung = preg_split( '/\s/', $pung );

	if ( is_array( $uri ) ) {
		$pung = array_merge( $pung, $uri );
	} else {
		$pung[] = $uri;
	}
	$new = implode( "\n", $pung );

	/**
	 * Filters the new ping URL to add for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string $new New ping URL to add.
	 */
	$new = apply_filters( 'add_ping', $new );

	$return = $wpdb->update( $wpdb->posts, array( 'pinged' => $new ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
	return $return;
}

/**
 * Retrieves enclosures already enclosed for a post.
 *
 * @since 1.5.0
 *
 * @param int $post_id Post ID.
 * @return string[] Array of enclosures for the given post.
 */
function get_enclosed( $post_id ) {
	$custom_fields = get_post_custom( $post_id );
	$pung          = array();
	if ( ! is_array( $custom_fields ) ) {
		return $pung;
	}

	foreach ( $custom_fields as $key => $val ) {
		if ( 'enclosure' !== $key || ! is_array( $val ) ) {
			continue;
		}
		foreach ( $val as $enc ) {
			$enclosure = explode( "\n", $enc );
			$pung[]    = trim( $enclosure[0] );
		}
	}

	/**
	 * Filters the list of enclosures already enclosed for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $pung    Array of enclosures for the given post.
	 * @param int      $post_id Post ID.
	 */
	return apply_filters( 'get_enclosed', $pung, $post_id );
}

/**
 * Retrieves URLs already pinged for a post.
 *
 * @since 1.5.0
 *
 * @since 4.7.0 `$post` can be a WP_Post object.
 *
 * @param int|WP_Post $post Post ID or object.
 * @return string[]|false Array of URLs already pinged for the given post, false if the post is not found.
 */
function get_pung( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$pung = trim( $post->pinged );
	$pung = preg_split( '/\s/', $pung );

	/**
	 * Filters the list of already-pinged URLs for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $pung Array of URLs already pinged for the given post.
	 */
	return apply_filters( 'get_pung', $pung );
}

/**
 * Retrieves URLs that need to be pinged.
 *
 * @since 1.5.0
 * @since 4.7.0 `$post` can be a WP_Post object.
 *
 * @param int|WP_Post $post Post ID or post object.
 * @return string[]|false List of URLs yet to ping.
 */
function get_to_ping( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$to_ping = sanitize_trackback_urls( $post->to_ping );
	$to_ping = preg_split( '/\s/', $to_ping, -1, PREG_SPLIT_NO_EMPTY );

	/**
	 * Filters the list of URLs yet to ping for the given post.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $to_ping List of URLs yet to ping.
	 */
	return apply_filters( 'get_to_ping', $to_ping );
}

/**
 * Does trackbacks for a list of URLs.
 *
 * @since 1.0.0
 *
 * @param string $tb_list Comma separated list of URLs.
 * @param int    $post_id Post ID.
 */
function trackback_url_list( $tb_list, $post_id ) {
	if ( ! empty( $tb_list ) ) {
		// Get post data.
		$postdata = get_post( $post_id, ARRAY_A );

		// Form an excerpt.
		$excerpt = strip_tags( $postdata['post_excerpt'] ? $postdata['post_excerpt'] : $postdata['post_content'] );

		if ( strlen( $excerpt ) > 255 ) {
			$excerpt = substr( $excerpt, 0, 252 ) . '&hellip;';
		}

		$trackback_urls = explode( ',', $tb_list );
		foreach ( (array) $trackback_urls as $tb_url ) {
			$tb_url = trim( $tb_url );
			trackback( $tb_url, wp_unslash( $postdata['post_title'] ), $excerpt, $post_id );
		}
	}
}

//
// Page functions.
//

/**
 * Gets a list of page IDs.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return string[] List of page IDs as strings.
 */
function get_all_page_ids() {
	global $wpdb;

	$page_ids = wp_cache_get( 'all_page_ids', 'posts' );
	if ( ! is_array( $page_ids ) ) {
		$page_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'page'" );
		wp_cache_add( 'all_page_ids', $page_ids, 'posts' );
	}

	return $page_ids;
}

/**
 * Retrieves page data given a page ID or page object.
 *
 * Use get_post() instead of get_page().
 *
 * @since 1.5.1
 * @deprecated 3.5.0 Use get_post()
 *
 * @param int|WP_Post $page   Page object or page ID. Passed by reference.
 * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                            correspond to a WP_Post object, an associative array, or a numeric array,
 *                            respectively. Default OBJECT.
 * @param string      $filter Optional. How the return value should be filtered. Accepts 'raw',
 *                            'edit', 'db', 'display'. Default 'raw'.
 * @return WP_Post|array|null WP_Post or array on success, null on failure.
 */
function get_page( $page, $output = OBJECT, $filter = 'raw' ) {
	return get_post( $page, $output, $filter );
}

/**
 * Retrieves a page given its path.
 *
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string       $page_path Page path.
 * @param string       $output    Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
 *                                correspond to a WP_Post object, an associative array, or a numeric array,
 *                                respectively. Default OBJECT.
 * @param string|array $post_type Optional. Post type or array of post types. Default 'page'.
 * @return WP_Post|array|null WP_Post (or array) on success, or null on failure.
 */
function get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
	global $wpdb;

	$last_changed = wp_cache_get_last_changed( 'posts' );

	$hash      = md5( $page_path . serialize( $post_type ) );
	$cache_key = "get_page_by_path:$hash:$last_changed";
	$cached    = wp_cache_get( $cache_key, 'post-queries' );
	if ( false !== $cached ) {
		// Special case: '0' is a bad `$page_path`.
		if ( '0' === $cached || 0 === $cached ) {
			return;
		} else {
			return get_post( $cached, $output );
		}
	}

	$page_path     = rawurlencode( urldecode( $page_path ) );
	$page_path     = str_replace( '%2F', '/', $page_path );
	$page_path     = str_replace( '%20', ' ', $page_path );
	$parts         = explode( '/', trim( $page_path, '/' ) );
	$parts         = array_map( 'sanitize_title_for_query', $parts );
	$escaped_parts = esc_sql( $parts );

	$in_string = "'" . implode( "','", $escaped_parts ) . "'";

	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type, 'attachment' );
	}

	$post_types          = esc_sql( $post_types );
	$post_type_in_string = "'" . implode( "','", $post_types ) . "'";
	$sql                 = "
		SELECT ID, post_name, post_parent, post_type
		FROM $wpdb->posts
		WHERE post_name IN ($in_string)
		AND post_type IN ($post_type_in_string)
	";

	$pages = $wpdb->get_results( $sql, OBJECT_K );

	$revparts = array_reverse( $parts );

	$foundid = 0;
	foreach ( (array) $pages as $page ) {
		if ( $page->post_name == $revparts[0] ) {
			$count = 0;
			$p     = $page;

			/*
			 * Loop through the given path parts from right to left,
			 * ensuring each matches the post ancestry.
			 */
			while ( 0 != $p->post_parent && isset( $pages[ $p->post_parent ] ) ) {
				++$count;
				$parent = $pages[ $p->post_parent ];
				if ( ! isset( $revparts[ $count ] ) || $parent->post_name != $revparts[ $count ] ) {
					break;
				}
				$p = $parent;
			}

			if ( 0 == $p->post_parent && count( $revparts ) === $count + 1 && $p->post_name == $revparts[ $count ] ) {
				$foundid = $page->ID;
				if ( $page->post_type == $post_type ) {
					break;
				}
			}
		}
	}

	// We cache misses as well as hits.
	wp_cache_set( $cache_key, $foundid, 'post-queries' );

	if ( $foundid ) {
		return get_post( $foundid, $output );
	}

	return null;
}

/**
 * Identifies descendants of a given page ID in a list of page objects.
 *
 * Descendants are identified from the `$pages` array passed to the function. No database queries are performed.
 *
 * @since 1.5.1
 *
 * @param int       $page_id Page ID.
 * @param WP_Post[] $pages   List of page objects from which descendants should be identified.
 * @return WP_Post[] List of page children.
 */
function get_page_children( $page_id, $pages ) {
	// Build a hash of ID -> children.
	$children = array();
	foreach ( (array) $pages as $page ) {
		$children[ (int) $page->post_parent ][] = $page;
	}

	$page_list = array();

	// Start the search by looking at immediate children.
	if ( isset( $children[ $page_id ] ) ) {
		// Always start at the end of the stack in order to preserve original `$pages` order.
		$to_look = array_reverse( $children[ $page_id ] );

		while ( $to_look ) {
			$p           = array_pop( $to_look );
			$page_list[] = $p;
			if ( isset( $children[ $p->ID ] ) ) {
				foreach ( array_reverse( $children[ $p->ID ] ) as $child ) {
					// Append to the `$to_look` stack to descend the tree.
					$to_look[] = $child;
				}
			}
		}
	}

	return $page_list;
}

/**
 * Orders the pages with children under parents in a flat list.
 *
 * It uses auxiliary structure to hold parent-children relationships and
 * runs in O(N) complexity
 *
 * @since 2.0.0
 *
 * @param WP_Post[] $pages   Posts array (passed by reference).
 * @param int       $page_id Optional. Parent page ID. Default 0.
 * @return string[] Array of post names keyed by ID and arranged by hierarchy. Children immediately follow their parents.
 */
function get_page_hierarchy( &$pages, $page_id = 0 ) {
	if ( empty( $pages ) ) {
		return array();
	}

	$children = array();
	foreach ( (array) $pages as $p ) {
		$parent_id                = (int) $p->post_parent;
		$children[ $parent_id ][] = $p;
	}

	$result = array();
	_page_traverse_name( $page_id, $children, $result );

	return $result;
}

/**
 * Traverses and return all the nested children post names of a root page.
 *
 * $children contains parent-children relations
 *
 * @since 2.9.0
 * @access private
 *
 * @see _page_traverse_name()
 *
 * @param int      $page_id  Page ID.
 * @param array    $children Parent-children relations (passed by reference).
 * @param string[] $result   Array of page names keyed by ID (passed by reference).
 */
function _page_traverse_name( $page_id, &$children, &$result ) {
	if ( isset( $children[ $page_id ] ) ) {
		foreach ( (array) $children[ $page_id ] as $child ) {
			$result[ $child->ID ] = $child->post_name;
			_page_traverse_name( $child->ID, $children, $result );
		}
	}
}

/**
 * Builds the URI path for a page.
 *
 * Sub pages will be in the "directory" under the parent page post name.
 *
 * @since 1.5.0
 * @since 4.6.0 The `$page` parameter was made optional.
 *
 * @param WP_Post|object|int $page Optional. Page ID or WP_Post object. Default is global $post.
 * @return string|false Page URI, false on error.
 */
function get_page_uri( $page = 0 ) {
	if ( ! $page instanceof WP_Post ) {
		$page = get_post( $page );
	}

	if ( ! $page ) {
		return false;
	}

	$uri = $page->post_name;

	foreach ( $page->ancestors as $parent ) {
		$parent = get_post( $parent );
		if ( $parent && $parent->post_name ) {
			$uri = $parent->post_name . '/' . $uri;
		}
	}

	/**
	 * Filters the URI for a page.
	 *
	 * @since 4.4.0
	 *
	 * @param string  $uri  Page URI.
	 * @param WP_Post $page Page object.
	 */
	return apply_filters( 'get_page_uri', $uri, $page );
}

/**
 * Retrieves an array of pages (or hierarchical post type items).
 *
 * @since 1.5.0
 * @since 6.3.0 Use WP_Query internally.
 *
 * @param array|string $args {
 *     Optional. Array or string of arguments to retrieve pages.
 *
 *     @type int          $child_of     Page ID to return child and grandchild pages of. Note: The value
 *                                      of `$hierarchical` has no bearing on whether `$child_of` returns
 *                                      hierarchical results. Default 0, or no restriction.
 *     @type string       $sort_order   How to sort retrieved pages. Accepts 'ASC', 'DESC'. Default 'ASC'.
 *     @type string       $sort_column  What columns to sort pages by, comma-separated. Accepts 'post_author',
 *                                      'post_date', 'post_title', 'post_name', 'post_modified', 'menu_order',
 *                                      'post_modified_gmt', 'post_parent', 'ID', 'rand', 'comment_count'.
 *                                      'post_' can be omitted for any values that start with it.
 *                                      Default 'post_title'.
 *     @type bool         $hierarchical Whether to return pages hierarchically. If false in conjunction with
 *                                      `$child_of` also being false, both arguments will be disregarded.
 *                                      Default true.
 *     @type int[]        $exclude      Array of page IDs to exclude. Default empty array.
 *     @type int[]        $include      Array of page IDs to include. Cannot be used with `$child_of`,
 *                                      `$parent`, `$exclude`, `$meta_key`, `$meta_value`, or `$hierarchical`.
 *                                      Default empty array.
 *     @type string       $meta_key     Only include pages with this meta key. Default empty.
 *     @type string       $meta_value   Only include pages with this meta value. Requires `$meta_key`.
 *                                      Default empty.
 *     @type string       $authors      A comma-separated list of author IDs. Default empty.
 *     @type int          $parent       Page ID to return direct children of. Default -1, or no restriction.
 *     @type string|int[] $exclude_tree Comma-separated string or array of page IDs to exclude.
 *                                      Default empty array.
 *     @type int          $number       The number of pages to return. Default 0, or all pages.
 *     @type int          $offset       The number of pages to skip before returning. Requires `$number`.
 *                                      Default 0.
 *     @type string       $post_type    The post type to query. Default 'page'.
 *     @type string|array $post_status  A comma-separated list or array of post statuses to include.
 *                                      Default 'publish'.
 * }
 * @return WP_Post[]|false Array of pages (or hierarchical post type items). Boolean false if the
 *                         specified post type is not hierarchical or the specified status is not
 *                         supported by the post type.
 */
function get_pages( $args = array() ) {
	$defaults = array(
		'child_of'     => 0,
		'sort_order'   => 'ASC',
		'sort_column'  => 'post_title',
		'hierarchical' => 1,
		'exclude'      => array(),
		'include'      => array(),
		'meta_key'     => '',
		'meta_value'   => '',
		'authors'      => '',
		'parent'       => -1,
		'exclude_tree' => array(),
		'number'       => '',
		'offset'       => 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
	);

	$parsed_args = wp_parse_args( $args, $defaults );

	$number       = (int) $parsed_args['number'];
	$offset       = (int) $parsed_args['offset'];
	$child_of     = (int) $parsed_args['child_of'];
	$hierarchical = $parsed_args['hierarchical'];
	$exclude      = $parsed_args['exclude'];
	$meta_key     = $parsed_args['meta_key'];
	$meta_value   = $parsed_args['meta_value'];
	$parent       = $parsed_args['parent'];
	$post_status  = $parsed_args['post_status'];

	// Make sure the post type is hierarchical.
	$hierarchical_post_types = get_post_types( array( 'hierarchical' => true ) );
	if ( ! in_array( $parsed_args['post_type'], $hierarchical_post_types, true ) ) {
		return false;
	}

	if ( $parent > 0 && ! $child_of ) {
		$hierarchical = false;
	}

	// Make sure we have a valid post status.
	if ( ! is_array( $post_status ) ) {
		$post_status = explode( ',', $post_status );
	}
	if ( array_diff( $post_status, get_post_stati() ) ) {
		return false;
	}

	$query_args = array(
		'orderby'                => 'post_title',
		'order'                  => 'ASC',
		'post__not_in'           => wp_parse_id_list( $exclude ),
		'meta_key'               => $meta_key,
		'meta_value'             => $meta_value,
		'posts_per_page'         => -1,
		'offset'                 => $offset,
		'post_type'              => $parsed_args['post_type'],
		'post_status'            => $post_status,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
	);

	if ( ! empty( $parsed_args['include'] ) ) {
		$child_of = 0; // Ignore child_of, parent, exclude, meta_key, and meta_value params if using include.
		$parent   = -1;
		unset( $query_args['post__not_in'], $query_args['meta_key'], $query_args['meta_value'] );
		$hierarchical           = false;
		$query_args['post__in'] = wp_parse_id_list( $parsed_args['include'] );
	}

	if ( ! empty( $parsed_args['authors'] ) ) {
		$post_authors = wp_parse_list( $parsed_args['authors'] );

		if ( ! empty( $post_authors ) ) {
			$query_args['author__in'] = array();
			foreach ( $post_authors as $post_author ) {
				// Do we have an author id or an author login?
				if ( 0 == (int) $post_author ) {
					$post_author = get_user_by( 'login', $post_author );
					if ( empty( $post_author ) ) {
						continue;
					}
					if ( empty( $post_author->ID ) ) {
						continue;
					}
					$post_author = $post_author->ID;
				}
				$query_args['author__in'][] = (int) $post_author;
			}
		}
	}

	if ( is_array( $parent ) ) {
		$post_parent__in = array_map( 'absint', (array) $parent );
		if ( ! empty( $post_parent__in ) ) {
			$query_args['post_parent__in'] = $post_parent__in;
		}
	} elseif ( $parent >= 0 ) {
		$query_args['post_parent'] = $parent;
	}

	/*
	 * Maintain backward compatibility for `sort_column` key.
	 * Additionally to `WP_Query`, it has been supporting the `post_modified_gmt` field, so this logic will translate
	 * it to `post_modified` which should result in the same order given the two dates in the fields match.
	 */
	$orderby = wp_parse_list( $parsed_args['sort_column'] );
	$orderby = array_map(
		static function ( $orderby_field ) {
			$orderby_field = trim( $orderby_field );
			if ( 'post_modified_gmt' === $orderby_field || 'modified_gmt' === $orderby_field ) {
				$orderby_field = str_replace( '_gmt', '', $orderby_field );
			}
			return $orderby_field;
		},
		$orderby
	);
	if ( $orderby ) {
		$query_args['orderby'] = array_fill_keys( $orderby, $parsed_args['sort_order'] );
	}

	$order = $parsed_args['sort_order'];
	if ( $order ) {
		$query_args['order'] = $order;
	}

	if ( ! empty( $number ) ) {
		$query_args['posts_per_page'] = $number;
	}

	/**
	 * Filters query arguments passed to WP_Query in get_pages.
	 *
	 * @since 6.3.0
	 *
	 * @param array $query_args  Array of arguments passed to WP_Query.
	 * @param array $parsed_args Array of get_pages() arguments.
	 */
	$query_args = apply_filters( 'get_pages_query_args', $query_args, $parsed_args );

	$pages = new WP_Query();
	$pages = $pages->query( $query_args );

	if ( $child_of || $hierarchical ) {
		$pages = get_page_children( $child_of, $pages );
	}

	if ( ! empty( $parsed_args['exclude_tree'] ) ) {
		$exclude = wp_parse_id_list( $parsed_args['exclude_tree'] );
		foreach ( $exclude as $id ) {
			$children = get_page_children( $id, $pages );
			foreach ( $children as $child ) {
				$exclude[] = $child->ID;
			}
		}

		$num_pages = count( $pages );
		for ( $i = 0; $i < $num_pages; $i++ ) {
			if ( in_array( $pages[ $i ]->ID, $exclude, true ) ) {
				unset( $pages[ $i ] );
			}
		}
	}

	/**
	 * Filters the retrieved list of pages.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_Post[] $pages       Array of page objects.
	 * @param array     $parsed_args Array of get_pages() arguments.
	 */
	return apply_filters( 'get_pages', $pages, $parsed_args );
}

//
// Attachment functions.
//

/**
 * Determines whether an attachment URI is local and really an attachment.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.0.0
 *
 * @param string $url URL to check
 * @return bool True on success, false on failure.
 */
function is_local_attachment( $url ) {
	if ( ! str_contains( $url, home_url() ) ) {
		return false;
	}
	if ( str_contains( $url, home_url( '/?attachment_id=' ) ) ) {
		return true;
	}

	$id = url_to_postid( $url );
	if ( $id ) {
		$post = get_post( $id );
		if ( 'attachment' === $post->post_type ) {
			return true;
		}
	}
	return false;
}

/**
 * Inserts an attachment.
 *
 * If you set the 'ID' in the $args parameter, it will mean that you are
 * updating and attempt to update the attachment. You can also set the
 * attachment name or title by setting the key 'post_name' or 'post_title'.
 *
 * You can set the dates for the attachment manually by setting the 'post_date'
 * and 'post_date_gmt' keys' values.
 *
 * By default, the comments will use the default settings for whether the
 * comments are allowed. You can close them manually or keep them open by
 * setting the value for the 'comment_status' key.
 *
 * @since 2.0.0
 * @since 4.7.0 Added the `$wp_error` parameter to allow a WP_Error to be returned on failure.
 * @since 5.6.0 Added the `$fire_after_hooks` parameter.
 *
 * @see wp_insert_post()
 *
 * @param string|array $args             Arguments for inserting an attachment.
 * @param string|false $file             Optional. Filename. Default false.
 * @param int          $parent_post_id   Optional. Parent post ID or 0 for no parent. Default 0.
 * @param bool         $wp_error         Optional. Whether to return a WP_Error on failure. Default false.
 * @param bool         $fire_after_hooks Optional. Whether to fire the after insert hooks. Default true.
 * @return int|WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
 */
function wp_insert_attachment( $args, $file = false, $parent_post_id = 0, $wp_error = false, $fire_after_hooks = true ) {
	$defaults = array(
		'file'        => $file,
		'post_parent' => 0,
	);

	$data = wp_parse_args( $args, $defaults );

	if ( ! empty( $parent_post_id ) ) {
		$data['post_parent'] = $parent_post_id;
	}

	$data['post_type'] = 'attachment';

	return wp_insert_post( $data, $wp_error, $fire_after_hooks );
}

/**
 * Trashes or deletes an attachment.
 *
 * When an attachment is permanently deleted, the file will also be removed.
 * Deletion removes all post meta fields, taxonomy, comments, etc. associated
 * with the attachment (except the main post).
 *
 * The attachment is moved to the Trash instead of permanently deleted unless Trash
 * for media is disabled, item is already in the Trash, or $force_delete is true.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int  $post_id      Attachment ID.
 * @param bool $force_delete Optional. Whether to bypass Trash and force deletion.
 *                           Default false.
 * @return WP_Post|false|null Post data on success, false or null on failure.
 */
function wp_delete_attachment( $post_id, $force_delete = false ) {
	global $wpdb;

	$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id ) );

	if ( ! $post ) {
		return $post;
	}

	$post = get_post( $post );

	if ( 'attachment' !== $post->post_type ) {
		return false;
	}

	if ( ! $force_delete && EMPTY_TRASH_DAYS && MEDIA_TRASH && 'trash' !== $post->post_status ) {
		return wp_trash_post( $post_id );
	}

	/**
	 * Filters whether an attachment deletion should take place.
	 *
	 * @since 5.5.0
	 *
	 * @param WP_Post|false|null $delete       Whether to go forward with deletion.
	 * @param WP_Post            $post         Post object.
	 * @param bool               $force_delete Whether to bypass the Trash.
	 */
	$check = apply_filters( 'pre_delete_attachment', null, $post, $force_delete );
	if ( null !== $check ) {
		return $check;
	}

	delete_post_meta( $post_id, '_wp_trash_meta_status' );
	delete_post_meta( $post_id, '_wp_trash_meta_time' );

	$meta         = wp_get_attachment_metadata( $post_id );
	$backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );
	$file         = get_attached_file( $post_id );

	if ( is_multisite() && is_string( $file ) && ! empty( $file ) ) {
		clean_dirsize_cache( $file );
	}

	/**
	 * Fires before an attachment is deleted, at the start of wp_delete_attachment().
	 *
	 * @since 2.0.0
	 * @since 5.5.0 Added the `$post` parameter.
	 *
	 * @param int     $post_id Attachment ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'delete_attachment', $post_id, $post );

	wp_delete_object_term_relationships( $post_id, array( 'category', 'post_tag' ) );
	wp_delete_object_term_relationships( $post_id, get_object_taxonomies( $post->post_type ) );

	// Delete all for any posts.
	delete_metadata( 'post', null, '_thumbnail_id', $post_id, true );

	wp_defer_comment_counting( true );

	$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d ORDER BY comment_ID DESC", $post_id ) );
	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}

	wp_defer_comment_counting( false );

	$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $post_id ) );
	foreach ( $post_meta_ids as $mid ) {
		delete_metadata_by_mid( 'post', $mid );
	}

	/** This action is documented in wp-includes/post.php */
	do_action( 'delete_post', $post_id, $post );
	$result = $wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
	if ( ! $result ) {
		return false;
	}
	/** This action is documented in wp-includes/post.php */
	do_action( 'deleted_post', $post_id, $post );

	wp_delete_attachment_files( $post_id, $meta, $backup_sizes, $file );

	clean_post_cache( $post );

	return $post;
}

/**
 * Deletes all files that belong to the given attachment.
 *
 * @since 4.9.7
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $post_id      Attachment ID.
 * @param array  $meta         The attachment's meta data.
 * @param array  $backup_sizes The meta data for the attachment's backup images.
 * @param string $file         Absolute path to the attachment's file.
 * @return bool True on success, false on failure.
 */
function wp_delete_attachment_files( $post_id, $meta, $backup_sizes, $file ) {
	global $wpdb;

	$uploadpath = wp_get_upload_dir();
	$deleted    = true;

	if ( ! empty( $meta['thumb'] ) ) {
		// Don't delete the thumb if another attachment uses it.
		if ( ! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%' . $wpdb->esc_like( $meta['thumb'] ) . '%', $post_id ) ) ) {
			$thumbfile = str_replace( wp_basename( $file ), $meta['thumb'], $file );

			if ( ! empty( $thumbfile ) ) {
				$thumbfile = path_join( $uploadpath['basedir'], $thumbfile );
				$thumbdir  = path_join( $uploadpath['basedir'], dirname( $file ) );

				if ( ! wp_delete_file_from_directory( $thumbfile, $thumbdir ) ) {
					$deleted = false;
				}
			}
		}
	}

	// Remove intermediate and backup images if there are any.
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		$intermediate_dir = path_join( $uploadpath['basedir'], dirname( $file ) );

		foreach ( $meta['sizes'] as $size => $sizeinfo ) {
			$intermediate_file = str_replace( wp_basename( $file ), $sizeinfo['file'], $file );

			if ( ! empty( $intermediate_file ) ) {
				$intermediate_file = path_join( $uploadpath['basedir'], $intermediate_file );

				if ( ! wp_delete_file_from_directory( $intermediate_file, $intermediate_dir ) ) {
					$deleted = false;
				}
			}
		}
	}

	if ( ! empty( $meta['original_image'] ) ) {
		if ( empty( $intermediate_dir ) ) {
			$intermediate_dir = path_join( $uploadpath['basedir'], dirname( $file ) );
		}

		$original_image = str_replace( wp_basename( $file ), $meta['original_image'], $file );

		if ( ! empty( $original_image ) ) {
			$original_image = path_join( $uploadpath['basedir'], $original_image );

			if ( ! wp_delete_file_from_directory( $original_image, $intermediate_dir ) ) {
				$deleted = false;
			}
		}
	}

	if ( is_array( $backup_sizes ) ) {
		$del_dir = path_join( $uploadpath['basedir'], dirname( $meta['file'] ) );

		foreach ( $backup_sizes as $size ) {
			$del_file = path_join( dirname( $meta['file'] ), $size['file'] );

			if ( ! empty( $del_file ) ) {
				$del_file = path_join( $uploadpath['basedir'], $del_file );

				if ( ! wp_delete_file_from_directory( $del_file, $del_dir ) ) {
					$deleted = false;
				}
			}
		}
	}

	if ( ! wp_delete_file_from_directory( $file, $uploadpath['basedir'] ) ) {
		$deleted = false;
	}

	return $deleted;
}

/**
 * Retrieves attachment metadata for attachment ID.
 *
 * @since 2.1.0
 * @since 6.0.0 The `$filesize` value was added to the returned array.
 *
 * @param int  $attachment_id Attachment post ID. Defaults to global $post.
 * @param bool $unfiltered    Optional. If true, filters are not run. Default false.
 * @return array|false {
 *     Attachment metadata. False on failure.
 *
 *     @type int    $width      The width of the attachment.
 *     @type int    $height     The height of the attachment.
 *     @type string $file       The file path relative to `wp-content/uploads`.
 *     @type array  $sizes      Keys are size slugs, each value is an array containing
 *                              'file', 'width', 'height', and 'mime-type'.
 *     @type array  $image_meta Image metadata.
 *     @type int    $filesize   File size of the attachment.
 * }
 */
function wp_get_attachment_metadata( $attachment_id = 0, $unfiltered = false ) {
	$attachment_id = (int) $attachment_id;

	if ( ! $attachment_id ) {
		$post = get_post();

		if ( ! $post ) {
			return false;
		}

		$attachment_id = $post->ID;
	}

	$data = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

	if ( ! $data ) {
		return false;
	}

	if ( $unfiltered ) {
		return $data;
	}

	/**
	 * Filters the attachment meta data.
	 *
	 * @since 2.1.0
	 *
	 * @param array $data          Array of meta data for the given attachment.
	 * @param int   $attachment_id Attachment post ID.
	 */
	return apply_filters( 'wp_get_attachment_metadata', $data, $attachment_id );
}

/**
 * Updates metadata for an attachment.
 *
 * @since 2.1.0
 *
 * @param int   $attachment_id Attachment post ID.
 * @param array $data          Attachment meta data.
 * @return int|false False if $post is invalid.
 */
function wp_update_attachment_metadata( $attachment_id, $data ) {
	$attachment_id = (int) $attachment_id;

	$post = get_post( $attachment_id );

	if ( ! $post ) {
		return false;
	}

	/**
	 * Filters the updated attachment meta data.
	 *
	 * @since 2.1.0
	 *
	 * @param array $data          Array of updated attachment meta data.
	 * @param int   $attachment_id Attachment post ID.
	 */
	$data = apply_filters( 'wp_update_attachment_metadata', $data, $post->ID );
	if ( $data ) {
		return update_post_meta( $post->ID, '_wp_attachment_metadata', $data );
	} else {
		return delete_post_meta( $post->ID, '_wp_attachment_metadata' );
	}
}

/**
 * Retrieves the URL for an attachment.
 *
 * @since 2.1.0
 *
 * @global string $pagenow The filename of the current screen.
 *
 * @param int $attachment_id Optional. Attachment post ID. Defaults to global $post.
 * @return string|false Attachment URL, otherwise false.
 */
function wp_get_attachment_url( $attachment_id = 0 ) {
	global $pagenow;

	$attachment_id = (int) $attachment_id;

	$post = get_post( $attachment_id );

	if ( ! $post ) {
		return false;
	}

	if ( 'attachment' !== $post->post_type ) {
		return false;
	}

	$url = '';
	// Get attached file.
	$file = get_post_meta( $post->ID, '_wp_attached_file', true );
	if ( $file ) {
		// Get upload directory.
		$uploads = wp_get_upload_dir();
		if ( $uploads && false === $uploads['error'] ) {
			// Check that the upload base exists in the file location.
			if ( str_starts_with( $file, $uploads['basedir'] ) ) {
				// Replace file location with url location.
				$url = str_replace( $uploads['basedir'], $uploads['baseurl'], $file );
			} elseif ( str_contains( $file, 'wp-content/uploads' ) ) {
				// Get the directory name relative to the basedir (back compat for pre-2.7 uploads).
				$url = trailingslashit( $uploads['baseurl'] . '/' . _wp_get_attachment_relative_path( $file ) ) . wp_basename( $file );
			} else {
				// It's a newly-uploaded file, therefore $file is relative to the basedir.
				$url = $uploads['baseurl'] . "/$file";
			}
		}
	}

	/*
	 * If any of the above options failed, Fallback on the GUID as used pre-2.7,
	 * not recommended to rely upon this.
	 */
	if ( ! $url ) {
		$url = get_the_guid( $post->ID );
	}

	// On SSL front end, URLs should be HTTPS.
	if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $pagenow ) {
		$url = set_url_scheme( $url );
	}

	/**
	 * Filters the attachment URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url           URL for the given attachment.
	 * @param int    $attachment_id Attachment post ID.
	 */
	$url = apply_filters( 'wp_get_attachment_url', $url, $post->ID );

	if ( ! $url ) {
		return false;
	}

	return $url;
}

/**
 * Retrieves the caption for an attachment.
 *
 * @since 4.6.0
 *
 * @param int $post_id Optional. Attachment ID. Default is the ID of the global `$post`.
 * @return string|false Attachment caption on success, false on failure.
 */
function wp_get_attachment_caption( $post_id = 0 ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	if ( 'attachment' !== $post->post_type ) {
		return false;
	}

	$caption = $post->post_excerpt;

	/**
	 * Filters the attachment caption.
	 *
	 * @since 4.6.0
	 *
	 * @param string $caption Caption for the given attachment.
	 * @param int    $post_id Attachment ID.
	 */
	return apply_filters( 'wp_get_attachment_caption', $caption, $post->ID );
}

/**
 * Retrieves URL for an attachment thumbnail.
 *
 * @since 2.1.0
 * @since 6.1.0 Changed to use wp_get_attachment_image_url().
 *
 * @param int $post_id Optional. Attachment ID. Default is the ID of the global `$post`.
 * @return string|false Thumbnail URL on success, false on failure.
 */
function wp_get_attachment_thumb_url( $post_id = 0 ) {
	$post_id = (int) $post_id;

	/*
	 * This uses image_downsize() which also looks for the (very) old format $image_meta['thumb']
	 * when the newer format $image_meta['sizes']['thumbnail'] doesn't exist.
	 */
	$thumbnail_url = wp_get_attachment_image_url( $post_id, 'thumbnail' );

	if ( empty( $thumbnail_url ) ) {
		return false;
	}

	/**
	 * Filters the attachment thumbnail URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $thumbnail_url URL for the attachment thumbnail.
	 * @param int    $post_id       Attachment ID.
	 */
	return apply_filters( 'wp_get_attachment_thumb_url', $thumbnail_url, $post_id );
}

/**
 * Verifies an attachment is of a given type.
 *
 * @since 4.2.0
 *
 * @param string      $type Attachment type. Accepts `image`, `audio`, `video`, or a file extension.
 * @param int|WP_Post $post Optional. Attachment ID or object. Default is global $post.
 * @return bool True if an accepted type or a matching file extension, false otherwise.
 */
function wp_attachment_is( $type, $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$file = get_attached_file( $post->ID );

	if ( ! $file ) {
		return false;
	}

	if ( str_starts_with( $post->post_mime_type, $type . '/' ) ) {
		return true;
	}

	$check = wp_check_filetype( $file );

	if ( empty( $check['ext'] ) ) {
		return false;
	}

	$ext = $check['ext'];

	if ( 'import' !== $post->post_mime_type ) {
		return $type === $ext;
	}

	switch ( $type ) {
		case 'image':
			$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp', 'avif', 'heic' );
			return in_array( $ext, $image_exts, true );

		case 'audio':
			return in_array( $ext, wp_get_audio_extensions(), true );

		case 'video':
			return in_array( $ext, wp_get_video_extensions(), true );

		default:
			return $type === $ext;
	}
}

/**
 * Determines whether an attachment is an image.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 2.1.0
 * @since 4.2.0 Modified into wrapper for wp_attachment_is() and
 *              allowed WP_Post object to be passed.
 *
 * @param int|WP_Post $post Optional. Attachment ID or object. Default is global $post.
 * @return bool Whether the attachment is an image.
 */
function wp_attachment_is_image( $post = null ) {
	return wp_attachment_is( 'image', $post );
}

/**
 * Retrieves the icon for a MIME type or attachment.
 *
 * @since 2.1.0
 * @since 6.5.0 Added the `$preferred_ext` parameter.
 *
 * @param string|int $mime          MIME type or attachment ID.
 * @param string     $preferred_ext File format to prefer in return. Default '.png'.
 * @return string|false Icon, false otherwise.
 */
function wp_mime_type_icon( $mime = 0, $preferred_ext = '.png' ) {
	if ( ! is_numeric( $mime ) ) {
		$icon = wp_cache_get( "mime_type_icon_$mime" );
	}

	// Check if preferred file format variable is present and is a validly formatted file extension.
	if ( ! empty( $preferred_ext ) && is_string( $preferred_ext ) && ! str_starts_with( $preferred_ext, '.' ) ) {
		$preferred_ext = '.' . strtolower( $preferred_ext );
	}

	$post_id = 0;
	if ( empty( $icon ) ) {
		$post_mimes = array();
		if ( is_numeric( $mime ) ) {
			$mime = (int) $mime;
			$post = get_post( $mime );
			if ( $post ) {
				$post_id = (int) $post->ID;
				$file    = get_attached_file( $post_id );
				$ext     = preg_replace( '/^.+?\.([^.]+)$/', '$1', $file );
				if ( ! empty( $ext ) ) {
					$post_mimes[] = $ext;
					$ext_type     = wp_ext2type( $ext );
					if ( $ext_type ) {
						$post_mimes[] = $ext_type;
					}
				}
				$mime = $post->post_mime_type;
			} else {
				$mime = 0;
			}
		} else {
			$post_mimes[] = $mime;
		}

		$icon_files = wp_cache_get( 'icon_files' );

		if ( ! is_array( $icon_files ) ) {
			/**
			 * Filters the icon directory path.
			 *
			 * @since 2.0.0
			 *
			 * @param string $path Icon directory absolute path.
			 */
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );

			/**
			 * Filters the icon directory URI.
			 *
			 * @since 2.0.0
			 *
			 * @param string $uri Icon directory URI.
			 */
			$icon_dir_uri = apply_filters( 'icon_dir_uri', includes_url( 'images/media' ) );

			/**
			 * Filters the array of icon directory URIs.
			 *
			 * @since 2.5.0
			 *
			 * @param string[] $uris Array of icon directory URIs keyed by directory absolute path.
			 */
			$dirs       = apply_filters( 'icon_dirs', array( $icon_dir => $icon_dir_uri ) );
			$icon_files = array();
			$all_icons  = array();
			while ( $dirs ) {
				$keys = array_keys( $dirs );
				$dir  = array_shift( $keys );
				$uri  = array_shift( $dirs );
				$dh   = opendir( $dir );
				if ( $dh ) {
					while ( false !== $file = readdir( $dh ) ) {
						$file = wp_basename( $file );
						if ( str_starts_with( $file, '.' ) ) {
							continue;
						}

						$ext = strtolower( substr( $file, -4 ) );
						if ( ! in_array( $ext, array( '.svg', '.png', '.gif', '.jpg' ), true ) ) {
							if ( is_dir( "$dir/$file" ) ) {
								$dirs[ "$dir/$file" ] = "$uri/$file";
							}
							continue;
						}
						$all_icons[ "$dir/$file" ] = "$uri/$file";
						if ( $ext === $preferred_ext ) {
							$icon_files[ "$dir/$file" ] = "$uri/$file";
						}
					}
					closedir( $dh );
				}
			}
			// If directory only contained icons of a non-preferred format, return those.
			if ( empty( $icon_files ) ) {
				$icon_files = $all_icons;
			}
			wp_cache_add( 'icon_files', $icon_files, 'default', 600 );
		}

		$types = array();
		// Icon wp_basename - extension = MIME wildcard.
		foreach ( $icon_files as $file => $uri ) {
			$types[ preg_replace( '/^([^.]*).*$/', '$1', wp_basename( $file ) ) ] =& $icon_files[ $file ];
		}

		if ( ! empty( $mime ) ) {
			$post_mimes[] = substr( $mime, 0, strpos( $mime, '/' ) );
			$post_mimes[] = substr( $mime, strpos( $mime, '/' ) + 1 );
			$post_mimes[] = str_replace( '/', '_', $mime );
		}

		$matches            = wp_match_mime_types( array_keys( $types ), $post_mimes );
		$matches['default'] = array( 'default' );

		foreach ( $matches as $match => $wilds ) {
			foreach ( $wilds as $wild ) {
				if ( ! isset( $types[ $wild ] ) ) {
					continue;
				}

				$icon = $types[ $wild ];
				if ( ! is_numeric( $mime ) ) {
					wp_cache_add( "mime_type_icon_$mime", $icon );
				}
				break 2;
			}
		}
	}

	/**
	 * Filters the mime type icon.
	 *
	 * @since 2.1.0
	 *
	 * @param string $icon    Path to the mime type icon.
	 * @param string $mime    Mime type.
	 * @param int    $post_id Attachment ID. Will equal 0 if the function passed
	 *                        the mime type.
	 */
	return apply_filters( 'wp_mime_type_icon', $icon, $mime, $post_id );
}

/**
 * Checks for changed slugs for published post objects and save the old slug.
 *
 * The function is used when a post object of any type is updated,
 * by comparing the current and previous post objects.
 *
 * If the slug was changed and not already part of the old slugs then it will be
 * added to the post meta field ('_wp_old_slug') for storing old slugs for that
 * post.
 *
 * The most logically usage of this function is redirecting changed post objects, so
 * that those that linked to an changed post will be redirected to the new post.
 *
 * @since 2.1.0
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post        The post object.
 * @param WP_Post $post_before The previous post object.
 */
function wp_check_for_changed_slugs( $post_id, $post, $post_before ) {
	// Don't bother if it hasn't changed.
	if ( $post->post_name == $post_before->post_name ) {
		return;
	}

	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}

	$old_slugs = (array) get_post_meta( $post_id, '_wp_old_slug' );

	// If we haven't added this old slug before, add it now.
	if ( ! empty( $post_before->post_name ) && ! in_array( $post_before->post_name, $old_slugs, true ) ) {
		add_post_meta( $post_id, '_wp_old_slug', $post_before->post_name );
	}

	// If the new slug was used previously, delete it from the list.
	if ( in_array( $post->post_name, $old_slugs, true ) ) {
		delete_post_meta( $post_id, '_wp_old_slug', $post->post_name );
	}
}

/**
 * Checks for changed dates for published post objects and save the old date.
 *
 * The function is used when a post object of any type is updated,
 * by comparing the current and previous post objects.
 *
 * If the date was changed and not already part of the old dates then it will be
 * added to the post meta field ('_wp_old_date') for storing old dates for that
 * post.
 *
 * The most logically usage of this function is redirecting changed post objects, so
 * that those that linked to an changed post will be redirected to the new post.
 *
 * @since 4.9.3
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post        The post object.
 * @param WP_Post $post_before The previous post object.
 */
function wp_check_for_changed_dates( $post_id, $post, $post_before ) {
	$previous_date = gmdate( 'Y-m-d', strtotime( $post_before->post_date ) );
	$new_date      = gmdate( 'Y-m-d', strtotime( $post->post_date ) );

	// Don't bother if it hasn't changed.
	if ( $new_date == $previous_date ) {
		return;
	}

	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}

	$old_dates = (array) get_post_meta( $post_id, '_wp_old_date' );

	// If we haven't added this old date before, add it now.
	if ( ! empty( $previous_date ) && ! in_array( $previous_date, $old_dates, true ) ) {
		add_post_meta( $post_id, '_wp_old_date', $previous_date );
	}

	// If the new slug was used previously, delete it from the list.
	if ( in_array( $new_date, $old_dates, true ) ) {
		delete_post_meta( $post_id, '_wp_old_date', $new_date );
	}
}

/**
 * Retrieves the private post SQL based on capability.
 *
 * This function provides a standardized way to appropriately select on the
 * post_status of a post type. The function will return a piece of SQL code
 * that can be added to a WHERE clause; this SQL is constructed to allow all
 * published posts, and all private posts to which the user has access.
 *
 * @since 2.2.0
 * @since 4.3.0 Added the ability to pass an array to `$post_type`.
 *
 * @param string|array $post_type Single post type or an array of post types. Currently only supports 'post' or 'page'.
 * @return string SQL code that can be added to a where clause.
 */
function get_private_posts_cap_sql( $post_type ) {
	return get_posts_by_author_sql( $post_type, false );
}

/**
 * Retrieves the post SQL based on capability, author, and type.
 *
 * @since 3.0.0
 * @since 4.3.0 Introduced the ability to pass an array of post types to `$post_type`.
 *
 * @see get_private_posts_cap_sql()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|string[] $post_type   Single post type or an array of post types.
 * @param bool            $full        Optional. Returns a full WHERE statement instead of just
 *                                     an 'andalso' term. Default true.
 * @param int             $post_author Optional. Query posts having a single author ID. Default null.
 * @param bool            $public_only Optional. Only return public posts. Skips cap checks for
 *                                     $current_user.  Default false.
 * @return string SQL WHERE code that can be added to a query.
 */
function get_posts_by_author_sql( $post_type, $full = true, $post_author = null, $public_only = false ) {
	global $wpdb;

	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type );
	}

	$post_type_clauses = array();
	foreach ( $post_types as $post_type ) {
		$post_type_obj = get_post_type_object( $post_type );

		if ( ! $post_type_obj ) {
			continue;
		}

		/**
		 * Filters the capability to read private posts for a custom post type
		 * when generating SQL for getting posts by author.
		 *
		 * @since 2.2.0
		 * @deprecated 3.2.0 The hook transitioned from "somewhat useless" to "totally useless".
		 *
		 * @param string $cap Capability.
		 */
		$cap = apply_filters_deprecated( 'pub_priv_sql_capability', array( '' ), '3.2.0' );

		if ( ! $cap ) {
			$cap = current_user_can( $post_type_obj->cap->read_private_posts );
		}

		// Only need to check the cap if $public_only is false.
		$post_status_sql = "post_status = 'publish'";

		if ( false === $public_only ) {
			if ( $cap ) {
				// Does the user have the capability to view private posts? Guess so.
				$post_status_sql .= " OR post_status = 'private'";
			} elseif ( is_user_logged_in() ) {
				// Users can view their own private posts.
				$id = get_current_user_id();
				if ( null === $post_author || ! $full ) {
					$post_status_sql .= " OR post_status = 'private' AND post_author = $id";
				} elseif ( $id == (int) $post_author ) {
					$post_status_sql .= " OR post_status = 'private'";
				} // Else none.
			} // Else none.
		}

		$post_type_clauses[] = "( post_type = '" . $post_type . "' AND ( $post_status_sql ) )";
	}

	if ( empty( $post_type_clauses ) ) {
		return $full ? 'WHERE 1 = 0' : '1 = 0';
	}

	$sql = '( ' . implode( ' OR ', $post_type_clauses ) . ' )';

	if ( null !== $post_author ) {
		$sql .= $wpdb->prepare( ' AND post_author = %d', $post_author );
	}

	if ( $full ) {
		$sql = 'WHERE ' . $sql;
	}

	return $sql;
}

/**
 * Retrieves the most recent time that a post on the site was published.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is the date when the last post was posted.
 * The 'gmt' is when the last post was posted in GMT formatted date.
 *
 * @since 0.71
 * @since 4.4.0 The `$post_type` argument was added.
 *
 * @param string $timezone  Optional. The timezone for the timestamp. Accepts 'server', 'blog', or 'gmt'.
 *                          'server' uses the server's internal timezone.
 *                          'blog' uses the `post_date` field, which proxies to the timezone set for the site.
 *                          'gmt' uses the `post_date_gmt` field.
 *                          Default 'server'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string The date of the last post, or false on failure.
 */
function get_lastpostdate( $timezone = 'server', $post_type = 'any' ) {
	$lastpostdate = _get_last_post_time( $timezone, 'date', $post_type );

	/**
	 * Filters the most recent time that a post on the site was published.
	 *
	 * @since 2.3.0
	 * @since 5.5.0 Added the `$post_type` parameter.
	 *
	 * @param string|false $lastpostdate The most recent time that a post was published,
	 *                                   in 'Y-m-d H:i:s' format. False on failure.
	 * @param string       $timezone     Location to use for getting the post published date.
	 *                                   See get_lastpostdate() for accepted `$timezone` values.
	 * @param string       $post_type    The post type to check.
	 */
	return apply_filters( 'get_lastpostdate', $lastpostdate, $timezone, $post_type );
}

/**
 * Gets the most recent time that a post on the site was modified.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is just when the last post was modified.
 * The 'gmt' is when the last post was modified in GMT time.
 *
 * @since 1.2.0
 * @since 4.4.0 The `$post_type` argument was added.
 *
 * @param string $timezone  Optional. The timezone for the timestamp. See get_lastpostdate()
 *                          for information on accepted values.
 *                          Default 'server'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string The timestamp in 'Y-m-d H:i:s' format, or false on failure.
 */
function get_lastpostmodified( $timezone = 'server', $post_type = 'any' ) {
	/**
	 * Pre-filter the return value of get_lastpostmodified() before the query is run.
	 *
	 * @since 4.4.0
	 *
	 * @param string|false $lastpostmodified The most recent time that a post was modified,
	 *                                       in 'Y-m-d H:i:s' format, or false. Returning anything
	 *                                       other than false will short-circuit the function.
	 * @param string       $timezone         Location to use for getting the post modified date.
	 *                                       See get_lastpostdate() for accepted `$timezone` values.
	 * @param string       $post_type        The post type to check.
	 */
	$lastpostmodified = apply_filters( 'pre_get_lastpostmodified', false, $timezone, $post_type );

	if ( false !== $lastpostmodified ) {
		return $lastpostmodified;
	}

	$lastpostmodified = _get_last_post_time( $timezone, 'modified', $post_type );
	$lastpostdate     = get_lastpostdate( $timezone, $post_type );

	if ( $lastpostdate > $lastpostmodified ) {
		$lastpostmodified = $lastpostdate;
	}

	/**
	 * Filters the most recent time that a post on the site was modified.
	 *
	 * @since 2.3.0
	 * @since 5.5.0 Added the `$post_type` parameter.
	 *
	 * @param string|false $lastpostmodified The most recent time that a post was modified,
	 *                                       in 'Y-m-d H:i:s' format. False on failure.
	 * @param string       $timezone         Location to use for getting the post modified date.
	 *                                       See get_lastpostdate() for accepted `$timezone` values.
	 * @param string       $post_type        The post type to check.
	 */
	return apply_filters( 'get_lastpostmodified', $lastpostmodified, $timezone, $post_type );
}

/**
 * Gets the timestamp of the last time any post was modified or published.
 *
 * @since 3.1.0
 * @since 4.4.0 The `$post_type` argument was added.
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $timezone  The timezone for the timestamp. See get_lastpostdate().
 *                          for information on accepted values.
 * @param string $field     Post field to check. Accepts 'date' or 'modified'.
 * @param string $post_type Optional. The post type to check. Default 'any'.
 * @return string|false The timestamp in 'Y-m-d H:i:s' format, or false on failure.
 */
function _get_last_post_time( $timezone, $field, $post_type = 'any' ) {
	global $wpdb;

	if ( ! in_array( $field, array( 'date', 'modified' ), true ) ) {
		return false;
	}

	$timezone = strtolower( $timezone );

	$key = "lastpost{$field}:$timezone";
	if ( 'any' !== $post_type ) {
		$key .= ':' . sanitize_key( $post_type );
	}

	$date = wp_cache_get( $key, 'timeinfo' );
	if ( false !== $date ) {
		return $date;
	}

	if ( 'any' === $post_type ) {
		$post_types = get_post_types( array( 'public' => true ) );
		array_walk( $post_types, array( $wpdb, 'escape_by_ref' ) );
		$post_types = "'" . implode( "', '", $post_types ) . "'";
	} else {
		$post_types = "'" . sanitize_key( $post_type ) . "'";
	}

	switch ( $timezone ) {
		case 'gmt':
			$date = $wpdb->get_var( "SELECT post_{$field}_gmt FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
		case 'blog':
			$date = $wpdb->get_var( "SELECT post_{$field} FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
		case 'server':
			$add_seconds_server = gmdate( 'Z' );
			$date               = $wpdb->get_var( "SELECT DATE_ADD(post_{$field}_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1" );
			break;
	}

	if ( $date ) {
		wp_cache_set( $key, $date, 'timeinfo' );

		return $date;
	}

	return false;
}

/**
 * Updates posts in cache.
 *
 * @since 1.5.1
 *
 * @param WP_Post[] $posts Array of post objects (passed by reference).
 */
function update_post_cache( &$posts ) {
	if ( ! $posts ) {
		return;
	}

	$data = array();
	foreach ( $posts as $post ) {
		if ( empty( $post->filter ) || 'raw' !== $post->filter ) {
			$post = sanitize_post( $post, 'raw' );
		}
		$data[ $post->ID ] = $post;
	}
	wp_cache_add_multiple( $data, 'posts' );
}

/**
 * Will clean the post in the cache.
 *
 * Cleaning means delete from the cache of the post. Will call to clean the term
 * object cache associated with the post ID.
 *
 * This function not run if $_wp_suspend_cache_invalidation is not empty. See
 * wp_suspend_cache_invalidation().
 *
 * @since 2.0.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param int|WP_Post $post Post ID or post object to remove from the cache.
 */
function clean_post_cache( $post ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	$post = get_post( $post );

	if ( ! $post ) {
		return;
	}

	wp_cache_delete( $post->ID, 'posts' );
	wp_cache_delete( 'post_parent:' . (string) $post->ID, 'posts' );
	wp_cache_delete( $post->ID, 'post_meta' );

	clean_object_term_cache( $post->ID, $post->post_type );

	wp_cache_delete( 'wp_get_archives', 'general' );

	/**
	 * Fires immediately after the given post's cache is cleaned.
	 *
	 * @since 2.5.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	do_action( 'clean_post_cache', $post->ID, $post );

	if ( 'page' === $post->post_type ) {
		wp_cache_delete( 'all_page_ids', 'posts' );

		/**
		 * Fires immediately after the given page's cache is cleaned.
		 *
		 * @since 2.5.0
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'clean_page_cache', $post->ID );
	}

	wp_cache_set_posts_last_changed();
}

/**
 * Updates post, term, and metadata caches for a list of post objects.
 *
 * @since 1.5.0
 *
 * @param WP_Post[] $posts             Array of post objects (passed by reference).
 * @param string    $post_type         Optional. Post type. Default 'post'.
 * @param bool      $update_term_cache Optional. Whether to update the term cache. Default true.
 * @param bool      $update_meta_cache Optional. Whether to update the meta cache. Default true.
 */
function update_post_caches( &$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true ) {
	// No point in doing all this work if we didn't match any posts.
	if ( ! $posts ) {
		return;
	}

	update_post_cache( $posts );

	$post_ids = array();
	foreach ( $posts as $post ) {
		$post_ids[] = $post->ID;
	}

	if ( ! $post_type ) {
		$post_type = 'any';
	}

	if ( $update_term_cache ) {
		if ( is_array( $post_type ) ) {
			$ptypes = $post_type;
		} elseif ( 'any' === $post_type ) {
			$ptypes = array();
			// Just use the post_types in the supplied posts.
			foreach ( $posts as $post ) {
				$ptypes[] = $post->post_type;
			}
			$ptypes = array_unique( $ptypes );
		} else {
			$ptypes = array( $post_type );
		}

		if ( ! empty( $ptypes ) ) {
			update_object_term_cache( $post_ids, $ptypes );
		}
	}

	if ( $update_meta_cache ) {
		update_postmeta_cache( $post_ids );
	}
}

/**
 * Updates post author user caches for a list of post objects.
 *
 * @since 6.1.0
 *
 * @param WP_Post[] $posts Array of post objects.
 */
function update_post_author_caches( $posts ) {
	/*
	 * cache_users() is a pluggable function so is not available prior
	 * to the `plugins_loaded` hook firing. This is to ensure against
	 * fatal errors when the function is not available.
	 */
	if ( ! function_exists( 'cache_users' ) ) {
		return;
	}

	$author_ids = wp_list_pluck( $posts, 'post_author' );
	$author_ids = array_map( 'absint', $author_ids );
	$author_ids = array_unique( array_filter( $author_ids ) );

	cache_users( $author_ids );
}

/**
 * Updates parent post caches for a list of post objects.
 *
 * @since 6.1.0
 *
 * @param WP_Post[] $posts Array of post objects.
 */
function update_post_parent_caches( $posts ) {
	$parent_ids = wp_list_pluck( $posts, 'post_parent' );
	$parent_ids = array_map( 'absint', $parent_ids );
	$parent_ids = array_unique( array_filter( $parent_ids ) );

	if ( ! empty( $parent_ids ) ) {
		_prime_post_caches( $parent_ids, false );
	}
}

/**
 * Updates metadata cache for a list of post IDs.
 *
 * Performs SQL query to retrieve the metadata for the post IDs and updates the
 * metadata cache for the posts. Therefore, the functions, which call this
 * function, do not need to perform SQL queries on their own.
 *
 * @since 2.1.0
 *
 * @param int[] $post_ids Array of post IDs.
 * @return array|false An array of metadata on success, false if there is nothing to update.
 */
function update_postmeta_cache( $post_ids ) {
	return update_meta_cache( 'post', $post_ids );
}

/**
 * Will clean the attachment in the cache.
 *
 * Cleaning means delete from the cache. Optionally will clean the term
 * object cache associated with the attachment ID.
 *
 * This function will not run if $_wp_suspend_cache_invalidation is not empty.
 *
 * @since 3.0.0
 *
 * @global bool $_wp_suspend_cache_invalidation
 *
 * @param int  $id          The attachment ID in the cache to clean.
 * @param bool $clean_terms Optional. Whether to clean terms cache. Default false.
 */
function clean_attachment_cache( $id, $clean_terms = false ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	$id = (int) $id;

	wp_cache_delete( $id, 'posts' );
	wp_cache_delete( $id, 'post_meta' );

	if ( $clean_terms ) {
		clean_object_term_cache( $id, 'attachment' );
	}

	/**
	 * Fires after the given attachment's cache is cleaned.
	 *
	 * @since 3.0.0
	 *
	 * @param int $id Attachment ID.
	 */
	do_action( 'clean_attachment_cache', $id );
}

//
// Hooks.
//

/**
 * Hook for managing future post transitions to published.
 *
 * @since 2.3.0
 * @access private
 *
 * @see wp_clear_scheduled_hook()
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Previous post status.
 * @param WP_Post $post       Post object.
 */
function _transition_post_status( $new_status, $old_status, $post ) {
	global $wpdb;

	if ( 'publish' !== $old_status && 'publish' === $new_status ) {
		// Reset GUID if transitioning to publish and it is empty.
		if ( '' === get_the_guid( $post->ID ) ) {
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post->ID ) ), array( 'ID' => $post->ID ) );
		}

		/**
		 * Fires when a post's status is transitioned from private to published.
		 *
		 * @since 1.5.0
		 * @deprecated 2.3.0 Use {@see 'private_to_publish'} instead.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action_deprecated( 'private_to_published', array( $post->ID ), '2.3.0', 'private_to_publish' );
	}

	// If published posts changed clear the lastpostmodified cache.
	if ( 'publish' === $new_status || 'publish' === $old_status ) {
		foreach ( array( 'server', 'gmt', 'blog' ) as $timezone ) {
			wp_cache_delete( "lastpostmodified:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone:{$post->post_type}", 'timeinfo' );
		}
	}

	if ( $new_status !== $old_status ) {
		wp_cache_delete( _count_posts_cache_key( $post->post_type ), 'counts' );
		wp_cache_delete( _count_posts_cache_key( $post->post_type, 'readable' ), 'counts' );
	}

	// Always clears the hook in case the post status bounced from future to draft.
	wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) );
}

/**
 * Hook used to schedule publication for a post marked for the future.
 *
 * The $post properties used and must exist are 'ID' and 'post_date_gmt'.
 *
 * @since 2.3.0
 * @access private
 *
 * @param int     $deprecated Not used. Can be set to null. Never implemented. Not marked
 *                            as deprecated with _deprecated_argument() as it conflicts with
 *                            wp_transition_post_status() and the default filter for _future_post_hook().
 * @param WP_Post $post       Post object.
 */
function _future_post_hook( $deprecated, $post ) {
	wp_clear_scheduled_hook( 'publish_future_post', array( $post->ID ) );
	wp_schedule_single_event( strtotime( get_gmt_from_date( $post->post_date ) . ' GMT' ), 'publish_future_post', array( $post->ID ) );
}

/**
 * Hook to schedule pings and enclosures when a post is published.
 *
 * Uses XMLRPC_REQUEST and WP_IMPORTING constants.
 *
 * @since 2.3.0
 * @access private
 *
 * @param int $post_id The ID of the post being published.
 */
function _publish_post_hook( $post_id ) {
	if ( defined( 'XMLRPC_REQUEST' ) ) {
		/**
		 * Fires when _publish_post_hook() is called during an XML-RPC request.
		 *
		 * @since 2.1.0
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'xmlrpc_publish_post', $post_id );
	}

	if ( defined( 'WP_IMPORTING' ) ) {
		return;
	}

	if ( get_option( 'default_pingback_flag' ) ) {
		add_post_meta( $post_id, '_pingme', '1', true );
	}
	add_post_meta( $post_id, '_encloseme', '1', true );

	$to_ping = get_to_ping( $post_id );
	if ( ! empty( $to_ping ) ) {
		add_post_meta( $post_id, '_trackbackme', '1' );
	}

	if ( ! wp_next_scheduled( 'do_pings' ) ) {
		wp_schedule_single_event( time(), 'do_pings' );
	}
}

/**
 * Returns the ID of the post's parent.
 *
 * @since 3.1.0
 * @since 5.9.0 The `$post` parameter was made optional.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
 * @return int|false Post parent ID (which can be 0 if there is no parent),
 *                   or false if the post does not exist.
 */
function wp_get_post_parent_id( $post = null ) {
	$post = get_post( $post );

	if ( ! $post || is_wp_error( $post ) ) {
		return false;
	}

	return (int) $post->post_parent;
}

/**
 * Checks the given subset of the post hierarchy for hierarchy loops.
 *
 * Prevents loops from forming and breaks those that it finds. Attached
 * to the {@see 'wp_insert_post_parent'} filter.
 *
 * @since 3.1.0
 *
 * @see wp_find_hierarchy_loop()
 *
 * @param int $post_parent ID of the parent for the post we're checking.
 * @param int $post_id     ID of the post we're checking.
 * @return int The new post_parent for the post, 0 otherwise.
 */
function wp_check_post_hierarchy_for_loops( $post_parent, $post_id ) {
	// Nothing fancy here - bail.
	if ( ! $post_parent ) {
		return 0;
	}

	// New post can't cause a loop.
	if ( ! $post_id ) {
		return $post_parent;
	}

	// Can't be its own parent.
	if ( $post_parent == $post_id ) {
		return 0;
	}

	// Now look for larger loops.
	$loop = wp_find_hierarchy_loop( 'wp_get_post_parent_id', $post_id, $post_parent );
	if ( ! $loop ) {
		return $post_parent; // No loop.
	}

	// Setting $post_parent to the given value causes a loop.
	if ( isset( $loop[ $post_id ] ) ) {
		return 0;
	}

	// There's a loop, but it doesn't contain $post_id. Break the loop.
	foreach ( array_keys( $loop ) as $loop_member ) {
		wp_update_post(
			array(
				'ID'          => $loop_member,
				'post_parent' => 0,
			)
		);
	}

	return $post_parent;
}

/**
 * Sets the post thumbnail (featured image) for the given post.
 *
 * @since 3.1.0
 *
 * @param int|WP_Post $post         Post ID or post object where thumbnail should be attached.
 * @param int         $thumbnail_id Thumbnail to attach.
 * @return int|bool Post meta ID if the key didn't exist (ie. this is the first time that
 *                  a thumbnail has been saved for the post), true on successful update,
 *                  false on failure or if the value passed is the same as the one that
 *                  is already in the database.
 */
function set_post_thumbnail( $post, $thumbnail_id ) {
	$post         = get_post( $post );
	$thumbnail_id = absint( $thumbnail_id );
	if ( $post && $thumbnail_id && get_post( $thumbnail_id ) ) {
		if ( wp_get_attachment_image( $thumbnail_id, 'thumbnail' ) ) {
			return update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
		} else {
			return delete_post_meta( $post->ID, '_thumbnail_id' );
		}
	}
	return false;
}

/**
 * Removes the thumbnail (featured image) from the given post.
 *
 * @since 3.3.0
 *
 * @param int|WP_Post $post Post ID or post object from which the thumbnail should be removed.
 * @return bool True on success, false on failure.
 */
function delete_post_thumbnail( $post ) {
	$post = get_post( $post );
	if ( $post ) {
		return delete_post_meta( $post->ID, '_thumbnail_id' );
	}
	return false;
}

/**
 * Deletes auto-drafts for new posts that are > 7 days old.
 *
 * @since 3.4.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_delete_auto_drafts() {
	global $wpdb;

	// Cleanup old auto-drafts more than 7 days old.
	$old_posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft' AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );
	foreach ( (array) $old_posts as $delete ) {
		// Force delete.
		wp_delete_post( $delete, true );
	}
}

/**
 * Queues posts for lazy-loading of term meta.
 *
 * @since 4.5.0
 *
 * @param WP_Post[] $posts Array of WP_Post objects.
 */
function wp_queue_posts_for_term_meta_lazyload( $posts ) {
	$post_type_taxonomies = array();
	$prime_post_terms     = array();
	foreach ( $posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

		if ( ! isset( $post_type_taxonomies[ $post->post_type ] ) ) {
			$post_type_taxonomies[ $post->post_type ] = get_object_taxonomies( $post->post_type );
		}

		foreach ( $post_type_taxonomies[ $post->post_type ] as $taxonomy ) {
			$prime_post_terms[ $taxonomy ][] = $post->ID;
		}
	}

	$term_ids = array();
	if ( $prime_post_terms ) {
		foreach ( $prime_post_terms as $taxonomy => $post_ids ) {
			$cached_term_ids = wp_cache_get_multiple( $post_ids, "{$taxonomy}_relationships" );
			if ( is_array( $cached_term_ids ) ) {
				$cached_term_ids = array_filter( $cached_term_ids );
				foreach ( $cached_term_ids as $_term_ids ) {
					// Backward compatibility for if a plugin is putting objects into the cache, rather than IDs.
					foreach ( $_term_ids as $term_id ) {
						if ( is_numeric( $term_id ) ) {
							$term_ids[] = (int) $term_id;
						} elseif ( isset( $term_id->term_id ) ) {
							$term_ids[] = (int) $term_id->term_id;
						}
					}
				}
			}
		}
		$term_ids = array_unique( $term_ids );
	}

	wp_lazyload_term_meta( $term_ids );
}

/**
 * Updates the custom taxonomies' term counts when a post's status is changed.
 *
 * For example, default posts term counts (for custom taxonomies) don't include
 * private / draft posts.
 *
 * @since 3.3.0
 * @access private
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function _update_term_count_on_transition_post_status( $new_status, $old_status, $post ) {
	// Update counts for the post's terms.
	foreach ( (array) get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$tt_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'tt_ids' ) );
		wp_update_term_count( $tt_ids, $taxonomy );
	}
}

/**
 * Adds any posts from the given IDs to the cache that do not already exist in cache.
 *
 * @since 3.4.0
 * @since 6.1.0 This function is no longer marked as "private".
 *
 * @see update_post_cache()
 * @see update_postmeta_cache()
 * @see update_object_term_cache()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int[] $ids               ID list.
 * @param bool  $update_term_cache Optional. Whether to update the term cache. Default true.
 * @param bool  $update_meta_cache Optional. Whether to update the meta cache. Default true.
 */
function _prime_post_caches( $ids, $update_term_cache = true, $update_meta_cache = true ) {
	global $wpdb;

	$non_cached_ids = _get_non_cached_ids( $ids, 'posts' );
	if ( ! empty( $non_cached_ids ) ) {
		$fresh_posts = $wpdb->get_results( sprintf( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN (%s)", implode( ',', $non_cached_ids ) ) );

		if ( $fresh_posts ) {
			// Despite the name, update_post_cache() expects an array rather than a single post.
			update_post_cache( $fresh_posts );
		}
	}

	if ( $update_meta_cache ) {
		update_postmeta_cache( $ids );
	}

	if ( $update_term_cache ) {
		$post_types = array_map( 'get_post_type', $ids );
		$post_types = array_unique( $post_types );
		update_object_term_cache( $ids, $post_types );
	}
}

/**
 * Prime the cache containing the parent ID of various post objects.
 *
 * @since 6.4.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int[] $ids ID list.
 */
function _prime_post_parent_id_caches( array $ids ) {
	global $wpdb;

	$ids = array_filter( $ids, '_validate_cache_id' );
	$ids = array_unique( array_map( 'intval', $ids ), SORT_NUMERIC );

	if ( empty( $ids ) ) {
		return;
	}

	$cache_keys = array();
	foreach ( $ids as $id ) {
		$cache_keys[ $id ] = 'post_parent:' . (string) $id;
	}

	$cached_data = wp_cache_get_multiple( array_values( $cache_keys ), 'posts' );

	$non_cached_ids = array();
	foreach ( $cache_keys as $id => $cache_key ) {
		if ( false === $cached_data[ $cache_key ] ) {
			$non_cached_ids[] = $id;
		}
	}

	if ( ! empty( $non_cached_ids ) ) {
		$fresh_posts = $wpdb->get_results( sprintf( "SELECT $wpdb->posts.ID, $wpdb->posts.post_parent FROM $wpdb->posts WHERE ID IN (%s)", implode( ',', $non_cached_ids ) ) );

		if ( $fresh_posts ) {
			$post_parent_data = array();
			foreach ( $fresh_posts as $fresh_post ) {
				$post_parent_data[ 'post_parent:' . (string) $fresh_post->ID ] = (int) $fresh_post->post_parent;
			}

			wp_cache_add_multiple( $post_parent_data, 'posts' );
		}
	}
}

/**
 * Adds a suffix if any trashed posts have a given slug.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since 4.5.0
 * @access private
 *
 * @param string $post_name Post slug.
 * @param int    $post_id   Optional. Post ID that should be ignored. Default 0.
 */
function wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_id = 0 ) {
	$trashed_posts_with_desired_slug = get_posts(
		array(
			'name'         => $post_name,
			'post_status'  => 'trash',
			'post_type'    => 'any',
			'nopaging'     => true,
			'post__not_in' => array( $post_id ),
		)
	);

	if ( ! empty( $trashed_posts_with_desired_slug ) ) {
		foreach ( $trashed_posts_with_desired_slug as $_post ) {
			wp_add_trashed_suffix_to_post_name_for_post( $_post );
		}
	}
}

/**
 * Adds a trashed suffix for a given post.
 *
 * Store its desired (i.e. current) slug so it can try to reclaim it
 * if the post is untrashed.
 *
 * For internal use.
 *
 * @since 4.5.0
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_Post $post The post.
 * @return string New slug for the post.
 */
function wp_add_trashed_suffix_to_post_name_for_post( $post ) {
	global $wpdb;

	$post = get_post( $post );

	if ( str_ends_with( $post->post_name, '__trashed' ) ) {
		return $post->post_name;
	}
	add_post_meta( $post->ID, '_wp_desired_post_slug', $post->post_name );
	$post_name = _truncate_post_slug( $post->post_name, 191 ) . '__trashed';
	$wpdb->update( $wpdb->posts, array( 'post_name' => $post_name ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
	return $post_name;
}

/**
 * Sets the last changed time for the 'posts' cache group.
 *
 * @since 5.0.0
 */
function wp_cache_set_posts_last_changed() {
	wp_cache_set_last_changed( 'posts' );
}

/**
 * Gets all available post MIME types for a given post type.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $type
 * @return string[] An array of MIME types.
 */
function get_available_post_mime_types( $type = 'attachment' ) {
	global $wpdb;

	/**
	 * Filters the list of available post MIME types for the given post type.
	 *
	 * @since 6.4.0
	 *
	 * @param string[]|null $mime_types An array of MIME types. Default null.
	 * @param string        $type       The post type name. Usually 'attachment' but can be any post type.
	 */
	$mime_types = apply_filters( 'pre_get_available_post_mime_types', null, $type );

	if ( ! is_array( $mime_types ) ) {
		$mime_types = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = %s AND post_mime_type != ''", $type ) );
	}

	// Remove nulls from returned $mime_types.
	return array_values( array_filter( $mime_types ) );
}

/**
 * Retrieves the path to an uploaded image file.
 *
 * Similar to `get_attached_file()` however some images may have been processed after uploading
 * to make them suitable for web use. In this case the attached "full" size file is usually replaced
 * with a scaled down version of the original image. This function always returns the path
 * to the originally uploaded image file.
 *
 * @since 5.3.0
 * @since 5.4.0 Added the `$unfiltered` parameter.
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $unfiltered Optional. Passed through to `get_attached_file()`. Default false.
 * @return string|false Path to the original image file or false if the attachment is not an image.
 */
function wp_get_original_image_path( $attachment_id, $unfiltered = false ) {
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	$image_file = get_attached_file( $attachment_id, $unfiltered );

	if ( empty( $image_meta['original_image'] ) ) {
		$original_image = $image_file;
	} else {
		$original_image = path_join( dirname( $image_file ), $image_meta['original_image'] );
	}

	/**
	 * Filters the path to the original image.
	 *
	 * @since 5.3.0
	 *
	 * @param string $original_image Path to original image file.
	 * @param int    $attachment_id  Attachment ID.
	 */
	return apply_filters( 'wp_get_original_image_path', $original_image, $attachment_id );
}

/**
 * Retrieves the URL to an original attachment image.
 *
 * Similar to `wp_get_attachment_url()` however some images may have been
 * processed after uploading. In this case this function returns the URL
 * to the originally uploaded image file.
 *
 * @since 5.3.0
 *
 * @param int $attachment_id Attachment post ID.
 * @return string|false Attachment image URL, false on error or if the attachment is not an image.
 */
function wp_get_original_image_url( $attachment_id ) {
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	$image_url = wp_get_attachment_url( $attachment_id );

	if ( ! $image_url ) {
		return false;
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );

	if ( empty( $image_meta['original_image'] ) ) {
		$original_image_url = $image_url;
	} else {
		$original_image_url = path_join( dirname( $image_url ), $image_meta['original_image'] );
	}

	/**
	 * Filters the URL to the original attachment image.
	 *
	 * @since 5.3.0
	 *
	 * @param string $original_image_url URL to original image.
	 * @param int    $attachment_id      Attachment ID.
	 */
	return apply_filters( 'wp_get_original_image_url', $original_image_url, $attachment_id );
}

/**
 * Filters callback which sets the status of an untrashed post to its previous status.
 *
 * This can be used as a callback on the `wp_untrash_post_status` filter.
 *
 * @since 5.6.0
 *
 * @param string $new_status      The new status of the post being restored.
 * @param int    $post_id         The ID of the post being restored.
 * @param string $previous_status The status of the post at the point where it was trashed.
 * @return string The new status of the post.
 */
function wp_untrash_post_set_previous_status( $new_status, $post_id, $previous_status ) {
	return $previous_status;
}

/**
 * Returns whether the post can be edited in the block editor.
 *
 * @since 5.0.0
 * @since 6.1.0 Moved to wp-includes from wp-admin.
 *
 * @param int|WP_Post $post Post ID or WP_Post object.
 * @return bool Whether the post can be edited in the block editor.
 */
function use_block_editor_for_post( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	// We're in the meta box loader, so don't use the block editor.
	if ( is_admin() && isset( $_GET['meta-box-loader'] ) ) {
		check_admin_referer( 'meta-box-loader', 'meta-box-loader-nonce' );
		return false;
	}

	$use_block_editor = use_block_editor_for_post_type( $post->post_type );

	/**
	 * Filters whether a post is able to be edited in the block editor.
	 *
	 * @since 5.0.0
	 *
	 * @param bool    $use_block_editor Whether the post can be edited or not.
	 * @param WP_Post $post             The post being checked.
	 */
	return apply_filters( 'use_block_editor_for_post', $use_block_editor, $post );
}

/**
 * Returns whether a post type is compatible with the block editor.
 *
 * The block editor depends on the REST API, and if the post type is not shown in the
 * REST API, then it won't work with the block editor.
 *
 * @since 5.0.0
 * @since 6.1.0 Moved to wp-includes from wp-admin.
 *
 * @param string $post_type The post type.
 * @return bool Whether the post type can be edited with the block editor.
 */
function use_block_editor_for_post_type( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return false;
	}

	if ( ! post_type_supports( $post_type, 'editor' ) ) {
		return false;
	}

	$post_type_object = get_post_type_object( $post_type );
	if ( $post_type_object && ! $post_type_object->show_in_rest ) {
		return false;
	}

	/**
	 * Filters whether a post is able to be edited in the block editor.
	 *
	 * @since 5.0.0
	 *
	 * @param bool   $use_block_editor  Whether the post type can be edited or not. Default true.
	 * @param string $post_type         The post type being checked.
	 */
	return apply_filters( 'use_block_editor_for_post_type', true, $post_type );
}

/**
 * Registers any additional post meta fields.
 *
 * @since 6.3.0 Adds `wp_pattern_sync_status` meta field to the wp_block post type so an unsynced option can be added.
 *
 * @link https://github.com/WordPress/gutenberg/pull/51144
 */
function wp_create_initial_post_meta() {
	register_post_meta(
		'wp_block',
		'wp_pattern_sync_status',
		array(
			'sanitize_callback' => 'sanitize_text_field',
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => array(
				'schema' => array(
					'type' => 'string',
					'enum' => array( 'partial', 'unsynced' ),
				),
			),
		)
	);
}
