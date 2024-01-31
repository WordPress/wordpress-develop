"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.Caption = Caption;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _blocks = require("@wordpress/blocks");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function Caption({
  key = 'caption',
  attributes,
  setAttributes,
  isSelected,
  insertBlocksAfter,
  placeholder = (0, _i18n.__)('Add caption'),
  label = (0, _i18n.__)('Caption text'),
  showToolbarButton = true,
  className
}) {
  const caption = attributes[key];
  const prevCaption = (0, _compose.usePrevious)(caption);
  const isCaptionEmpty = _blockEditor.RichText.isEmpty(caption);
  const isPrevCaptionEmpty = _blockEditor.RichText.isEmpty(prevCaption);
  const [showCaption, setShowCaption] = (0, _element.useState)(!isCaptionEmpty);

  // We need to show the caption when changes come from
  // history navigation(undo/redo).
  (0, _element.useEffect)(() => {
    if (!isCaptionEmpty && isPrevCaptionEmpty) {
      setShowCaption(true);
    }
  }, [isCaptionEmpty, isPrevCaptionEmpty]);
  (0, _element.useEffect)(() => {
    if (!isSelected && isCaptionEmpty) {
      setShowCaption(false);
    }
  }, [isSelected, isCaptionEmpty]);

  // Focus the caption when we click to add one.
  const ref = (0, _element.useCallback)(node => {
    if (node && isCaptionEmpty) {
      node.focus();
    }
  }, [isCaptionEmpty]);
  return (0, _react.createElement)(_react.Fragment, null, showToolbarButton && (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: () => {
      setShowCaption(!showCaption);
      if (showCaption && caption) {
        setAttributes({
          caption: undefined
        });
      }
    },
    icon: _icons.caption,
    isPressed: showCaption,
    label: showCaption ? (0, _i18n.__)('Remove caption') : (0, _i18n.__)('Add caption')
  })), showCaption && (!_blockEditor.RichText.isEmpty(caption) || isSelected) && (0, _react.createElement)(_blockEditor.RichText, {
    identifier: key,
    tagName: "figcaption",
    className: (0, _classnames.default)(className, (0, _blockEditor.__experimentalGetElementClassName)('caption')),
    ref: ref,
    "aria-label": label,
    placeholder: placeholder,
    value: caption,
    onChange: value => setAttributes({
      caption: value
    }),
    inlineToolbar: true,
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }));
}
//# sourceMappingURL=caption.js.map