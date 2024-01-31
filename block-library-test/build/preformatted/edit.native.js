"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PreformattedEdit;
var _react = require("react");
var _reactNative = require("react-native");
var _compose = require("@wordpress/compose");
var _edit = _interopRequireDefault(require("./edit.js"));
var _styles = _interopRequireDefault(require("./styles.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function PreformattedEdit(props) {
  const {
    style
  } = props;
  const textBaseStyle = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.wpRichTextLight, _styles.default.wpRichTextDark);
  const wpBlockPreformatted = (0, _compose.usePreferredColorSchemeStyle)(_styles.default.wpBlockPreformattedLight, _styles.default.wpBlockPreformattedDark);
  const richTextStyle = {
    ...(!style?.baseColors && textBaseStyle),
    ...(style?.fontSize && {
      fontSize: style.fontSize
    }),
    ...(style?.color && {
      color: style.color
    })
  };
  const containerStyles = [wpBlockPreformatted, style?.backgroundColor && {
    backgroundColor: style.backgroundColor
  }, style?.baseColors?.color && !style?.backgroundColor && _styles.default['wp-block-preformatted__no-background']];
  const propsWithStyle = {
    ...props,
    style: richTextStyle
  };
  return (0, _react.createElement)(_reactNative.View, {
    style: containerStyles
  }, (0, _react.createElement)(_edit.default, {
    ...propsWithStyle
  }));
}
//# sourceMappingURL=edit.native.js.map