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
  name: 'cover',
  title: (0, _i18n.__)('Cover'),
  description: (0, _i18n.__)('Add an image or video with a text overlay.'),
  attributes: {
    layout: {
      type: 'constrained'
    }
  },
  isDefault: true,
  icon: _icons.cover
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map