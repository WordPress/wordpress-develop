"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
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

const getNameFromLabelV1 = content => {
  return (0, _removeAccents.default)((0, _dom.__unstableStripHTML)(content))
  // Convert anything that's not a letter or number to a hyphen.
  .replace(/[^\p{L}\p{N}]+/gu, '-')
  // Convert to lowercase
  .toLowerCase()
  // Remove any remaining leading or trailing hyphens.
  .replace(/(^-+)|(-+$)/g, '');
};

// Version without wrapper div in saved markup
// See: https://github.com/WordPress/gutenberg/pull/56507
const v1 = {
  attributes: {
    type: {
      type: 'string',
      default: 'text'
    },
    name: {
      type: 'string'
    },
    label: {
      type: 'string',
      default: 'Label',
      selector: '.wp-block-form-input__label-content',
      source: 'html',
      __experimentalRole: 'content'
    },
    inlineLabel: {
      type: 'boolean',
      default: false
    },
    required: {
      type: 'boolean',
      default: false,
      selector: '.wp-block-form-input__input',
      source: 'attribute',
      attribute: 'required'
    },
    placeholder: {
      type: 'string',
      selector: '.wp-block-form-input__input',
      source: 'attribute',
      attribute: 'placeholder',
      __experimentalRole: 'content'
    },
    value: {
      type: 'string',
      default: '',
      selector: 'input',
      source: 'attribute',
      attribute: 'value'
    },
    visibilityPermissions: {
      type: 'string',
      default: 'all'
    }
  },
  supports: {
    className: false,
    anchor: true,
    reusable: false,
    spacing: {
      margin: ['top', 'bottom']
    },
    __experimentalBorder: {
      radius: true,
      __experimentalSkipSerialization: true,
      __experimentalDefaultControls: {
        radius: true
      }
    }
  },
  save({
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
    if ('hidden' === type) {
      return (0, _react.createElement)("input", {
        type: type,
        name: name,
        value: value
      });
    }

    /* eslint-disable jsx-a11y/label-has-associated-control */
    return (0, _react.createElement)("label", {
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
      name: name || getNameFromLabelV1(label),
      required: required,
      "aria-required": required,
      placeholder: placeholder || undefined,
      style: inputStyle
    }));
    /* eslint-enable jsx-a11y/label-has-associated-control */
  }
};
const deprecated = [v1];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map