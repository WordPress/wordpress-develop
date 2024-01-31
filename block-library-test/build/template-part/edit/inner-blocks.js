"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TemplatePartInnerBlocks;
var _react = require("react");
var _coreData = require("@wordpress/core-data");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

function TemplatePartInnerBlocks({
  postId: id,
  hasInnerBlocks,
  layout,
  tagName: TagName,
  blockProps
}) {
  const themeSupportsLayout = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    return getSettings()?.supportsLayout;
  }, []);
  const [defaultLayout] = (0, _blockEditor.useSettings)('layout');
  const usedLayout = layout?.inherit ? defaultLayout || {} : layout;
  const [blocks, onInput, onChange] = (0, _coreData.useEntityBlockEditor)('postType', 'wp_template_part', {
    id
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    value: blocks,
    onInput,
    onChange,
    renderAppender: hasInnerBlocks ? undefined : _blockEditor.InnerBlocks.ButtonBlockAppender,
    layout: themeSupportsLayout ? usedLayout : undefined
  });
  return (0, _react.createElement)(TagName, {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=inner-blocks.js.map