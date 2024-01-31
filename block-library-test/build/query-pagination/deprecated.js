"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const deprecated = [
// Version with wrapper `div` element.
{
  save() {
    return (0, _react.createElement)("div", {
      ..._blockEditor.useBlockProps.save()
    }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
  }
}];
var _default = exports.default = deprecated;
//# sourceMappingURL=deprecated.js.map