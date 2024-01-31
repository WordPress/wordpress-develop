"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = VerseEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function VerseEdit({
  attributes,
  setAttributes,
  mergeBlocks,
  onRemove,
  insertBlocksAfter,
  style
}) {
  const {
    textAlign,
    content
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    style
  });
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_blockEditor.AlignmentToolbar, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)(_blockEditor.RichText, {
    tagName: "pre",
    identifier: "content",
    preserveWhiteSpace: true,
    value: content,
    onChange: nextContent => {
      setAttributes({
        content: nextContent
      });
    },
    "aria-label": (0, _i18n.__)('Verse text'),
    placeholder: (0, _i18n.__)('Write verse…'),
    onRemove: onRemove,
    onMerge: mergeBlocks,
    textAlign: textAlign,
    ...blockProps,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }));
}
//# sourceMappingURL=edit.js.map