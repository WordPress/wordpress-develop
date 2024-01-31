"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
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
    content,
    dropCap,
    direction
  } = attributes;
  const className = (0, _classnames.default)({
    'has-drop-cap': align === ((0, _i18n.isRTL)() ? 'left' : 'right') || align === 'center' ? false : dropCap,
    [`has-text-align-${align}`]: align
  });
  return (0, _react.createElement)("p", {
    ..._blockEditor.useBlockProps.save({
      className,
      dir: direction
    })
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map