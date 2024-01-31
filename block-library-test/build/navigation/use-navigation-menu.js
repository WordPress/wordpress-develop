"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useNavigationMenu;
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _constants = require("./constants");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function useNavigationMenu(ref) {
  const permissions = (0, _coreData.useResourcePermissions)('navigation', ref);
  const {
    navigationMenu,
    isNavigationMenuResolved,
    isNavigationMenuMissing
  } = (0, _data.useSelect)(select => {
    return selectExistingMenu(select, ref);
  }, [ref]);
  const {
    canCreate,
    canUpdate,
    canDelete,
    isResolving,
    hasResolved
  } = permissions;
  const {
    records: navigationMenus,
    isResolving: isResolvingNavigationMenus,
    hasResolved: hasResolvedNavigationMenus
  } = (0, _coreData.useEntityRecords)('postType', `wp_navigation`, _constants.PRELOADED_NAVIGATION_MENUS_QUERY);
  const canSwitchNavigationMenu = ref ? navigationMenus?.length > 1 : navigationMenus?.length > 0;
  return {
    navigationMenu,
    isNavigationMenuResolved,
    isNavigationMenuMissing,
    navigationMenus,
    isResolvingNavigationMenus,
    hasResolvedNavigationMenus,
    canSwitchNavigationMenu,
    canUserCreateNavigationMenu: canCreate,
    isResolvingCanUserCreateNavigationMenu: isResolving,
    hasResolvedCanUserCreateNavigationMenu: hasResolved,
    canUserUpdateNavigationMenu: canUpdate,
    hasResolvedCanUserUpdateNavigationMenu: ref ? hasResolved : undefined,
    canUserDeleteNavigationMenu: canDelete,
    hasResolvedCanUserDeleteNavigationMenu: ref ? hasResolved : undefined
  };
}
function selectExistingMenu(select, ref) {
  if (!ref) {
    return {
      isNavigationMenuResolved: false,
      isNavigationMenuMissing: true
    };
  }
  const {
    getEntityRecord,
    getEditedEntityRecord,
    hasFinishedResolution
  } = select(_coreData.store);
  const args = ['postType', 'wp_navigation', ref];
  const navigationMenu = getEntityRecord(...args);
  const editedNavigationMenu = getEditedEntityRecord(...args);
  const hasResolvedNavigationMenu = hasFinishedResolution('getEditedEntityRecord', args);

  // Only published Navigation posts are considered valid.
  // Draft Navigation posts are valid only on the editor,
  // requiring a post update to publish to show in frontend.
  // To achieve that, index.php must reflect this validation only for published.
  const isNavigationMenuPublishedOrDraft = editedNavigationMenu.status === 'publish' || editedNavigationMenu.status === 'draft';
  return {
    isNavigationMenuResolved: hasResolvedNavigationMenu,
    isNavigationMenuMissing: hasResolvedNavigationMenu && (!navigationMenu || !isNavigationMenuPublishedOrDraft),
    // getEditedEntityRecord will return the post regardless of status.
    // Therefore if the found post is not published then we should ignore it.
    navigationMenu: isNavigationMenuPublishedOrDraft ? editedNavigationMenu : null
  };
}
//# sourceMappingURL=use-navigation-menu.js.map