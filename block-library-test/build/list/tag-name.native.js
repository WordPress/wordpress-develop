"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _element = require("@wordpress/element");
var _primitives = require("@wordpress/primitives");
/**
 * WordPress dependencies
 */

function TagName(props, ref) {
  const {
    start,
    ...extraProps
  } = props;
  return (0, _react.createElement)(_primitives.View, {
    ref: ref,
    ...extraProps
  });
}
var _default = exports.default = (0, _element.forwardRef)(TagName);
//# sourceMappingURL=tag-name.native.js.map