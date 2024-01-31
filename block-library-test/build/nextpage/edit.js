"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NextPageEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

function NextPageEdit() {
  return (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)("span", null, (0, _i18n.__)('Page break')));
}
//# sourceMappingURL=edit.js.map