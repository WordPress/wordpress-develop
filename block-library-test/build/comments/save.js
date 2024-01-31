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

function save({
  attributes: {
    tagName: Tag,
    legacy
  }
}) {
  const blockProps = _blockEditor.useBlockProps.save();
  const innerBlocksProps = _blockEditor.useInnerBlocksProps.save(blockProps);

  // The legacy version is dynamic (i.e. PHP rendered) and doesn't allow inner
  // blocks, so nothing is saved in that case.
  return legacy ? null : (0, _react.createElement)(Tag, {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=save.js.map