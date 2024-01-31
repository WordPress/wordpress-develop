"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = saveV1;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _deprecated = require("../deprecated");
var _constants = require("./constants");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function saveV1({
  attributes
}) {
  const {
    images,
    columns = (0, _deprecated.defaultColumnsNumberV1)(attributes),
    imageCrop,
    caption,
    linkTo
  } = attributes;
  const className = `columns-${columns} ${imageCrop ? 'is-cropped' : ''}`;
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save({
      className
    })
  }, (0, _react.createElement)("ul", {
    className: "blocks-gallery-grid"
  }, images.map(image => {
    let href;
    switch (linkTo) {
      case _constants.LINK_DESTINATION_MEDIA:
        href = image.fullUrl || image.url;
        break;
      case _constants.LINK_DESTINATION_ATTACHMENT:
        href = image.link;
        break;
    }
    const img = (0, _react.createElement)("img", {
      src: image.url,
      alt: image.alt,
      "data-id": image.id,
      "data-full-url": image.fullUrl,
      "data-link": image.link,
      className: image.id ? `wp-image-${image.id}` : null
    });
    return (0, _react.createElement)("li", {
      key: image.id || image.url,
      className: "blocks-gallery-item"
    }, (0, _react.createElement)("figure", null, href ? (0, _react.createElement)("a", {
      href: href
    }, img) : img, !_blockEditor.RichText.isEmpty(image.caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
      tagName: "figcaption",
      className: (0, _classnames.default)('blocks-gallery-item__caption', (0, _blockEditor.__experimentalGetElementClassName)('caption')),
      value: image.caption
    })));
  })), !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "figcaption",
    className: (0, _classnames.default)('blocks-gallery-caption', (0, _blockEditor.__experimentalGetElementClassName)('caption')),
    value: caption
  }));
}
//# sourceMappingURL=save.js.map