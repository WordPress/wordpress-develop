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
    showContent
  } = attributes;
  const summary = attributes.summary ? attributes.summary : 'Details';
  const blockProps = _blockEditor.useBlockProps.save();
  return (0, _react.createElement)("details", {
    ...blockProps,
    open: showContent
  }, (0, _react.createElement)("summary", null, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: summary
  })), (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map