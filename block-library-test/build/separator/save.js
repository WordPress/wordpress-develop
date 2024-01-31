"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = separatorSave;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function separatorSave({
  attributes
}) {
  const {
    backgroundColor,
    style,
    opacity
  } = attributes;
  const customColor = style?.color?.background;
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  // The hr support changing color using border-color, since border-color
  // is not yet supported in the color palette, we use background-color.

  // The dots styles uses text for the dots, to change those dots color is
  // using color, not backgroundColor.
  const colorClass = (0, _blockEditor.getColorClassName)('color', backgroundColor);
  const className = (0, _classnames.default)({
    'has-text-color': backgroundColor || customColor,
    [colorClass]: colorClass,
    'has-css-opacity': opacity === 'css',
    'has-alpha-channel-opacity': opacity === 'alpha-channel'
  }, colorProps.className);
  const styles = {
    backgroundColor: colorProps?.style?.backgroundColor,
    color: colorClass ? undefined : customColor
  };
  return (0, _react.createElement)("hr", {
    ..._blockEditor.useBlockProps.save({
      className,
      style: styles
    })
  });
}
//# sourceMappingURL=save.js.map