"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function save({
  attributes
}) {
  const {
    align,
    citation
  } = attributes;
  const className = (0, _classnames.default)({
    [`has-text-align-${align}`]: align
  });
  return (0, _react.createElement)("blockquote", {
    ..._blockEditor.useBlockProps.save({
      className
    })
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null), !_blockEditor.RichText.isEmpty(citation) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "cite",
    value: citation
  }));
}
//# sourceMappingURL=save.js.map