"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const TEMPLATE = [['core/buttons', {}, [['core/button', {
  text: (0, _i18n.__)('Submit'),
  tagName: 'button',
  type: 'submit'
}]]]];
const Edit = () => {
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE,
    templateLock: 'all'
  });
  return (0, _react.createElement)("div", {
    className: "wp-block-form-submit-wrapper",
    ...innerBlocksProps
  });
};
var _default = exports.default = Edit;
//# sourceMappingURL=edit.js.map