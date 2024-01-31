"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.CodeEdit = CodeEdit;
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _blocks = require("@wordpress/blocks");
var _theme = _interopRequireDefault(require("./theme.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Block code style
 */

function CodeEdit(props) {
  const {
    attributes,
    setAttributes,
    onRemove,
    style,
    insertBlocksAfter,
    mergeBlocks
  } = props;
  const codeStyle = {
    ...(0, _compose.usePreferredColorSchemeStyle)(_theme.default.blockCode, _theme.default.blockCodeDark)
  };
  const textStyle = style?.fontSize ? {
    fontSize: style.fontSize
  } : {};
  const placeholderStyle = (0, _compose.usePreferredColorSchemeStyle)(_theme.default.placeholder, _theme.default.placeholderDark);
  return (0, _react.createElement)(_reactNative.View, {
    style: codeStyle
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "pre",
    value: attributes.content,
    identifier: "content",
    style: textStyle,
    underlineColorAndroid: "transparent",
    onChange: content => setAttributes({
      content
    }),
    onMerge: mergeBlocks,
    onRemove: onRemove,
    placeholder: (0, _i18n.__)('Write code…'),
    "aria-label": (0, _i18n.__)('Code'),
    placeholderTextColor: placeholderStyle.color,
    preserveWhiteSpace: true,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }));
}
var _default = exports.default = CodeEdit;
//# sourceMappingURL=edit.native.js.map