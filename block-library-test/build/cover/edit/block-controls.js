"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CoverBlockControls;
var _react = require("react");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _shared = require("../shared");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function CoverBlockControls({
  attributes,
  setAttributes,
  onSelectMedia,
  currentSettings,
  toggleUseFeaturedImage
}) {
  const {
    contentPosition,
    id,
    useFeaturedImage,
    minHeight,
    minHeightUnit
  } = attributes;
  const {
    hasInnerBlocks,
    url
  } = currentSettings;
  const [prevMinHeightValue, setPrevMinHeightValue] = (0, _element.useState)(minHeight);
  const [prevMinHeightUnit, setPrevMinHeightUnit] = (0, _element.useState)(minHeightUnit);
  const isMinFullHeight = minHeightUnit === 'vh' && minHeight === 100;
  const toggleMinFullHeight = () => {
    if (isMinFullHeight) {
      // If there aren't previous values, take the default ones.
      if (prevMinHeightUnit === 'vh' && prevMinHeightValue === 100) {
        return setAttributes({
          minHeight: undefined,
          minHeightUnit: undefined
        });
      }

      // Set the previous values of height.
      return setAttributes({
        minHeight: prevMinHeightValue,
        minHeightUnit: prevMinHeightUnit
      });
    }
    setPrevMinHeightValue(minHeight);
    setPrevMinHeightUnit(minHeightUnit);

    // Set full height.
    return setAttributes({
      minHeight: 100,
      minHeightUnit: 'vh'
    });
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.__experimentalBlockAlignmentMatrixControl, {
    label: (0, _i18n.__)('Change content position'),
    value: contentPosition,
    onChange: nextPosition => setAttributes({
      contentPosition: nextPosition
    }),
    isDisabled: !hasInnerBlocks
  }), (0, _react.createElement)(_blockEditor.__experimentalBlockFullHeightAligmentControl, {
    isActive: isMinFullHeight,
    onToggle: toggleMinFullHeight,
    isDisabled: !hasInnerBlocks
  })), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_blockEditor.MediaReplaceFlow, {
    mediaId: id,
    mediaURL: url,
    allowedTypes: _shared.ALLOWED_MEDIA_TYPES,
    accept: "image/*,video/*",
    onSelect: onSelectMedia,
    onToggleFeaturedImage: toggleUseFeaturedImage,
    useFeaturedImage: useFeaturedImage,
    name: !url ? (0, _i18n.__)('Add Media') : (0, _i18n.__)('Replace')
  })));
}
//# sourceMappingURL=block-controls.js.map