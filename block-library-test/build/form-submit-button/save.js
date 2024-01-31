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

const Save = () => {
  const blockProps = _blockEditor.useBlockProps.save();
  return (0, _react.createElement)("div", {
    className: "wp-block-form-submit-wrapper",
    ...blockProps
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
};
var _default = exports.default = Save;
//# sourceMappingURL=save.js.map