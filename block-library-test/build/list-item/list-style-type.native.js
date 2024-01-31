"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ListStyleType;
var _react = require("react");
var _reactNative = require("react-native");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _style = _interopRequireDefault(require("./style.scss"));
var _icons = require("./icons");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DEFAULT_ICON_SIZE = 6;
function getListNumberIndex(start, blockIndex, reversed, numberOfListItems) {
  if (start) {
    return reversed ? numberOfListItems - 1 + start - blockIndex : start + blockIndex;
  }
  if (reversed) {
    return numberOfListItems - blockIndex;
  }
  return blockIndex + 1;
}
function OrderedList({
  blockIndex,
  color,
  fontSize,
  numberOfListItems,
  reversed,
  start,
  style
}) {
  const orderedStyles = [_style.default['wp-block-list-item__list-item-container--ordered'], _element.Platform.isIOS && _style.default['wp-block-list-item__list-item-ordered--default'], _element.Platform.isIOS && style?.fontSize && _style.default['wp-block-list-item__list-item-ordered--custom']];
  const numberStyle = [{
    fontSize,
    color
  }];
  const currentIndex = getListNumberIndex(start, blockIndex, reversed, numberOfListItems);
  return (0, _react.createElement)(_reactNative.View, {
    style: orderedStyles
  }, (0, _react.createElement)(_reactNative.Text, {
    style: numberStyle
  }, currentIndex, "."));
}
function IconList({
  fontSize,
  color,
  defaultFontSize,
  indentationLevel
}) {
  const iconSize = parseInt(fontSize * DEFAULT_ICON_SIZE / defaultFontSize, 10);
  let listIcon = (0, _icons.circle)(iconSize, color);
  if (indentationLevel === 1) {
    listIcon = (0, _icons.circleOutline)(iconSize, color);
  } else if (indentationLevel > 1) {
    listIcon = (0, _icons.square)(iconSize, color);
  }
  const listStyles = [_style.default['wp-block-list-item__list-item-container'], {
    marginTop: fontSize / 2
  }];
  return (0, _react.createElement)(_reactNative.View, {
    style: listStyles
  }, (0, _react.createElement)(_components.Icon, {
    icon: listIcon,
    size: iconSize
  }));
}
function ListStyleType({
  blockIndex,
  indentationLevel,
  numberOfListItems,
  ordered,
  reversed,
  start,
  style
}) {
  let defaultFontSize = _style.default['wp-block-list-item__list-item--default'].fontSize;
  if (style?.baseColors?.typography?.fontSize) {
    defaultFontSize = parseInt(style.baseColors.typography.fontSize, 10);
  }
  const fontSize = parseInt(style?.fontSize ? style.fontSize : defaultFontSize, 10);
  const colorWithPreferredScheme = (0, _compose.usePreferredColorSchemeStyle)(_style.default['wp-block-list-item__list-item--default'], _style.default['wp-block-list-item__list-item--default--dark']);
  const defaultColor = style?.baseColors?.color?.text ? style.baseColors.color.text : colorWithPreferredScheme.color;
  const color = style?.color ? style.color : defaultColor;
  if (ordered) {
    return (0, _react.createElement)(OrderedList, {
      blockIndex: blockIndex,
      color: color,
      fontSize: fontSize,
      numberOfListItems: numberOfListItems,
      reversed: reversed,
      start: start,
      style: style
    });
  }
  return (0, _react.createElement)(IconList, {
    color: color,
    defaultFontSize: defaultFontSize,
    fontSize: fontSize,
    indentationLevel: indentationLevel
  });
}
//# sourceMappingURL=list-style-type.native.js.map