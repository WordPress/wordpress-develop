"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _classnames = _interopRequireDefault(require("classnames"));
/**
 * WordPress dependencies
 */

/**
 * External dependencies
 */

const TEMPLATE = [['core/paragraph', {
  content: (0, _i18n.__)("Enter the message you wish displayed for form submission error/success, and select the type of the message (success/error) from the block's options.")
}]];
const Edit = ({
  attributes,
  clientId
}) => {
  const {
    type
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)('wp-block-form-submission-notification', {
      [`form-notification-type-${type}`]: type
    })
  });
  const {
    hasInnerBlocks
  } = (0, _data.useSelect)(select => {
    const {
      getBlock
    } = select(_blockEditor.store);
    const block = getBlock(clientId);
    return {
      hasInnerBlocks: !!(block && block.innerBlocks.length)
    };
  }, [clientId]);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    renderAppender: hasInnerBlocks ? undefined : _blockEditor.InnerBlocks.ButtonBlockAppender
  });
  return (0, _react.createElement)("div", {
    ...innerBlocksProps,
    "data-message-success": (0, _i18n.__)('Submission success notification'),
    "data-message-error": (0, _i18n.__)('Submission error notification')
  });
};
var _default = exports.default = Edit;
//# sourceMappingURL=edit.js.map