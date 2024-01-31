"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _compose = require("@wordpress/compose");
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

const EmbedLoading = () => {
  const style = (0, _compose.usePreferredColorSchemeStyle)(_styles.default['embed-preview__loading'], _styles.default['embed-preview__loading--dark']);
  return (0, _react.createElement)(_reactNative.View, {
    style: style
  }, (0, _react.createElement)(_reactNative.ActivityIndicator, {
    animating: true
  }));
};
var _default = exports.default = EmbedLoading;
//# sourceMappingURL=embed-loading.native.js.map