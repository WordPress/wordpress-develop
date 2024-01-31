"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function save({
  attributes,
  className
}) {
  const {
    tagName,
    type,
    textAlign,
    fontSize,
    linkTarget,
    rel,
    style,
    text,
    title,
    url,
    width
  } = attributes;
  if (_blockEditor.RichText.isEmpty(text)) {
    return null;
  }
  const TagName = tagName || 'a';
  const isButtonTag = 'button' === TagName;
  const buttonType = type || 'button';
  const borderProps = (0, _blockEditor.__experimentalGetBorderClassesAndStyles)(attributes);
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  const spacingProps = (0, _blockEditor.__experimentalGetSpacingClassesAndStyles)(attributes);
  const shadowProps = (0, _blockEditor.__experimentalGetShadowClassesAndStyles)(attributes);
  const buttonClasses = (0, _classnames.default)('wp-block-button__link', colorProps.className, borderProps.className, {
    [`has-text-align-${textAlign}`]: textAlign,
    // For backwards compatibility add style that isn't provided via
    // block support.
    'no-border-radius': style?.border?.radius === 0
  }, (0, _blockEditor.__experimentalGetElementClassName)('button'));
  const buttonStyle = {
    ...borderProps.style,
    ...colorProps.style,
    ...spacingProps.style,
    ...shadowProps.style
  };

  // The use of a `title` attribute here is soft-deprecated, but still applied
  // if it had already been assigned, for the sake of backward-compatibility.
  // A title will no longer be assigned for new or updated button block links.

  const wrapperClasses = (0, _classnames.default)(className, {
    [`has-custom-width wp-block-button__width-${width}`]: width,
    [`has-custom-font-size`]: fontSize || style?.typography?.fontSize
  });
  return (0, _react.createElement)("div", {
    ..._blockEditor.useBlockProps.save({
      className: wrapperClasses
    })
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: TagName,
    type: isButtonTag ? buttonType : null,
    className: buttonClasses,
    href: isButtonTag ? null : url,
    title: title,
    style: buttonStyle,
    value: text,
    target: isButtonTag ? null : linkTarget,
    rel: isButtonTag ? null : rel
  }));
}
//# sourceMappingURL=save.js.map