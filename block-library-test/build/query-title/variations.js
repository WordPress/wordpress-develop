"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

const variations = [{
  isDefault: true,
  name: 'archive-title',
  title: (0, _i18n.__)('Archive Title'),
  description: (0, _i18n.__)('Display the archive title based on the queried object.'),
  icon: _icons.title,
  attributes: {
    type: 'archive'
  },
  scope: ['inserter']
}, {
  isDefault: false,
  name: 'search-title',
  title: (0, _i18n.__)('Search Results Title'),
  description: (0, _i18n.__)('Display the search results title based on the queried object.'),
  icon: _icons.title,
  attributes: {
    type: 'search'
  },
  scope: ['inserter']
}];

/**
 * Add `isActive` function to all `query-title` variations, if not defined.
 * `isActive` function is used to find a variation match from a created
 *  Block by providing its attributes.
 */
variations.forEach(variation => {
  if (variation.isActive) return;
  variation.isActive = (blockAttributes, variationAttributes) => blockAttributes.type === variationAttributes.type;
});
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map