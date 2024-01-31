import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { AlignmentControl, BlockControls, RichText, useBlockProps, getColorObjectByAttributeValues, __experimentalGetColorClassesAndStyles as getColorClassesAndStyles } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { Figure } from './figure';
import { BlockQuote } from './blockquote';
const getBackgroundColor = ({
  attributes,
  colors,
  style
}) => {
  const {
    backgroundColor
  } = attributes;
  const colorProps = getColorClassesAndStyles(attributes);
  const colorObject = getColorObjectByAttributeValues(colors, backgroundColor);
  return colorObject?.color || colorProps.style?.backgroundColor || colorProps.style?.background || style?.backgroundColor;
};
const getTextColor = ({
  attributes,
  colors,
  style
}) => {
  const colorProps = getColorClassesAndStyles(attributes);
  const colorObject = getColorObjectByAttributeValues(colors, attributes.textColor);
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
  const blockProps = useBlockProps({
    backgroundColor: getBackgroundColor(props),
    borderColor: getBorderColor(props)
  });
  const shouldShowCitation = !RichText.isEmpty(citation) || isSelected;
  return createElement(Fragment, null, createElement(BlockControls, {
    group: "block"
  }, createElement(AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), createElement(Figure, {
    ...blockProps
  }, createElement(BlockQuote, {
    textColor: getTextColor(props)
  }, createElement(RichText, {
    identifier: "value",
    value: value,
    onChange: nextValue => setAttributes({
      value: nextValue
    }),
    "aria-label": __('Pullquote text'),
    placeholder:
    // translators: placeholder text used for the quote
    __('Add quote'),
    textAlign: textAlign !== null && textAlign !== void 0 ? textAlign : 'center'
  }), shouldShowCitation && createElement(RichText, {
    identifier: "citation",
    value: citation,
    "aria-label": __('Pullquote citation text'),
    placeholder:
    // translators: placeholder text used for the citation
    __('Add citation'),
    onChange: nextCitation => setAttributes({
      citation: nextCitation
    }),
    __unstableMobileNoFocusOnMount: true,
    textAlign: textAlign !== null && textAlign !== void 0 ? textAlign : 'center',
    __unstableOnSplitAtEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  }))));
}
export default PullQuoteEdit;
//# sourceMappingURL=edit.native.js.map