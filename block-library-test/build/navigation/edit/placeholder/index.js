"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationPlaceholder;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _a11y = require("@wordpress/a11y");
var _element = require("@wordpress/element");
var _useNavigationEntities = _interopRequireDefault(require("../../use-navigation-entities"));
var _placeholderPreview = _interopRequireDefault(require("./placeholder-preview"));
var _navigationMenuSelector = _interopRequireDefault(require("../navigation-menu-selector"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function NavigationPlaceholder({
  isSelected,
  currentMenuId,
  clientId,
  canUserCreateNavigationMenu = false,
  isResolvingCanUserCreateNavigationMenu,
  onSelectNavigationMenu,
  onSelectClassicMenu,
  onCreateEmpty
}) {
  const {
    isResolvingMenus,
    hasResolvedMenus
  } = (0, _useNavigationEntities.default)();
  (0, _element.useEffect)(() => {
    if (!isSelected) {
      return;
    }
    if (isResolvingMenus) {
      (0, _a11y.speak)((0, _i18n.__)('Loading navigation block setup options…'));
    }
    if (hasResolvedMenus) {
      (0, _a11y.speak)((0, _i18n.__)('Navigation block setup options ready.'));
    }
  }, [hasResolvedMenus, isResolvingMenus, isSelected]);
  const isResolvingActions = isResolvingMenus && isResolvingCanUserCreateNavigationMenu;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.Placeholder, {
    className: "wp-block-navigation-placeholder"
  }, (0, _react.createElement)(_placeholderPreview.default, {
    isVisible: !isSelected
  }), (0, _react.createElement)("div", {
    "aria-hidden": !isSelected ? true : undefined,
    className: "wp-block-navigation-placeholder__controls"
  }, (0, _react.createElement)("div", {
    className: "wp-block-navigation-placeholder__actions"
  }, (0, _react.createElement)("div", {
    className: "wp-block-navigation-placeholder__actions__indicator"
  }, (0, _react.createElement)(_icons.Icon, {
    icon: _icons.navigation
  }), " ", (0, _i18n.__)('Navigation')), (0, _react.createElement)("hr", null), isResolvingActions && (0, _react.createElement)(_components.Spinner, null), (0, _react.createElement)(_navigationMenuSelector.default, {
    currentMenuId: currentMenuId,
    clientId: clientId,
    onSelectNavigationMenu: onSelectNavigationMenu,
    onSelectClassicMenu: onSelectClassicMenu
  }), (0, _react.createElement)("hr", null), canUserCreateNavigationMenu && (0, _react.createElement)(_components.Button, {
    variant: "tertiary",
    onClick: onCreateEmpty
  }, (0, _i18n.__)('Start empty'))))));
}
//# sourceMappingURL=index.js.map