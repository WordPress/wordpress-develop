"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
var _editor = _interopRequireDefault(require("./editor.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function EditTitle({
  getStylesFromColorScheme,
  title
}) {
  const globalStyles = (0, _components.useGlobalStyles)();
  const baseColors = globalStyles?.baseColors?.color;
  const lockIconStyle = [getStylesFromColorScheme(_editor.default.lockIcon, _editor.default.lockIconDark), baseColors && {
    color: baseColors.text
  }];
  const titleStyle = [getStylesFromColorScheme(_editor.default.title, _editor.default.titleDark), baseColors && {
    color: baseColors.text
  }];
  const infoIconStyle = [getStylesFromColorScheme(_editor.default.infoIcon, _editor.default.infoIconDark), baseColors && {
    color: baseColors.text
  }];
  const separatorStyle = getStylesFromColorScheme(_editor.default.separator, _editor.default.separatorDark);
  return (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.titleContainer
  }, (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.lockIconContainer
  }, (0, _react.createElement)(_components.Icon, {
    label: (0, _i18n.__)('Lock icon'),
    icon: _icons.lock,
    size: 16,
    style: lockIconStyle
  })), (0, _react.createElement)(_reactNative.Text, {
    numberOfLines: 1,
    style: titleStyle
  }, title), (0, _react.createElement)(_reactNative.View, {
    style: _editor.default.helpIconContainer
  }, (0, _react.createElement)(_components.Icon, {
    label: (0, _i18n.__)('Help icon'),
    icon: _icons.help,
    size: 20,
    style: infoIconStyle
  })), (0, _react.createElement)(_reactNative.View, {
    style: separatorStyle
  }));
}
var _default = exports.default = (0, _compose.withPreferredColorScheme)(EditTitle);
//# sourceMappingURL=edit-title.native.js.map