"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = exports.CLASSIC_MENU_CONVERSION_SUCCESS = exports.CLASSIC_MENU_CONVERSION_PENDING = exports.CLASSIC_MENU_CONVERSION_IDLE = exports.CLASSIC_MENU_CONVERSION_ERROR = void 0;
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _menuItemsToBlocks = _interopRequireDefault(require("../menu-items-to-blocks"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const CLASSIC_MENU_CONVERSION_SUCCESS = exports.CLASSIC_MENU_CONVERSION_SUCCESS = 'success';
const CLASSIC_MENU_CONVERSION_ERROR = exports.CLASSIC_MENU_CONVERSION_ERROR = 'error';
const CLASSIC_MENU_CONVERSION_PENDING = exports.CLASSIC_MENU_CONVERSION_PENDING = 'pending';
const CLASSIC_MENU_CONVERSION_IDLE = exports.CLASSIC_MENU_CONVERSION_IDLE = 'idle';

// This is needed to ensure that multiple components using this hook
// do not import the same classic menu twice.
let classicMenuBeingConvertedId = null;
function useConvertClassicToBlockMenu(createNavigationMenu, {
  throwOnError = false
} = {}) {
  const registry = (0, _data.useRegistry)();
  const {
    editEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  const [status, setStatus] = (0, _element.useState)(CLASSIC_MENU_CONVERSION_IDLE);
  const [error, setError] = (0, _element.useState)(null);
  const convertClassicMenuToBlockMenu = (0, _element.useCallback)(async (menuId, menuName, postStatus = 'publish') => {
    let navigationMenu;
    let classicMenuItems;

    // 1. Fetch the classic Menu items.
    try {
      classicMenuItems = await registry.resolveSelect(_coreData.store).getMenuItems({
        menus: menuId,
        per_page: -1,
        context: 'view'
      });
    } catch (err) {
      throw new Error((0, _i18n.sprintf)(
      // translators: %s: the name of a menu (e.g. Header navigation).
      (0, _i18n.__)(`Unable to fetch classic menu "%s" from API.`), menuName), {
        cause: err
      });
    }

    // Handle offline response which resolves to `null`.
    if (classicMenuItems === null) {
      throw new Error((0, _i18n.sprintf)(
      // translators: %s: the name of a menu (e.g. Header navigation).
      (0, _i18n.__)(`Unable to fetch classic menu "%s" from API.`), menuName));
    }

    // 2. Convert the classic items into blocks.
    const {
      innerBlocks
    } = (0, _menuItemsToBlocks.default)(classicMenuItems);

    // 3. Create the `wp_navigation` Post with the blocks.
    try {
      navigationMenu = await createNavigationMenu(menuName, innerBlocks, postStatus);

      /**
       * Immediately trigger editEntityRecord to change the wp_navigation post status to 'publish'.
       * This status change causes the menu to be displayed on the front of the site and sets the post state to be "dirty".
       * The problem being solved is if saveEditedEntityRecord was used here, the menu would be updated on the frontend and the editor _automatically_,
       * without user interaction.
       * If the user abandons the site editor without saving, there would still be a wp_navigation post created as draft.
       */
      await editEntityRecord('postType', 'wp_navigation', navigationMenu.id, {
        status: 'publish'
      }, {
        throwOnError: true
      });
    } catch (err) {
      throw new Error((0, _i18n.sprintf)(
      // translators: %s: the name of a menu (e.g. Header navigation).
      (0, _i18n.__)(`Unable to create Navigation Menu "%s".`), menuName), {
        cause: err
      });
    }
    return navigationMenu;
  }, [createNavigationMenu, editEntityRecord, registry]);
  const convert = (0, _element.useCallback)(async (menuId, menuName, postStatus) => {
    // Check whether this classic menu is being imported already.
    if (classicMenuBeingConvertedId === menuId) {
      return;
    }

    // Set the ID for the currently importing classic menu.
    classicMenuBeingConvertedId = menuId;
    if (!menuId || !menuName) {
      setError('Unable to convert menu. Missing menu details.');
      setStatus(CLASSIC_MENU_CONVERSION_ERROR);
      return;
    }
    setStatus(CLASSIC_MENU_CONVERSION_PENDING);
    setError(null);
    return await convertClassicMenuToBlockMenu(menuId, menuName, postStatus).then(navigationMenu => {
      setStatus(CLASSIC_MENU_CONVERSION_SUCCESS);
      // Reset the ID for the currently importing classic menu.
      classicMenuBeingConvertedId = null;
      return navigationMenu;
    }).catch(err => {
      setError(err?.message);
      // Reset the ID for the currently importing classic menu.
      setStatus(CLASSIC_MENU_CONVERSION_ERROR);

      // Reset the ID for the currently importing classic menu.
      classicMenuBeingConvertedId = null;

      // Rethrow error for debugging.
      if (throwOnError) {
        throw new Error((0, _i18n.sprintf)(
        // translators: %s: the name of a menu (e.g. Header navigation).
        (0, _i18n.__)(`Unable to create Navigation Menu "%s".`), menuName), {
          cause: err
        });
      }
    });
  }, [convertClassicMenuToBlockMenu, throwOnError]);
  return {
    convert,
    status,
    error
  };
}
var _default = exports.default = useConvertClassicToBlockMenu;
//# sourceMappingURL=use-convert-classic-menu-to-block-menu.js.map