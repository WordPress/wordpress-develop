"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = AccessibleDescription;
var _react = require("react");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

function AccessibleDescription({
  id,
  children
}) {
  return (0, _react.createElement)(_components.VisuallyHidden, null, (0, _react.createElement)("div", {
    id: id,
    className: "wp-block-navigation__description"
  }, children));
}
//# sourceMappingURL=accessible-description.js.map