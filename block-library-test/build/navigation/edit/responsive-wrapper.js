"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ResponsiveWrapper;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _icons = require("@wordpress/icons");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _overlayMenuIcon = _interopRequireDefault(require("./overlay-menu-icon"));
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function ResponsiveWrapper({
  children,
  id,
  isOpen,
  isResponsive,
  onToggle,
  isHiddenByDefault,
  overlayBackgroundColor,
  overlayTextColor,
  hasIcon,
  icon
}) {
  if (!isResponsive) {
    return children;
  }
  const responsiveContainerClasses = (0, _classnames.default)('wp-block-navigation__responsive-container', {
    'has-text-color': !!overlayTextColor.color || !!overlayTextColor?.class,
    [(0, _blockEditor.getColorClassName)('color', overlayTextColor?.slug)]: !!overlayTextColor?.slug,
    'has-background': !!overlayBackgroundColor.color || overlayBackgroundColor?.class,
    [(0, _blockEditor.getColorClassName)('background-color', overlayBackgroundColor?.slug)]: !!overlayBackgroundColor?.slug,
    'is-menu-open': isOpen,
    'hidden-by-default': isHiddenByDefault
  });
  const styles = {
    color: !overlayTextColor?.slug && overlayTextColor?.color,
    backgroundColor: !overlayBackgroundColor?.slug && overlayBackgroundColor?.color && overlayBackgroundColor.color
  };
  const openButtonClasses = (0, _classnames.default)('wp-block-navigation__responsive-container-open', {
    'always-shown': isHiddenByDefault
  });
  const modalId = `${id}-modal`;
  const dialogProps = {
    className: 'wp-block-navigation__responsive-dialog',
    ...(isOpen && {
      role: 'dialog',
      'aria-modal': true,
      'aria-label': (0, _i18n.__)('Menu')
    })
  };
  return (0, _react.createElement)(_react.Fragment, null, !isOpen && (0, _react.createElement)(_components.Button, {
    "aria-haspopup": "true",
    "aria-label": hasIcon && (0, _i18n.__)('Open menu'),
    className: openButtonClasses,
    onClick: () => onToggle(true)
  }, hasIcon && (0, _react.createElement)(_overlayMenuIcon.default, {
    icon: icon
  }), !hasIcon && (0, _i18n.__)('Menu')), (0, _react.createElement)("div", {
    className: responsiveContainerClasses,
    style: styles,
    id: modalId
  }, (0, _react.createElement)("div", {
    className: "wp-block-navigation__responsive-close",
    tabIndex: "-1"
  }, (0, _react.createElement)("div", {
    ...dialogProps
  }, (0, _react.createElement)(_components.Button, {
    className: "wp-block-navigation__responsive-container-close",
    "aria-label": hasIcon && (0, _i18n.__)('Close menu'),
    onClick: () => onToggle(false)
  }, hasIcon && (0, _react.createElement)(_icons.Icon, {
    icon: _icons.close
  }), !hasIcon && (0, _i18n.__)('Close')), (0, _react.createElement)("div", {
    className: "wp-block-navigation__responsive-container-content",
    id: `${modalId}-content`
  }, children)))));
}
//# sourceMappingURL=responsive-wrapper.js.map