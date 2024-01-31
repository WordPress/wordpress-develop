"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _native = require("@react-navigation/native");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _icons = require("@wordpress/icons");
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

function FocalPointSettingsButton({
  disabled,
  focalPoint,
  onFocalPointChange,
  url
}) {
  const navigation = (0, _native.useNavigation)();
  return (0, _react.createElement)(_components.BottomSheet.Cell, {
    customActionButton: true,
    disabled: disabled,
    labelStyle: disabled && _style.default.dimmedActionButton,
    leftAlign: true,
    label: (0, _i18n.__)('Edit focal point'),
    onPress: () => {
      navigation.navigate(_blockEditor.blockSettingsScreens.focalPoint, {
        focalPoint,
        onFocalPointChange,
        url
      });
    }
  }, (0, _react.createElement)(_reactNative.View, {
    style: disabled && _style.default.dimmedActionButton
  }, (0, _react.createElement)(_components.Icon, {
    icon: _icons.chevronRight
  })));
}
var _default = exports.default = FocalPointSettingsButton;
//# sourceMappingURL=focal-point-settings-button.native.js.map