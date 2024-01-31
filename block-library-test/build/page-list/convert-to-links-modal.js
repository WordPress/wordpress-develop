"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.ConvertToLinksModal = ConvertToLinksModal;
exports.convertDescription = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const convertDescription = exports.convertDescription = (0, _i18n.__)("This navigation menu displays your website's pages. Editing it will enable you to add, delete, or reorder pages. However, new pages will no longer be added automatically.");
function ConvertToLinksModal({
  onClick,
  onClose,
  disabled
}) {
  return (0, _react.createElement)(_components.Modal, {
    onRequestClose: onClose,
    title: (0, _i18n.__)('Edit Page List'),
    className: 'wp-block-page-list-modal',
    aria: {
      describedby: 'wp-block-page-list-modal__description'
    }
  }, (0, _react.createElement)("p", {
    id: 'wp-block-page-list-modal__description'
  }, convertDescription), (0, _react.createElement)("div", {
    className: "wp-block-page-list-modal-buttons"
  }, (0, _react.createElement)(_components.Button, {
    variant: "tertiary",
    onClick: onClose
  }, (0, _i18n.__)('Cancel')), (0, _react.createElement)(_components.Button, {
    variant: "primary",
    disabled: disabled,
    onClick: onClick
  }, (0, _i18n.__)('Edit'))));
}
//# sourceMappingURL=convert-to-links-modal.js.map