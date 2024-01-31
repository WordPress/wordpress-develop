import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, PlainText } from '@wordpress/block-editor';
const arrowMap = {
  none: '',
  arrow: '→',
  chevron: '»'
};
export default function QueryPaginationNextEdit({
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
    href: "#pagination-next-pseudo-link",
    onClick: event => event.preventDefault(),
    ...useBlockProps()
  }, showLabel && createElement(PlainText, {
    __experimentalVersion: 2,
    tagName: "span",
    "aria-label": __('Next page link'),
    placeholder: __('Next Page'),
    value: label,
    onChange: newLabel => setAttributes({
      label: newLabel
    })
  }), displayArrow && createElement("span", {
    className: `wp-block-query-pagination-next-arrow is-arrow-${paginationArrow}`,
    "aria-hidden": true
  }, displayArrow));
}
//# sourceMappingURL=edit.js.map