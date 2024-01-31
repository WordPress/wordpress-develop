"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
var _icons = require("@wordpress/icons");
var _i18n = require("@wordpress/i18n");
var _htmlEntities = require("@wordpress/html-entities");
var _element = require("@wordpress/element");
var _coreData = require("@wordpress/core-data");
var _useNavigationMenu = _interopRequireDefault(require("../use-navigation-menu"));
var _useNavigationEntities = _interopRequireDefault(require("../use-navigation-entities"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function buildMenuLabel(title, id, status) {
  if (!title) {
    /* translators: %s is the index of the menu in the list of menus. */
    return (0, _i18n.sprintf)((0, _i18n.__)('(no title %s)'), id);
  }
  if (status === 'publish') {
    return (0, _htmlEntities.decodeEntities)(title);
  }
  return (0, _i18n.sprintf)(
  // translators: %1s: title of the menu; %2s: status of the menu (draft, pending, etc.).
  (0, _i18n.__)('%1$s (%2$s)'), (0, _htmlEntities.decodeEntities)(title), status);
}
function NavigationMenuSelector({
  currentMenuId,
  onSelectNavigationMenu,
  onSelectClassicMenu,
  onCreateNew,
  actionLabel,
  createNavigationMenuIsSuccess,
  createNavigationMenuIsError
}) {
  /* translators: %s: The name of a menu. */
  const createActionLabel = (0, _i18n.__)("Create from '%s'");
  const [isCreatingMenu, setIsCreatingMenu] = (0, _element.useState)(false);
  actionLabel = actionLabel || createActionLabel;
  const {
    menus: classicMenus
  } = (0, _useNavigationEntities.default)();
  const {
    navigationMenus,
    isResolvingNavigationMenus,
    hasResolvedNavigationMenus,
    canUserCreateNavigationMenu,
    canSwitchNavigationMenu
  } = (0, _useNavigationMenu.default)();
  const [currentTitle] = (0, _coreData.useEntityProp)('postType', 'wp_navigation', 'title');
  const menuChoices = (0, _element.useMemo)(() => {
    return navigationMenus?.map(({
      id,
      title,
      status
    }, index) => {
      const label = buildMenuLabel(title?.rendered, index + 1, status);
      return {
        value: id,
        label,
        ariaLabel: (0, _i18n.sprintf)(actionLabel, label)
      };
    }) || [];
  }, [navigationMenus, actionLabel]);
  const hasNavigationMenus = !!navigationMenus?.length;
  const hasClassicMenus = !!classicMenus?.length;
  const showNavigationMenus = !!canSwitchNavigationMenu;
  const showClassicMenus = !!canUserCreateNavigationMenu;
  const noMenuSelected = hasNavigationMenus && !currentMenuId;
  const noBlockMenus = !hasNavigationMenus && hasResolvedNavigationMenus;
  const menuUnavailable = hasResolvedNavigationMenus && currentMenuId === null;
  let selectorLabel = '';
  if (isCreatingMenu || isResolvingNavigationMenus) {
    selectorLabel = (0, _i18n.__)('Loading…');
  } else if (noMenuSelected || noBlockMenus || menuUnavailable) {
    // Note: classic Menus may be available.
    selectorLabel = (0, _i18n.__)('Choose or create a Navigation menu');
  } else {
    // Current Menu's title.
    selectorLabel = currentTitle;
  }
  (0, _element.useEffect)(() => {
    if (isCreatingMenu && (createNavigationMenuIsSuccess || createNavigationMenuIsError)) {
      setIsCreatingMenu(false);
    }
  }, [hasResolvedNavigationMenus, createNavigationMenuIsSuccess, canUserCreateNavigationMenu, createNavigationMenuIsError, isCreatingMenu, menuUnavailable, noBlockMenus, noMenuSelected]);
  const NavigationMenuSelectorDropdown = (0, _react.createElement)(_components.DropdownMenu, {
    label: selectorLabel,
    icon: _icons.moreVertical,
    toggleProps: {
      isSmall: true
    }
  }, ({
    onClose
  }) => (0, _react.createElement)(_react.Fragment, null, showNavigationMenus && hasNavigationMenus && (0, _react.createElement)(_components.MenuGroup, {
    label: (0, _i18n.__)('Menus')
  }, (0, _react.createElement)(_components.MenuItemsChoice, {
    value: currentMenuId,
    onSelect: menuId => {
      setIsCreatingMenu(true);
      onSelectNavigationMenu(menuId);
      onClose();
    },
    choices: menuChoices,
    disabled: isCreatingMenu
  })), showClassicMenus && hasClassicMenus && (0, _react.createElement)(_components.MenuGroup, {
    label: (0, _i18n.__)('Import Classic Menus')
  }, classicMenus?.map(menu => {
    const label = (0, _htmlEntities.decodeEntities)(menu.name);
    return (0, _react.createElement)(_components.MenuItem, {
      onClick: () => {
        setIsCreatingMenu(true);
        onSelectClassicMenu(menu);
        onClose();
      },
      key: menu.id,
      "aria-label": (0, _i18n.sprintf)(createActionLabel, label),
      disabled: isCreatingMenu
    }, label);
  })), canUserCreateNavigationMenu && (0, _react.createElement)(_components.MenuGroup, {
    label: (0, _i18n.__)('Tools')
  }, (0, _react.createElement)(_components.MenuItem, {
    disabled: isCreatingMenu,
    onClick: () => {
      onClose();
      onCreateNew();
      setIsCreatingMenu(true);
    }
  }, (0, _i18n.__)('Create new menu')))));
  return NavigationMenuSelectorDropdown;
}
var _default = exports.default = NavigationMenuSelector;
//# sourceMappingURL=navigation-menu-selector.js.map