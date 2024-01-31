"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _embedLinkSettings = _interopRequireDefault(require("./embed-link-settings"));
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _data = require("@wordpress/data");
var _editPost = require("@wordpress/edit-post");
/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

// eslint-disable-next-line no-restricted-imports

function getResponsiveHelp(checked) {
  return checked ? (0, _i18n.__)('This embed will preserve its aspect ratio when the browser is resized.') : (0, _i18n.__)('This embed may not preserve its aspect ratio when the browser is resized.');
}
const EmbedControls = ({
  blockSupportsResponsive,
  themeSupportsResponsive,
  allowResponsive,
  toggleResponsive,
  url,
  linkLabel,
  onEditURL
}) => {
  const {
    closeGeneralSidebar: closeSettingsBottomSheet
  } = (0, _data.useDispatch)(_editPost.store);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, themeSupportsResponsive && blockSupportsResponsive && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Media settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    label: (0, _i18n.__)('Resize for smaller devices'),
    checked: allowResponsive,
    help: getResponsiveHelp,
    onChange: toggleResponsive
  })), (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Link settings')
  }, (0, _react.createElement)(_embedLinkSettings.default, {
    value: url,
    label: linkLabel,
    onSubmit: value => {
      closeSettingsBottomSheet();
      onEditURL(value);
    }
  }))));
};
var _default = exports.default = EmbedControls;
//# sourceMappingURL=embed-controls.native.js.map