"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.getNameBySite = exports.getIconBySite = void 0;
var _i18n = require("@wordpress/i18n");
var _variations = _interopRequireDefault(require("./variations"));
var _icons = require("./icons");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Retrieves the social service's icon component.
 *
 * @param {string} name key for a social service (lowercase slug)
 *
 * @return {Component} Icon component for social service.
 */
const getIconBySite = name => {
  const variation = _variations.default.find(v => v.name === name);
  return variation ? variation.icon : _icons.ChainIcon;
};

/**
 * Retrieves the display name for the social service.
 *
 * @param {string} name key for a social service (lowercase slug)
 *
 * @return {string} Display name for social service
 */
exports.getIconBySite = getIconBySite;
const getNameBySite = name => {
  const variation = _variations.default.find(v => v.name === name);
  return variation ? variation.title : (0, _i18n.__)('Social Icon');
};
exports.getNameBySite = getNameBySite;
//# sourceMappingURL=social-list.js.map