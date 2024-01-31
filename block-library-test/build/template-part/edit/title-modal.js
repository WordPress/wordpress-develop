"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TitleModal;
var _react = require("react");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

function TitleModal({
  areaLabel,
  onClose,
  onSubmit
}) {
  // Restructure onCreate to set the blocks on local state.
  // Add modal to confirm title and trigger onCreate.
  const [title, setTitle] = (0, _element.useState)((0, _i18n.__)('Untitled Template Part'));
  const submitForCreation = event => {
    event.preventDefault();
    onSubmit(title);
  };
  return (0, _react.createElement)(_components.Modal, {
    title: (0, _i18n.sprintf)(
    // Translators: %s as template part area title ("Header", "Footer", etc.).
    (0, _i18n.__)('Name and create your new %s'), areaLabel.toLowerCase()),
    overlayClassName: "wp-block-template-part__placeholder-create-new__title-form",
    onRequestClose: onClose
  }, (0, _react.createElement)("form", {
    onSubmit: submitForCreation
  }, (0, _react.createElement)(_components.__experimentalVStack, {
    spacing: "5"
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Name'),
    value: title,
    onChange: setTitle
  }), (0, _react.createElement)(_components.__experimentalHStack, {
    justify: "right"
  }, (0, _react.createElement)(_components.Button, {
    variant: "primary",
    type: "submit",
    disabled: !title.length,
    "aria-disabled": !title.length
  }, (0, _i18n.__)('Create'))))));
}
//# sourceMappingURL=title-modal.js.map