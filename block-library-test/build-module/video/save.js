import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import Tracks from './tracks';
export default function save({
  attributes
}) {
  const {
    autoplay,
    caption,
    controls,
    loop,
    muted,
    poster,
    preload,
    src,
    playsInline,
    tracks
  } = attributes;
  return createElement("figure", {
    ...useBlockProps.save()
  }, src && createElement("video", {
    autoPlay: autoplay,
    controls: controls,
    loop: loop,
    muted: muted,
    poster: poster,
    preload: preload !== 'metadata' ? preload : undefined,
    src: src,
    playsInline: playsInline
  }, createElement(Tracks, {
    tracks: tracks
  })), !RichText.isEmpty(caption) && createElement(RichText.Content, {
    className: __experimentalGetElementClassName('caption'),
    tagName: "figcaption",
    value: caption
  }));
}
//# sourceMappingURL=save.js.map