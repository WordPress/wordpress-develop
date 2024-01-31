"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.NextPageEdit = NextPageEdit;
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _components = require("@wordpress/components");
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

function NextPageEdit({
  attributes,
  isSelected,
  onFocus,
  getStylesFromColorScheme
}) {
  const {
    customText = (0, _i18n.__)('Page break')
  } = attributes;
  const accessibilityTitle = attributes.customText || '';
  const accessibilityState = isSelected ? ['selected'] : [];
  const textStyle = getStylesFromColorScheme(_editor.default.nextpageText, _editor.default.nextpageTextDark);
  const lineStyle = getStylesFromColorScheme(_editor.default.nextpageLine, _editor.default.nextpageLineDark);
  return (0, _react.createElement)(_reactNative.View, {
    accessible: true,
    accessibilityLabel: (0, _i18n.sprintf)( /* translators: accessibility text. %s: Page break text. */
    (0, _i18n.__)('Page break block. %s'), accessibilityTitle),
    accessibilityStates: accessibilityState,
    onAccessibilityTap: onFocus
  }, (0, _react.createElement)(_components.HorizontalRule, {
    text: customText,
    marginLeft: 0,
    marginRight: 0,
    textStyle: textStyle,
    lineStyle: lineStyle
  }));
}
var _default = exports.default = (0, _compose.withPreferredColorScheme)(NextPageEdit);
//# sourceMappingURL=edit.native.js.map