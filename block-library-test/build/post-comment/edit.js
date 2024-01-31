"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Edit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _icons = require("@wordpress/icons");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const TEMPLATE = [['core/avatar'], ['core/comment-author-name'], ['core/comment-date'], ['core/comment-content'], ['core/comment-reply-link'], ['core/comment-edit-link']];
function Edit({
  attributes: {
    commentId
  },
  setAttributes
}) {
  const [commentIdInput, setCommentIdInput] = (0, _element.useState)(commentId);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE
  });
  if (!commentId) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: _icons.blockDefault,
      label: (0, _i18n._x)('Post Comment', 'block title'),
      instructions: (0, _i18n.__)('To show a comment, input the comment ID.')
    }, (0, _react.createElement)(_components.TextControl, {
      __nextHasNoMarginBottom: true,
      value: commentId,
      onChange: val => setCommentIdInput(parseInt(val))
    }), (0, _react.createElement)(_components.Button, {
      variant: "primary",
      onClick: () => {
        setAttributes({
          commentId: commentIdInput
        });
      }
    }, (0, _i18n.__)('Save'))));
  }
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=edit.js.map