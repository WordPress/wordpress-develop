"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _dedupe = _interopRequireDefault(require("classnames/dedupe"));
var _blockEditor = require("@wordpress/block-editor");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function save({
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
  const className = (0, _dedupe.default)('wp-block-embed', {
    [`is-type-${type}`]: type,
    [`is-provider-${providerNameSlug}`]: providerNameSlug,
    [`wp-block-embed-${providerNameSlug}`]: providerNameSlug
  });
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save({
      className
    })
  }, (0, _react.createElement)("div", {
    className: "wp-block-embed__wrapper"
  }, `\n${url}\n` /* URL needs to be on its own line. */), !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
    tagName: "figcaption",
    value: caption
  }));
}
//# sourceMappingURL=save.js.map