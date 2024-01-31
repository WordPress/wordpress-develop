"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _navigationMenuSelector = _interopRequireDefault(require("./navigation-menu-selector"));
var _lockUnlock = require("../../lock-unlock");
var _deletedNavigationWarning = _interopRequireDefault(require("./deleted-navigation-warning"));
var _useNavigationMenu = _interopRequireDefault(require("../use-navigation-menu"));
var _leafMoreMenu = _interopRequireDefault(require("./leaf-more-menu"));
var _updateAttributes = require("../../navigation-link/update-attributes");
var _linkUi = require("../../navigation-link/link-ui");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/* translators: %s: The name of a menu. */
const actionLabel = (0, _i18n.__)("Switch to '%s'");
const BLOCKS_WITH_LINK_UI_SUPPORT = ['core/navigation-link', 'core/navigation-submenu'];
const {
  PrivateListView
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
function AdditionalBlockContent({
  block,
  insertedBlock,
  setInsertedBlock
}) {
  const {
    updateBlockAttributes
  } = (0, _data.useDispatch)(_blockEditor.store);
  const supportsLinkControls = BLOCKS_WITH_LINK_UI_SUPPORT?.includes(insertedBlock?.name);
  const blockWasJustInserted = insertedBlock?.clientId === block.clientId;
  const showLinkControls = supportsLinkControls && blockWasJustInserted;
  if (!showLinkControls) {
    return null;
  }
  const setInsertedBlockAttributes = _insertedBlockClientId => _updatedAttributes => {
    if (!_insertedBlockClientId) return;
    updateBlockAttributes(_insertedBlockClientId, _updatedAttributes);
  };
  return (0, _react.createElement)(_linkUi.LinkUI, {
    clientId: insertedBlock?.clientId,
    link: insertedBlock?.attributes,
    onClose: () => {
      setInsertedBlock(null);
    },
    onChange: updatedValue => {
      (0, _updateAttributes.updateAttributes)(updatedValue, setInsertedBlockAttributes(insertedBlock?.clientId), insertedBlock?.attributes);
      setInsertedBlock(null);
    },
    onCancel: () => {
      setInsertedBlock(null);
    }
  });
}
const MainContent = ({
  clientId,
  currentMenuId,
  isLoading,
  isNavigationMenuMissing,
  onCreateNew
}) => {
  const hasChildren = (0, _data.useSelect)(select => {
    return !!select(_blockEditor.store).getBlockCount(clientId);
  }, [clientId]);
  const {
    navigationMenu
  } = (0, _useNavigationMenu.default)(currentMenuId);
  if (currentMenuId && isNavigationMenuMissing) {
    return (0, _react.createElement)(_deletedNavigationWarning.default, {
      onCreateNew: onCreateNew
    });
  }
  if (isLoading) {
    return (0, _react.createElement)(_components.Spinner, null);
  }
  const description = navigationMenu ? (0, _i18n.sprintf)( /* translators: %s: The name of a menu. */
  (0, _i18n.__)('Structure for navigation menu: %s'), navigationMenu?.title || (0, _i18n.__)('Untitled menu')) : (0, _i18n.__)('You have not yet created any menus. Displaying a list of your Pages');
  return (0, _react.createElement)("div", {
    className: "wp-block-navigation__menu-inspector-controls"
  }, !hasChildren && (0, _react.createElement)("p", {
    className: "wp-block-navigation__menu-inspector-controls__empty-message"
  }, (0, _i18n.__)('This navigation menu is empty.')), (0, _react.createElement)(PrivateListView, {
    rootClientId: clientId,
    isExpanded: true,
    description: description,
    showAppender: true,
    blockSettingsMenu: _leafMoreMenu.default,
    additionalBlockContent: AdditionalBlockContent
  }));
};
const MenuInspectorControls = props => {
  const {
    createNavigationMenuIsSuccess,
    createNavigationMenuIsError,
    currentMenuId = null,
    onCreateNew,
    onSelectClassicMenu,
    onSelectNavigationMenu,
    isManageMenusButtonDisabled,
    blockEditingMode
  } = props;
  return (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "list"
  }, (0, _react.createElement)(_components.PanelBody, {
    title: null
  }, (0, _react.createElement)(_components.__experimentalHStack, {
    className: "wp-block-navigation-off-canvas-editor__header"
  }, (0, _react.createElement)(_components.__experimentalHeading, {
    className: "wp-block-navigation-off-canvas-editor__title",
    level: 2
  }, (0, _i18n.__)('Menu')), blockEditingMode === 'default' && (0, _react.createElement)(_navigationMenuSelector.default, {
    currentMenuId: currentMenuId,
    onSelectClassicMenu: onSelectClassicMenu,
    onSelectNavigationMenu: onSelectNavigationMenu,
    onCreateNew: onCreateNew,
    createNavigationMenuIsSuccess: createNavigationMenuIsSuccess,
    createNavigationMenuIsError: createNavigationMenuIsError,
    actionLabel: actionLabel,
    isManageMenusButtonDisabled: isManageMenusButtonDisabled
  })), (0, _react.createElement)(MainContent, {
    ...props
  })));
};
var _default = exports.default = MenuInspectorControls;
//# sourceMappingURL=menu-inspector-controls.js.map