"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PreformattedEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function PreformattedEdit({
  attributes,
  mergeBlocks,
  setAttributes,
  onRemove,
  insertBlocksAfter,
  style
}) {
  const {
    content
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    style
  });
  return (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "pre",
    identifier: "content",
    preserveWhiteSpace: true,
    value: content,
    onChange: nextContent => {
      setAttributes({
        content: nextContent
      });
    },
    onRemove: onRemove,
    "aria-label": (0, _i18n.__)('Preformatted text'),
    placeholder: (0, _i18n.__)('Write preformatted text…'),
    onMerge: mergeBlocks,
    ...blockProps,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  });
}
//# sourceMappingURL=edit.js.map