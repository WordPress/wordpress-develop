"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryNoResultsEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
/**
 * WordPress dependencies
 */

const TEMPLATE = [['core/paragraph', {
  placeholder: (0, _i18n.__)('Add text or blocks that will display when a query returns no results.')
}]];
function QueryNoResultsEdit() {
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE
  });
  return (0, _react.createElement)("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=edit.js.map