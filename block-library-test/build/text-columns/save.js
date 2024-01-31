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
    width,
    content,
    columns
  } = attributes;
  return (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save({
      className: `align${width} columns-${columns}`
    })
  }, Array.from({
    length: columns
  }).map((_, index) => (0, _react.createElement)("div", {
    className: "wp-block-column",
    key: `column-${index}`
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "p",
    value: content?.[index]?.children
  }))));
}
//# sourceMappingURL=save.js.map