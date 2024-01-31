"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.SELECT_NAVIGATION_MENUS_ARGS = exports.PRIORITIZED_INSERTER_BLOCKS = exports.PRELOADED_NAVIGATION_MENUS_QUERY = exports.NAVIGATION_MOBILE_COLLAPSE = exports.DEFAULT_BLOCK = void 0;
const DEFAULT_BLOCK = exports.DEFAULT_BLOCK = {
  name: 'core/navigation-link'
};
const PRIORITIZED_INSERTER_BLOCKS = exports.PRIORITIZED_INSERTER_BLOCKS = ['core/navigation-link/page', 'core/navigation-link'];

// These parameters must be kept aligned with those in
// lib/compat/wordpress-6.3/navigation-block-preloading.php
// and
// edit-site/src/components/sidebar-navigation-screen-navigation-menus/constants.js
const PRELOADED_NAVIGATION_MENUS_QUERY = exports.PRELOADED_NAVIGATION_MENUS_QUERY = {
  per_page: 100,
  status: ['publish', 'draft'],
  order: 'desc',
  orderby: 'date'
};
const SELECT_NAVIGATION_MENUS_ARGS = exports.SELECT_NAVIGATION_MENUS_ARGS = ['postType', 'wp_navigation', PRELOADED_NAVIGATION_MENUS_QUERY];
const NAVIGATION_MOBILE_COLLAPSE = exports.NAVIGATION_MOBILE_COLLAPSE = '600px';
//# sourceMappingURL=constants.js.map