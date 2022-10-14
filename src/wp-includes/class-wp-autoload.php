<?php
/**
 * Autoloader for WordPress classes.
 *
 * Include this file if you'd like to avoid having to create your own autoloader.
 *
 * @package WordPress
 */

/**
 * Autoloader.
 */
final class WP_Autoload {

	/**
	 * An array of classes and their respective paths.
	 *
	 * @static
	 * @access private
	 *
	 * @var array
	 */
	private static $classes = array(
		/* Classes in the wp-includes/ folder. */
		'Walker_CategoryDropdown'                     => 'wp-includes/class-walker-category-dropdown.php',
		'Walker_Category'                             => 'wp-includes/class-walker-category.php',
		'Walker_Comment'                              => 'wp-includes/class-walker-comment.php',
		'Walker_Nav_Menu'                             => 'wp-includes/class-walker-nav-menu.php',
		'Walker_PageDropdown'                         => 'wp-includes/class-walker-page-dropdown.php',
		'Walker_Page'                                 => 'wp-includes/class-walker-page.php',
		'WP_Admin_Bar'                                => 'wp-includes/class-wp-admin-bar.php',
		'WP_Ajax_Response'                            => 'wp-includes/class-wp-ajax-response.php',
		'WP_Application_Passwords'                    => 'wp-includes/class-wp-application-passwords.php',
		'WP_Block_Editor_Context'                     => 'wp-includes/class-wp-block-editor-context.php',
		'WP_Block_List'                               => 'wp-includes/class-wp-block-list.php',
		'WP_Block_Parser_Block'                       => 'wp-includes/class-wp-block-parser.php',
		'WP_Block_Parser_Frame'                       => 'wp-includes/class-wp-block-parser.php',
		'WP_Block_Parser'                             => 'wp-includes/class-wp-block-parser.php',
		'WP_Block_Pattern_Categories_Registry'        => 'wp-includes/class-wp-block-pattern-categories-registry.php',
		'WP_Block_Patterns_Registry'                  => 'wp-includes/class-wp-block-patterns-registry.php',
		'WP_Block_Styles_Registry'                    => 'wp-includes/class-wp-block-styles-registry.php',
		'WP_Block_Supports'                           => 'wp-includes/class-wp-block-supports.php',
		'WP_Block_Template'                           => 'wp-includes/class-wp-block-template.php',
		'WP_Block_Type_Registry'                      => 'wp-includes/class-wp-block-type-registry.php',
		'WP_Block_Type'                               => 'wp-includes/class-wp-block-type.php',
		'WP_Block'                                    => 'wp-includes/class-wp-block.php',
		'WP_Comment_Query'                            => 'wp-includes/class-wp-comment-query.php',
		'WP_Comment'                                  => 'wp-includes/class-wp-comment.php',
		'WP_Customize_Control'                        => 'wp-includes/class-wp-customize-control.php',
		'WP_Customize_Manager'                        => 'wp-includes/class-wp-customize-manager.php',
		'WP_Customize_Nav_Menus'                      => 'wp-includes/class-wp-customize-nav-menus.php',
		'WP_Customize_Panel'                          => 'wp-includes/class-wp-customize-panel.php',
		'WP_Customize_Section'                        => 'wp-includes/class-wp-customize-section.php',
		'WP_Customize_Setting'                        => 'wp-includes/class-wp-customize-setting.php',
		'WP_Customize_Widgets'                        => 'wp-includes/class-wp-customize-widgets.php',
		'WP_Date_Query'                               => 'wp-includes/class-wp-date-query.php',
		'WP_Dependencies'                             => 'wp-includes/class-wp-dependencies.php',
		'_WP_Dependency'                              => 'wp-includes/class-wp-dependency.php',
		'_WP_Editors'                                 => 'wp-includes/class-wp-editor.php',
		'WP_Embed'                                    => 'wp-includes/class-wp-embed.php',
		'WP_Error'                                    => 'wp-includes/class-wp-error.php',
		'WP_Fatal_Error_Handler'                      => 'wp-includes/class-wp-fatal-error-handler.php',
		'WP_Feed_Cache_Transient'                     => 'wp-includes/class-wp-feed-cache-transient.php',
		'WP_Feed_Cache'                               => 'wp-includes/class-wp-feed-cache.php',
		'WP_Hook'                                     => 'wp-includes/class-wp-hook.php',
		'WP_Http_Cookie'                              => 'wp-includes/class-wp-http-cookie.php',
		'WP_Http_Curl'                                => 'wp-includes/class-wp-http-curl.php',
		'WP_Http_Encoding'                            => 'wp-includes/class-wp-http-encoding.php',
		'WP_HTTP_IXR_Client'                          => 'wp-includes/class-wp-http-ixr-client.php',
		'WP_HTTP_Proxy'                               => 'wp-includes/class-wp-http-proxy.php',
		'WP_HTTP_Requests_Hooks'                      => 'wp-includes/class-wp-http-requests-hooks.php',
		'WP_HTTP_Requests_Response'                   => 'wp-includes/class-wp-http-requests-response.php',
		'WP_HTTP_Response'                            => 'wp-includes/class-wp-http-response.php',
		'WP_Http_Streams'                             => 'wp-includes/class-wp-http-streams.php',
		'WP_HTTP_Fsockopen'                           => 'wp-includes/class-wp-http-streams.php',
		'WP_Http'                                     => 'wp-includes/class-wp-http.php',
		'WP_Image_Editor_GD'                          => 'wp-includes/class-wp-image-editor-gd.php',
		'WP_Image_Editor_Imagick'                     => 'wp-includes/class-wp-image-editor-imagick.php',
		'WP_Image_Editor'                             => 'wp-includes/class-wp-image-editor.php',
		'WP_List_Util'                                => 'wp-includes/class-wp-list-util.php',
		'WP_Locale_Switcher'                          => 'wp-includes/class-wp-locale-switcher.php',
		'WP_Locale'                                   => 'wp-includes/class-wp-locale.php',
		'WP_MatchesMapRegex'                          => 'wp-includes/class-wp-matchesmapregex.php',
		'WP_Meta_Query'                               => 'wp-includes/class-wp-meta-query.php',
		'WP_Metadata_Lazyloader'                      => 'wp-includes/class-wp-metadata-lazyloader.php',
		'WP_Network_Query'                            => 'wp-includes/class-wp-network-query.php',
		'WP_Network'                                  => 'wp-includes/class-wp-network.php',
		'WP_Object_Cache'                             => 'wp-includes/class-wp-object-cache.php',
		'WP_oEmbed_Controller'                        => 'wp-includes/class-wp-oembed-controller.php',
		'WP_oEmbed'                                   => 'wp-includes/class-wp-oembed.php',
		'WP_Paused_Extensions_Storage'                => 'wp-includes/class-wp-paused-extensions-storage.php',
		'WP_Post_Type'                                => 'wp-includes/class-wp-post-type.php',
		'WP_Post'                                     => 'wp-includes/class-wp-post.php',
		'WP_Query'                                    => 'wp-includes/class-wp-query.php',
		'WP_Recovery_Mode_Cookie_Service'             => 'wp-includes/class-wp-recovery-mode-cookie-service.php',
		'WP_Recovery_Mode_Email_Service'              => 'wp-includes/class-wp-recovery-mode-email-service.php',
		'WP_Recovery_Mode_Key_Service'                => 'wp-includes/class-wp-recovery-mode-key-service.php',
		'WP_Recovery_Mode_Link_Service'               => 'wp-includes/class-wp-recovery-mode-link-service.php',
		'WP_Recovery_Mode'                            => 'wp-includes/class-wp-recovery-mode.php',
		'WP_Rewrite'                                  => 'wp-includes/class-wp-rewrite.php',
		'WP_Role'                                     => 'wp-includes/class-wp-role.php',
		'WP_Roles'                                    => 'wp-includes/class-wp-roles.php',
		'WP_Scripts'                                  => 'wp-includes/class-wp-scripts.php',
		'WP_Session_Tokens'                           => 'wp-includes/class-wp-session-tokens.php',
		'WP_SimplePie_File'                           => 'wp-includes/class-wp-simplepie-file.php',
		'WP_SimplePie_Sanitize_KSES'                  => 'wp-includes/class-wp-simplepie-sanitize-kses.php',
		'WP_Site_Query'                               => 'wp-includes/class-wp-site-query.php',
		'WP_Site'                                     => 'wp-includes/class-wp-site.php',
		'WP_Styles'                                   => 'wp-includes/class-wp-styles.php',
		'WP_Tax_Query'                                => 'wp-includes/class-wp-tax-query.php',
		'WP_Taxonomy'                                 => 'wp-includes/class-wp-taxonomy.php',
		'WP_Term_Query'                               => 'wp-includes/class-wp-term-query.php',
		'WP_Term'                                     => 'wp-includes/class-wp-term.php',
		'WP_Text_Diff_Renderer_inline'                => 'wp-includes/class-wp-text-diff-renderer-inline.php',
		'WP_Text_Diff_Renderer_Table'                 => 'wp-includes/class-wp-text-diff-renderer-table.php',
		'WP_Textdomain_Registry'                      => 'wp-includes/class-wp-textdomain-registry.php',
		'WP_Theme_JSON_Data'                          => 'wp-includes/class-wp-theme-json-data.php',
		'WP_Theme_JSON_Resolver'                      => 'wp-includes/class-wp-theme-json-resolver.php',
		'WP_Theme_JSON_Schema'                        => 'wp-includes/class-wp-theme-json-schema.php',
		'WP_Theme_JSON'                               => 'wp-includes/class-wp-theme-json.php',
		'WP_Theme'                                    => 'wp-includes/class-wp-theme.php',
		'WP_User_Meta_Session_Tokens'                 => 'wp-includes/class-wp-user-meta-session-tokens.php',
		'WP_User_Query'                               => 'wp-includes/class-wp-user-query.php',
		'WP_User_Request'                             => 'wp-includes/class-wp-user-request.php',
		'WP_User'                                     => 'wp-includes/class-wp-user.php',
		'Walker'                                      => 'wp-includes/class-wp-walker.php',
		'WP_Widget_Factory'                           => 'wp-includes/class-wp-widget-factory.php',
		'WP_Widget'                                   => 'wp-includes/class-wp-widget.php',
		'wp_xmlrpc_server'                            => 'wp-includes/class-wp-xmlrpc-server.php',
		'WP'                                          => 'wp-includes/class-wp.php',
		'wpdb'                                        => 'wp-includes/class-wpdb.php', // Defines some constants.

		/* Classes in the wp-includes/customize/ folder. */
		'WP_Customize_Background_Image_Control'       => 'wp-includes/customize/class-wp-customize-background-image-control.php',
		'WP_Customize_Background_Image_Setting'       => 'wp-includes/customize/class-wp-customize-background-image-setting.php',
		'WP_Customize_Background_Position_Control'    => 'wp-includes/customize/class-wp-customize-background-position-control.php',
		'WP_Customize_Code_Editor_Control'            => 'wp-includes/customize/class-wp-customize-code-editor-control.php',
		'WP_Customize_Color_Control'                  => 'wp-includes/customize/class-wp-customize-color-control.php',
		'WP_Customize_Cropped_Image_Control'          => 'wp-includes/customize/class-wp-customize-cropped-image-control.php',
		'WP_Customize_Custom_CSS_Setting'             => 'wp-includes/customize/class-wp-customize-custom-css-setting.php',
		'WP_Customize_Date_Time_Control'              => 'wp-includes/customize/class-wp-customize-date-time-control.php',
		'WP_Customize_Filter_Setting'                 => 'wp-includes/customize/class-wp-customize-filter-setting.php',
		'WP_Customize_Header_Image_Control'           => 'wp-includes/customize/class-wp-customize-header-image-control.php',
		'WP_Customize_Header_Image_Setting'           => 'wp-includes/customize/class-wp-customize-header-image-setting.php',
		'WP_Customize_Image_Control'                  => 'wp-includes/customize/class-wp-customize-image-control.php',
		'WP_Customize_Media_Control'                  => 'wp-includes/customize/class-wp-customize-media-control.php',
		'WP_Customize_Nav_Menu_Auto_Add_Control'      => 'wp-includes/customize/class-wp-customize-nav-menu-auto-add-control.php',
		'WP_Customize_Nav_Menu_Control'               => 'wp-includes/customize/class-wp-customize-nav-menu-control.php',
		'WP_Customize_Nav_Menu_Item_Control'          => 'wp-includes/customize/class-wp-customize-nav-menu-item-control.php',
		'WP_Customize_Nav_Menu_Item_Setting'          => 'wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
		'WP_Customize_Nav_Menu_Location_Control'      => 'wp-includes/customize/class-wp-customize-nav-menu-location-control.php',
		'WP_Customize_Nav_Menu_Locations_Control'     => 'wp-includes/customize/class-wp-customize-nav-menu-locations-control.php',
		'WP_Customize_Nav_Menu_Name_Control'          => 'wp-includes/customize/class-wp-customize-nav-menu-name-control.php',
		'WP_Customize_Nav_Menu_Section'               => 'wp-includes/customize/class-wp-customize-nav-menu-section.php',
		'WP_Customize_Nav_Menu_Setting'               => 'wp-includes/customize/class-wp-customize-nav-menu-setting.php',
		'WP_Customize_Nav_Menus_Panel'                => 'wp-includes/customize/class-wp-customize-nav-menus-panel.php',
		'WP_Customize_New_Menu_Control'               => 'wp-includes/customize/class-wp-customize-new-menu-control.php',
		'WP_Customize_New_Menu_Section'               => 'wp-includes/customize/class-wp-customize-new-menu-section.php',
		'WP_Customize_Partial'                        => 'wp-includes/customize/class-wp-customize-partial.php',
		'WP_Customize_Selective_Refresh'              => 'wp-includes/customize/class-wp-customize-selective-refresh.php',
		'WP_Customize_Sidebar_Section'                => 'wp-includes/customize/class-wp-customize-sidebar-section.php',
		'WP_Customize_Site_Icon_Control'              => 'wp-includes/customize/class-wp-customize-site-icon-control.php',
		'WP_Customize_Theme_Control'                  => 'wp-includes/customize/class-wp-customize-theme-control.php',
		'WP_Customize_Themes_Panel'                   => 'wp-includes/customize/class-wp-customize-themes-panel.php',
		'WP_Customize_Themes_Section'                 => 'wp-includes/customize/class-wp-customize-themes-section.php',
		'WP_Customize_Upload_Control'                 => 'wp-includes/customize/class-wp-customize-upload-control.php',
		'WP_Sidebar_Block_Editor_Control'             => 'wp-includes/customize/class-wp-sidebar-block-editor-control.php',
		'WP_Widget_Area_Customize_Control'            => 'wp-includes/customize/class-wp-widget-area-customize-control.php',
		'WP_Widget_Form_Customize_Control'            => 'wp-includes/customize/class-wp-widget-form-customize-control.php',

		/* Classes in the wp-includes/IXR folder. */
		'IXR_Base64'                                  => 'wp-includes/IXR/class-IXR-base64.php',
		'IXR_Client'                                  => 'wp-includes/IXR/class-IXR-client.php',
		'IXR_ClientMulticall'                         => 'wp-includes/IXR/class-IXR-clientmulticall.php',
		'IXR_Date'                                    => 'wp-includes/IXR/class-IXR-date.php',
		'IXR_Error'                                   => 'wp-includes/IXR/class-IXR-error.php',
		'IXR_IntrospectionServer'                     => 'wp-includes/IXR/class-IXR-introspectionserver.php',
		'IXR_Message'                                 => 'wp-includes/IXR/class-IXR-message.php',
		'IXR_Request'                                 => 'wp-includes/IXR/class-IXR-request.php',
		'IXR_Server'                                  => 'wp-includes/IXR/class-IXR-server.php',
		'IXR_Value'                                   => 'wp-includes/IXR/class-IXR-value.php',

		/* Classes in the wp-includes/pomo folder. */
		'Translation_Entry'                           => 'wp-includes/pomo/entry.php',
		'MO'                                          => 'wp-includes/pomo/mo.php',
		'Plural_Forms'                                => 'wp-includes/pomo/plural-forms.php',
		'PO'                                          => 'wp-includes/pomo/po.php',
		'POMO_Reader'                                 => 'wp-includes/pomo/streams.php',
		'POMO_FileReader'                             => 'wp-includes/pomo/streams.php',
		'POMO_StringReader'                           => 'wp-includes/pomo/streams.php',
		'POMO_CachedFileReader'                       => 'wp-includes/pomo/streams.php',
		'POMO_CachedIntFileReader'                    => 'wp-includes/pomo/streams.php',
		'Translations'                                => 'wp-includes/pomo/translations.php',
		'Gettext_Translations'                        => 'wp-includes/pomo/translations.php',
		'NOOP_Translations'                           => 'wp-includes/pomo/translations.php',

		/* Classes in the wp-includes/rest-api folder. */
		'WP_REST_Application_Passwords_Controller'    => 'wp-includes/rest-api/endpoints/class-wp-rest-application-passwords-controller.php',
		'WP_REST_Attachments_Controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
		'WP_REST_Autosaves_Controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
		'WP_REST_Block_Directory_Controller'          => 'wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
		'WP_REST_Block_Pattern_Categories_Controller' => 'wp-includes/rest-api/endpoints/class-wp-rest-block-pattern-categories-controller.php',
		'WP_REST_Block_Patterns_Controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-block-patterns-controller.php',
		'WP_REST_Block_Renderer_Controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-block-renderer-controller.php',
		'WP_REST_Block_Types_Controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
		'WP_REST_Blocks_Controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-blocks-controller.php',
		'WP_REST_Comments_Controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
		'WP_REST_Controller'                          => 'wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
		'WP_REST_Edit_Site_Export_Controller'         => 'wp-includes/rest-api/endpoints/class-wp-rest-edit-site-export-controller.php',
		'WP_REST_Global_Styles_Controller'            => 'wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
		'WP_REST_Menu_Items_Controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php',
		'WP_REST_Menu_Locations_Controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-menu-locations-controller.php',
		'WP_REST_Menus_Controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
		'WP_REST_Pattern_Directory_Controller'        => 'wp-includes/rest-api/endpoints/class-wp-rest-pattern-directory-controller.php',
		'WP_REST_Plugins_Controller'                  => 'wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
		'WP_REST_Post_Statuses_Controller'            => 'wp-includes/rest-api/endpoints/class-wp-rest-post-statuses-controller.php',
		'WP_REST_Post_Types_Controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
		'WP_REST_Posts_Controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
		'WP_REST_Revisions_Controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php',
		'WP_REST_Search_Controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-search-controller.php',
		'WP_REST_Settings_Controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-settings-controller.php',
		'WP_REST_Sidebars_Controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-sidebars-controller.php',
		'WP_REST_Site_Health_Controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-site-health-controller.php',
		'WP_REST_Taxonomies_Controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-taxonomies-controller.php',
		'WP_REST_Templates_Controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-templates-controller.php',
		'WP_REST_Terms_Controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
		'WP_REST_Themes_Controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-themes-controller.php',
		'WP_REST_URL_Details_Controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-url-details-controller.php',
		'WP_REST_Users_Controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
		'WP_REST_Widget_Types_Controller'             => 'wp-includes/rest-api/endpoints/class-wp-rest-widget-types-controller.php',
		'WP_REST_Widgets_Controller'                  => 'wp-includes/rest-api/endpoints/class-wp-rest-widgets-controller.php',
		'WP_REST_Comment_Meta_Fields'                 => 'wp-includes/rest-api/fields/class-wp-rest-comment-meta-fields.php',
		'WP_REST_Meta_Fields'                         => 'wp-includes/rest-api/fields/class-wp-rest-meta-fields.php',
		'WP_REST_Post_Meta_Fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-post-meta-fields.php',
		'WP_REST_Term_Meta_Fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-term-meta-fields.php',
		'WP_REST_User_Meta_Fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-user-meta-fields.php',
		'WP_REST_Post_Format_Search_Handler'          => 'wp-includes/rest-api/search/class-wp-rest-post-format-search-handler.php',
		'WP_REST_Post_Search_Handler'                 => 'wp-includes/rest-api/search/class-wp-rest-post-search-handler.php',
		'WP_REST_Search_Handler'                      => 'wp-includes/rest-api/search/class-wp-rest-search-handler.php',
		'WP_REST_Term_Search_Handler'                 => 'wp-includes/rest-api/search/class-wp-rest-term-search-handler.php',
		'WP_REST_Request'                             => 'wp-includes/rest-api/class-wp-rest-request.php',
		'WP_REST_Response'                            => 'wp-includes/rest-api/class-wp-rest-response.php',
		'WP_REST_Server'                              => 'wp-includes/rest-api/class-wp-rest-server.php',

		/* Classes in wp-includes/sitemaps. */
		'WP_Sitemaps_Posts'                           => 'wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
		'WP_Sitemaps_Taxonomies'                      => 'wp-includes/sitemaps/providers/class-wp-sitemaps-taxonomies.php',
		'WP_Sitemaps_Users'                           => 'wp-includes/sitemaps/providers/class-wp-sitemaps-users.php',
		'WP_Sitemaps_Index'                           => 'wp-includes/sitemaps/class-wp-sitemaps-index.php',
		'WP_Sitemaps_Provider'                        => 'wp-includes/sitemaps/class-wp-sitemaps-provider.php',
		'WP_Sitemaps_Registry'                        => 'wp-includes/sitemaps/class-wp-sitemaps-registry.php',
		'WP_Sitemaps_Renderer'                        => 'wp-includes/sitemaps/class-wp-sitemaps-renderer.php',
		'WP_Sitemaps_Stylesheet'                      => 'wp-includes/sitemaps/class-wp-sitemaps-stylesheet.php',
		'WP_Sitemaps'                                 => 'wp-includes/sitemaps/class-wp-sitemaps.php',

		/* Classes in wp-includes/style-engine. */
		'WP_Style_Engine_CSS_Declarations'            => 'wp-includes/style-engine/class-wp-style-engine-css-declarations.php',
		'WP_Style_Engine_CSS_Rule'                    => 'wp-includes/style-engine/class-wp-style-engine-css-rule.php',
		'WP_Style_Engine_CSS_Rules_Store'             => 'wp-includes/style-engine/class-wp-style-engine-css-rules-store.php',
		'WP_Style_Engine_Processor'                   => 'wp-includes/style-engine/class-wp-style-engine-processor.php',
		'WP_Style_Engine'                             => 'wp-includes/style-engine/class-wp-style-engine.php',

		/* Classes in wp-includes/widgets. */
		'WP_Nav_Menu_Widget'                          => 'wp-includes/widgets/class-wp-nav-menu-widget.php',
		'WP_Widget_Archives'                          => 'wp-includes/widgets/class-wp-widget-archives.php',
		'WP_Widget_Block'                             => 'wp-includes/widgets/class-wp-widget-block.php',
		'WP_Widget_Calendar'                          => 'wp-includes/widgets/class-wp-widget-calendar.php',
		'WP_Widget_Categories'                        => 'wp-includes/widgets/class-wp-widget-categories.php',
		'WP_Widget_Custom_HTML'                       => 'wp-includes/widgets/class-wp-widget-custom-html.php',
		'WP_Widget_Links'                             => 'wp-includes/widgets/class-wp-widget-links.php',
		'WP_Widget_Media_Audio'                       => 'wp-includes/widgets/class-wp-widget-media-audio.php',
		'WP_Widget_Media_Gallery'                     => 'wp-includes/widgets/class-wp-widget-media-gallery.php',
		'WP_Widget_Media_Image'                       => 'wp-includes/widgets/class-wp-widget-media-image.php',
		'WP_Widget_Media_Video'                       => 'wp-includes/widgets/class-wp-widget-media-video.php',
		'WP_Widget_Media'                             => 'wp-includes/widgets/class-wp-widget-media.php',
		'WP_Widget_Meta'                              => 'wp-includes/widgets/class-wp-widget-meta.php',
		'WP_Widget_Pages'                             => 'wp-includes/widgets/class-wp-widget-pages.php',
		'WP_Widget_Recent_Comments'                   => 'wp-includes/widgets/class-wp-widget-recent-comments.php',
		'WP_Widget_Recent_Posts'                      => 'wp-includes/widgets/class-wp-widget-recent-posts.php',
		'WP_Widget_RSS'                               => 'wp-includes/widgets/class-wp-widget-rss.php',
		'WP_Widget_Search'                            => 'wp-includes/widgets/class-wp-widget-search.php',
		'WP_Widget_Tag_Cloud'                         => 'wp-includes/widgets/class-wp-widget-tag-cloud.php',
		'WP_Widget_Text'                              => 'wp-includes/widgets/class-wp-widget-text.php',

		/* Classes in wp-admin/includes. */
		'Automatic_Upgrader_Skin'                     => 'wp-admin/includes/class-automatic-upgrader-skin.php',
		'Bulk_Plugin_Upgrader_Skin'                   => 'wp-admin/includes/class-bulk-plugin-upgrader-skin.php',
		'Bulk_Theme_Upgrader_Skin'                    => 'wp-admin/includes/class-bulk-theme-upgrader-skin.php',
		'Bulk_Upgrader_Skin'                          => 'wp-admin/includes/class-bulk-upgrader-skin.php',
		'Core_Upgrader'                               => 'wp-admin/includes/class-core-upgrader.php',
		'Custom_Background'                           => 'wp-admin/includes/class-custom-background.php',
		'Custom_Image_Header'                         => 'wp-admin/includes/class-custom-image-header.php',
		'File_Upload_Upgrader'                        => 'wp-admin/includes/class-file-upload-upgrader.php',
		'ftp_pure'                                    => 'wp-admin/includes/class-ftp-pure.php',
		'ftp_sockets'                                 => 'wp-admin/includes/class-ftp-sockets.php',
		'Language_Pack_Upgrader_Skin'                 => 'wp-admin/includes/class-language-pack-upgrader-skin.php',
		'Language_Pack_Upgrader'                      => 'wp-admin/includes/class-language-pack-upgrader.php',
		'Plugin_Installer_Skin'                       => 'wp-admin/includes/class-plugin-installer-skin.php',
		'Plugin_Upgrader_Skin'                        => 'wp-admin/includes/class-plugin-upgrader-skin.php',
		'Plugin_Upgrader'                             => 'wp-admin/includes/class-plugin-upgrader.php',
		'Theme_Installer_Skin'                        => 'wp-admin/includes/class-theme-installer-skin.php',
		'Theme_Upgrader_Skin'                         => 'wp-admin/includes/class-theme-upgrader-skin.php',
		'Theme_Upgrader'                              => 'wp-admin/includes/class-theme-upgrader.php',
		'Walker_Category_Checklist'                   => 'wp-admin/includes/class-walker-category-checklist.php',
		'Walker_Nav_Menu_Checklist'                   => 'wp-admin/includes/class-walker-nav-menu-checklist.php',
		'Walker_Nav_Menu_Edit'                        => 'wp-admin/includes/class-walker-nav-menu-edit.php',
		'WP_Ajax_Upgrader_Skin'                       => 'wp-admin/includes/class-wp-ajax-upgrader-skin.php',
		'WP_Application_Passwords_List_Table'         => 'wp-admin/includes/class-wp-application-passwords-list-table.php',
		'WP_Automatic_Updater'                        => 'wp-admin/includes/class-wp-automatic-updater.php',
		'WP_Comments_List_Table'                      => 'wp-admin/includes/class-wp-comments-list-table.php',
		'WP_Community_Events'                         => 'wp-admin/includes/class-wp-community-events.php',
		'WP_Debug_Data'                               => 'wp-admin/includes/class-wp-debug-data.php',
		'WP_Filesystem_Base'                          => 'wp-admin/includes/class-wp-filesystem-base.php',
		'WP_Filesystem_Direct'                        => 'wp-admin/includes/class-wp-filesystem-direct.php',
		'WP_Filesystem_FTPext'                        => 'wp-admin/includes/class-wp-filesystem-ftpext.php',
		'WP_Filesystem_ftpsockets'                    => 'wp-admin/includes/class-wp-filesystem-ftpsockets.php',
		'WP_Filesystem_SSH2'                          => 'wp-admin/includes/class-wp-filesystem-ssh2.php',
		'WP_Importer'                                 => 'wp-admin/includes/class-wp-importer.php', // Contains some additional functions.
		'WP_Internal_Pointers'                        => 'wp-admin/includes/class-wp-internal-pointers.php',
		'WP_Links_List_Table'                         => 'wp-admin/includes/class-wp-links-list-table.php',
		'_WP_List_Table_Compat'                       => 'wp-admin/includes/class-wp-list-table-compat.php',
		'WP_List_Table'                               => 'wp-admin/includes/class-wp-list-table.php',
		'WP_Media_List_Table'                         => 'wp-admin/includes/class-wp-media-list-table.php',
		'WP_MS_Sites_List_Table'                      => 'wp-admin/includes/class-wp-ms-sites-list-table.php',
		'WP_MS_Themes_List_Table'                     => 'wp-admin/includes/class-wp-ms-themes-list-table.php',
		'WP_MS_Users_List_Table'                      => 'wp-admin/includes/class-wp-ms-users-list-table.php',
		'WP_Plugin_Install_List_Table'                => 'wp-admin/includes/class-wp-plugin-install-list-table.php',
		'WP_Plugins_List_Table'                       => 'wp-admin/includes/class-wp-plugins-list-table.php',
		'WP_Post_Comments_List_Table'                 => 'wp-admin/includes/class-wp-post-comments-list-table.php',
		'WP_Posts_List_Table'                         => 'wp-admin/includes/class-wp-posts-list-table.php',
		'WP_Privacy_Data_Export_Requests_List_Table'  => 'wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php',
		'WP_Privacy_Data_Removal_Requests_List_Table' => 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php',
		'WP_Privacy_Policy_Content'                   => 'wp-admin/includes/class-wp-privacy-policy-content.php',
		'WP_Privacy_Requests_Table'                   => 'wp-admin/includes/class-wp-privacy-requests-table.php',
		'WP_Screen'                                   => 'wp-admin/includes/class-wp-screen.php',
		'WP_Site_Health_Auto_Updates'                 => 'wp-admin/includes/class-wp-site-health-auto-updates.php',
		'WP_Site_Health'                              => 'wp-admin/includes/class-wp-site-health.php',
		'WP_Site_Icon'                                => 'wp-admin/includes/class-wp-site-icon.php',
		'WP_Terms_List_Table'                         => 'wp-admin/includes/class-wp-terms-list-table.php',
		'WP_Theme_Install_List_Table'                 => 'wp-admin/includes/class-wp-theme-install-list-table.php',
		'WP_Themes_List_Table'                        => 'wp-admin/includes/class-wp-themes-list-table.php',
		'WP_Upgrader_Skin'                            => 'wp-admin/includes/class-wp-upgrader-skin.php',
		'WP_Upgrader'                                 => 'wp-admin/includes/class-wp-upgrader.php',
		'WP_Users_List_Table'                         => 'wp-admin/includes/class-wp-users-list-table.php',
		'WP_User_Search'                              => 'wp-admin/includes/deprecated.php',
		'WP_Privacy_Data_Export_Requests_Table'       => 'wp-admin/includes/deprecated.php',
		'WP_Privacy_Data_Removal_Requests_Table'      => 'wp-admin/includes/deprecated.php',
	);

	/**
	 * Additional autoloaders for bundled libraries.
	 *
	 * @static
	 * @access private
	 *
	 * @var array
	 */
	private static $extra_autoloaders = array(
		array(
			'path'     => 'wp-includes/class-simplepie.php',
			'callback' => 'wp_simplepie_autoload',
		),
		array(
			'path'     => 'wp-includes/class-requests.php',
			'callback' => array( 'Requests', 'autoloader' ),
		),
		array(
			'path'     => 'wp-includes/sodium_compat/autoload.php',
			'callback' => null,
		),
	);

	/**
	 * Register the autoloader.
	 *
	 * Note: the autoloader is *prepended* in the autoload queue.
	 * This is done to ensure that the Requests 2.0 autoloader takes precedence
	 * over a potentially (dependency-registered) Requests 1.x autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		// Autoload WordPress classes.
		spl_autoload_register( array( __CLASS__, 'autoload' ), true, true );

		// Autoload bundled libraries.
		foreach ( self::$extra_autoloaders as $autoloader ) {
			require_once ABSPATH . $autoloader['path'];

			if ( is_callable( $autoloader['callback'] ) ) {
				spl_autoload_register( $autoloader['callback'], true, true );
			}
		}
	}

	/**
	 * Autoload a Requests class.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( isset( self::$classes[ $class ] ) ) {
			require_once ABSPATH . self::$classes[ $class ];
		}
	}
}
