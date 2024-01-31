"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.Gallery = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _galleryImage = _interopRequireDefault(require("./gallery-image"));
var _deprecated = require("../deprecated");
var _galleryStyles = _interopRequireDefault(require("./gallery-styles.scss"));
var _tiles = _interopRequireDefault(require("./tiles"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

const TILE_SPACING = 15;

// we must limit displayed columns since readable content max-width is 580px
const MAX_DISPLAYED_COLUMNS = 4;
const MAX_DISPLAYED_COLUMNS_NARROW = 2;
const {
  isFullWidth
} = _components.alignmentHelpers;
const Gallery = props => {
  const [isCaptionSelected, setIsCaptionSelected] = (0, _element.useState)(false);
  (0, _element.useEffect)(_reactNativeBridge.mediaUploadSync, []);
  const isRTL = (0, _data.useSelect)(select => {
    return !!select(_blockEditor.store).getSettings().isRTL;
  }, []);
  const {
    clientId,
    selectedImage,
    mediaPlaceholder,
    onBlur,
    onMoveBackward,
    onMoveForward,
    onRemoveImage,
    onSelectImage,
    onSetImageAttributes,
    onFocusGalleryCaption,
    attributes,
    isSelected,
    isNarrow,
    onFocus,
    insertBlocksAfter
  } = props;
  const {
    align,
    columns = (0, _deprecated.defaultColumnsNumberV1)(attributes),
    imageCrop,
    images
  } = attributes;

  // limit displayed columns when isNarrow is true (i.e. when viewport width is
  // less than "small", where small = 600)
  const displayedColumns = isNarrow ? Math.min(columns, MAX_DISPLAYED_COLUMNS_NARROW) : Math.min(columns, MAX_DISPLAYED_COLUMNS);
  const selectImage = index => {
    return () => {
      if (isCaptionSelected) {
        setIsCaptionSelected(false);
      }
      // We need to fully invoke the curried function here.
      onSelectImage(index)();
    };
  };
  const focusGalleryCaption = () => {
    if (!isCaptionSelected) {
      setIsCaptionSelected(true);
    }
    onFocusGalleryCaption();
  };
  return (0, _react.createElement)(_reactNative.View, {
    style: {
      flex: 1
    }
  }, (0, _react.createElement)(_tiles.default, {
    columns: displayedColumns,
    spacing: TILE_SPACING,
    style: isSelected ? _galleryStyles.default.galleryTilesContainerSelected : undefined
  }, images.map((img, index) => {
    const ariaLabel = (0, _i18n.sprintf)( /* translators: 1: the order number of the image. 2: the total number of images. */
    (0, _i18n.__)('image %1$d of %2$d in gallery'), index + 1, images.length);
    return (0, _react.createElement)(_galleryImage.default, {
      key: img.id ? `${img.id}-${index}` : img.url,
      url: img.url,
      alt: img.alt,
      id: parseInt(img.id, 10) // make id an integer explicitly
      ,
      isCropped: imageCrop,
      isFirstItem: index === 0,
      isLastItem: index + 1 === images.length,
      isSelected: isSelected && selectedImage === index,
      isBlockSelected: isSelected,
      onMoveBackward: onMoveBackward(index),
      onMoveForward: onMoveForward(index),
      onRemove: onRemoveImage(index),
      onSelect: selectImage(index),
      onSelectBlock: onFocus,
      setAttributes: attrs => onSetImageAttributes(index, attrs),
      caption: img.caption,
      "aria-label": ariaLabel,
      isRTL: isRTL
    });
  })), (0, _react.createElement)(_reactNative.View, {
    style: isFullWidth(align) && _galleryStyles.default.fullWidth
  }, mediaPlaceholder), (0, _react.createElement)(_blockEditor.BlockCaption, {
    clientId: clientId,
    isSelected: isCaptionSelected,
    accessible: true,
    accessibilityLabelCreator: caption => _blockEditor.RichText.isEmpty(caption) ? /* translators: accessibility text. Empty gallery caption. */
    'Gallery caption. Empty' : (0, _i18n.sprintf)( /* translators: accessibility text. %s: gallery caption. */
    (0, _i18n.__)('Gallery caption. %s'), caption),
    onFocus: focusGalleryCaption,
    onBlur: onBlur // Always assign onBlur as props.
    ,
    insertBlocksAfter: insertBlocksAfter
  }));
};
exports.Gallery = Gallery;
var _default = exports.default = Gallery;
//# sourceMappingURL=gallery.native.js.map