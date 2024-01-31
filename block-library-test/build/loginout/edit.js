"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = LoginOutEdit;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function LoginOutEdit({
  attributes,
  setAttributes
}) {
  const {
    displayLoginAsForm,
    redirectToCurrent
  } = attributes;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display login as form'),
    checked: displayLoginAsForm,
    onChange: () => setAttributes({
      displayLoginAsForm: !displayLoginAsForm
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Redirect to current URL'),
    checked: redirectToCurrent,
    onChange: () => setAttributes({
      redirectToCurrent: !redirectToCurrent
    })
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)({
      className: 'logged-in'
    })
  }, (0, _react.createElement)("a", {
    href: "#login-pseudo-link"
  }, (0, _i18n.__)('Log out'))));
}
//# sourceMappingURL=edit.js.map