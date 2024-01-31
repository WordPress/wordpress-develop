import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps, __experimentalGetElementClassName } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    autoplay,
    caption,
    loop,
    preload,
    src
  } = attributes;
  return src && createElement("figure", {
    ...useBlockProps.save()
  }, createElement("audio", {
    controls: "controls",
    src: src,
    autoPlay: autoplay,
    loop: loop,
    preload: preload
  }), !RichText.isEmpty(caption) && createElement(RichText.Content, {
    tagName: "figcaption",
    value: caption,
    className: __experimentalGetElementClassName('caption')
  }));
}
//# sourceMappingURL=save.js.map