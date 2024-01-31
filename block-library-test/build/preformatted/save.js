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
    content
  } = attributes;
  return (0, _react.createElement)("pre", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map