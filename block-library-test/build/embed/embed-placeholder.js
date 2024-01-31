"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
/**
 * WordPress dependencies
 */

const EmbedPlaceholder = ({
  icon,
  label,
  value,
  onSubmit,
  onChange,
  cannotEmbed,
  fallback,
  tryAgain
}) => {
  return (0, _react.createElement)(_components.Placeholder, {
    icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
      icon: icon,
      showColors: true
    }),
    label: label,
    className: "wp-block-embed",
    instructions: (0, _i18n.__)('Paste a link to the content you want to display on your site.')
  }, (0, _react.createElement)("form", {
    onSubmit: onSubmit
  }, (0, _react.createElement)("input", {
    type: "url",
    value: value || '',
    className: "components-placeholder__input",
    "aria-label": label,
    placeholder: (0, _i18n.__)('Enter URL to embed here…'),
    onChange: onChange
  }), (0, _react.createElement)(_components.Button, {
    variant: "primary",
    type: "submit"
  }, (0, _i18n._x)('Embed', 'button label'))), (0, _react.createElement)("div", {
    className: "wp-block-embed__learn-more"
  }, (0, _react.createElement)(_components.ExternalLink, {
    href: (0, _i18n.__)('https://wordpress.org/documentation/article/embeds/')
  }, (0, _i18n.__)('Learn more about embeds'))), cannotEmbed && (0, _react.createElement)("div", {
    className: "components-placeholder__error"
  }, (0, _react.createElement)("div", {
    className: "components-placeholder__instructions"
  }, (0, _i18n.__)('Sorry, this content could not be embedded.')), (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    onClick: tryAgain
  }, (0, _i18n._x)('Try again', 'button label')), ' ', (0, _react.createElement)(_components.Button, {
    variant: "secondary",
    onClick: fallback
  }, (0, _i18n._x)('Convert to link', 'button label'))));
};
var _default = exports.default = EmbedPlaceholder;
//# sourceMappingURL=embed-placeholder.js.map