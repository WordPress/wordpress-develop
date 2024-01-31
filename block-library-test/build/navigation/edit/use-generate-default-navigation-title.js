"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useGenerateDefaultNavigationTitle;
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _useTemplatePartAreaLabel = _interopRequireDefault(require("../use-template-part-area-label"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DRAFT_MENU_PARAMS = ['postType', 'wp_navigation', {
  status: 'draft',
  per_page: -1
}];
const PUBLISHED_MENU_PARAMS = ['postType', 'wp_navigation', {
  per_page: -1,
  status: 'publish'
}];
function useGenerateDefaultNavigationTitle(clientId) {
  // The block will be disabled in a block preview, use this as a way of
  // avoiding the side-effects of this component for block previews.
  const isDisabled = (0, _element.useContext)(_components.Disabled.Context);

  // Because we can't conditionally call hooks, pass an undefined client id
  // arg to bypass the expensive `useTemplateArea` code. The hook will return
  // early.
  const area = (0, _useTemplatePartAreaLabel.default)(isDisabled ? undefined : clientId);
  const registry = (0, _data.useRegistry)();
  return (0, _element.useCallback)(async () => {
    // Ensure other navigation menus have loaded so an
    // accurate name can be created.
    if (isDisabled) {
      return '';
    }
    const {
      getEntityRecords
    } = registry.resolveSelect(_coreData.store);
    const [draftNavigationMenus, navigationMenus] = await Promise.all([getEntityRecords(...DRAFT_MENU_PARAMS), getEntityRecords(...PUBLISHED_MENU_PARAMS)]);
    const title = area ? (0, _i18n.sprintf)(
    // translators: %s: the name of a menu (e.g. Header navigation).
    (0, _i18n.__)('%s navigation'), area) :
    // translators: 'navigation' as in website navigation.
    (0, _i18n.__)('Navigation');

    // Determine how many menus start with the automatic title.
    const matchingMenuTitleCount = [...draftNavigationMenus, ...navigationMenus].reduce((count, menu) => menu?.title?.raw?.startsWith(title) ? count + 1 : count, 0);

    // Append a number to the end of the title if a menu with
    // the same name exists.
    const titleWithCount = matchingMenuTitleCount > 0 ? `${title} ${matchingMenuTitleCount + 1}` : title;
    return titleWithCount || '';
  }, [isDisabled, area, registry]);
}
//# sourceMappingURL=use-generate-default-navigation-title.js.map