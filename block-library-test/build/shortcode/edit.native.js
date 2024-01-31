"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.ShortcodeEdit = ShortcodeEdit;
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
var _style = _interopRequireDefault(require("./style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ShortcodeEdit(props) {
  const {
    attributes,
    setAttributes,
    onFocus,
    onBlur,
    getStylesFromColorScheme,
    blockWidth
  } = props;
  const titleStyle = getStylesFromColorScheme(_style.default.blockTitle, _style.default.blockTitleDark);
  const shortcodeContainerStyle = getStylesFromColorScheme(_style.default.blockShortcodeContainer, _style.default.blockShortcodeContainerDark);
  const shortcodeStyle = getStylesFromColorScheme(_style.default.blockShortcode, _style.default.blockShortcodeDark);
  const placeholderStyle = getStylesFromColorScheme(_style.default.placeholder, _style.default.placeholderDark);
  const maxWidth = blockWidth - shortcodeContainerStyle.paddingLeft + shortcodeContainerStyle.paddingRight;
  const onChange = (0, _element.useCallback)(text => setAttributes({
    text
  }), [setAttributes]);
  return (0, _react.createElement)(_reactNative.View, null, (0, _react.createElement)(_reactNative.Text, {
    style: titleStyle
  }, (0, _i18n.__)('Shortcode')), (0, _react.createElement)(_reactNative.View, {
    style: shortcodeContainerStyle
  }, (0, _react.createElement)(_blockEditor.PlainText, {
    __experimentalVersion: 2,
    value: attributes.text,
    style: shortcodeStyle,
    onChange: onChange,
    placeholder: (0, _i18n.__)('Add a shortcode…'),
    onFocus: onFocus,
    onBlur: onBlur,
    placeholderTextColor: placeholderStyle.color,
    maxWidth: maxWidth,
    disableAutocorrection: true
  })));
}
var _default = exports.default = (0, _compose.withPreferredColorScheme)(ShortcodeEdit);
//# sourceMappingURL=edit.native.js.map