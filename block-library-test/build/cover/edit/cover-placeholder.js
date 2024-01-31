"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CoverPlaceholder;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _shared = require("../shared");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function CoverPlaceholder({
  disableMediaButtons = false,
  children,
  onSelectMedia,
  onError,
  style,
  toggleUseFeaturedImage
}) {
  return (0, _react.createElement)(_blockEditor.MediaPlaceholder, {
    icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: _icons.cover
    }),
    labels: {
      title: (0, _i18n.__)('Cover'),
      instructions: (0, _i18n.__)('Drag and drop onto this block, upload, or select existing media from your library.')
    },
    onSelect: onSelectMedia,
    accept: "image/*,video/*",
    allowedTypes: _shared.ALLOWED_MEDIA_TYPES,
    disableMediaButtons: disableMediaButtons,
    onToggleFeaturedImage: toggleUseFeaturedImage,
    onError: onError,
    style: style
  }, children);
}
//# sourceMappingURL=cover-placeholder.js.map