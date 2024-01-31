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
    textAlign,
    citation,
    value
  } = attributes;
  const shouldShowCitation = !_blockEditor.RichText.isEmpty(citation);
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save({
      className: (0, _classnames.default)({
        [`has-text-align-${textAlign}`]: textAlign
      })
    })
  }, (0, _react.createElement)("blockquote", null, (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "p",
    value: value
  }), shouldShowCitation && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "cite",
    value: citation
  })));
}
//# sourceMappingURL=save.js.map