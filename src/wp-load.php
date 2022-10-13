<?php
/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

spl_autoload_register(
	function( $name ) {
		static $classes;
		if ( ! $classes ) {
			$classes = array(
				/* Classes in the wp-includes/ folder. */
				'Walker_CategoryDropdown'                  => ABSPATH . WPINC . '/class-walker-category-dropdown.php',
				'Walker_Category'                          => ABSPATH . WPINC . '/class-walker-category.php',
				'Walker_Comment'                           => ABSPATH . WPINC . '/class-walker-comment.php',
				'Walker_Nav_Menu'                          => ABSPATH . WPINC . '/class-walker-nav-menu.php',
				'Walker_PageDropdown'                      => ABSPATH . WPINC . '/class-walker-page-dropdown.php',
				'Walker_Page'                              => ABSPATH . WPINC . '/class-walker-page.php',
				'WP_Admin_Bar'                             => ABSPATH . WPINC . '/class-wp-admin-bar.php',
				'WP_Ajax_Response'                         => ABSPATH . WPINC . '/class-wp-ajax-response.php',
				'WP_Application_Passwords'                 => ABSPATH . WPINC . '/class-wp-application-passwords.php',
				'WP_Block_Editor_Context'                  => ABSPATH . WPINC . '/class-wp-block-editor-context.php',
				'WP_Block_List'                            => ABSPATH . WPINC . '/class-wp-block-list.php',
				'WP_Block_Parser_Block'                    => ABSPATH . WPINC . '/class-wp-block-parser.php',
				'WP_Block_Parser_Frame'                    => ABSPATH . WPINC . '/class-wp-block-parser.php',
				'WP_Block_Parser'                          => ABSPATH . WPINC . '/class-wp-block-parser.php',
				'WP_Block_Pattern_Categories_Registry'     => ABSPATH . WPINC . '/class-wp-block-pattern-categories-registry.php', // Contains some functions as well.
				'WP_Block_Patterns_Registry'               => ABSPATH . WPINC . '/class-wp-block-patterns-registry.php', // Contains some functions as well.
				'WP_Block_Styles_Registry'                 => ABSPATH . WPINC . '/class-wp-block-styles-registry.php',
				'WP_Block_Supports'                        => ABSPATH . WPINC . '/class-wp-block-supports.php',
				'WP_Block_Template'                        => ABSPATH . WPINC . '/class-wp-block-template.php',
				'WP_Block_Type_Registry'                   => ABSPATH . WPINC . '/class-wp-block-type-registry.php',
				'WP_Block_Type'                            => ABSPATH . WPINC . '/class-wp-block-type.php',
				'WP_Block'                                 => ABSPATH . WPINC . '/class-wp-block.php',
				'WP_Comment_Query'                         => ABSPATH . WPINC . '/class-wp-comment-query.php',
				'WP_Comment'                               => ABSPATH . WPINC . '/class-wp-comment.php',
				'WP_Customize_Control'                     => ABSPATH . WPINC . '/class-wp-customize-control.php', // Includes files for other controls as well.
				'WP_Customize_Manager'                     => ABSPATH . WPINC . '/class-wp-customize-manager.php',
				'WP_Customize_Nav_Menus'                   => ABSPATH . WPINC . '/class-wp-customize-nav-menus.php',
				'WP_Customize_Panel'                       => ABSPATH . WPINC . '/class-wp-customize-panel.php', // Includes files for other panels as well.
				'WP_Customize_Section'                     => ABSPATH . WPINC . '/class-wp-customize-section.php', // Includes files for other sections as well.
				'WP_Customize_Setting'                     => ABSPATH . WPINC . '/class-wp-customize-setting.php', // Includes files for other settings as well.
				'WP_Customize_Widgets'                     => ABSPATH . WPINC . '/class-wp-customize-widgets.php',
				'WP_Date_Query'                            => ABSPATH . WPINC . '/class-wp-date-query.php',
				'WP_Dependencies'                          => ABSPATH . WPINC . '/class-wp-dependencies.php',
				'_WP_Dependency'                           => ABSPATH . WPINC . '/class-wp-dependency.php',
				'_WP_Editors'                              => ABSPATH . WPINC . '/class-wp-editor.php',
				'WP_Embed'                                 => ABSPATH . WPINC . '/class-wp-embed.php',
				'WP_Error'                                 => ABSPATH . WPINC . '/class-wp-error.php',
				'WP_Fatal_Error_Handler'                   => ABSPATH . WPINC . '/class-wp-fatal-error-handler.php',
				'WP_Feed_Cache_Transient'                  => ABSPATH . WPINC . '/class-wp-feed-cache-transient.php',
				'WP_Feed_Cache'                            => ABSPATH . WPINC . '/class-wp-feed-cache.php',
				'WP_Hook'                                  => ABSPATH . WPINC . '/class-wp-hook.php',
				'WP_Http_Cookie'                           => ABSPATH . WPINC . '/class-wp-http-cookie.php',
				'WP_Http_Curl'                             => ABSPATH . WPINC . '/class-wp-http-curl.php',
				'WP_Http_Encoding'                         => ABSPATH . WPINC . '/class-wp-http-encoding.php',
				'WP_HTTP_IXR_Client'                       => ABSPATH . WPINC . '/class-wp-http-ixr-client.php',
				'WP_HTTP_Proxy'                            => ABSPATH . WPINC . '/class-wp-http-proxy.php',
				'WP_HTTP_Requests_Hooks'                   => ABSPATH . WPINC . '/class-wp-http-requests-hooks.php',
				'WP_HTTP_Requests_Response'                => ABSPATH . WPINC . '/class-wp-http-requests-response.php',
				'WP_HTTP_Response'                         => ABSPATH . WPINC . '/class-wp-http-response.php',
				'WP_Http_Streams'                          => ABSPATH . WPINC . '/class-wp-http-streams.php',
				'WP_HTTP_Fsockopen'                        => ABSPATH . WPINC . '/class-wp-http-streams.php',
				'WP_Http'                                  => ABSPATH . WPINC . '/class-wp-http.php',
				'WP_Image_Editor_GD'                       => ABSPATH . WPINC . '/class-wp-image-editor-gd.php',
				'WP_Image_Editor_Imagick'                  => ABSPATH . WPINC . '/class-wp-image-editor-imagick.php',
				'WP_Image_Editor'                          => ABSPATH . WPINC . '/class-wp-image-editor.php',
				'WP_List_Util'                             => ABSPATH . WPINC . '/class-wp-list-util.php',
				'WP_Locale_Switcher'                       => ABSPATH . WPINC . '/class-wp-locale-switcher.php',
				'WP_Locale'                                => ABSPATH . WPINC . '/class-wp-locale.php',
				'WP_MatchesMapRegex'                       => ABSPATH . WPINC . '/class-wp-matchesmapregex.php',
				'WP_Meta_Query'                            => ABSPATH . WPINC . '/class-wp-meta-query.php',
				'WP_Metadata_Lazyloader'                   => ABSPATH . WPINC . '/class-wp-metadata-lazyloader.php',
				'WP_Network_Query'                         => ABSPATH . WPINC . '/class-wp-network-query.php',
				'WP_Network'                               => ABSPATH . WPINC . '/class-wp-network.php',
				'WP_Object_Cache'                          => ABSPATH . WPINC . '/class-wp-object-cache.php',
				'WP_oEmbed_Controller'                     => ABSPATH . WPINC . '/class-wp-oembed-controller.php',
				'WP_oEmbed'                                => ABSPATH . WPINC . '/class-wp-oembed.php',
				'WP_Paused_Extensions_Storage'             => ABSPATH . WPINC . '/class-wp-paused-extensions-storage.php',
				'WP_Post_Type'                             => ABSPATH . WPINC . '/class-wp-post-type.php',
				'WP_Post'                                  => ABSPATH . WPINC . '/class-wp-post.php',
				'WP_Query'                                 => ABSPATH . WPINC . '/class-wp-query.php',
				'WP_Recovery_Mode_Cookie_Service'          => ABSPATH . WPINC . '/class-wp-recovery-mode-cookie-service.php',
				'WP_Recovery_Mode_Email_Service'           => ABSPATH . WPINC . '/class-wp-recovery-mode-email-service.php',
				'WP_Recovery_Mode_Key_Service'             => ABSPATH . WPINC . '/class-wp-recovery-mode-key-service.php',
				'WP_Recovery_Mode_Link_Service'            => ABSPATH . WPINC . '/class-wp-recovery-mode-link-service.php',
				'WP_Recovery_Mode'                         => ABSPATH . WPINC . '/class-wp-recovery-mode.php',
				'WP_Rewrite'                               => ABSPATH . WPINC . '/class-wp-rewrite.php',
				'WP_Role'                                  => ABSPATH . WPINC . '/class-wp-role.php',
				'WP_Roles'                                 => ABSPATH . WPINC . '/class-wp-roles.php',
				'WP_Scripts'                               => ABSPATH . WPINC . '/class-wp-scripts.php',
				'WP_Session_Tokens'                        => ABSPATH . WPINC . '/class-wp-session-tokens.php',
				'WP_SimplePie_File'                        => ABSPATH . WPINC . '/class-wp-simplepie-file.php',
				'WP_SimplePie_Sanitize_KSES'               => ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php',
				'WP_Site_Query'                            => ABSPATH . WPINC . '/class-wp-site-query.php',
				'WP_Site'                                  => ABSPATH . WPINC . '/class-wp-site.php',
				'WP_Styles'                                => ABSPATH . WPINC . '/class-wp-styles.php',
				'WP_Tax_Query'                             => ABSPATH . WPINC . '/class-wp-tax-query.php',
				'WP_Taxonomy'                              => ABSPATH . WPINC . '/class-wp-taxonomy.php',
				'WP_Term_Query'                            => ABSPATH . WPINC . '/class-wp-term-query.php',
				'WP_Term'                                  => ABSPATH . WPINC . '/class-wp-term.php',
				'WP_Text_Diff_Renderer_inline'             => ABSPATH . WPINC . '/class-wp-text-diff-renderer-inline.php',
				'WP_Text_Diff_Renderer_Table'              => ABSPATH . WPINC . '/class-wp-text-diff-renderer-table.php',
				'WP_Textdomain_Registry'                   => ABSPATH . WPINC . '/class-wp-textdomain-registry.php',
				'WP_Theme_JSON_Data'                       => ABSPATH . WPINC . '/class-wp-theme-json-data.php',
				'WP_Theme_JSON_Resolver'                   => ABSPATH . WPINC . '/class-wp-theme-json-resolver.php',
				'WP_Theme_JSON_Schema'                     => ABSPATH . WPINC . '/class-wp-theme-json-schema.php',
				'WP_Theme_JSON'                            => ABSPATH . WPINC . '/class-wp-theme-json.php',
				'WP_Theme'                                 => ABSPATH . WPINC . '/class-wp-theme.php',
				'WP_User_Meta_Session_Tokens'              => ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php',
				'WP_User_Query'                            => ABSPATH . WPINC . '/class-wp-user-query.php',
				'WP_User_Request'                          => ABSPATH . WPINC . '/class-wp-user-request.php',
				'WP_User'                                  => ABSPATH . WPINC . '/class-wp-user.php',
				'Walker'                                   => ABSPATH . WPINC . '/class-wp-walker.php',
				'WP_Widget_Factory'                        => ABSPATH . WPINC . '/class-wp-widget-factory.php',
				'WP_Widget'                                => ABSPATH . WPINC . '/class-wp-widget.php',
				'wp_xmlrpc_server'                         => ABSPATH . WPINC . '/class-wp-xmlrpc-server.php',
				'WP'                                       => ABSPATH . WPINC . '/class-wp.php',
				'wpdb'                                     => ABSPATH . WPINC . '/class-wpdb.php', // Defines some constants.

				/* Classes in the wp-includes/customize/ folder. */
				'WP_Customize_Background_Image_Control'    => ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php',
				'WP_Customize_Background_Image_Setting'    => ABSPATH . WPINC . '/customize/class-wp-customize-background-image-setting.php',
				'WP_Customize_Background_Position_Control' => ABSPATH . WPINC . '/customize/class-wp-customize-background-position-control.php',
				'WP_Customize_Code_Editor_Control'         => ABSPATH . WPINC . '/customize/class-wp-customize-code-editor-control.php',
				'WP_Customize_Color_Control'               => ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php',
				'WP_Customize_Cropped_Image_Control'       => ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php',
				'WP_Customize_Custom_CSS_Setting'          => ABSPATH . WPINC . '/customize/class-wp-customize-custom-css-setting.php',
				'WP_Customize_Date_Time_Control'           => ABSPATH . WPINC . '/customize/class-wp-customize-date-time-control.php',
				'WP_Customize_Filter_Setting'              => ABSPATH . WPINC . '/customize/class-wp-customize-filter-setting.php',
				'WP_Customize_Header_Image_Control'        => ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php',
				'WP_Customize_Header_Image_Setting'        => ABSPATH . WPINC . '/customize/class-wp-customize-header-image-setting.php',
				'WP_Customize_Image_Control'               => ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php',
				'WP_Customize_Media_Control'               => ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php',
				'WP_Customize_Nav_Menu_Auto_Add_Control'   => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php',
				'WP_Customize_Nav_Menu_Control'            => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php',
				'WP_Customize_Nav_Menu_Item_Control'       => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php',
				'WP_Customize_Nav_Menu_Item_Setting'       => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-setting.php',
				'WP_Customize_Nav_Menu_Location_Control'   => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php',
				'WP_Customize_Nav_Menu_Locations_Control'  => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-locations-control.php',
				'WP_Customize_Nav_Menu_Name_Control'       => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php',
				'WP_Customize_Nav_Menu_Section'            => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php',
				'WP_Customize_Nav_Menu_Setting'            => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-setting.php',
				'WP_Customize_Nav_Menus_Panel'             => ABSPATH . WPINC . '/customize/class-wp-customize-nav-menus-panel.php',
				'WP_Customize_New_Menu_Control'            => ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-control.php',
				'WP_Customize_New_Menu_Section'            => ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-section.php',
				'WP_Customize_Partial'                     => ABSPATH . WPINC . '/customize/class-wp-customize-partial.php',
				'WP_Customize_Selective_Refresh'           => ABSPATH . WPINC . '/customize/class-wp-customize-selective-refresh.php',
				'WP_Customize_Sidebar_Section'             => ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php',
				'WP_Customize_Site_Icon_Control'           => ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php',
				'WP_Customize_Theme_Control'               => ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php',
				'WP_Customize_Themes_Panel'                => ABSPATH . WPINC . '/customize/class-wp-customize-themes-panel.php',
				'WP_Customize_Themes_Section'              => ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php',
				'WP_Customize_Upload_Control'              => ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php',
				'WP_Sidebar_Block_Editor_Control'          => ABSPATH . WPINC . '/customize/class-wp-sidebar-block-editor-control.php',
				'WP_Widget_Area_Customize_Control'         => ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php',
				'WP_Widget_Form_Customize_Control'         => ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php',

				/* Classes in the wp-includes/IXR folder. */
				'IXR_Base64'                               => ABSPATH . WPINC . '/IXR/class-IXR-base64.php',
				'IXR_Client'                               => ABSPATH . WPINC . '/IXR/class-IXR-client.php',
				'IXR_ClientMulticall'                      => ABSPATH . WPINC . '/IXR/class-IXR-clientmulticall.php',
				'IXR_Date'                                 => ABSPATH . WPINC . '/IXR/class-IXR-date.php',
				'IXR_Error'                                => ABSPATH . WPINC . '/IXR/class-IXR-error.php',
				'IXR_IntrospectionServer'                  => ABSPATH . WPINC . '/IXR/class-IXR-introspectionserver.php',
				'IXR_Message'                              => ABSPATH . WPINC . '/IXR/class-IXR-message.php',
				'IXR_Request'                              => ABSPATH . WPINC . '/IXR/class-IXR-request.php',
				'IXR_Server'                               => ABSPATH . WPINC . '/IXR/class-IXR-server.php',
				'IXR_Value'                                => ABSPATH . WPINC . '/IXR/class-IXR-value.php',

				/* Classes in the wp-includes/pomo folder. */
				'Translation_Entry'                        => ABSPATH . WPINC . '/pomo/entry.php',
				'MO'                                       => ABSPATH . WPINC . '/pomo/mo.php',
				'Plural_Forms'                             => ABSPATH . WPINC . '/pomo/plural-forms.php',
				'PO'                                       => ABSPATH . WPINC . '/pomo/po.php',
				'POMO_Reader'                              => ABSPATH . WPINC . '/pomo/streams.php',
				'POMO_FileReader'                          => ABSPATH . WPINC . '/pomo/streams.php',
				'POMO_StringReader'                        => ABSPATH . WPINC . '/pomo/streams.php',
				'POMO_CachedFileReader'                    => ABSPATH . WPINC . '/pomo/streams.php',
				'POMO_CachedIntFileReader'                 => ABSPATH . WPINC . '/pomo/streams.php',
				'Translations'                             => ABSPATH . WPINC . '/pomo/translations.php',
				'Gettext_Translations'                     => ABSPATH . WPINC . '/pomo/translations.php',
				'NOOP_Translations'                        => ABSPATH . WPINC . '/pomo/translations.php',

				/* Classes in the wp-includes/rest-api folder. */
				'WP_REST_Application_Passwords_Controller' => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-application-passwords-controller.php',
				'WP_REST_Attachments_Controller'           => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-attachments-controller.php',
				'WP_REST_Autosaves_Controller'             => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
				'WP_REST_Block_Directory_Controller'       => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
				'WP_REST_Block_Pattern_Categories_Controller' => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-pattern-categories-controller.php',
				'WP_REST_Block_Patterns_Controller'        => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-patterns-controller.php',
				'WP_REST_Block_Renderer_Controller'        => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-renderer-controller.php',
				'WP_REST_Block_Types_Controller'           => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-types-controller.php',
				'WP_REST_Blocks_Controller'                => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-blocks-controller.php',
				'WP_REST_Comments_Controller'              => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-comments-controller.php',
				'WP_REST_Controller'                       => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php',
				'WP_REST_Edit_Site_Export_Controller'      => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-edit-site-export-controller.php',
				'WP_REST_Global_Styles_Controller'         => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
				'WP_REST_Menu_Items_Controller'            => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menu-items-controller.php',
				'WP_REST_Menu_Locations_Controller'        => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menu-locations-controller.php',
				'WP_REST_Menus_Controller'                 => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menus-controller.php',
				'WP_REST_Pattern_Directory_Controller'     => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-pattern-directory-controller.php',
				'WP_REST_Plugins_Controller'               => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-plugins-controller.php',
				'WP_REST_Post_Statuses_Controller'         => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-statuses-controller.php',
				'WP_REST_Post_Types_Controller'            => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-types-controller.php',
				'WP_REST_Posts_Controller'                 => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-posts-controller.php',
				'WP_REST_Revisions_Controller'             => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-revisions-controller.php',
				'WP_REST_Search_Controller'                => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-search-controller.php',
				'WP_REST_Settings_Controller'              => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-settings-controller.php',
				'WP_REST_Sidebars_Controller'              => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-sidebars-controller.php',
				'WP_REST_Site_Health_Controller'           => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-site-health-controller.php',
				'WP_REST_Taxonomies_Controller'            => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-taxonomies-controller.php',
				'WP_REST_Templates_Controller'             => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-templates-controller.php',
				'WP_REST_Terms_Controller'                 => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-terms-controller.php',
				'WP_REST_Themes_Controller'                => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-themes-controller.php',
				'WP_REST_URL_Details_Controller'           => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-url-details-controller.php',
				'WP_REST_Users_Controller'                 => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-users-controller.php',
				'WP_REST_Widget_Types_Controller'          => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-widget-types-controller.php',
				'WP_REST_Widgets_Controller'               => ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-widgets-controller.php',
				'WP_REST_Comment_Meta_Fields'              => ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-comment-meta-fields.php',
				'WP_REST_Meta_Fields'                      => ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-meta-fields.php',
				'WP_REST_Post_Meta_Fields'                 => ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-post-meta-fields.php',
				'WP_REST_Term_Meta_Fields'                 => ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-term-meta-fields.php',
				'WP_REST_User_Meta_Fields'                 => ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-user-meta-fields.php',
				'WP_REST_Post_Format_Search_Handler'       => ABSPATH . WPINC . '/rest-api/search/class-wp-rest-post-format-search-handler.php',
				'WP_REST_Post_Search_Handler'              => ABSPATH . WPINC . '/rest-api/search/class-wp-rest-post-search-handler.php',
				'WP_REST_Search_Handler'                   => ABSPATH . WPINC . '/rest-api/search/class-wp-rest-search-handler.php',
				'WP_REST_Term_Search_Handler'              => ABSPATH . WPINC . '/rest-api/search/class-wp-rest-term-search-handler.php',
				'WP_REST_Request'                          => ABSPATH . WPINC . '/rest-api/class-wp-rest-request.php',
				'WP_REST_Response'                         => ABSPATH . WPINC . '/rest-api/class-wp-rest-response.php',
				'WP_REST_Server'                           => ABSPATH . WPINC . '/rest-api/class-wp-rest-server.php',

				/* Classes in wp-includes/sitemaps. */
				'WP_Sitemaps_Posts'                        => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-posts.php',
				'WP_Sitemaps_Taxonomies'                   => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-taxonomies.php',
				'WP_Sitemaps_Users'                        => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-users.php',
				'WP_Sitemaps_Index'                        => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-index.php',
				'WP_Sitemaps_Provider'                     => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-provider.php',
				'WP_Sitemaps_Registry'                     => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-registry.php',
				'WP_Sitemaps_Renderer'                     => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-renderer.php',
				'WP_Sitemaps_Stylesheet'                   => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-stylesheet.php',
				'WP_Sitemaps'                              => ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps.php',

				/* Classes in wp-includes/style-engine. */
				'WP_Style_Engine_CSS_Declarations'         => ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-declarations.php',
				'WP_Style_Engine_CSS_Rule'                 => ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-rule.php',
				'WP_Style_Engine_CSS_Rules_Store'          => ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-rules-store.php',
				'WP_Style_Engine_Processor'                => ABSPATH . WPINC . '/style-engine/class-wp-style-engine-processor.php',
				'WP_Style_Engine'                          => ABSPATH . WPINC . '/style-engine/class-wp-style-engine.php',

				/* Classes in wp-includes/widgets. */
				'WP_Nav_Menu_Widget'                       => ABSPATH . WPINC . '/widgets/class-wp-nav-menu-widget.php',
				'WP_Widget_Archives'                       => ABSPATH . WPINC . '/widgets/class-wp-widget-archives.php',
				'WP_Widget_Block'                          => ABSPATH . WPINC . '/widgets/class-wp-widget-block.php',
				'WP_Widget_Calendar'                       => ABSPATH . WPINC . '/widgets/class-wp-widget-calendar.php',
				'WP_Widget_Categories'                     => ABSPATH . WPINC . '/widgets/class-wp-widget-categories.php',
				'WP_Widget_Custom_HTML'                    => ABSPATH . WPINC . '/widgets/class-wp-widget-custom-html.php',
				'WP_Widget_Links'                          => ABSPATH . WPINC . '/widgets/class-wp-widget-links.php',
				'WP_Widget_Media_Audio'                    => ABSPATH . WPINC . '/widgets/class-wp-widget-media-audio.php',
				'WP_Widget_Media_Gallery'                  => ABSPATH . WPINC . '/widgets/class-wp-widget-media-gallery.php',
				'WP_Widget_Media_Image'                    => ABSPATH . WPINC . '/widgets/class-wp-widget-media-image.php',
				'WP_Widget_Media_Video'                    => ABSPATH . WPINC . '/widgets/class-wp-widget-media-video.php',
				'WP_Widget_Media'                          => ABSPATH . WPINC . '/widgets/class-wp-widget-media.php',
				'WP_Widget_Meta'                           => ABSPATH . WPINC . '/widgets/class-wp-widget-meta.php',
				'WP_Widget_Pages'                          => ABSPATH . WPINC . '/widgets/class-wp-widget-pages.php',
				'WP_Widget_Recent_Comments'                => ABSPATH . WPINC . '/widgets/class-wp-widget-recent-comments.php',
				'WP_Widget_Recent_Posts'                   => ABSPATH . WPINC . '/widgets/class-wp-widget-recent-posts.php',
				'WP_Widget_RSS'                            => ABSPATH . WPINC . '/widgets/class-wp-widget-rss.php',
				'WP_Widget_Search'                         => ABSPATH . WPINC . '/widgets/class-wp-widget-search.php',
				'WP_Widget_Tag_Cloud'                      => ABSPATH . WPINC . '/widgets/class-wp-widget-tag-cloud.php',
				'WP_Widget_Text'                           => ABSPATH . WPINC . '/widgets/class-wp-widget-text.php',

				/* Classes in wp-admin/includes. */
				'Automatic_Upgrader_Skin'                  => ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php',
				'Bulk_Plugin_Upgrader_Skin'                => ABSPATH . 'wp-admin/includes/class-bulk-plugin-upgrader-skin.php',
				'Bulk_Theme_Upgrader_Skin'                 => ABSPATH . 'wp-admin/includes/class-bulk-theme-upgrader-skin.php',
				'Bulk_Upgrader_Skin'                       => ABSPATH . 'wp-admin/includes/class-bulk-upgrader-skin.php',
				'Core_Upgrader'                            => ABSPATH . 'wp-admin/includes/class-core-upgrader.php',
				'Custom_Background'                        => ABSPATH . 'wp-admin/includes/class-custom-background.php',
				'Custom_Image_Header'                      => ABSPATH . 'wp-admin/includes/class-custom-image-header.php',
				'File_Upload_Upgrader'                     => ABSPATH . 'wp-admin/includes/class-file-upload-upgrader.php',
				'ftp_pure'                                 => ABSPATH . 'wp-admin/includes/class-ftp-pure.php',
				'ftp_sockets'                              => ABSPATH . 'wp-admin/includes/class-ftp-sockets.php',
				'Language_Pack_Upgrader_Skin'              => ABSPATH . 'wp-admin/includes/class-language-pack-upgrader-skin.php',
				'Language_Pack_Upgrader'                   => ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php',
				'Plugin_Installer_Skin'                    => ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php',
				'Plugin_Upgrader_Skin'                     => ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php',
				'Plugin_Upgrader'                          => ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php',
				'Theme_Installer_Skin'                     => ABSPATH . 'wp-admin/includes/class-theme-installer-skin.php',
				'Theme_Upgrader_Skin'                      => ABSPATH . 'wp-admin/includes/class-theme-upgrader-skin.php',
				'Theme_Upgrader'                           => ABSPATH . 'wp-admin/includes/class-theme-upgrader.php',
				'Walker_Category_Checklist'                => ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php',
				'Walker_Nav_Menu_Checklist'                => ABSPATH . 'wp-admin/includes/class-walker-nav-menu-checklist.php',
				'Walker_Nav_Menu_Edit'                     => ABSPATH . 'wp-admin/includes/class-walker-nav-menu-edit.php',
				'WP_Ajax_Upgrader_Skin'                    => ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php',
				'WP_Application_Passwords_List_Table'      => ABSPATH . 'wp-admin/includes/class-wp-application-passwords-list-table.php',
				'WP_Automatic_Updater'                     => ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php',
				'WP_Comments_List_Table'                   => ABSPATH . 'wp-admin/includes/class-wp-comments-list-table.php',
				'WP_Community_Events'                      => ABSPATH . 'wp-admin/includes/class-wp-community-events.php',
				'WP_Debug_Data'                            => ABSPATH . 'wp-admin/includes/class-wp-debug-data.php',
				'WP_Filesystem_Base'                       => ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php',
				'WP_Filesystem_Direct'                     => ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php',
				'WP_Filesystem_FTPext'                     => ABSPATH . 'wp-admin/includes/class-wp-filesystem-ftpext.php',
				'WP_Filesystem_ftpsockets'                 => ABSPATH . 'wp-admin/includes/class-wp-filesystem-ftpsockets.php',
				'WP_Filesystem_SSH2'                       => ABSPATH . 'wp-admin/includes/class-wp-filesystem-ssh2.php',
				'WP_Importer'                              => ABSPATH . 'wp-admin/includes/class-wp-importer.php', // Contains some additional functions.
				'WP_Internal_Pointers'                     => ABSPATH . 'wp-admin/includes/class-wp-internal-pointers.php',
				'WP_Links_List_Table'                      => ABSPATH . 'wp-admin/includes/class-wp-links-list-table.php',
				'_WP_List_Table_Compat'                    => ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php',
				'WP_List_Table'                            => ABSPATH . 'wp-admin/includes/class-wp-list-table.php',
				'WP_Media_List_Table'                      => ABSPATH . 'wp-admin/includes/class-wp-media-list-table.php',
				'WP_MS_Sites_List_Table'                   => ABSPATH . 'wp-admin/includes/class-wp-ms-sites-list-table.php',
				'WP_MS_Themes_List_Table'                  => ABSPATH . 'wp-admin/includes/class-wp-ms-themes-list-table.php',
				'WP_MS_Users_List_Table'                   => ABSPATH . 'wp-admin/includes/class-wp-ms-users-list-table.php',
				'WP_Plugin_Install_List_Table'             => ABSPATH . 'wp-admin/includes/class-wp-plugin-install-list-table.php',
				'WP_Plugins_List_Table'                    => ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php',
				'WP_Post_Comments_List_Table'              => ABSPATH . 'wp-admin/includes/class-wp-post-comments-list-table.php',
				'WP_Posts_List_Table'                      => ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php',
				'WP_Privacy_Data_Export_Requests_List_Table' => ABSPATH . 'wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php',
				'WP_Privacy_Data_Removal_Requests_List_Table' => ABSPATH . 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php',
				'WP_Privacy_Policy_Content'                => ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php',
				'WP_Privacy_Requests_Table'                => ABSPATH . 'wp-admin/includes/class-wp-privacy-requests-table.php',
				'WP_Screen'                                => ABSPATH . 'wp-admin/includes/class-wp-screen.php',
				'WP_Site_Health_Auto_Updates'              => ABSPATH . 'wp-admin/includes/class-wp-site-health-auto-updates.php',
				'WP_Site_Health'                           => ABSPATH . 'wp-admin/includes/class-wp-site-health.php',
				'WP_Site_Icon'                             => ABSPATH . 'wp-admin/includes/class-wp-site-icon.php',
				'WP_Terms_List_Table'                      => ABSPATH . 'wp-admin/includes/class-wp-terms-list-table.php',
				'WP_Theme_Install_List_Table'              => ABSPATH . 'wp-admin/includes/class-wp-theme-install-list-table.php',
				'WP_Themes_List_Table'                     => ABSPATH . 'wp-admin/includes/class-wp-themes-list-table.php',
				'WP_Upgrader_Skin'                         => ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php',
				'WP_Upgrader'                              => ABSPATH . 'wp-admin/includes/class-wp-upgrader.php', // Includes some more files.
				'WP_Users_List_Table'                      => ABSPATH . 'wp-admin/includes/class-wp-users-list-table.php',
				'WP_User_Search'                           => ABSPATH . 'wp-admin/includes/deprecated.php',
				'WP_Privacy_Data_Export_Requests_Table'    => ABSPATH . 'wp-admin/includes/deprecated.php',
				'WP_Privacy_Data_Removal_Requests_Table'   => ABSPATH . 'wp-admin/includes/deprecated.php',
			);
		}

		if ( isset( $classes[ $name ] ) ) {
			require_once $classes[ $name ];
			return;
		}
	}
);

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Initialize error reporting to a known set of levels.
	 *
	 * This will be adapted in wp_debug_mode() located in wp-includes/load.php based on WP_DEBUG.
	 * @see https://www.php.net/manual/en/errorfunc.constants.php List of known error levels.
	 */
	error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
}

/*
 * If wp-config.php exists in the WordPress root, or if it exists in the root and wp-settings.php
 * doesn't, load wp-config.php. The secondary check for wp-settings.php has the added benefit
 * of avoiding cases where the current directory is a nested installation, e.g. / is WordPress(a)
 * and /blog/ is WordPress(b).
 *
 * If neither set of conditions is true, initiate loading the setup process.
 */
if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

	/** The config file resides in ABSPATH */
	require_once ABSPATH . 'wp-config.php';

} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

	/** The config file resides one level above ABSPATH but is not part of another installation */
	require_once dirname( ABSPATH ) . '/wp-config.php';

} else {

	// A config file doesn't exist.

	define( 'WPINC', 'wp-includes' );
	require_once ABSPATH . WPINC . '/version.php';
	require_once ABSPATH . WPINC . '/compat.php';
	require_once ABSPATH . WPINC . '/load.php';

	// Check for the required PHP version and for the MySQL extension or a database drop-in.
	wp_check_php_mysql_versions();

	// Standardize $_SERVER variables across setups.
	wp_fix_server_vars();

	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	require_once ABSPATH . WPINC . '/functions.php';

	$path = wp_guess_url() . '/wp-admin/setup-config.php';

	// Redirect to setup-config.php.
	if ( ! str_contains( $_SERVER['REQUEST_URI'], 'setup-config' ) ) {
		header( 'Location: ' . $path );
		exit;
	}

	wp_load_translations_early();

	// Die with an error message.
	$die = '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "There doesn't seem to be a %s file. It is needed before the installation can continue." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: 1: Documentation URL, 2: wp-config.php */
		__( 'Need more help? <a href="%1$s">Read the support article on %2$s</a>.' ),
		__( 'https://wordpress.org/documentation/article/editing-wp-config-php/' ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "You can create a %s file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p><a href="' . $path . '" class="button button-large">' . __( 'Create a Configuration File' ) . '</a></p>';

	wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
}
