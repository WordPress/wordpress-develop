import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  return createElement("li", {
    ...useBlockProps.save()
  }, createElement(RichText.Content, {
    value: attributes.content
  }), createElement(InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map