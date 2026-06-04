# Test Progress: src/wp-admin/includes/ajax-actions.php
**Master Ticket: [65252](https://core.trac.wordpress.org/ticket/65252)**

This file tracks the status of unit tests for functions in `src/wp-admin/includes/ajax-actions.php`.

## Completed Functions (Tests Found)

| Function | Status |
| :--- | :--- |
| `wp_ajax_ajax_tag_search` | ✅ Found |
| `wp_ajax_wp_compression_test` | ✅ Found |
| `_wp_ajax_delete_comment_response` | ✅ Found |
| `wp_ajax_delete_comment` | ✅ Found |
| `wp_ajax_dim_comment` | ✅ Found |
| `wp_ajax_add_tag` | ✅ Found |
| `wp_ajax_get_comments` | ✅ Found |
| `wp_ajax_replyto_comment` | ✅ Found |
| `wp_ajax_edit_comment` | ✅ Found |
| `wp_ajax_add_meta` | ✅ Found |
| `wp_ajax_inline_save` | ✅ Found |
| `wp_ajax_image_editor` | ✅ Found |
| `wp_ajax_set_attachment_thumbnail` | ✅ Found |
| `wp_ajax_send_attachment_to_editor` | ✅ Found |
| `wp_ajax_heartbeat` | ✅ Found |
| `wp_ajax_crop_image` | ✅ Found |
| `wp_ajax_update_theme` | ✅ Found |
| `wp_ajax_update_plugin` | ✅ Found |
| `wp_ajax_delete_plugin` | ✅ Found |
| `wp_ajax_wp_privacy_export_personal_data` | ✅ Found |
| `wp_ajax_wp_privacy_erase_personal_data` | ✅ Found |
| `wp_ajax_parse_media_shortcode` | ✅ Found |

## Missing Functions (Tests Not Found)
These tests were moved to tests/phpunit/tests/admin/includes/ajax-actions in https://core.trac.wordpress.org/ticket/65226

| Function                                    | Status    | Ticket                                                  | Pull Request                                   | commited | 
|:--------------------------------------------|:----------|:--------------------------------------------------------|:-----------------------------------------------|:---------|
| `wp_ajax_nopriv_heartbeat`                  | created   | ✅ [65236](https://core.trac.wordpress.org/ticket/65236) | `65236-ajax-actions-wp_ajax_nopriv_heartbeat`  |          |
|                                             |           |                                                         |                                                |          |
| `wp_ajax_fetch_list`                        | created   | ✅ [65237](https://core.trac.wordpress.org/ticket/65237) | `65237-ajax-actions-wp_ajax_fetch_list`        |          |
| `wp_ajax_imgedit_preview`                   | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-wp_ajax_imgedit_preview`   |          |
| `wp_ajax_oembed_cache`                      | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-wp_ajax_oembed_cache`      |          |
| `wp_ajax_autocomplete_user`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-wp_ajax_autocomplete_user` |          |
| `wp_ajax_get_community_events`              | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-get_community_events`      |          |
| `wp_ajax_dashboard_widgets`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-dashboard_widgets`         |          |
| `wp_ajax_logged_in`                         | created   | ✅ [65242](https://core.trac.wordpress.org/ticket/65242) | `65242-ajax-actions-wp_ajax_logged_in`         |          |
| `_wp_ajax_add_hierarchical_term`            | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-add_hierarchical_term`     |          |
| `wp_ajax_delete_tag`                        | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-delete_tag`                |          |
| `wp_ajax_delete_link`                       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-delete_link`               |          |
| `wp_ajax_delete_meta`                       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-delete_meta`               |          |
| `wp_ajax_delete_post`                       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-delete_post`               |          |
| `wp_ajax_trash_post`                        | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-trash_post`                |          |
| `wp_ajax_untrash_post`                      | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-untrash_post`              |          |
| `wp_ajax_delete_page`                       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-delete_page`               |          |
| `wp_ajax_add_link_category`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-add_link_category`         |          |
| `wp_ajax_get_tagcloud`                      | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-get_tagcloud`              |          |
| `wp_ajax_add_menu_item`                     | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-add_menu_item`             |          |
| `wp_ajax_add_user`                          | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-add_user`                  |          |
| `wp_ajax_closed_postboxes`                  | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-closed_postboxes`          |          |
| `wp_ajax_hidden_columns`                    | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-hidden_columns`            |          |
| `wp_ajax_update_welcome_panel`              | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-update_welcome_panel`      |          |
| `wp_ajax_menu_get_metabox`                  | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-menu_get_metabox`          |          |
| `wp_ajax_wp_link_ajax`                      | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-wp_link_ajax`              |          |
| `wp_ajax_menu_locations_save`               | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-menu_locations_save`       |          |
| `wp_ajax_meta_box_order`                    | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-meta_box_order`            |          |
| `wp_ajax_menu_quick_search`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-menu_quick_search`         |          |
| `wp_ajax_get_permalink`                     | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-get_permalink`             |          |
| `wp_ajax_sample_permalink`                  | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_inline_save_tax`                   | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-inline_save_tax`           |          |
| `wp_ajax_find_posts`                        | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-find_posts`                |          |
| `wp_ajax_widgets_order`                     | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-widgets_order`             |          |
| `wp_ajax_save_widget`                       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_update_widget`                     | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_delete_inactive_widgets`           | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_delete_inactive_widgets`                | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_media_create_image_subsizes`       | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_upload_attachment`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_set_post_thumbnail`                | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_get_post_thumbnail_html`           | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_date_format`                       | created   | ✅ [65225](https://core.trac.wordpress.org/ticket/65225) | `65225-ajax-date_format`                       |          |
| `wp_ajax_time_format`                       | created   | ✅ [65228](https://core.trac.wordpress.org/ticket/65228) | `65228-ajax-time_format`                       |          |
| `wp_ajax_wp_fullscreen_save_post`           | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_wp_remove_post_lock`               | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_dismiss_wp_pointer`                | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_get_attachment`                    | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_query_attachments`                 | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_save_attachment`                   | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_save_attachment_compat`            | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-split-tests`               | ✅       |
| `wp_ajax_save_attachment_order`             | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-save-attachment-order`      |          |
| `wp_ajax_send_attachment_to_editor`         | created   | ✅ [65252](https://core.trac.wordpress.org/ticket/65252) | `65252-ajax-actions-save-attachment-order`      |          |
| `wp_ajax_send_link_to_editor`               | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_get_revision_diffs`                | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_save_user_color_scheme`            | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_query_themes`                      | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_parse_embed`                       | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_destroy_sessions`                  | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_generate_password`                 | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_nopriv_generate_password`          | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_save_wporg_username`               | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_install_theme`                     | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_delete_theme`                      | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_install_plugin`                    | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_activate_plugin`                   | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_search_plugins`                    | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_search_install_plugins`            | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_edit_theme_plugin_file`            | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_health_check_dotorg_communication` | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_health_check_background_updates`   | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_health_check_loopback_requests`    | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_health_check_site_status_result`   | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_health_check_get_sizes`            | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_rest_nonce`                        | created   | ✅ [65243](https://core.trac.wordpress.org/ticket/65243) | `65243-ajax-actions-wp_ajax_rest_nonce`        |          |
| `wp_ajax_toggle_auto_updates`               | ❌ Missing |                                                         |                                                |          |
| `wp_ajax_send_password_reset`               | ❌ Missing |                                                         |                                                |          |

---
*Last updated: 2026-05-15*
