"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsPaginationPreviousEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const arrowMap = {
  none: '',
  arrow: '←',
  chevron: '«'
};
function CommentsPaginationPreviousEdit({
  attributes: {
    label
  },
  setAttributes,
  context: {
    'comments/paginationArrow': paginationArrow
  }
}) {
  const displayArrow = arrowMap[paginationArrow];
  return (0, _react.createElement)("a", {
    href: "#comments-pagination-previous-pseudo-link",
    onClick: event => event.preventDefault(),
    ...(0, _blockEditor.useBlockProps)()
  }, displayArrow && (0, _react.createElement)("span", {
    className: `wp-block-comments-pagination-previous-arrow is-arrow-${paginationArrow}`
  }, displayArrow), (0, _react.createElement)(_blockEditor.PlainText, {
    __experimentalVersion: 2,
    tagName: "span",
    "aria-label": (0, _i18n.__)('Older comments page link'),
    placeholder: (0, _i18n.__)('Older Comments'),
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }));
}
//# sourceMappingURL=edit.js.map