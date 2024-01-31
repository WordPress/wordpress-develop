"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = saveWithInnerBlocks;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _save = _interopRequireDefault(require("./v1/save"));
var _shared = require("./shared");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function saveWithInnerBlocks({
  attributes
}) {
  if (!(0, _shared.isGalleryV2Enabled)()) {
    return (0, _save.default)({
      attributes
    });
  }
  const {
    caption,
    columns,
    imageCrop
  } = attributes;
  const className = (0, _classnames.default)('has-nested-images', {
    [`columns-${columns}`]: columns !== undefined,
    [`columns-default`]: columns === undefined,
    'is-cropped': imageCrop
  });
  const blockProps = _blockEditor.useBlockProps.save({
    className
  });
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)("figure", {
    ...innerBlocksProps
  }, innerBlocksProps.children, !_blockEditor.RichText.isEmpty(caption) && (0, _react.createElement)(_blockEditor.RichText.Content, {
    tagName: "figcaption",
    className: (0, _classnames.default)('blocks-gallery-caption', (0, _blockEditor.__experimentalGetElementClassName)('caption')),
    value: caption
  }));
}
//# sourceMappingURL=save.js.map