"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TableOfContentsList;
var _react = require("react");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

const ENTRY_CLASS_NAME = 'wp-block-table-of-contents__entry';
function TableOfContentsList({
  nestedHeadingList,
  disableLinkActivation,
  onClick
}) {
  return (0, _react.createElement)(_react.Fragment, null, nestedHeadingList.map((node, index) => {
    const {
      content,
      link
    } = node.heading;
    const entry = link ? (0, _react.createElement)("a", {
      className: ENTRY_CLASS_NAME,
      href: link,
      "aria-disabled": disableLinkActivation || undefined,
      onClick: disableLinkActivation && 'function' === typeof onClick ? onClick : undefined
    }, content) : (0, _react.createElement)("span", {
      className: ENTRY_CLASS_NAME
    }, content);
    return (0, _react.createElement)("li", {
      key: index
    }, entry, node.children ? (0, _react.createElement)("ol", null, (0, _react.createElement)(TableOfContentsList, {
      nestedHeadingList: node.children,
      disableLinkActivation: disableLinkActivation,
      onClick: disableLinkActivation && 'function' === typeof onClick ? onClick : undefined
    })) : null);
  }));
}
//# sourceMappingURL=list.js.map