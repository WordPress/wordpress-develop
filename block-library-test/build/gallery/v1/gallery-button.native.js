"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.Button = Button;
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _components = require("@wordpress/components");
var _galleryImageStyle = _interopRequireDefault(require("./gallery-image-style.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function Button(props) {
  const {
    icon,
    iconSize = 24,
    onClick,
    disabled,
    'aria-disabled': ariaDisabled,
    accessibilityLabel = 'button',
    style: customStyle
  } = props;
  const buttonStyle = _reactNative.StyleSheet.compose(_galleryImageStyle.default.buttonActive, customStyle);
  const isDisabled = disabled || ariaDisabled;
  const {
    fill
  } = isDisabled ? _galleryImageStyle.default.buttonDisabled : _galleryImageStyle.default.button;
  return (0, _react.createElement)(_reactNative.TouchableOpacity, {
    style: buttonStyle,
    activeOpacity: 0.7,
    accessibilityLabel: accessibilityLabel,
    accessibilityRole: 'button',
    onPress: onClick,
    disabled: isDisabled
  }, (0, _react.createElement)(_components.Icon, {
    icon: icon,
    fill: fill,
    size: iconSize
  }));
}
var _default = exports.default = Button;
//# sourceMappingURL=gallery-button.native.js.map