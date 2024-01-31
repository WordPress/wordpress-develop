"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = save;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function save() {
  const blockProps = _blockEditor.useBlockProps.save();
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map