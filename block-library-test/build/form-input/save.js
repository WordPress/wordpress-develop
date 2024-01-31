"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _removeAccents = _interopRequireDefault(require("remove-accents"));
var _blockEditor = require("@wordpress/block-editor");
var _dom = require("@wordpress/dom");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Get the name attribute from a content string.
 *
 * @param {string} content The block content.
 *
 * @return {string} Returns the slug.
 */
const getNameFromLabel = content => {
  return (0, _removeAccents.default)((0, _dom.__unstableStripHTML)(content))
  // Convert anything that's not a letter or number to a hyphen.
  .replace(/[^\p{L}\p{N}]+/gu, '-')
  // Convert to lowercase
  .toLowerCase()
  // Remove any remaining leading or trailing hyphens.
  .replace(/(^-+)|(-+$)/g, '');
};
function save({
  attributes
}) {
  const {
    type,
    name,
    label,
    inlineLabel,
    required,
    placeholder,
    value
  } = attributes;
  const borderProps = (0, _blockEditor.__experimentalGetBorderClassesAndStyles)(attributes);
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  const inputStyle = {
    ...borderProps.style,
    ...colorProps.style
  };
  const inputClasses = (0, _classnames.default)('wp-block-form-input__input', colorProps.className, borderProps.className);
  const TagName = type === 'textarea' ? 'textarea' : 'input';
  const blockProps = _blockEditor.useBlockProps.save();
  if ('hidden' === type) {
    return (0, _react.createElement)("input", {
      type: type,
      name: name,
      value: value
    });
  }
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)("label", {
    className: (0, _classnames.default)('wp-block-form-input__label', {
      'is-label-inline': inlineLabel
    })
  }, (0, _react.createElement)("span", {
    className: "wp-block-form-input__label-content"
  }, (0, _react.createElement)(_blockEditor.RichText.Content, {
    value: label
  })), (0, _react.createElement)(TagName, {
    className: inputClasses,
    type: 'textarea' === type ? undefined : type,
    name: name || getNameFromLabel(label),
    required: required,
    "aria-required": required,
    placeholder: placeholder || undefined,
    style: inputStyle
  })));
}
//# sourceMappingURL=save.js.map