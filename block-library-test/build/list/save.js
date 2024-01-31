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
    ordered,
    type,
    reversed,
    start
  } = attributes;
  const TagName = ordered ? 'ol' : 'ul';
  return (0, _react.createElement)(TagName, {
    ..._blockEditor.useBlockProps.save({
      reversed,
      start,
      style: {
        listStyleType: ordered && type !== 'decimal' ? type : undefined
      }
    })
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map