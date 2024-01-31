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
  name: 'post-date-modified',
  title: (0, _i18n.__)('Modified Date'),
  description: (0, _i18n.__)("Display a post's last updated date."),
  attributes: {
    displayType: 'modified'
  },
  scope: ['block', 'inserter'],
  isActive: blockAttributes => blockAttributes.displayType === 'modified',
  icon: _icons.postDate
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map