import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { ToggleControl, PanelBody } from '@wordpress/components';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
export default function ReadMore({
  attributes: {
    content,
    linkTarget
  },
  setAttributes,
  insertBlocksAfter
}) {
  const blockProps = useBlockProps();
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Open in new tab'),
    onChange: value => setAttributes({
      linkTarget: value ? '_blank' : '_self'
    }),
    checked: linkTarget === '_blank'
  }))), createElement(RichText, {
    tagName: "a",
    "aria-label": __('“Read more” link text'),
    placeholder: __('Read more'),
    value: content,
    onChange: newValue => setAttributes({
      content: newValue
    }),
    __unstableOnSplitAtEnd: () => insertBlocksAfter(createBlock(getDefaultBlockName())),
    withoutInteractiveFormatting: true,
    ...blockProps
  }));
}
//# sourceMappingURL=edit.js.map