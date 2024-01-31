"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.BlockQuote = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _element = require("@wordpress/element");
var _blockquote = _interopRequireDefault(require("./blockquote.scss"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const BlockQuote = props => {
  const citationStyle = {
    ..._blockquote.default.citation
  };
  const quoteStyle = {
    ..._blockquote.default.quote
  };
  if (props.textColor) {
    quoteStyle.color = props.textColor;
    quoteStyle.placeholderColor = props.textColor;
    citationStyle.color = props.textColor;
    citationStyle.placeholderColor = props.textColor;
  }
  const newChildren = _element.Children.map(props.children, child => {
    if (child && child.props.identifier === 'value') {
      return (0, _element.cloneElement)(child, {
        style: quoteStyle
      });
    }
    if (child && child.props.identifier === 'citation') {
      return (0, _element.cloneElement)(child, {
        style: citationStyle
      });
    }
    return child;
  });
  return (0, _react.createElement)(_reactNative.View, null, newChildren);
};
exports.BlockQuote = BlockQuote;
//# sourceMappingURL=blockquote.native.js.map