"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.Gallery = void 0;
var _react = require("react");
var _reactNative = require("react-native");
var _shared = require("./shared");
var _galleryStyles = _interopRequireDefault(require("./gallery-styles.scss"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _reactNativeBridge = require("@wordpress/react-native-bridge");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

const TILE_SPACING = 8;

// we must limit displayed columns since readable content max-width is 580px
const MAX_DISPLAYED_COLUMNS = 4;
const MAX_DISPLAYED_COLUMNS_NARROW = 2;
const Gallery = props => {
  const [isCaptionSelected, setIsCaptionSelected] = (0, _element.useState)(false);
  const [resizeObserver, sizes] = (0, _compose.useResizeObserver)();
  const [maxWidth, setMaxWidth] = (0, _element.useState)(0);
  (0, _element.useEffect)(_reactNativeBridge.mediaUploadSync, []);
  const {
    mediaPlaceholder,
    attributes,
    images,
    isNarrow,
    onBlur,
    insertBlocksAfter,
    clientId
  } = props;
  (0, _element.useEffect)(() => {
    const {
      width
    } = sizes || {};
    if (width) {
      setMaxWidth(width);
    }
  }, [sizes]);
  const {
    align,
    columns = (0, _shared.defaultColumnsNumber)(images.length)
  } = attributes;
  const displayedColumns = Math.min(columns, isNarrow ? MAX_DISPLAYED_COLUMNS_NARROW : MAX_DISPLAYED_COLUMNS);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({}, {
    contentResizeMode: 'stretch',
    orientation: 'horizontal',
    renderAppender: false,
    numColumns: displayedColumns,
    marginHorizontal: TILE_SPACING,
    marginVertical: TILE_SPACING,
    layout: {
      type: 'default',
      alignments: []
    },
    gridProperties: {
      numColumns: displayedColumns
    },
    parentWidth: maxWidth + 2 * TILE_SPACING
  });
  const focusGalleryCaption = () => {
    if (!isCaptionSelected) {
      setIsCaptionSelected(true);
    }
  };
  const isFullWidth = align === _components.WIDE_ALIGNMENTS.alignments.full;
  return (0, _react.createElement)(_reactNative.View, {
    style: isFullWidth && _galleryStyles.default.fullWidth
  }, resizeObserver, (0, _react.createElement)(_reactNative.View, {
    ...innerBlocksProps
  }), (0, _react.createElement)(_reactNative.View, {
    style: [isFullWidth && _galleryStyles.default.fullWidth, _galleryStyles.default.galleryAppender]
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