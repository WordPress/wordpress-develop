"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CommentsPaginationNextEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const arrowMap = {
  none: '',
  arrow: '→',
  chevron: '»'
};
function CommentsPaginationNextEdit({
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
    href: "#comments-pagination-next-pseudo-link",
    onClick: event => event.preventDefault(),
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)(_blockEditor.PlainText, {
    __experimentalVersion: 2,
    tagName: "span",
    "aria-label": (0, _i18n.__)('Newer comments page link'),
    placeholder: (0, _i18n.__)('Newer Comments'),
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }), displayArrow && (0, _react.createElement)("span", {
    className: `wp-block-comments-pagination-next-arrow is-arrow-${paginationArrow}`
  }, displayArrow));
}
//# sourceMappingURL=edit.js.map