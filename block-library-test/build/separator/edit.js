"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = SeparatorEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _useDeprecatedOpacity = _interopRequireDefault(require("./use-deprecated-opacity"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function SeparatorEdit({
  attributes,
  setAttributes
}) {
  const {
    backgroundColor,
    opacity,
    style
  } = attributes;
  const colorProps = (0, _blockEditor.__experimentalUseColorProps)(attributes);
  const currentColor = colorProps?.style?.backgroundColor;
  const hasCustomColor = !!style?.color?.background;
  (0, _useDeprecatedOpacity.default)(opacity, currentColor, setAttributes);

  // The dots styles uses text for the dots, to change those dots color is
  // using color, not backgroundColor.
  const colorClass = (0, _blockEditor.getColorClassName)('color', backgroundColor);
  const className = (0, _classnames.default)({
    'has-text-color': backgroundColor || currentColor,
    [colorClass]: colorClass,
    'has-css-opacity': opacity === 'css',
    'has-alpha-channel-opacity': opacity === 'alpha-channel'
  }, colorProps.className);
  const styles = {
    color: currentColor,
    backgroundColor: currentColor
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.HorizontalRule, {
    ...(0, _blockEditor.useBlockProps)({
      className,
      style: hasCustomColor ? styles : undefined
    })
  }));
}
//# sourceMappingURL=edit.js.map