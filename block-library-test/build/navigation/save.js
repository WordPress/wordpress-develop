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
  attributes
}) {
  if (attributes.ref) {
    // Avoid rendering inner blocks when a ref is defined.
    // When this id is defined the inner blocks are loaded from the
    // `wp_navigation` entity rather than the hard-coded block html.
    return;
  }
  return (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null);
}
//# sourceMappingURL=save.js.map