import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { defaultColumnsNumberV1 } from '../deprecated';
import { LINK_DESTINATION_ATTACHMENT, LINK_DESTINATION_MEDIA } from './constants';
export default function saveV1({
  attributes
}) {
  const {
    images,
    columns = defaultColumnsNumberV1(attributes),
    imageCrop,
    caption,
    linkTo
  } = attributes;
  const className = `columns-${columns} ${imageCrop ? 'is-cropped' : ''}`;
  return createElement("figure", {
    ...useBlockProps.save({
      className
    })
  }, createElement("ul", {
    className: "blocks-gallery-grid"
  }, images.map(image => {
    let href;
    switch (linkTo) {
      case LINK_DESTINATION_MEDIA:
        href = image.fullUrl || image.url;
        break;
      case LINK_DESTINATION_ATTACHMENT:
        href = image.link;
        break;
    }
    const img = createElement("img", {
      src: image.url,
      alt: image.alt,
      "data-id": image.id,
      "data-full-url": image.fullUrl,
      "data-link": image.link,
      className: image.id ? `wp-image-${image.id}` : null
    });
    return createElement("li", {
      key: image.id || image.url,
      className: "blocks-gallery-item"
    }, createElement("figure", null, href ? createElement("a", {
      href: href
    }, img) : img, !RichText.isEmpty(image.caption) && createElement(RichText.Content, {
      tagName: "figcaption",
      className: classnames('blocks-gallery-item__caption', __experimentalGetElementClassName('caption')),
      value: image.caption
    })));
  })), !RichText.isEmpty(caption) && createElement(RichText.Content, {
    tagName: "figcaption",
    className: classnames('blocks-gallery-caption', __experimentalGetElementClassName('caption')),
    value: caption
  }));
}
//# sourceMappingURL=save.js.map