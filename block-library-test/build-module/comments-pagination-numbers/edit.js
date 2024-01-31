import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
const PaginationItem = ({
  content,
  tag: Tag = 'a',
  extraClass = ''
}) => Tag === 'a' ? createElement(Tag, {
  className: `page-numbers ${extraClass}`,
  href: "#comments-pagination-numbers-pseudo-link",
  onClick: event => event.preventDefault()
}, content) : createElement(Tag, {
  className: `page-numbers ${extraClass}`
}, content);
export default function CommentsPaginationNumbersEdit() {
  return createElement("div", {
    ...useBlockProps()
  }, createElement(PaginationItem, {
    content: "1"
  }), createElement(PaginationItem, {
    content: "2"
  }), createElement(PaginationItem, {
    content: "3",
    tag: "span",
    extraClass: "current"
  }), createElement(PaginationItem, {
    content: "4"
  }), createElement(PaginationItem, {
    content: "5"
  }), createElement(PaginationItem, {
    content: "...",
    tag: "span",
    extraClass: "dots"
  }), createElement(PaginationItem, {
    content: "8"
  }));
}
//# sourceMappingURL=edit.js.map