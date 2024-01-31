import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { Placeholder, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { navigation, Icon } from '@wordpress/icons';
import { speak } from '@wordpress/a11y';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import useNavigationEntities from '../../use-navigation-entities';
import PlaceholderPreview from './placeholder-preview';
import NavigationMenuSelector from '../navigation-menu-selector';
export default function NavigationPlaceholder({
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
  } = useNavigationEntities();
  useEffect(() => {
    if (!isSelected) {
      return;
    }
    if (isResolvingMenus) {
      speak(__('Loading navigation block setup options…'));
    }
    if (hasResolvedMenus) {
      speak(__('Navigation block setup options ready.'));
    }
  }, [hasResolvedMenus, isResolvingMenus, isSelected]);
  const isResolvingActions = isResolvingMenus && isResolvingCanUserCreateNavigationMenu;
  return createElement(Fragment, null, createElement(Placeholder, {
    className: "wp-block-navigation-placeholder"
  }, createElement(PlaceholderPreview, {
    isVisible: !isSelected
  }), createElement("div", {
    "aria-hidden": !isSelected ? true : undefined,
    className: "wp-block-navigation-placeholder__controls"
  }, createElement("div", {
    className: "wp-block-navigation-placeholder__actions"
  }, createElement("div", {
    className: "wp-block-navigation-placeholder__actions__indicator"
  }, createElement(Icon, {
    icon: navigation
  }), " ", __('Navigation')), createElement("hr", null), isResolvingActions && createElement(Spinner, null), createElement(NavigationMenuSelector, {
    currentMenuId: currentMenuId,
    clientId: clientId,
    onSelectNavigationMenu: onSelectNavigationMenu,
    onSelectClassicMenu: onSelectClassicMenu
  }), createElement("hr", null), canUserCreateNavigationMenu && createElement(Button, {
    variant: "tertiary",
    onClick: onCreateEmpty
  }, __('Start empty'))))));
}
//# sourceMappingURL=index.js.map