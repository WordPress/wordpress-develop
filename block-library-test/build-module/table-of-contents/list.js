import { createElement, Fragment } from "react";
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

const ENTRY_CLASS_NAME = 'wp-block-table-of-contents__entry';
export default function TableOfContentsList({
  nestedHeadingList,
  disableLinkActivation,
  onClick
}) {
  return createElement(Fragment, null, nestedHeadingList.map((node, index) => {
    const {
      content,
      link
    } = node.heading;
    const entry = link ? createElement("a", {
      className: ENTRY_CLASS_NAME,
      href: link,
      "aria-disabled": disableLinkActivation || undefined,
      onClick: disableLinkActivation && 'function' === typeof onClick ? onClick : undefined
    }, content) : createElement("span", {
      className: ENTRY_CLASS_NAME
    }, content);
    return createElement("li", {
      key: index
    }, entry, node.children ? createElement("ol", null, createElement(TableOfContentsList, {
      nestedHeadingList: node.children,
      disableLinkActivation: disableLinkActivation,
      onClick: disableLinkActivation && 'function' === typeof onClick ? onClick : undefined
    })) : null);
  }));
}
//# sourceMappingURL=list.js.map