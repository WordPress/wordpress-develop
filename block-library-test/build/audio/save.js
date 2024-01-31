"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function save({
  attributes
}) {
  const {
    autoplay,
    caption,
    loop,
    preload,
    src
  } = attributes;
  return src && (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save()
  }, (0, _react.createElement)("audio", {
    controls: "controls",
    src: src,
    autoPlay: autoplay,
    loop: loop,
    preload: preload
  }), !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "figcaption",
    value: caption,
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption')
  }));
}
//# sourceMappingURL=save.js.map