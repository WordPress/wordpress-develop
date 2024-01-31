import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, PlainText } from '@wordpress/block-editor';
const arrowMap = {
  none: '',
  arrow: '←',
  chevron: '«'
};
export default function QueryPaginationPreviousEdit({
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
  return createElement("a", {
    href: "#pagination-previous-pseudo-link",
    onClick: event => event.preventDefault(),
    ...useBlockProps()
  }, displayArrow && createElement("span", {
    className: `wp-block-query-pagination-previous-arrow is-arrow-${paginationArrow}`,
    "aria-hidden": true
  }, displayArrow), showLabel && createElement(PlainText, {
    __experimentalVersion: 2,
    tagName: "span",
    "aria-label": __('Previous page link'),
    placeholder: __('Previous Page'),
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }));
}
//# sourceMappingURL=edit.js.map