"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

function TagName(props, ref) {
  const {
    ordered,
    ...extraProps
  } = props;
  const Tag = ordered ? 'ol' : 'ul';
  return (0, _react.createElement)(Tag, {
    ref: ref,
    ...extraProps
  });
}
var _default = exports.default = (0, _element.forwardRef)(TagName);
//# sourceMappingURL=tag-name.js.map