"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsPaginationNumbersEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const PaginationItem = ({
  content,
  tag: Tag = 'a',
  extraClass = ''
}) => Tag === 'a' ? (0, _react.createElement)(Tag, {
  className: `page-numbers ${extraClass}`,
  href: "#comments-pagination-numbers-pseudo-link",
  onClick: event => event.preventDefault()
}, content) : (0, _react.createElement)(Tag, {
  className: `page-numbers ${extraClass}`
}, content);
function CommentsPaginationNumbersEdit() {
  return (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)(PaginationItem, {
    content: "1"
  }), (0, _react.createElement)(PaginationItem, {
    content: "2"
  }), (0, _react.createElement)(PaginationItem, {
    content: "3",
    tag: "span",
    extraClass: "current"
  }), (0, _react.createElement)(PaginationItem, {
    content: "4"
  }), (0, _react.createElement)(PaginationItem, {
    content: "5"
  }), (0, _react.createElement)(PaginationItem, {
    content: "...",
    tag: "span",
    extraClass: "dots"
  }), (0, _react.createElement)(PaginationItem, {
    content: "8"
  }));
}
//# sourceMappingURL=edit.js.map