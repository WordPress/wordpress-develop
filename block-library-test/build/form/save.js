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

const Save = ({
  attributes
}) => {
  const blockProps = _blockEditor.useBlockProps.save();
  const {
    submissionMethod
  } = attributes;
  return (0, _react.createElement)("form", {
    ...blockProps,
    className: "wp-block-form",
    encType: submissionMethod === 'email' ? 'text/plain' : null
  }, (0, _react.createElement)(_blockEditor.InnerBlocks.Content, null));
};
var _default = exports.default = Save;
//# sourceMappingURL=save.js.map