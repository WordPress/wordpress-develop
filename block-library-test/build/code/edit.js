"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CodeEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

function CodeEdit({
  attributes,
  setAttributes,
  onRemove,
  insertBlocksAfter,
  mergeBlocks
}) {
  const blockProps = (0, _blockEditor.useBlockProps)();
  return (0, _react.createElement)("pre", {
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "code",
    identifier: "content",
    value: attributes.content,
    onChange: content => setAttributes({
      content
    }),
    onRemove: onRemove,
    onMerge: mergeBlocks,
    placeholder: (0, _i18n.__)('Write code…'),
    "aria-label": (0, _i18n.__)('Code'),
    preserveWhiteSpace: true,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }));
}
//# sourceMappingURL=edit.js.map