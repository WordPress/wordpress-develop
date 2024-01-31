import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { imageFillStyles } from './media-container';
import { DEFAULT_MEDIA_SIZE_SLUG } from './constants';
const DEFAULT_MEDIA_WIDTH = 50;
const noop = () => {};
export default function save({
  attributes
}) {
  const {
    isStackedOnMobile,
    mediaAlt,
    mediaPosition,
    mediaType,
    mediaUrl,
    mediaWidth,
    mediaId,
    verticalAlignment,
    imageFill,
    focalPoint,
    linkClass,
    href,
    linkTarget,
    rel
  } = attributes;
  const mediaSizeSlug = attributes.mediaSizeSlug || DEFAULT_MEDIA_SIZE_SLUG;
  const newRel = !rel ? undefined : rel;
  const imageClasses = classnames({
    [`wp-image-${mediaId}`]: mediaId && mediaType === 'image',
    [`size-${mediaSizeSlug}`]: mediaId && mediaType === 'image'
  });
  let image = createElement("img", {
    src: mediaUrl,
    alt: mediaAlt,
    className: imageClasses || null
  });
  if (href) {
    image = createElement("a", {
      className: linkClass,
      href: href,
      target: linkTarget,
      rel: newRel
    }, image);
  }
  const mediaTypeRenders = {
    image: () => image,
    video: () => createElement("video", {
      controls: true,
      src: mediaUrl
    })
  };
  const className = classnames({
    'has-media-on-the-right': 'right' === mediaPosition,
    'is-stacked-on-mobile': isStackedOnMobile,
    [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment,
    'is-image-fill': imageFill
  });
  const backgroundStyles = imageFill ? imageFillStyles(mediaUrl, focalPoint) : {};
  let gridTemplateColumns;
  if (mediaWidth !== DEFAULT_MEDIA_WIDTH) {
    gridTemplateColumns = 'right' === mediaPosition ? `auto ${mediaWidth}%` : `${mediaWidth}% auto`;
  }
  const style = {
    gridTemplateColumns
  };
  if ('right' === mediaPosition) {
    return createElement("div", {
      ...useBlockProps.save({
        className,
        style
      })
    }, createElement("div", {
      ...useInnerBlocksProps.save({
        className: 'wp-block-media-text__content'
      })
    }), createElement("figure", {
      className: "wp-block-media-text__media",
      style: backgroundStyles
    }, (mediaTypeRenders[mediaType] || noop)()));
  }
  return createElement("div", {
    ...useBlockProps.save({
      className,
      style
    })
  }, createElement("figure", {
    className: "wp-block-media-text__media",
    style: backgroundStyles
  }, (mediaTypeRenders[mediaType] || noop)()), createElement("div", {
    ...useInnerBlocksProps.save({
      className: 'wp-block-media-text__content'
    })
  }));
}
//# sourceMappingURL=save.js.map