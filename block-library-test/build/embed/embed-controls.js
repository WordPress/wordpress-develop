"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

function getResponsiveHelp(checked) {
  return checked ? (0, _i18n.__)('This embed will preserve its aspect ratio when the browser is resized.') : (0, _i18n.__)('This embed may not preserve its aspect ratio when the browser is resized.');
}
const EmbedControls = ({
  blockSupportsResponsive,
  showEditButton,
  themeSupportsResponsive,
  allowResponsive,
  toggleResponsive,
  switchBackToURLInput
}) => (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, showEditButton && (0, _react.createElement)(_components.ToolbarButton, {
  className: "components-toolbar__control",
  label: (0, _i18n.__)('Edit URL'),
  icon: _icons.edit,
  onClick: switchBackToURLInput
}))), themeSupportsResponsive && blockSupportsResponsive && (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
  title: (0, _i18n.__)('Media settings'),
  className: "blocks-responsive"
}, (0, _react.createElement)(_components.ToggleControl, {
  __nextHasNoMarginBottom: true,
  label: (0, _i18n.__)('Resize for smaller devices'),
  checked: allowResponsive,
  help: getResponsiveHelp,
  onChange: toggleResponsive
}))));
var _default = exports.default = EmbedControls;
//# sourceMappingURL=embed-controls.js.map