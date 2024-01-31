"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryPaginationNumbersEdit;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
/**
 * WordPress dependencies
 */

const createPaginationItem = (content, Tag = 'a', extraClass = '') => (0, _react.createElement)(Tag, {
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
  return (0, _react.createElement)(_react.Fragment, null, paginationItems);
};
function QueryPaginationNumbersEdit({
  attributes,
  setAttributes
}) {
  const {
    midSize
  } = attributes;
  const paginationNumbers = previewPaginationNumbers(parseInt(midSize, 10));
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.RangeControl, {
    label: (0, _i18n.__)('Number of links'),
    help: (0, _i18n.__)('Specify how many links can appear before and after the current page number. Links to the first, current and last page are always visible.'),
    value: midSize,
    onChange: value => {
      setAttributes({
        midSize: parseInt(value, 10)
      });
    },
    min: 0,
    max: 5,
    withInputField: false
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, paginationNumbers));
}
//# sourceMappingURL=edit.js.map