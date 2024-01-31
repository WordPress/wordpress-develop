import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { PanelBody, ToggleControl, SelectControl, Disabled } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
export default function ArchivesEdit({
  attributes,
  setAttributes
}) {
  const {
    showLabel,
    showPostCounts,
    displayAsDropdown,
    type
  } = attributes;
  return createElement(Fragment, null, createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display as dropdown'),
    checked: displayAsDropdown,
    onChange: () => setAttributes({
      displayAsDropdown: !displayAsDropdown
    })
  }), displayAsDropdown && createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show label'),
    checked: showLabel,
    onChange: () => setAttributes({
      showLabel: !showLabel
    })
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show post counts'),
    checked: showPostCounts,
    onChange: () => setAttributes({
      showPostCounts: !showPostCounts
    })
  }), createElement(SelectControl, {
    __nextHasNoMarginBottom: true,
    label: __('Group by:'),
    options: [{
      label: __('Year'),
      value: 'yearly'
    }, {
      label: __('Month'),
      value: 'monthly'
    }, {
      label: __('Week'),
      value: 'weekly'
    }, {
      label: __('Day'),
      value: 'daily'
    }],
    value: type,
    onChange: value => setAttributes({
      type: value
    })
  }))), createElement("div", {
    ...useBlockProps()
  }, createElement(Disabled, null, createElement(ServerSideRender, {
    block: "core/archives",
    skipBlockSupportAttributes: true,
    attributes: attributes
  }))));
}
//# sourceMappingURL=edit.js.map