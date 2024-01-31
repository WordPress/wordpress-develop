import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { BlockControls, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, Disabled, PanelBody, Placeholder, RangeControl, ToggleControl, ToolbarGroup, __experimentalHStack as HStack, __experimentalInputControl as InputControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { grid, list, edit, rss } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { prependHTTP } from '@wordpress/url';
import ServerSideRender from '@wordpress/server-side-render';
const DEFAULT_MIN_ITEMS = 1;
const DEFAULT_MAX_ITEMS = 20;
export default function RSSEdit({
  attributes,
  setAttributes
}) {
  const [isEditing, setIsEditing] = useState(!attributes.feedURL);
  const {
    blockLayout,
    columns,
    displayAuthor,
    displayDate,
    displayExcerpt,
    excerptLength,
    feedURL,
    itemsToShow
  } = attributes;
  function toggleAttribute(propName) {
    return () => {
      const value = attributes[propName];
      setAttributes({
        [propName]: !value
      });
    };
  }
  function onSubmitURL(event) {
    event.preventDefault();
    if (feedURL) {
      setAttributes({
        feedURL: prependHTTP(feedURL)
      });
      setIsEditing(false);
    }
  }
  const blockProps = useBlockProps();
  if (isEditing) {
    return createElement("div", {
      ...blockProps
    }, createElement(Placeholder, {
      icon: rss,
      label: "RSS"
    }, createElement("form", {
      onSubmit: onSubmitURL,
      className: "wp-block-rss__placeholder-form"
    }, createElement(HStack, {
      wrap: true
    }, createElement(InputControl, {
      __next40pxDefaultSize: true,
      placeholder: __('Enter URL here…'),
      value: feedURL,
      onChange: value => setAttributes({
        feedURL: value
      }),
      className: "wp-block-rss__placeholder-input"
    }), createElement(Button, {
      __next40pxDefaultSize: true,
      variant: "primary",
      type: "submit"
    }, __('Use URL'))))));
  }
  const toolbarControls = [{
    icon: edit,
    title: __('Edit RSS URL'),
    onClick: () => setIsEditing(true)
  }, {
    icon: list,
    title: __('List view'),
    onClick: () => setAttributes({
      blockLayout: 'list'
    }),
    isActive: blockLayout === 'list'
  }, {
    icon: grid,
    title: __('Grid view'),
    onClick: () => setAttributes({
      blockLayout: 'grid'
    }),
    isActive: blockLayout === 'grid'
  }];
  return createElement(Fragment, null, createElement(BlockControls, null, createElement(ToolbarGroup, {
    controls: toolbarControls
  })), createElement(InspectorControls, null, createElement(PanelBody, {
    title: __('Settings')
  }, createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Number of items'),
    value: itemsToShow,
    onChange: value => setAttributes({
      itemsToShow: value
    }),
    min: DEFAULT_MIN_ITEMS,
    max: DEFAULT_MAX_ITEMS,
    required: true
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display author'),
    checked: displayAuthor,
    onChange: toggleAttribute('displayAuthor')
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display date'),
    checked: displayDate,
    onChange: toggleAttribute('displayDate')
  }), createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Display excerpt'),
    checked: displayExcerpt,
    onChange: toggleAttribute('displayExcerpt')
  }), displayExcerpt && createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Max number of words in excerpt'),
    value: excerptLength,
    onChange: value => setAttributes({
      excerptLength: value
    }),
    min: 10,
    max: 100,
    required: true
  }), blockLayout === 'grid' && createElement(RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: __('Columns'),
    value: columns,
    onChange: value => setAttributes({
      columns: value
    }),
    min: 2,
    max: 6,
    required: true
  }))), createElement("div", {
    ...blockProps
  }, createElement(Disabled, null, createElement(ServerSideRender, {
    block: "core/rss",
    attributes: attributes
  }))));
}
//# sourceMappingURL=edit.js.map