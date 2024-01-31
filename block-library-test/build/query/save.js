"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QuerySave;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function QuerySave({
  attributes: {
    tagName: Tag = 'div'
  }
}) {
  const blockProps = _blockEditor.useBlockProps.save();
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)(Tag, {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map