import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    textAlign,
    citation,
    value
  } = attributes;
  const shouldShowCitation = !RichText.isEmpty(citation);
  return createElement("figure", {
    ...useBlockProps.save({
      className: classnames({
        [`has-text-align-${textAlign}`]: textAlign
      })
    })
  }, createElement("blockquote", null, createElement(RichText.Content, {
    tagName: "p",
    value: value
  }), shouldShowCitation && createElement(RichText.Content, {
    tagName: "cite",
    value: citation
  })));
}
//# sourceMappingURL=save.js.map