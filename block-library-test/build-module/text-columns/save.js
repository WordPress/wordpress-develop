import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    width,
    content,
    columns
  } = attributes;
  return createElement("div", {
    ...useBlockProps.save({
      className: `align${width} columns-${columns}`
    })
  }, Array.from({
    length: columns
  }).map((_, index) => createElement("div", {
    className: "wp-block-column",
    key: `column-${index}`
  }, createElement(RichText.Content, {
    tagName: "p",
    value: content?.[index]?.children
  }))));
}
//# sourceMappingURL=save.js.map