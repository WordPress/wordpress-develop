"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _tracks = _interopRequireDefault(require("./tracks"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function save({
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
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save()
  }, src && (0, _react.createElement)("video", {
    autoPlay: autoplay,
    controls: controls,
    loop: loop,
    muted: muted,
    poster: poster,
    preload: preload !== 'metadata' ? preload : undefined,
    src: src,
    playsInline: playsInline
  }, (0, _react.createElement)(_tracks.default, {
    tracks: tracks
  })), !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
    tagName: "figcaption",
    value: caption
  }));
}
//# sourceMappingURL=save.js.map