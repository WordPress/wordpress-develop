"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = EnhancedPaginationModal;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _utils = require("../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const modalDescriptionId = 'wp-block-query-enhanced-pagination-modal__description';
function EnhancedPaginationModal({
  clientId,
  attributes: {
    enhancedPagination
  },
  setAttributes
}) {
  const [isOpen, setOpen] = (0, _element.useState)(false);
  const {
    hasBlocksFromPlugins,
    hasPostContentBlock,
    hasUnsupportedBlocks
  } = (0, _utils.useUnsupportedBlocks)(clientId);
  (0, _element.useEffect)(() => {
    if (enhancedPagination && hasUnsupportedBlocks) {
      setAttributes({
        enhancedPagination: false
      });
      setOpen(true);
    }
  }, [enhancedPagination, hasUnsupportedBlocks, setAttributes]);
  const closeModal = () => {
    setOpen(false);
  };
  let notice = (0, _i18n.__)('If you still want to prevent full page reloads, remove that block, then disable "Force page reload" again in the Query Block settings.');
  if (hasBlocksFromPlugins) {
    notice = (0, _i18n.__)('Currently, avoiding full page reloads is not possible when blocks from plugins are present inside the Query block.') + ' ' + notice;
  } else if (hasPostContentBlock) {
    notice = (0, _i18n.__)('Currently, avoiding full page reloads is not possible when a Content block is present inside the Query block.') + ' ' + notice;
  }
  return isOpen && (0, _react.createElement)(_components.Modal, {
    title: (0, _i18n.__)('Query block: Force page reload enabled'),
    className: "wp-block-query__enhanced-pagination-modal",
    aria: {
      describedby: modalDescriptionId
    },
    role: "alertdialog",
    focusOnMount: "firstElement",
    isDismissible: false,
    onRequestClose: closeModal
  }, (0, _react.createElement)(_components.__experimentalVStack, {
    alignment: "right",
    spacing: 5
  }, (0, _react.createElement)("span", {
    id: modalDescriptionId
  }, notice), (0, _react.createElement)(_components.Button, {
    variant: "primary",
    onClick: closeModal
  }, (0, _i18n.__)('OK'))));
}
//# sourceMappingURL=enhanced-pagination-modal.js.map