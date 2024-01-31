"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryPaginationNextEdit;
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
function QueryPaginationNextEdit({
  attributes: {
    label
  },
  setAttributes,
  context: {
    paginationArrow,
    showLabel
  }
}) {
  const displayArrow = arrowMap[paginationArrow];
  return (0, _react.createElement)("a", {
    href: "#pagination-next-pseudo-link",
    onClick: event => event.preventDefault(),
    ...(0, _blockEditor.useBlockProps)()
  }, showLabel && (0, _react.createElement)(_blockEditor.PlainText, {
    __experimentalVersion: 2,
    tagName: "span",
    "aria-label": (0, _i18n.__)('Next page link'),
    placeholder: (0, _i18n.__)('Next Page'),
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }), displayArrow && (0, _react.createElement)("span", {
    className: `wp-block-query-pagination-next-arrow is-arrow-${paginationArrow}`,
    "aria-hidden": true
  }, displayArrow));
}
//# sourceMappingURL=edit.js.map