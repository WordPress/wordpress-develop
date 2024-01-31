"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const PlaceholderPreview = ({
  isVisible = true
}) => {
  return (0, _react.createElement)("div", {
    "aria-hidden": !isVisible ? true : undefined,
    className: "wp-block-navigation-placeholder__preview"
  }, (0, _react.createElement)("div", {
    className: "wp-block-navigation-placeholder__actions__indicator"
  }, (0, _react.createElement)(_icons.Icon, {
    icon: _icons.navigation
  }), (0, _i18n.__)('Navigation')));
};
var _default = exports.default = PlaceholderPreview;
//# sourceMappingURL=placeholder-preview.js.map