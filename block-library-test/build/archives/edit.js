"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ArchivesEdit;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _serverSideRender = _interopRequireDefault(require("@wordpress/server-side-render"));
/**
 * WordPress dependencies
 */

function ArchivesEdit({
  attributes,
  setAttributes
}) {
  const {
    showLabel,
    showPostCounts,
    displayAsDropdown,
    type
  } = attributes;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display as dropdown'),
    checked: displayAsDropdown,
    onChange: () => setAttributes({
      displayAsDropdown: !displayAsDropdown
    })
  }), displayAsDropdown && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show label'),
    checked: showLabel,
    onChange: () => setAttributes({
      showLabel: !showLabel
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show post counts'),
    checked: showPostCounts,
    onChange: () => setAttributes({
      showPostCounts: !showPostCounts
    })
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Group by:'),
    options: [{
      label: (0, _i18n.__)('Year'),
      value: 'yearly'
    }, {
      label: (0, _i18n.__)('Month'),
      value: 'monthly'
    }, {
      label: (0, _i18n.__)('Week'),
      value: 'weekly'
    }, {
      label: (0, _i18n.__)('Day'),
      value: 'daily'
    }],
    value: type,
    onChange: value => setAttributes({
      type: value
    })
  }))), (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_serverSideRender.default, {
    block: "core/archives",
    skipBlockSupportAttributes: true,
    attributes: attributes
  }))));
}
//# sourceMappingURL=edit.js.map