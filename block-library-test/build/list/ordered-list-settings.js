"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

const OrderedListSettings = ({
  setAttributes,
  reversed,
  start,
  type
}) => (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
  title: (0, _i18n.__)('Ordered list settings')
}, (0, _react.createElement)(_components.TextControl, {
  __nextHasNoMarginBottom: true,
  label: (0, _i18n.__)('Start value'),
  type: "number",
  onChange: value => {
    const int = parseInt(value, 10);
    setAttributes({
      // It should be possible to unset the value,
      // e.g. with an empty string.
      start: isNaN(int) ? undefined : int
    });
  },
  value: Number.isInteger(start) ? start.toString(10) : '',
  step: "1"
}), (0, _react.createElement)(_components.SelectControl, {
  __nextHasNoMarginBottom: true,
  label: (0, _i18n.__)('Numbering style'),
  options: [{
    label: (0, _i18n.__)('Numbers'),
    value: 'decimal'
  }, {
    label: (0, _i18n.__)('Uppercase letters'),
    value: 'upper-alpha'
  }, {
    label: (0, _i18n.__)('Lowercase letters'),
    value: 'lower-alpha'
  }, {
    label: (0, _i18n.__)('Uppercase Roman numerals'),
    value: 'upper-roman'
  }, {
    label: (0, _i18n.__)('Lowercase Roman numerals'),
    value: 'lower-roman'
  }],
  value: type,
  onChange: newValue => setAttributes({
    type: newValue
  })
}), (0, _react.createElement)(_components.ToggleControl, {
  __nextHasNoMarginBottom: true,
  label: (0, _i18n.__)('Reverse list numbering'),
  checked: reversed || false,
  onChange: value => {
    setAttributes({
      // Unset the attribute if not reversed.
      reversed: value || undefined
    });
  }
})));
var _default = exports.default = OrderedListSettings;
//# sourceMappingURL=ordered-list-settings.js.map