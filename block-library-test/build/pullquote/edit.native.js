"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _figure = require("./figure");
var _blockquote = require("./blockquote");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const getBackgroundColor = ({
  attributes,
  colors,
  style
}) => {
  const {
    backgroundColor
  } = attributes;
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  const colorObject = (0, _blockEditor.getColorObjectByAttributeValues)(colors, backgroundColor);
  return colorObject?.color || colorProps.style?.backgroundColor || colorProps.style?.background || style?.backgroundColor;
};
const getTextColor = ({
  attributes,
  colors,
  style
}) => {
  const colorProps = (0, _blockEditor.__experimentalGetColorClassesAndStyles)(attributes);
  const colorObject = (0, _blockEditor.getColorObjectByAttributeValues)(colors, attributes.textColor);
  return colorObject?.color || colorProps.style?.color || style?.color || style?.baseColors?.color?.text;
};
const getBorderColor = props => {
  const {
    wrapperProps
  } = props;
  const defaultColor = getTextColor(props);
  return wrapperProps?.style?.borderColor || defaultColor;
};
/**
 * Internal dependencies
 */

function PullQuoteEdit(props) {
  const {
    attributes,
    setAttributes,
    isSelected,
    insertBlocksAfter
  } = props;
  const {
    textAlign,
    citation,
    value
  } = attributes;
  const blockProps = (0, _blockEditor.useBlockProps)({
    backgroundColor: getBackgroundColor(props),
    borderColor: getBorderColor(props)
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
  }, (0, _react.createElement)(_blockquote.BlockQuote, {
    textColor: getTextColor(props)
  }, (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "value",
    value: value,
    onChange: nextValue => setAttributes({
      value: nextValue
    }),
    "aria-label": (0, _i18n.__)('Pullquote text'),
    placeholder:
    // translators: placeholder text used for the quote
    (0, _i18n.__)('Add quote'),
    textAlign: textAlign !== null && textAlign !== void 0 ? textAlign : 'center'
  }), shouldShowCitation && (0, _react.createElement)(_blockEditor.RichText, {
    identifier: "citation",
    value: citation,
    "aria-label": (0, _i18n.__)('Pullquote citation text'),
    placeholder:
    // translators: placeholder text used for the citation
    (0, _i18n.__)('Add citation'),
    onChange: nextCitation => setAttributes({
      citation: nextCitation
    }),
    __unstableMobileNoFocusOnMount: true,
    textAlign: textAlign !== null && textAlign !== void 0 ? textAlign : 'center',
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }))));
}
var _default = exports.default = PullQuoteEdit;
//# sourceMappingURL=edit.native.js.map