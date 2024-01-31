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
  return (0, _react.createElement)("li", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: attributes.content
  }), (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map