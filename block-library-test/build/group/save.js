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
    tagName: Tag
  }
}) {
  return (0, _react.createElement)(Tag, {
    ..._blockEditor.useInnerBlocksProps.save(_blockEditor.useBlockProps.save())
  });
}
//# sourceMappingURL=save.js.map