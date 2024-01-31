import { createElement } from "react";
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    align,
    citation
  } = attributes;
  const className = classNames({
    [`has-text-align-${align}`]: align
  });
  return createElement("blockquote", {
    ...useBlockProps.save({
      className
    })
  }, createElement(InnerBlocks.Content, null), !RichText.isEmpty(citation) && createElement(RichText.Content, {
    tagName: "cite",
    value: citation
  }));
}
//# sourceMappingURL=save.js.map