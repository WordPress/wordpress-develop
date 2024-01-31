import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName, __experimentalGetBorderClassesAndStyles as getBorderClassesAndStyles } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    url,
    alt,
    caption,
    align,
    href,
    rel,
    linkClass,
    width,
    height,
    aspectRatio,
    scale,
    id,
    linkTarget,
    sizeSlug,
    title
  } = attributes;
  const newRel = !rel ? undefined : rel;
  const borderProps = getBorderClassesAndStyles(attributes);
  const classes = classnames({
    // All other align classes are handled by block supports.
    // `{ align: 'none' }` is unique to transforms for the image block.
    alignnone: 'none' === align,
    [`size-${sizeSlug}`]: sizeSlug,
    'is-resized': width || height,
    'has-custom-border': !!borderProps.className || borderProps.style && Object.keys(borderProps.style).length > 0
  });
  const imageClasses = classnames(borderProps.className, {
    [`wp-image-${id}`]: !!id
  });
  const image = createElement("img", {
    src: url,
    alt: alt,
    className: imageClasses || undefined,
    style: {
      ...borderProps.style,
      aspectRatio,
      objectFit: scale,
      width,
      height
    },
    title: title
  });
  const figure = createElement(Fragment, null, href ? createElement("a", {
    className: linkClass,
    href: href,
    target: linkTarget,
    rel: newRel
  }, image) : image, !RichText.isEmpty(caption) && createElement(RichText.Content, {
    className: __experimentalGetElementClassName('caption'),
    tagName: "figcaption",
    value: caption
  }));
  return createElement("figure", {
    ...useBlockProps.save({
      className: classes
    })
  }, figure);
}
//# sourceMappingURL=save.js.map