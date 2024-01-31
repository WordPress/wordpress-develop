import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
export default function CodeEdit({
  attributes,
  setAttributes,
  onRemove,
  insertBlocksAfter,
  mergeBlocks
}) {
  const blockProps = useBlockProps();
  return createElement("pre", {
    ...blockProps
  }, createElement(RichText, {
    tagName: "code",
    identifier: "content",
    value: attributes.content,
    onChange: content => setAttributes({
      content
    }),
    onRemove: onRemove,
    onMerge: mergeBlocks,
    placeholder: __('Write code…'),
    "aria-label": __('Code'),
    preserveWhiteSpace: true,
    __unstablePastePlainText: true,
    __unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName()))
  }));
}
//# sourceMappingURL=edit.js.map