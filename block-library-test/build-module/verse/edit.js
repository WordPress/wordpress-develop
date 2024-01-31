import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText, BlockControls, AlignmentToolbar, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
export default function VerseEdit({
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
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    style
  });
  return createElement(Fragment, null, createElement(BlockControls, null, createElement(AlignmentToolbar, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), createElement(RichText, {
    tagName: "pre",
    identifier: "content",
    preserveWhiteSpace: true,
    value: content,
    onChange: nextContent => {
      setAttributes({
        content: nextContent
      });
    },
    "aria-label": __('Verse text'),
    placeholder: __('Write verse…'),
    onRemove: onRemove,
    onMerge: mergeBlocks,
    textAlign: textAlign,
    ...blockProps,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  }));
}
//# sourceMappingURL=edit.js.map