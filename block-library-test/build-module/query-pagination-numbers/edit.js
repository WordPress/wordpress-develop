import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
const createPaginationItem = (content, Tag = 'a', extraClass = '') => createElement(Tag, {
  key: content,
  className: `page-numbers ${extraClass}`
}, content);
const previewPaginationNumbers = midSize => {
  const paginationItems = [];

  // First set of pagination items.
  for (let i = 1; i <= midSize; i++) {
    paginationItems.push(createPaginationItem(i));
  }

  // Current pagination item.
  paginationItems.push(createPaginationItem(midSize + 1, 'span', 'current'));

  // Second set of pagination items.
  for (let i = 1; i <= midSize; i++) {
    paginationItems.push(createPaginationItem(midSize + 1 + i));
  }

  // Dots.
  paginationItems.push(createPaginationItem('...', 'span', 'dots'));

  // Last pagination item.
  paginationItems.push(createPaginationItem(midSize * 2 + 3));
  return createElement(Fragment, null, paginationItems);
};
export default function QueryPaginationNumbersEdit({
  attributes,
  setAttributes
}) {
  const {
    midSize
  } = attributes;
  const paginationNumbers = previewPaginationNumbers(parseInt(midSize, 10));
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(RangeControl, {
    label: __('Number of links'),
    help: __('Specify how many links can appear before and after the current page number. Links to the first, current and last page are always visible.'),
    value: midSize,
    onChange: value => {
      setAttributes({
        midSize: parseInt(value, 10)
      });
    },
    min: 0,
    max: 5,
    withInputField: false
  }))), createElement("div", {
    ...useBlockProps()
  }, paginationNumbers));
}
//# sourceMappingURL=edit.js.map