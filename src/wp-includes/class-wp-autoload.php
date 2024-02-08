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
	 * Note: The classnames should be lowercased.
	 *
	 * @access private
	 *
	 * @var array
	 */
	const CLASSES_PATHS = array(
		/* Classes in the wp-includes/ folder. */
		'passwordhash'                                => 'wp-includes/class-phpass.php',
		'pop3'                                        => 'wp-includes/class-pop3.php',
		'services_json'                               => 'wp-includes/class-json.php',
		'services_json_error'                         => 'wp-includes/class-json.php',
		'walker_categorydropdown'                     => 'wp-includes/class-walker-category-dropdown.php',
		'walker_category'                             => 'wp-includes/class-walker-category.php',
		'walker_comment'                              => 'wp-includes/class-walker-comment.php',
		'walker_nav_menu'                             => 'wp-includes/class-walker-nav-menu.php',
		'walker_pagedropdown'                         => 'wp-includes/class-walker-page-dropdown.php',
		'walker_page'                                 => 'wp-includes/class-walker-page.php',
		'wp_admin_bar'                                => 'wp-includes/class-wp-admin-bar.php',
		'wp_ajax_response'                            => 'wp-includes/class-wp-ajax-response.php',
		'wp_application_passwords'                    => 'wp-includes/class-wp-application-passwords.php',
		'wp_block_bindings_registry'                  => 'wp-includes/class-wp-block-bindings-registry.php',
		'wp_block_bindings_source'                    => 'wp-includes/class-wp-block-bindings-source.php',
		'wp_block_editor_context'                     => 'wp-includes/class-wp-block-editor-context.php',
		'wp_block_list'                               => 'wp-includes/class-wp-block-list.php',
		'wp_block_parser_block'                       => 'wp-includes/class-wp-block-parser-block.php',
		'wp_block_parser_frame'                       => 'wp-includes/class-wp-block-parser-frame.php',
		'wp_block_parser'                             => 'wp-includes/class-wp-block-parser.php',
		'wp_block_pattern_categories_registry'        => 'wp-includes/class-wp-block-pattern-categories-registry.php',
		'wp_block_patterns_registry'                  => 'wp-includes/class-wp-block-patterns-registry.php',
		'wp_block_styles_registry'                    => 'wp-includes/class-wp-block-styles-registry.php',
		'wp_block_supports'                           => 'wp-includes/class-wp-block-supports.php',
		'wp_block_template'                           => 'wp-includes/class-wp-block-template.php',
		'wp_block_type_registry'                      => 'wp-includes/class-wp-block-type-registry.php',
		'wp_block_type'                               => 'wp-includes/class-wp-block-type.php',
		'wp_block'                                    => 'wp-includes/class-wp-block.php',
		'wp_classic_to_block_menu_converter'          => 'wp-includes/class-wp-classic-to-block-menu-converter.php',
		'wp_comment_query'                            => 'wp-includes/class-wp-comment-query.php',
		'wp_comment'                                  => 'wp-includes/class-wp-comment.php',
		'wp_customize_control'                        => 'wp-includes/class-wp-customize-control.php',
		'wp_customize_manager'                        => 'wp-includes/class-wp-customize-manager.php',
		'wp_customize_nav_menus'                      => 'wp-includes/class-wp-customize-nav-menus.php',
		'wp_customize_panel'                          => 'wp-includes/class-wp-customize-panel.php',
		'wp_customize_section'                        => 'wp-includes/class-wp-customize-section.php',
		'wp_customize_setting'                        => 'wp-includes/class-wp-customize-setting.php',
		'wp_customize_widgets'                        => 'wp-includes/class-wp-customize-widgets.php',
		'wp_date_query'                               => 'wp-includes/class-wp-date-query.php',
		'wp_dependencies'                             => 'wp-includes/class-wp-dependencies.php',
		'_wp_dependency'                              => 'wp-includes/class-wp-dependency.php',
		'wp_duotone'                                  => 'wp-includes/class-wp-duotone.php',
		'_wp_editors'                                 => 'wp-includes/class-wp-editor.php',
		'wp_embed'                                    => 'wp-includes/class-wp-embed.php',
		'wp_error'                                    => 'wp-includes/class-wp-error.php',
		'wp_fatal_error_handler'                      => 'wp-includes/class-wp-fatal-error-handler.php',
		'wp_feed_cache_transient'                     => 'wp-includes/class-wp-feed-cache-transient.php',
		'wp_feed_cache'                               => 'wp-includes/class-wp-feed-cache.php',
		'wp_hook'                                     => 'wp-includes/class-wp-hook.php',
		'wp_http_cookie'                              => 'wp-includes/class-wp-http-cookie.php',
		'wp_http_curl'                                => 'wp-includes/class-wp-http-curl.php',
		'wp_http_encoding'                            => 'wp-includes/class-wp-http-encoding.php',
		'wp_http_ixr_client'                          => 'wp-includes/class-wp-http-ixr-client.php',
		'wp_http_proxy'                               => 'wp-includes/class-wp-http-proxy.php',
		'wp_http_requests_hooks'                      => 'wp-includes/class-wp-http-requests-hooks.php',
		'wp_http_requests_response'                   => 'wp-includes/class-wp-http-requests-response.php',
		'wp_http_response'                            => 'wp-includes/class-wp-http-response.php',
		'wp_http_streams'                             => 'wp-includes/class-wp-http-streams.php',
		'wp_http_fsockopen'                           => 'wp-includes/class-wp-http-streams.php',
		'wp_http'                                     => 'wp-includes/class-wp-http.php',
		'wp_image_editor_gd'                          => 'wp-includes/class-wp-image-editor-gd.php',
		'wp_image_editor_imagick'                     => 'wp-includes/class-wp-image-editor-imagick.php',
		'wp_image_editor'                             => 'wp-includes/class-wp-image-editor.php',
		'wp_list_util'                                => 'wp-includes/class-wp-list-util.php',
		'wp_locale_switcher'                          => 'wp-includes/class-wp-locale-switcher.php',
		'wp_locale'                                   => 'wp-includes/class-wp-locale.php',
		'wp_matchesmapregex'                          => 'wp-includes/class-wp-matchesmapregex.php',
		'wp_meta_query'                               => 'wp-includes/class-wp-meta-query.php',
		'wp_metadata_lazyloader'                      => 'wp-includes/class-wp-metadata-lazyloader.php',
		'wp_navigation_fallback'                      => 'wp-includes/class-wp-navigation-fallback.php',
		'wp_network_query'                            => 'wp-includes/class-wp-network-query.php',
		'wp_network'                                  => 'wp-includes/class-wp-network.php',
		'wp_object_cache'                             => 'wp-includes/class-wp-object-cache.php',
		'wp_oembed_controller'                        => 'wp-includes/class-wp-oembed-controller.php',
		'wp_oembed'                                   => 'wp-includes/class-wp-oembed.php',
		'wp_paused_extensions_storage'                => 'wp-includes/class-wp-paused-extensions-storage.php',
		'wp_plugin_dependencies'                      => 'wp-includes/class-wp-plugin-dependencies.php',
		'wp_post_type'                                => 'wp-includes/class-wp-post-type.php',
		'wp_post'                                     => 'wp-includes/class-wp-post.php',
		'wp_query'                                    => 'wp-includes/class-wp-query.php',
		'wp_recovery_mode_cookie_service'             => 'wp-includes/class-wp-recovery-mode-cookie-service.php',
		'wp_recovery_mode_email_service'              => 'wp-includes/class-wp-recovery-mode-email-service.php',
		'wp_recovery_mode_key_service'                => 'wp-includes/class-wp-recovery-mode-key-service.php',
		'wp_recovery_mode_link_service'               => 'wp-includes/class-wp-recovery-mode-link-service.php',
		'wp_recovery_mode'                            => 'wp-includes/class-wp-recovery-mode.php',
		'wp_rewrite'                                  => 'wp-includes/class-wp-rewrite.php',
		'wp_role'                                     => 'wp-includes/class-wp-role.php',
		'wp_roles'                                    => 'wp-includes/class-wp-roles.php',
		'wp_script_modules'                           => 'wp-includes/class-wp-script-modules.php',
		'wp_scripts'                                  => 'wp-includes/class-wp-scripts.php',
		'wp_session_tokens'                           => 'wp-includes/class-wp-session-tokens.php',
		'wp_simplepie_file'                           => 'wp-includes/class-wp-simplepie-file.php',
		'wp_simplepie_sanitize_kses'                  => 'wp-includes/class-wp-simplepie-sanitize-kses.php',
		'wp_site_query'                               => 'wp-includes/class-wp-site-query.php',
		'wp_site'                                     => 'wp-includes/class-wp-site.php',
		'wp_styles'                                   => 'wp-includes/class-wp-styles.php',
		'wp_tax_query'                                => 'wp-includes/class-wp-tax-query.php',
		'wp_taxonomy'                                 => 'wp-includes/class-wp-taxonomy.php',
		'wp_term_query'                               => 'wp-includes/class-wp-term-query.php',
		'wp_term'                                     => 'wp-includes/class-wp-term.php',
		'wp_text_diff_renderer_inline'                => 'wp-includes/class-wp-text-diff-renderer-inline.php',
		'wp_text_diff_renderer_table'                 => 'wp-includes/class-wp-text-diff-renderer-table.php',
		'wp_textdomain_registry'                      => 'wp-includes/class-wp-textdomain-registry.php',
		'wp_theme_json_data'                          => 'wp-includes/class-wp-theme-json-data.php',
		'wp_theme_json_resolver'                      => 'wp-includes/class-wp-theme-json-resolver.php',
		'wp_theme_json_schema'                        => 'wp-includes/class-wp-theme-json-schema.php',
		'wp_theme_json'                               => 'wp-includes/class-wp-theme-json.php',
		'wp_theme'                                    => 'wp-includes/class-wp-theme.php',
		'wp_user_meta_session_tokens'                 => 'wp-includes/class-wp-user-meta-session-tokens.php',
		'wp_user_query'                               => 'wp-includes/class-wp-user-query.php',
		'wp_user_request'                             => 'wp-includes/class-wp-user-request.php',
		'wp_user'                                     => 'wp-includes/class-wp-user.php',
		'walker'                                      => 'wp-includes/class-wp-walker.php',
		'wp_widget_factory'                           => 'wp-includes/class-wp-widget-factory.php',
		'wp_widget'                                   => 'wp-includes/class-wp-widget.php',
		'wp_xmlrpc_server'                            => 'wp-includes/class-wp-xmlrpc-server.php',
		'wp'                                          => 'wp-includes/class-wp.php',
		'wpdb'                                        => 'wp-includes/class-wpdb.php', // Defines some constants.

		/* Classes in the wp-includes/fonts folder. */
		'wp_font_collection'                          => 'wp-includes/fonts/class-wp-font-collection.php',
		'wp_font_face_resolver'                       => 'wp-includes/fonts/class-wp-font-face-resolver.php',
		'wp_font_face'                                => 'wp-includes/fonts/class-wp-font-face.php',
		'wp_font_library'                             => 'wp-includes/fonts/class-wp-font-library.php',
		'wp_font_utils'                               => 'wp-includes/fonts/class-wp-font-utils.php',

		/* Classes in the wp-includes/html-api/ folder. */
		'wp_html_active_formatting_elements'          => 'wp-includes/html-api/class-wp-html-active-formatting-elements.php',
		'wp_html_attribute_token'                     => 'wp-includes/html-api/class-wp-html-attribute-token.php',
		'wp_html_open_elements'                       => 'wp-includes/html-api/class-wp-html-open-elements.php',
		'wp_html_processor_state'                     => 'wp-includes/html-api/class-wp-html-processor-state.php',
		'wp_html_processor'                           => 'wp-includes/html-api/class-wp-html-processor.php',
		'wp_html_span'                                => 'wp-includes/html-api/class-wp-html-span.php',
		'wp_html_tag_processor'                       => 'wp-includes/html-api/class-wp-html-tag-processor.php',
		'wp_html_text_replacement'                    => 'wp-includes/html-api/class-wp-html-text-replacement.php',
		'wp_html_token'                               => 'wp-includes/html-api/class-wp-html-token.php',
		'wp_html_unsupported_exception'               => 'wp-includes/html-api/class-wp-html-unsupported-exception.php',

		/* Classes in the wp-includes/interactivity-api folder. */
		'wp_interactivity_api_directives_processor'   => 'wp-includes/interactivity-api/class-wp-interactivity-api-directives-processor.php',
		'wp_interactivity_api'                        => 'wp-includes/interactivity-api/class-wp-interactivity-api.php',

		/* Classes in the wp-includes/customize/ folder. */
		'wp_customize_background_image_control'       => 'wp-includes/customize/class-wp-customize-background-image-control.php',
		'wp_customize_background_image_setting'       => 'wp-includes/customize/class-wp-customize-background-image-setting.php',
		'wp_customize_background_position_control'    => 'wp-includes/customize/class-wp-customize-background-position-control.php',
		'wp_customize_code_editor_control'            => 'wp-includes/customize/class-wp-customize-code-editor-control.php',
		'wp_customize_color_control'                  => 'wp-includes/customize/class-wp-customize-color-control.php',
		'wp_customize_cropped_image_control'          => 'wp-includes/customize/class-wp-customize-cropped-image-control.php',
		'wp_customize_custom_css_setting'             => 'wp-includes/customize/class-wp-customize-custom-css-setting.php',
		'wp_customize_date_time_control'              => 'wp-includes/customize/class-wp-customize-date-time-control.php',
		'wp_customize_filter_setting'                 => 'wp-includes/customize/class-wp-customize-filter-setting.php',
		'wp_customize_header_image_control'           => 'wp-includes/customize/class-wp-customize-header-image-control.php',
		'wp_customize_header_image_setting'           => 'wp-includes/customize/class-wp-customize-header-image-setting.php',
		'wp_customize_image_control'                  => 'wp-includes/customize/class-wp-customize-image-control.php',
		'wp_customize_media_control'                  => 'wp-includes/customize/class-wp-customize-media-control.php',
		'wp_customize_nav_menu_auto_add_control'      => 'wp-includes/customize/class-wp-customize-nav-menu-auto-add-control.php',
		'wp_customize_nav_menu_control'               => 'wp-includes/customize/class-wp-customize-nav-menu-control.php',
		'wp_customize_nav_menu_item_control'          => 'wp-includes/customize/class-wp-customize-nav-menu-item-control.php',
		'wp_customize_nav_menu_item_setting'          => 'wp-includes/customize/class-wp-customize-nav-menu-item-setting.php',
		'wp_customize_nav_menu_location_control'      => 'wp-includes/customize/class-wp-customize-nav-menu-location-control.php',
		'wp_customize_nav_menu_locations_control'     => 'wp-includes/customize/class-wp-customize-nav-menu-locations-control.php',
		'wp_customize_nav_menu_name_control'          => 'wp-includes/customize/class-wp-customize-nav-menu-name-control.php',
		'wp_customize_nav_menu_section'               => 'wp-includes/customize/class-wp-customize-nav-menu-section.php',
		'wp_customize_nav_menu_setting'               => 'wp-includes/customize/class-wp-customize-nav-menu-setting.php',
		'wp_customize_nav_menus_panel'                => 'wp-includes/customize/class-wp-customize-nav-menus-panel.php',
		'wp_customize_new_menu_control'               => 'wp-includes/customize/class-wp-customize-new-menu-control.php',
		'wp_customize_new_menu_section'               => 'wp-includes/customize/class-wp-customize-new-menu-section.php',
		'wp_customize_partial'                        => 'wp-includes/customize/class-wp-customize-partial.php',
		'wp_customize_selective_refresh'              => 'wp-includes/customize/class-wp-customize-selective-refresh.php',
		'wp_customize_sidebar_section'                => 'wp-includes/customize/class-wp-customize-sidebar-section.php',
		'wp_customize_site_icon_control'              => 'wp-includes/customize/class-wp-customize-site-icon-control.php',
		'wp_customize_theme_control'                  => 'wp-includes/customize/class-wp-customize-theme-control.php',
		'wp_customize_themes_panel'                   => 'wp-includes/customize/class-wp-customize-themes-panel.php',
		'wp_customize_themes_section'                 => 'wp-includes/customize/class-wp-customize-themes-section.php',
		'wp_customize_upload_control'                 => 'wp-includes/customize/class-wp-customize-upload-control.php',
		'wp_sidebar_block_editor_control'             => 'wp-includes/customize/class-wp-sidebar-block-editor-control.php',
		'wp_widget_area_customize_control'            => 'wp-includes/customize/class-wp-widget-area-customize-control.php',
		'wp_widget_form_customize_control'            => 'wp-includes/customize/class-wp-widget-form-customize-control.php',

		/* Classes in the wp-includes/IXR folder. */
		'ixr_base64'                                  => 'wp-includes/IXR/class-IXR-base64.php',
		'ixr_client'                                  => 'wp-includes/IXR/class-IXR-client.php',
		'ixr_clientmulticall'                         => 'wp-includes/IXR/class-IXR-clientmulticall.php',
		'ixr_date'                                    => 'wp-includes/IXR/class-IXR-date.php',
		'ixr_error'                                   => 'wp-includes/IXR/class-IXR-error.php',
		'ixr_introspectionserver'                     => 'wp-includes/IXR/class-IXR-introspectionserver.php',
		'ixr_message'                                 => 'wp-includes/IXR/class-IXR-message.php',
		'ixr_request'                                 => 'wp-includes/IXR/class-IXR-request.php',
		'ixr_server'                                  => 'wp-includes/IXR/class-IXR-server.php',
		'ixr_value'                                   => 'wp-includes/IXR/class-IXR-value.php',

		/* Classes in the wp-includes/l10n folder. */
		'wp_translation_controller'                   => 'wp-includes/l10n/class-wp-translation-controller.php',
		'wp_translation_file_mo'                      => 'wp-includes/l10n/class-wp-translation-file-mo.php',
		'wp_translation_file_php'                     => 'wp-includes/l10n/class-wp-translation-file-php.php',
		'wp_translation_file'                         => 'wp-includes/l10n/class-wp-translation-file.php',
		'wp_translations'                             => 'wp-includes/l10n/class-wp-translations.php',

		/* Classes in the wp-includes/pomo folder. */
		'translation_entry'                           => 'wp-includes/pomo/entry.php',
		'mo'                                          => 'wp-includes/pomo/mo.php',
		'plural_forms'                                => 'wp-includes/pomo/plural-forms.php',
		'po'                                          => 'wp-includes/pomo/po.php',
		'pomo_reader'                                 => 'wp-includes/pomo/streams.php',
		'pomo_filereader'                             => 'wp-includes/pomo/streams.php',
		'pomo_stringreader'                           => 'wp-includes/pomo/streams.php',
		'pomo_cachedfilereader'                       => 'wp-includes/pomo/streams.php',
		'pomo_cachedintfilereader'                    => 'wp-includes/pomo/streams.php',
		'translations'                                => 'wp-includes/pomo/translations.php',
		'gettext_translations'                        => 'wp-includes/pomo/translations.php',
		'noop_translations'                           => 'wp-includes/pomo/translations.php',

		/* Classes in the wp-includes/Text folder. */
		'text_diff'                                   => 'wp-includes/Text/Diff.php',
		'text_diff_engine_native'                     => 'wp-includes/Text/Diff/Engine/native.php',
		'text_diff_engine_shell'                      => 'wp-includes/Text/Diff/Engine/shell.php',
		'text_diff_engine_string'                     => 'wp-includes/Text/Diff/Engine/string.php',
		'text_diff_engine_xdiff'                      => 'wp-includes/Text/Diff/Engine/xdiff.php',
		'text_diff_renderer_inline'                   => 'wp-includes/Text/Diff/Renderer/inline.php',
		'text_diff_renderer'                          => 'wp-includes/Text/Diff/Renderer.php',

		/* Classes in the wp-includes/rest-api folder. */
		'wp_rest_application_passwords_controller'    => 'wp-includes/rest-api/endpoints/class-wp-rest-application-passwords-controller.php',
		'wp_rest_attachments_controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php',
		'wp_rest_autosaves_controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php',
		'wp_rest_block_directory_controller'          => 'wp-includes/rest-api/endpoints/class-wp-rest-block-directory-controller.php',
		'wp_rest_block_pattern_categories_controller' => 'wp-includes/rest-api/endpoints/class-wp-rest-block-pattern-categories-controller.php',
		'wp_rest_block_patterns_controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-block-patterns-controller.php',
		'wp_rest_block_renderer_controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-block-renderer-controller.php',
		'wp_rest_block_types_controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-block-types-controller.php',
		'wp_rest_blocks_controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-blocks-controller.php',
		'wp_rest_comments_controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php',
		'wp_rest_controller'                          => 'wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
		'wp_rest_edit_site_export_controller'         => 'wp-includes/rest-api/endpoints/class-wp-rest-edit-site-export-controller.php',
		'wp_rest_font_collections_controller'         => 'wp-includes/rest-api/endpoints/class-wp-rest-font-collections-controller.php',
		'wp_rest_font_faces_controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-font-faces-controller.php',
		'wp_rest_font_families_controller'            => 'wp-includes/rest-api/endpoints/class-wp-rest-font-families-controller.php',
		'wp_rest_global_styles_controller'            => 'wp-includes/rest-api/endpoints/class-wp-rest-global-styles-controller.php',
		'wp_rest_global_styles_revisions_controller'  => 'wp-includes/rest-api/endpoints/class-wp-rest-global-styles-revisions-controller.php',
		'wp_rest_menu_items_controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-menu-items-controller.php',
		'wp_rest_menu_locations_controller'           => 'wp-includes/rest-api/endpoints/class-wp-rest-menu-locations-controller.php',
		'wp_rest_menus_controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-menus-controller.php',
		'wp_rest_navigation_fallback_controller'      => 'wp-includes/rest-api/endpoints/class-wp-rest-navigation-fallback-controller.php',
		'wp_rest_pattern_directory_controller'        => 'wp-includes/rest-api/endpoints/class-wp-rest-pattern-directory-controller.php',
		'wp_rest_plugins_controller'                  => 'wp-includes/rest-api/endpoints/class-wp-rest-plugins-controller.php',
		'wp_rest_post_statuses_controller'            => 'wp-includes/rest-api/endpoints/class-wp-rest-post-statuses-controller.php',
		'wp_rest_post_types_controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-post-types-controller.php',
		'wp_rest_posts_controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
		'wp_rest_revisions_controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php',
		'wp_rest_search_controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-search-controller.php',
		'wp_rest_settings_controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-settings-controller.php',
		'wp_rest_sidebars_controller'                 => 'wp-includes/rest-api/endpoints/class-wp-rest-sidebars-controller.php',
		'wp_rest_site_health_controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-site-health-controller.php',
		'wp_rest_taxonomies_controller'               => 'wp-includes/rest-api/endpoints/class-wp-rest-taxonomies-controller.php',
		'wp_rest_template_autosaves_controller'       => 'wp-includes/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php',
		'wp_rest_template_revisions_controller'       => 'wp-includes/rest-api/endpoints/class-wp-rest-template-revisions-controller.php',
		'wp_rest_templates_controller'                => 'wp-includes/rest-api/endpoints/class-wp-rest-templates-controller.php',
		'wp_rest_terms_controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php',
		'wp_rest_themes_controller'                   => 'wp-includes/rest-api/endpoints/class-wp-rest-themes-controller.php',
		'wp_rest_url_details_controller'              => 'wp-includes/rest-api/endpoints/class-wp-rest-url-details-controller.php',
		'wp_rest_users_controller'                    => 'wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php',
		'wp_rest_widget_types_controller'             => 'wp-includes/rest-api/endpoints/class-wp-rest-widget-types-controller.php',
		'wp_rest_widgets_controller'                  => 'wp-includes/rest-api/endpoints/class-wp-rest-widgets-controller.php',
		'wp_rest_comment_meta_fields'                 => 'wp-includes/rest-api/fields/class-wp-rest-comment-meta-fields.php',
		'wp_rest_meta_fields'                         => 'wp-includes/rest-api/fields/class-wp-rest-meta-fields.php',
		'wp_rest_post_meta_fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-post-meta-fields.php',
		'wp_rest_term_meta_fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-term-meta-fields.php',
		'wp_rest_user_meta_fields'                    => 'wp-includes/rest-api/fields/class-wp-rest-user-meta-fields.php',
		'wp_rest_post_format_search_handler'          => 'wp-includes/rest-api/search/class-wp-rest-post-format-search-handler.php',
		'wp_rest_post_search_handler'                 => 'wp-includes/rest-api/search/class-wp-rest-post-search-handler.php',
		'wp_rest_search_handler'                      => 'wp-includes/rest-api/search/class-wp-rest-search-handler.php',
		'wp_rest_term_search_handler'                 => 'wp-includes/rest-api/search/class-wp-rest-term-search-handler.php',
		'wp_rest_request'                             => 'wp-includes/rest-api/class-wp-rest-request.php',
		'wp_rest_response'                            => 'wp-includes/rest-api/class-wp-rest-response.php',
		'wp_rest_server'                              => 'wp-includes/rest-api/class-wp-rest-server.php',

		/* Classes in wp-includes/sitemaps. */
		'wp_sitemaps_posts'                           => 'wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php',
		'wp_sitemaps_taxonomies'                      => 'wp-includes/sitemaps/providers/class-wp-sitemaps-taxonomies.php',
		'wp_sitemaps_users'                           => 'wp-includes/sitemaps/providers/class-wp-sitemaps-users.php',
		'wp_sitemaps_index'                           => 'wp-includes/sitemaps/class-wp-sitemaps-index.php',
		'wp_sitemaps_provider'                        => 'wp-includes/sitemaps/class-wp-sitemaps-provider.php',
		'wp_sitemaps_registry'                        => 'wp-includes/sitemaps/class-wp-sitemaps-registry.php',
		'wp_sitemaps_renderer'                        => 'wp-includes/sitemaps/class-wp-sitemaps-renderer.php',
		'wp_sitemaps_stylesheet'                      => 'wp-includes/sitemaps/class-wp-sitemaps-stylesheet.php',
		'wp_sitemaps'                                 => 'wp-includes/sitemaps/class-wp-sitemaps.php',

		/* Classes in wp-includes/style-engine. */
		'wp_style_engine_css_declarations'            => 'wp-includes/style-engine/class-wp-style-engine-css-declarations.php',
		'wp_style_engine_css_rule'                    => 'wp-includes/style-engine/class-wp-style-engine-css-rule.php',
		'wp_style_engine_css_rules_store'             => 'wp-includes/style-engine/class-wp-style-engine-css-rules-store.php',
		'wp_style_engine_processor'                   => 'wp-includes/style-engine/class-wp-style-engine-processor.php',
		'wp_style_engine'                             => 'wp-includes/style-engine/class-wp-style-engine.php',

		/* Classes in wp-includes/widgets. */
		'wp_nav_menu_widget'                          => 'wp-includes/widgets/class-wp-nav-menu-widget.php',
		'wp_widget_archives'                          => 'wp-includes/widgets/class-wp-widget-archives.php',
		'wp_widget_block'                             => 'wp-includes/widgets/class-wp-widget-block.php',
		'wp_widget_calendar'                          => 'wp-includes/widgets/class-wp-widget-calendar.php',
		'wp_widget_categories'                        => 'wp-includes/widgets/class-wp-widget-categories.php',
		'wp_widget_custom_html'                       => 'wp-includes/widgets/class-wp-widget-custom-html.php',
		'wp_widget_links'                             => 'wp-includes/widgets/class-wp-widget-links.php',
		'wp_widget_media_audio'                       => 'wp-includes/widgets/class-wp-widget-media-audio.php',
		'wp_widget_media_gallery'                     => 'wp-includes/widgets/class-wp-widget-media-gallery.php',
		'wp_widget_media_image'                       => 'wp-includes/widgets/class-wp-widget-media-image.php',
		'wp_widget_media_video'                       => 'wp-includes/widgets/class-wp-widget-media-video.php',
		'wp_widget_media'                             => 'wp-includes/widgets/class-wp-widget-media.php',
		'wp_widget_meta'                              => 'wp-includes/widgets/class-wp-widget-meta.php',
		'wp_widget_pages'                             => 'wp-includes/widgets/class-wp-widget-pages.php',
		'wp_widget_recent_comments'                   => 'wp-includes/widgets/class-wp-widget-recent-comments.php',
		'wp_widget_recent_posts'                      => 'wp-includes/widgets/class-wp-widget-recent-posts.php',
		'wp_widget_rss'                               => 'wp-includes/widgets/class-wp-widget-rss.php',
		'wp_widget_search'                            => 'wp-includes/widgets/class-wp-widget-search.php',
		'wp_widget_tag_cloud'                         => 'wp-includes/widgets/class-wp-widget-tag-cloud.php',
		'wp_widget_text'                              => 'wp-includes/widgets/class-wp-widget-text.php',

		/* Classes in wp-admin/includes. */
		'automatic_upgrader_skin'                     => 'wp-admin/includes/class-automatic-upgrader-skin.php',
		'bulk_plugin_upgrader_skin'                   => 'wp-admin/includes/class-bulk-plugin-upgrader-skin.php',
		'bulk_theme_upgrader_skin'                    => 'wp-admin/includes/class-bulk-theme-upgrader-skin.php',
		'bulk_upgrader_skin'                          => 'wp-admin/includes/class-bulk-upgrader-skin.php',
		'core_upgrader'                               => 'wp-admin/includes/class-core-upgrader.php',
		'custom_background'                           => 'wp-admin/includes/class-custom-background.php',
		'custom_image_header'                         => 'wp-admin/includes/class-custom-image-header.php',
		'file_upload_upgrader'                        => 'wp-admin/includes/class-file-upload-upgrader.php',
		'ftp'                                         => 'wp-admin/includes/class-ftp.php',
		'ftp_base'                                    => 'wp-admin/includes/class-ftp.php',
		'ftp_pure'                                    => 'wp-admin/includes/class-ftp-pure.php',
		'ftp_sockets'                                 => 'wp-admin/includes/class-ftp-sockets.php',
		'language_pack_upgrader_skin'                 => 'wp-admin/includes/class-language-pack-upgrader-skin.php',
		'language_pack_upgrader'                      => 'wp-admin/includes/class-language-pack-upgrader.php',
		'pclzip'                                      => 'wp-admin/includes/class-pclzip.php',
		'plugin_installer_skin'                       => 'wp-admin/includes/class-plugin-installer-skin.php',
		'plugin_upgrader_skin'                        => 'wp-admin/includes/class-plugin-upgrader-skin.php',
		'plugin_upgrader'                             => 'wp-admin/includes/class-plugin-upgrader.php',
		'theme_installer_skin'                        => 'wp-admin/includes/class-theme-installer-skin.php',
		'theme_upgrader_skin'                         => 'wp-admin/includes/class-theme-upgrader-skin.php',
		'theme_upgrader'                              => 'wp-admin/includes/class-theme-upgrader.php',
		'walker_category_checklist'                   => 'wp-admin/includes/class-walker-category-checklist.php',
		'walker_nav_menu_checklist'                   => 'wp-admin/includes/class-walker-nav-menu-checklist.php',
		'walker_nav_menu_edit'                        => 'wp-admin/includes/class-walker-nav-menu-edit.php',
		'wp_ajax_upgrader_skin'                       => 'wp-admin/includes/class-wp-ajax-upgrader-skin.php',
		'wp_application_passwords_list_table'         => 'wp-admin/includes/class-wp-application-passwords-list-table.php',
		'wp_automatic_updater'                        => 'wp-admin/includes/class-wp-automatic-updater.php',
		'wp_comments_list_table'                      => 'wp-admin/includes/class-wp-comments-list-table.php',
		'wp_community_events'                         => 'wp-admin/includes/class-wp-community-events.php',
		'wp_debug_data'                               => 'wp-admin/includes/class-wp-debug-data.php',
		'wp_filesystem_base'                          => 'wp-admin/includes/class-wp-filesystem-base.php',
		'wp_filesystem_direct'                        => 'wp-admin/includes/class-wp-filesystem-direct.php',
		'wp_filesystem_ftpext'                        => 'wp-admin/includes/class-wp-filesystem-ftpext.php',
		'wp_filesystem_ftpsockets'                    => 'wp-admin/includes/class-wp-filesystem-ftpsockets.php',
		'wp_filesystem_ssh2'                          => 'wp-admin/includes/class-wp-filesystem-ssh2.php',
		'wp_importer'                                 => 'wp-admin/includes/class-wp-importer.php', // Contains some additional functions.
		'wp_internal_pointers'                        => 'wp-admin/includes/class-wp-internal-pointers.php',
		'wp_links_list_table'                         => 'wp-admin/includes/class-wp-links-list-table.php',
		'_wp_list_table_compat'                       => 'wp-admin/includes/class-wp-list-table-compat.php',
		'wp_list_table'                               => 'wp-admin/includes/class-wp-list-table.php',
		'wp_media_list_table'                         => 'wp-admin/includes/class-wp-media-list-table.php',
		'wp_ms_sites_list_table'                      => 'wp-admin/includes/class-wp-ms-sites-list-table.php',
		'wp_ms_themes_list_table'                     => 'wp-admin/includes/class-wp-ms-themes-list-table.php',
		'wp_ms_users_list_table'                      => 'wp-admin/includes/class-wp-ms-users-list-table.php',
		'wp_plugin_install_list_table'                => 'wp-admin/includes/class-wp-plugin-install-list-table.php',
		'wp_plugins_list_table'                       => 'wp-admin/includes/class-wp-plugins-list-table.php',
		'wp_post_comments_list_table'                 => 'wp-admin/includes/class-wp-post-comments-list-table.php',
		'wp_posts_list_table'                         => 'wp-admin/includes/class-wp-posts-list-table.php',
		'wp_privacy_data_export_requests_list_table'  => 'wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php',
		'wp_privacy_data_removal_requests_list_table' => 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php',
		'wp_privacy_policy_content'                   => 'wp-admin/includes/class-wp-privacy-policy-content.php',
		'wp_privacy_requests_table'                   => 'wp-admin/includes/class-wp-privacy-requests-table.php',
		'wp_screen'                                   => 'wp-admin/includes/class-wp-screen.php',
		'wp_site_health_auto_updates'                 => 'wp-admin/includes/class-wp-site-health-auto-updates.php',
		'wp_site_health'                              => 'wp-admin/includes/class-wp-site-health.php',
		'wp_site_icon'                                => 'wp-admin/includes/class-wp-site-icon.php',
		'wp_terms_list_table'                         => 'wp-admin/includes/class-wp-terms-list-table.php',
		'wp_theme_install_list_table'                 => 'wp-admin/includes/class-wp-theme-install-list-table.php',
		'wp_themes_list_table'                        => 'wp-admin/includes/class-wp-themes-list-table.php',
		'wp_upgrader_skin'                            => 'wp-admin/includes/class-wp-upgrader-skin.php',
		'wp_upgrader'                                 => 'wp-admin/includes/class-wp-upgrader.php',
		'wp_users_list_table'                         => 'wp-admin/includes/class-wp-users-list-table.php',

		/* Classes in wp-admin/includes/deprecated.php. */
		'wp_user_search'                              => 'wp-admin/includes/deprecated.php',
		'wp_privacy_data_export_requests_table'       => 'wp-admin/includes/deprecated.php',
		'wp_privacy_data_removal_requests_table'      => 'wp-admin/includes/deprecated.php',
	);

	/**
	 * Whether the autoloader has already been registered or not.
	 *
	 * Avoid registering the autoloader multiple times.
	 *
	 * @static
	 * @access private
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		// Bail early if already registered.
		if ( self::$registered ) {
			return;
		}

		self::register_external_bundled();
		self::register_core();

		self::$registered = true;
	}

	/**
	 * Register the autoloader for external, bundled libraries.
	 *
	 * @return void
	 */
	public static function register_external_bundled() {
		require_once ABSPATH . 'wp-includes/Requests/src/Autoload.php';
		require_once ABSPATH . 'wp-includes/sodium_compat/autoload.php';

		spl_autoload_register( array( '\WpOrg\Requests\Autoload', 'load' ) );
	}

	/**
	 * Register the autoloader for WordPress Core classes.
	 *
	 * @return void
	 */
	public static function register_core() {
		spl_autoload_register( array( __CLASS__, 'autoload_core' ), true, true );
	}

	/**
	 * Autoload a WordPress class.
	 *
	 * @param string $class_name Class name.
	 * @return void
	 */
	public static function autoload_core( string $class_name ) {
		// Lowercase the classname to accommodate for WP classes written with wrong cases.
		$class_name = strtolower( $class_name );

		// Load Avifinfo classes.
		if ( str_starts_with( $class_name, 'avifinfo' ) ) {
			// This file contains multiple classes, so we need to use require_once.
			require_once ABSPATH . 'wp-includes/class-avif-info.php';
			return;
		}

		// Load SimplePie classes.
		if ( str_starts_with( $class_name, 'simplepie' ) ) {
			require_once ABSPATH . 'wp-includes/class-simplepie.php';
			return;
		}

		// Bail early if the class is not a WP class.
		// Use empty() instead of !isset() for performance reasons (saves a BOOL_NOT opcode).
		if ( empty( self::CLASSES_PATHS[ $class_name ] ) ) {
			return;
		}

		require ABSPATH . self::CLASSES_PATHS[ $class_name ];
	}
}

// Register the autoloader.
WP_Autoload::register();
