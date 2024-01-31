import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    showContent
  } = attributes;
  const summary = attributes.summary ? attributes.summary : 'Details';
  const blockProps = useBlockProps.save();
  return createElement("details", {
    ...blockProps,
    open: showContent
  }, createElement("summary", null, createElement(RichText.Content, {
    value: summary
  })), createElement(InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map