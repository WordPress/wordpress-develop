"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = RSSEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
var _url = require("@wordpress/url");
var _serverSideRender = _interopRequireDefault(require("@wordpress/server-side-render"));
/**
 * WordPress dependencies
 */

const DEFAULT_MIN_ITEMS = 1;
const DEFAULT_MAX_ITEMS = 20;
function RSSEdit({
  attributes,
  setAttributes
}) {
  const [isEditing, setIsEditing] = (0, _element.useState)(!attributes.feedURL);
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
        feedURL: (0, _url.prependHTTP)(feedURL)
      });
      setIsEditing(false);
    }
  }
  const blockProps = (0, _blockEditor.useBlockProps)();
  if (isEditing) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: _icons.rss,
      label: "RSS"
    }, (0, _react.createElement)("form", {
      onSubmit: onSubmitURL,
      className: "wp-block-rss__placeholder-form"
    }, (0, _react.createElement)(_components.__experimentalHStack, {
      wrap: true
    }, (0, _react.createElement)(_components.__experimentalInputControl, {
      __next40pxDefaultSize: true,
      placeholder: (0, _i18n.__)('Enter URL here…'),
      value: feedURL,
      onChange: value => setAttributes({
        feedURL: value
      }),
      className: "wp-block-rss__placeholder-input"
    }), (0, _react.createElement)(_components.Button, {
      __next40pxDefaultSize: true,
      variant: "primary",
      type: "submit"
    }, (0, _i18n.__)('Use URL'))))));
  }
  const toolbarControls = [{
    icon: _icons.edit,
    title: (0, _i18n.__)('Edit RSS URL'),
    onClick: () => setIsEditing(true)
  }, {
    icon: _icons.list,
    title: (0, _i18n.__)('List view'),
    onClick: () => setAttributes({
      blockLayout: 'list'
    }),
    isActive: blockLayout === 'list'
  }, {
    icon: _icons.grid,
    title: (0, _i18n.__)('Grid view'),
    onClick: () => setAttributes({
      blockLayout: 'grid'
    }),
    isActive: blockLayout === 'grid'
  }];
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, {
    controls: toolbarControls
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Number of items'),
    value: itemsToShow,
    onChange: value => setAttributes({
      itemsToShow: value
    }),
    min: DEFAULT_MIN_ITEMS,
    max: DEFAULT_MAX_ITEMS,
    required: true
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display author'),
    checked: displayAuthor,
    onChange: toggleAttribute('displayAuthor')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display date'),
    checked: displayDate,
    onChange: toggleAttribute('displayDate')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display excerpt'),
    checked: displayExcerpt,
    onChange: toggleAttribute('displayExcerpt')
  }), displayExcerpt && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Max number of words in excerpt'),
    value: excerptLength,
    onChange: value => setAttributes({
      excerptLength: value
    }),
    min: 10,
    max: 100,
    required: true
  }), blockLayout === 'grid' && (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Columns'),
    value: columns,
    onChange: value => setAttributes({
      columns: value
    }),
    min: 2,
    max: 6,
    required: true
  }))), (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_serverSideRender.default, {
    block: "core/rss",
    attributes: attributes
  }))));
}
//# sourceMappingURL=edit.js.map