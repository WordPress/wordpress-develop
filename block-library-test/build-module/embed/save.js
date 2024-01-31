import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames/dedupe';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    url,
    caption,
    type,
    providerNameSlug
  } = attributes;
  if (!url) {
    return null;
  }
  const className = classnames('wp-block-embed', {
    [`is-type-${type}`]: type,
    [`is-provider-${providerNameSlug}`]: providerNameSlug,
    [`wp-block-embed-${providerNameSlug}`]: providerNameSlug
  });
  return createElement("figure", {
    ...useBlockProps.save({
      className
    })
  }, createElement("div", {
    className: "wp-block-embed__wrapper"
  }, `\n${url}\n` /* URL needs to be on its own line. */), !RichText.isEmpty(caption) && createElement(RichText.Content, {
    className: __experimentalGetElementClassName('caption'),
    tagName: "figcaption",
    value: caption
  }));
}
//# sourceMappingURL=save.js.map