"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ShortcodeEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function ShortcodeEdit({
  attributes,
  setAttributes
}) {
  const instanceId = (0, _compose.useInstanceId)(ShortcodeEdit);
  const inputId = `blocks-shortcode-input-${instanceId}`;
  return (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)({
      className: 'components-placeholder'
    })
  }, (0, _react.createElement)("label", {
    htmlFor: inputId,
    className: "components-placeholder__label"
  }, (0, _react.createElement)(_icons.Icon, {
    icon: _icons.shortcode
  }), (0, _i18n.__)('Shortcode')), (0, _react.createElement)(_blockEditor.PlainText, {
    className: "blocks-shortcode__textarea",
    id: inputId,
    value: attributes.text,
    "aria-label": (0, _i18n.__)('Shortcode text'),
    placeholder: (0, _i18n.__)('Write shortcode here…'),
    onChange: text => setAttributes({
      text
    })
  }));
}
//# sourceMappingURL=edit.js.map