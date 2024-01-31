import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { AlignmentControl, BlockControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { Platform } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Figure } from './figure';
import { BlockQuote } from './blockquote';
const isWebPlatform = Platform.OS === 'web';
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
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
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
  }, createElement(BlockQuote, null, createElement(RichText, {
    identifier: "value",
    tagName: "p",
    value: value,
    onChange: nextValue => setAttributes({
      value: nextValue
    }),
    "aria-label": __('Pullquote text'),
    placeholder:
    // translators: placeholder text used for the quote
    __('Add quote'),
    textAlign: "center"
  }), shouldShowCitation && createElement(RichText, {
    identifier: "citation",
    tagName: isWebPlatform ? 'cite' : undefined,
    style: {
      display: 'block'
    },
    value: citation,
    "aria-label": __('Pullquote citation text'),
    placeholder:
    // translators: placeholder text used for the citation
    __('Add citation'),
    onChange: nextCitation => setAttributes({
      citation: nextCitation
    }),
    className: "wp-block-pullquote__citation",
    __unstableMobileNoFocusOnMount: true,
    textAlign: "center",
    __unstableOnSplitAtEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  }))));
}
export default PullQuoteEdit;
//# sourceMappingURL=edit.js.map