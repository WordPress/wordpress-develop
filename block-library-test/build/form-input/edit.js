"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function InputFieldBlock({
  attributes,
  setAttributes,
  className
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
  const blockProps = (0, _blockEditor.useBlockProps)();
  const ref = (0, _element.useRef)();
  const TagName = type === 'textarea' ? 'textarea' : 'input';
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const colorProps = (0, _blockEditor.__experimentalUseColorProps)(attributes);
  if (ref.current) {
    ref.current.focus();
  }
  const controls = (0, _react.createElement)(_react.Fragment, null, 'hidden' !== type && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Input settings')
  }, 'checkbox' !== type && (0, _react.createElement)(_components.CheckboxControl, {
    label: (0, _i18n.__)('Inline label'),
    checked: inlineLabel,
    onChange: newVal => {
      setAttributes({
        inlineLabel: newVal
      });
    }
  }), (0, _react.createElement)(_components.CheckboxControl, {
    label: (0, _i18n.__)('Required'),
    checked: required,
    onChange: newVal => {
      setAttributes({
        required: newVal
      });
    }
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.TextControl, {
    autoComplete: "off",
    label: (0, _i18n.__)('Name'),
    value: name,
    onChange: newVal => {
      setAttributes({
        name: newVal
      });
    },
    help: (0, _i18n.__)('Affects the "name" atribute of the input element, and is used as a name for the form submission results.')
  })));
  if ('hidden' === type) {
    return (0, _react.createElement)(_react.Fragment, null, controls, (0, _react.createElement)("input", {
      type: "hidden",
      className: (0, _classnames.default)(className, 'wp-block-form-input__input', colorProps.className, borderProps.className),
      "aria-label": (0, _i18n.__)('Value'),
      value: value,
      onChange: event => setAttributes({
        value: event.target.value
      })
    }));
  }
  return (0, _react.createElement)("div", {
    ...blockProps
  }, controls, (0, _react.createElement)("span", {
    className: (0, _classnames.default)('wp-block-form-input__label', {
      'is-label-inline': inlineLabel || 'checkbox' === type
    })
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "span",
    className: "wp-block-form-input__label-content",
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    }),
    "aria-label": label ? (0, _i18n.__)('Label') : (0, _i18n.__)('Empty label'),
    "data-empty": label ? false : true,
    placeholder: (0, _i18n.__)('Type the label for this input')
  }), (0, _react.createElement)(TagName, {
    type: 'textarea' === type ? undefined : type,
    className: (0, _classnames.default)(className, 'wp-block-form-input__input', colorProps.className, borderProps.className),
    "aria-label": (0, _i18n.__)('Optional placeholder text')
    // We hide the placeholder field's placeholder when there is a value. This
    // stops screen readers from reading the placeholder field's placeholder
    // which is confusing.
    ,
    placeholder: placeholder ? undefined : (0, _i18n.__)('Optional placeholder…'),
    value: placeholder,
    onChange: event => setAttributes({
      placeholder: event.target.value
    }),
    "aria-required": required,
    style: {
      ...borderProps.style,
      ...colorProps.style
    }
  })));
}
var _default = exports.default = InputFieldBlock;
//# sourceMappingURL=edit.js.map