"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function save({
  attributes
}) {
  const {
    height,
    width,
    style
  } = attributes;
  const {
    layout: {
      selfStretch
    } = {}
  } = style || {};
  // If selfStretch is set to 'fill' or 'fit', don't set default height.
  const finalHeight = selfStretch === 'fill' || selfStretch === 'fit' ? undefined : height;
  return (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save({
      style: {
        height: (0, _blockEditor.getSpacingPresetCssVar)(finalHeight),
        width: (0, _blockEditor.getSpacingPresetCssVar)(width)
      },
      'aria-hidden': true
    })
  });
}
//# sourceMappingURL=save.js.map