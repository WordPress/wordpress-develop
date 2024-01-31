"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.Gallery = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blocks = require("@wordpress/blocks");
var _galleryImage = _interopRequireDefault(require("./gallery-image"));
var _deprecated = require("../deprecated");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const Gallery = props => {
  const {
    attributes,
    isSelected,
    setAttributes,
    selectedImage,
    mediaPlaceholder,
    onMoveBackward,
    onMoveForward,
    onRemoveImage,
    onSelectImage,
    onDeselectImage,
    onSetImageAttributes,
    insertBlocksAfter,
    blockProps
  } = props;
  const {
    align,
    columns = (0, _deprecated.defaultColumnsNumberV1)(attributes),
    caption,
    imageCrop,
    images
  } = attributes;
  return (0, _react.createElement)("figure", {
    ...blockProps,
    className: (0, _classnames.default)(blockProps.className, {
      [`align${align}`]: align,
      [`columns-${columns}`]: columns,
      'is-cropped': imageCrop
    })
  }, (0, _react.createElement)("ul", {
    className: "blocks-gallery-grid"
  }, images.map((img, index) => {
    const ariaLabel = (0, _i18n.sprintf)( /* translators: 1: the order number of the image. 2: the total number of images. */
    (0, _i18n.__)('image %1$d of %2$d in gallery'), index + 1, images.length);
    return (0, _react.createElement)("li", {
      className: "blocks-gallery-item",
      key: img.id ? `${img.id}-${index}` : img.url
    }, (0, _react.createElement)(_galleryImage.default, {
      url: img.url,
      alt: img.alt,
      id: img.id,
      isFirstItem: index === 0,
      isLastItem: index + 1 === images.length,
      isSelected: isSelected && selectedImage === index,
      onMoveBackward: onMoveBackward(index),
      onMoveForward: onMoveForward(index),
      onRemove: onRemoveImage(index),
      onSelect: onSelectImage(index),
      onDeselect: onDeselectImage(index),
      setAttributes: attrs => onSetImageAttributes(index, attrs),
      caption: img.caption,
      "aria-label": ariaLabel,
      sizeSlug: attributes.sizeSlug
    }));
  })), mediaPlaceholder, (0, _react.createElement)(RichTextVisibilityHelper, {
    isHidden: !isSelected && _blockEditor.RichText.isEmpty(caption),
    tagName: "figcaption",
    className: (0, _classnames.default)('blocks-gallery-caption', (0, _blockEditor.__experimentalGetElementClassName)('caption')),
    "aria-label": (0, _i18n.__)('Gallery caption text'),
    placeholder: (0, _i18n.__)('Write gallery caption…'),
    value: caption,
    onChange: value => setAttributes({
      caption: value
    }),
    inlineToolbar: true,
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
  }));
};
exports.Gallery = Gallery;
function RichTextVisibilityHelper({
  isHidden,
  ...richTextProps
}) {
  return isHidden ? (0, _react.createElement)(_components.VisuallyHidden, {
    as: _blockEditor.RichText,
    ...richTextProps
  }) : (0, _react.createElement)(_blockEditor.RichText, {
    ...richTextProps
  });
}
var _default = exports.default = Gallery;
//# sourceMappingURL=gallery.js.map