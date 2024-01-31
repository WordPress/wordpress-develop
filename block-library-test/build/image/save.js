"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
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
  const borderProps = (0, _blockEditor.__experimentalGetBorderClassesAndStyles)(attributes);
  const classes = (0, _classnames.default)({
    // All other align classes are handled by block supports.
    // `{ align: 'none' }` is unique to transforms for the image block.
    alignnone: 'none' === align,
    [`size-${sizeSlug}`]: sizeSlug,
    'is-resized': width || height,
    'has-custom-border': !!borderProps.className || borderProps.style && Object.keys(borderProps.style).length > 0
  });
  const imageClasses = (0, _classnames.default)(borderProps.className, {
    [`wp-image-${id}`]: !!id
  });
  const image = (0, _react.createElement)("img", {
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
  const figure = (0, _react.createElement)(_react.Fragment, null, href ? (0, _react.createElement)("a", {
    className: linkClass,
    href: href,
    target: linkTarget,
    rel: newRel
  }, image) : image, !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
    tagName: "figcaption",
    value: caption
  }));
  return (0, _react.createElement)("figure", {
    ..._blockEditor.useBlockProps.save({
      className: classes
    })
  }, figure);
}
//# sourceMappingURL=save.js.map