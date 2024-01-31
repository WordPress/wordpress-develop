"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.Figure = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _compose = require("@wordpress/compose");
var _figure = _interopRequireDefault(require("./figure.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const Figure = ({
  children,
  backgroundColor,
  borderColor
}) => {
  const wpPullquoteFigure = (0, _compose.usePreferredColorSchemeStyle)(_figure.default.light, _figure.default.dark);
  const customStyles = {};
  if (borderColor) {
    customStyles.borderTopColor = borderColor;
    customStyles.borderBottomColor = borderColor;
  }
  if (backgroundColor) {
    customStyles.backgroundColor = backgroundColor;
  }
  return (0, _react.createElement)(_reactNative.View, {
    style: [wpPullquoteFigure, customStyles]
  }, children);
};
exports.Figure = Figure;
//# sourceMappingURL=figure.native.js.map