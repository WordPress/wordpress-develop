"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = OverlayMenuPreview;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _overlayMenuIcon = _interopRequireDefault(require("./overlay-menu-icon"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function OverlayMenuPreview({
  setAttributes,
  hasIcon,
  icon
}) {
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show icon button'),
    help: (0, _i18n.__)('Configure the visual appearance of the button that toggles the overlay menu.'),
    onChange: value => setAttributes({
      hasIcon: value
    }),
    checked: hasIcon
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Icon'),
    value: icon,
    onChange: value => setAttributes({
      icon: value
    }),
    isBlock: true
  }, (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "handle",
    "aria-label": (0, _i18n.__)('handle'),
    label: (0, _react.createElement)(_overlayMenuIcon.default, {
      icon: "handle"
    })
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "menu",
    "aria-label": (0, _i18n.__)('menu'),
    label: (0, _react.createElement)(_overlayMenuIcon.default, {
      icon: "menu"
    })
  })));
}
//# sourceMappingURL=overlay-menu-preview.js.map