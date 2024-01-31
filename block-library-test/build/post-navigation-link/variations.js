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
  name: 'post-next',
  title: (0, _i18n.__)('Next post'),
  description: (0, _i18n.__)('Displays the post link that follows the current post.'),
  icon: _icons.next,
  attributes: {
    type: 'next'
  },
  scope: ['inserter', 'transform']
}, {
  name: 'post-previous',
  title: (0, _i18n.__)('Previous post'),
  description: (0, _i18n.__)('Displays the post link that precedes the current post.'),
  icon: _icons.previous,
  attributes: {
    type: 'previous'
  },
  scope: ['inserter', 'transform']
}];

/**
 * Add `isActive` function to all `post-navigation-link` variations, if not defined.
 * `isActive` function is used to find a variation match from a created
 *  Block by providing its attributes.
 */
variations.forEach(variation => {
  if (variation.isActive) return;
  variation.isActive = (blockAttributes, variationAttributes) => blockAttributes.type === variationAttributes.type;
});
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map