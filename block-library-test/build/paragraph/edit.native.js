"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

const name = 'core/paragraph';
const allowedParentBlockAlignments = ['left', 'center', 'right'];
function ParagraphBlock({
  attributes,
  mergeBlocks,
  onReplace,
  setAttributes,
  style,
  clientId,
  parentBlockAlignment
}) {
  const isRTL = (0, _data.useSelect)(select => {
    return !!select(_blockEditor.store).getSettings().isRTL;
  }, []);
  const {
    align,
    content,
    placeholder
  } = attributes;
  const styles = {
    ...(style?.baseColors && {
      color: style.baseColors?.color?.text,
      placeholderColor: style.color || style.baseColors?.color?.text,
      linkColor: style.baseColors?.elements?.link?.color?.text
    }),
    ...style
  };
  const onAlignmentChange = (0, _element.useCallback)(nextAlign => {
    setAttributes({
      align: nextAlign
    });
  }, []);
  const parentTextAlignment = allowedParentBlockAlignments.includes(parentBlockAlignment) ? parentBlockAlignment : undefined;
  const textAlignment = align || parentTextAlignment;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: align,
    isRTL: isRTL,
    onChange: onAlignmentChange
  })), (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "content",
    tagName: "p",
    value: content,
    deleteEnter: true,
    style: styles,
    onChange: nextContent => {
      setAttributes({
        content: nextContent
      });
    },
    onSplit: (value, isOriginal) => {
      let newAttributes;
      if (isOriginal || value) {
        newAttributes = {
          ...attributes,
          content: value
        };
      }
      const block = (0, _blocks.createBlock)(name, newAttributes);
      if (isOriginal) {
        block.clientId = clientId;
      }
      return block;
    },
    onMerge: mergeBlocks,
    onReplace: onReplace,
    onRemove: onReplace ? () => onReplace([]) : undefined,
    placeholder: placeholder || (0, _i18n.__)('Start writing…'),
    textAlign: textAlignment,
    __unstableEmbedURLOnPaste: true
  }));
}
var _default = exports.default = ParagraphBlock;
//# sourceMappingURL=edit.native.js.map