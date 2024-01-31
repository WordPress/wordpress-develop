import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    ordered,
    type,
    reversed,
    start
  } = attributes;
  const TagName = ordered ? 'ol' : 'ul';
  return createElement(TagName, {
    ...useBlockProps.save({
      reversed,
      start,
      style: {
        listStyleType: ordered && type !== 'decimal' ? type : undefined
      }
    })
  }, createElement(InnerBlocks.Content, null));
}
//# sourceMappingURL=save.js.map