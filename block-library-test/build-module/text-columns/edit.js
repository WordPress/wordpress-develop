import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { PanelBody, RangeControl } from '@wordpress/components';
import { BlockControls, BlockAlignmentToolbar, InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import deprecated from '@wordpress/deprecated';
export default function TextColumnsEdit({
  attributes,
  setAttributes
}) {
  const {
    width,
    content,
    columns
  } = attributes;
  deprecated('The Text Columns block', {
    since: '5.3',
    alternative: 'the Columns block'
  });
  return createElement(Fragment, null, createElement(BlockControls, null, createElement(BlockAlignmentToolbar, {
    value: width,
    onChange: nextWidth => setAttributes({
      width: nextWidth
    }),
    controls: ['center', 'wide', 'full']
  })), createElement(InspectorControls, null, createElement(PanelBody, null, createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Columns'),
    value: columns,
    onChange: value => setAttributes({
      columns: value
    }),
    min: 2,
    max: 4,
    required: true
  }))), createElement("div", {
    ...useBlockProps({
      className: `align${width} columns-${columns}`
    })
  }, Array.from({
    length: columns
  }).map((_, index) => {
    return createElement("div", {
      className: "wp-block-column",
      key: `column-${index}`
    }, createElement(RichText, {
      tagName: "p",
      value: content?.[index]?.children,
      onChange: nextContent => {
        setAttributes({
          content: [...content.slice(0, index), {
            children: nextContent
          }, ...content.slice(index + 1)]
        });
      },
      "aria-label": sprintf(
      // translators: %d: column index (starting with 1)
      __('Column %d text'), index + 1),
      placeholder: __('New Column')
    }));
  })));
}
//# sourceMappingURL=edit.js.map