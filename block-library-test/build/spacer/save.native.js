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
  attributes: {
    height,
    width
  }
}) {
  return (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save({
      style: {
        height: (0, _blockEditor.getSpacingPresetCssVar)(height),
        width: (0, _blockEditor.getSpacingPresetCssVar)(width)
      },
      'aria-hidden': true
    })
  });
}
//# sourceMappingURL=save.native.js.map