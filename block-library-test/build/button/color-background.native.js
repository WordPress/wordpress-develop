"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
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

function ColorBackground({
  children,
  borderRadiusValue,
  backgroundColor
}) {
  const {
    isGradient
  } = _components.colorsUtils;
  const wrapperStyles = [_editor.default.richTextWrapper, {
    borderRadius: borderRadiusValue,
    backgroundColor
  }];
  return (0, _react.createElement)(_reactNative.View, {
    style: wrapperStyles
  }, isGradient(backgroundColor) && (0, _react.createElement)(_components.Gradient, {
    gradientValue: backgroundColor,
    angleCenter: {
      x: 0.5,
      y: 0.5
    },
    style: [_editor.default.linearGradient, {
      borderRadius: borderRadiusValue
    }]
  }), children);
}
var _default = exports.default = ColorBackground;
//# sourceMappingURL=color-background.native.js.map