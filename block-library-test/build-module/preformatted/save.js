import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    content
  } = attributes;
  return createElement("pre", {
    ...useBlockProps.save()
  }, createElement(RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map