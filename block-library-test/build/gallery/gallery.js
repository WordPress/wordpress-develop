"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = Gallery;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _primitives = require("@wordpress/primitives");
var _caption = require("../utils/caption");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function Gallery(props) {
  const {
    attributes,
    isSelected,
    setAttributes,
    mediaPlaceholder,
    insertBlocksAfter,
    blockProps,
    __unstableLayoutClassNames: layoutClassNames,
    isContentLocked,
    multiGallerySelection
  } = props;
  const {
    align,
    columns,
    imageCrop
  } = attributes;
  return (0, _react.createElement)("figure", {
    ...blockProps,
    className: (0, _classnames.default)(blockProps.className, layoutClassNames, 'blocks-gallery-grid', {
      [`align${align}`]: align,
      [`columns-${columns}`]: columns !== undefined,
      [`columns-default`]: columns === undefined,
      'is-cropped': imageCrop
    })
  }, blockProps.children, isSelected && !blockProps.children && (0, _react.createElement)(_primitives.View, {
    className: "blocks-gallery-media-placeholder-wrapper"
  }, mediaPlaceholder), (0, _react.createElement)(_caption.Caption, {
    attributes: attributes,
    setAttributes: setAttributes,
    isSelected: isSelected,
    insertBlocksAfter: insertBlocksAfter,
    showToolbarButton: !multiGallerySelection && !isContentLocked,
    className: "blocks-gallery-caption",
    label: (0, _i18n.__)('Gallery caption text'),
    placeholder: (0, _i18n.__)('Add gallery caption')
  }));
}
//# sourceMappingURL=gallery.js.map