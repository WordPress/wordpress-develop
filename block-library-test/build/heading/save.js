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
    content,
    level
  } = attributes;
  const TagName = 'h' + level;
  const className = (0, _classnames.default)({
    [`has-text-align-${textAlign}`]: textAlign
  });
  return (0, _react.createElement)(TagName, {
    ..._blockEditor.useBlockProps.save({
      className
    })
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map