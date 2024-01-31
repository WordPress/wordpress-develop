"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = OverlayMenuIcon;
var _react = require("react");
var _primitives = require("@wordpress/primitives");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function OverlayMenuIcon({
  icon
}) {
  if (icon === 'menu') {
    return (0, _react.createElement)(_icons.Icon, {
      icon: _icons.menu
    });
  }
  return (0, _react.createElement)(_primitives.SVG, {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    width: "24",
    height: "24",
    "aria-hidden": "true",
    focusable: "false"
  }, (0, _react.createElement)(_primitives.Rect, {
    x: "4",
    y: "7.5",
    width: "16",
    height: "1.5"
  }), (0, _react.createElement)(_primitives.Rect, {
    x: "4",
    y: "15",
    width: "16",
    height: "1.5"
  }));
}
//# sourceMappingURL=overlay-menu-icon.js.map