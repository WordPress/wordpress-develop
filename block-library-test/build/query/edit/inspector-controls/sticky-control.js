"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = StickyControl;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const stickyOptions = [{
  label: (0, _i18n.__)('Include'),
  value: ''
}, {
  label: (0, _i18n.__)('Exclude'),
  value: 'exclude'
}, {
  label: (0, _i18n.__)('Only'),
  value: 'only'
}];
function StickyControl({
  value,
  onChange
}) {
  return (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Sticky posts'),
    options: stickyOptions,
    value: value,
    onChange: onChange,
    help: (0, _i18n.__)('Blog posts can be “stickied”, a feature that places them at the top of the front page of posts, keeping it there until new sticky posts are published.')
  });
}
//# sourceMappingURL=sticky-control.js.map