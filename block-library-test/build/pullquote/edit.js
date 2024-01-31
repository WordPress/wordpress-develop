"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _element = require("@wordpress/element");
var _figure = require("./figure");
var _blockquote = require("./blockquote");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const isWebPlatform = _element.Platform.OS === 'web';
function PullQuoteEdit({
  attributes,
  setAttributes,
  isSelected,
  insertBlocksAfter
}) {
  const {
    textAlign,
    citation,
    value
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const shouldShowCitation = !_blockEditor.RichText.isEmpty(citation) || isSelected;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)(_figure.Figure, {
    ...blockProps
  }, (0, _react.createElement)(_blockquote.BlockQuote, null, (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "value",
    tagName: "p",
    value: value,
    onChange: nextValue => setAttributes({
      value: nextValue
    }),
    "aria-label": (0, _i18n.__)('Pullquote text'),
    placeholder:
    // translators: placeholder text used for the quote
    (0, _i18n.__)('Add quote'),
    textAlign: "center"
  }), shouldShowCitation && (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "citation",
    tagName: isWebPlatform ? 'cite' : undefined,
    style: {
      display: 'block'
    },
    value: citation,
    "aria-label": (0, _i18n.__)('Pullquote citation text'),
    placeholder:
    // translators: placeholder text used for the citation
    (0, _i18n.__)('Add citation'),
    onChange: nextCitation => setAttributes({
      citation: nextCitation
    }),
    className: "wp-block-pullquote__citation",
    __unstableMobileNoFocusOnMount: true,
    textAlign: "center",
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }))));
}
var _default = exports.default = PullQuoteEdit;
//# sourceMappingURL=edit.js.map