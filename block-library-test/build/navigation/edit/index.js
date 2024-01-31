"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _a11y = require("@wordpress/a11y");
var _icons = require("@wordpress/icons");
var _compose = require("@wordpress/compose");
var _useNavigationMenu = _interopRequireDefault(require("../use-navigation-menu"));
var _useNavigationEntities = _interopRequireDefault(require("../use-navigation-entities"));
var _placeholder = _interopRequireDefault(require("./placeholder"));
var _responsiveWrapper = _interopRequireDefault(require("./responsive-wrapper"));
var _innerBlocks = _interopRequireDefault(require("./inner-blocks"));
var _navigationMenuNameControl = _interopRequireDefault(require("./navigation-menu-name-control"));
var _unsavedInnerBlocks = _interopRequireDefault(require("./unsaved-inner-blocks"));
var _navigationMenuDeleteControl = _interopRequireDefault(require("./navigation-menu-delete-control"));
var _useNavigationNotice = _interopRequireDefault(require("./use-navigation-notice"));
var _overlayMenuIcon = _interopRequireDefault(require("./overlay-menu-icon"));
var _overlayMenuPreview = _interopRequireDefault(require("./overlay-menu-preview"));
var _useConvertClassicMenuToBlockMenu = _interopRequireWildcard(require("./use-convert-classic-menu-to-block-menu"));
var _useCreateNavigationMenu = _interopRequireDefault(require("./use-create-navigation-menu"));
var _useInnerBlocks = require("./use-inner-blocks");
var _utils = require("./utils");
var _manageMenusButton = _interopRequireDefault(require("./manage-menus-button"));
var _menuInspectorControls = _interopRequireDefault(require("./menu-inspector-controls"));
var _deletedNavigationWarning = _interopRequireDefault(require("./deleted-navigation-warning"));
var _accessibleDescription = _interopRequireDefault(require("./accessible-description"));
var _accessibleMenuDescription = _interopRequireDefault(require("./accessible-menu-description"));
var _constants = require("../constants");
var _lockUnlock = require("../../lock-unlock");
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function Navigation({
  attributes,
  setAttributes,
  clientId,
  isSelected,
  className,
  backgroundColor,
  setBackgroundColor,
  textColor,
  setTextColor,
  overlayBackgroundColor,
  setOverlayBackgroundColor,
  overlayTextColor,
  setOverlayTextColor,
  // These props are used by the navigation editor to override specific
  // navigation block settings.
  hasSubmenuIndicatorSetting = true,
  customPlaceholder: CustomPlaceholder = null,
  __unstableLayoutClassNames: layoutClassNames
}) {
  const {
    openSubmenusOnClick,
    overlayMenu,
    showSubmenuIcon,
    templateLock,
    layout: {
      justifyContent,
      orientation = 'horizontal',
      flexWrap = 'wrap'
    } = {},
    hasIcon,
    icon = 'handle'
  } = attributes;
  const ref = attributes.ref;
  const setRef = (0, _element.useCallback)(postId => {
    setAttributes({
      ref: postId
    });
  }, [setAttributes]);
  const recursionId = `navigationMenu/${ref}`;
  const hasAlreadyRendered = (0, _blockEditor.useHasRecursion)(recursionId);
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();

  // Preload classic menus, so that they don't suddenly pop-in when viewing
  // the Select Menu dropdown.
  const {
    menus: classicMenus
  } = (0, _useNavigationEntities.default)();
  const [showNavigationMenuStatusNotice, hideNavigationMenuStatusNotice] = (0, _useNavigationNotice.default)({
    name: 'block-library/core/navigation/status'
  });
  const [showClassicMenuConversionNotice, hideClassicMenuConversionNotice] = (0, _useNavigationNotice.default)({
    name: 'block-library/core/navigation/classic-menu-conversion'
  });
  const [showNavigationMenuPermissionsNotice, hideNavigationMenuPermissionsNotice] = (0, _useNavigationNotice.default)({
    name: 'block-library/core/navigation/permissions/update'
  });
  const {
    create: createNavigationMenu,
    status: createNavigationMenuStatus,
    error: createNavigationMenuError,
    value: createNavigationMenuPost,
    isPending: isCreatingNavigationMenu,
    isSuccess: createNavigationMenuIsSuccess,
    isError: createNavigationMenuIsError
  } = (0, _useCreateNavigationMenu.default)(clientId);
  const createUntitledEmptyNavigationMenu = () => {
    createNavigationMenu('');
  };
  const {
    hasUncontrolledInnerBlocks,
    uncontrolledInnerBlocks,
    isInnerBlockSelected,
    innerBlocks
  } = (0, _useInnerBlocks.useInnerBlocks)(clientId);
  const hasSubmenus = !!innerBlocks.find(block => block.name === 'core/navigation-submenu');
  const {
    replaceInnerBlocks,
    selectBlock,
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const [isResponsiveMenuOpen, setResponsiveMenuVisibility] = (0, _element.useState)(false);
  const [overlayMenuPreview, setOverlayMenuPreview] = (0, _element.useState)(false);
  const {
    hasResolvedNavigationMenus,
    isNavigationMenuResolved,
    isNavigationMenuMissing,
    canUserUpdateNavigationMenu,
    hasResolvedCanUserUpdateNavigationMenu,
    canUserDeleteNavigationMenu,
    hasResolvedCanUserDeleteNavigationMenu,
    canUserCreateNavigationMenu,
    isResolvingCanUserCreateNavigationMenu,
    hasResolvedCanUserCreateNavigationMenu
  } = (0, _useNavigationMenu.default)(ref);
  const navMenuResolvedButMissing = hasResolvedNavigationMenus && isNavigationMenuMissing;
  const {
    convert: convertClassicMenu,
    status: classicMenuConversionStatus,
    error: classicMenuConversionError
  } = (0, _useConvertClassicMenuToBlockMenu.default)(createNavigationMenu);
  const isConvertingClassicMenu = classicMenuConversionStatus === _useConvertClassicMenuToBlockMenu.CLASSIC_MENU_CONVERSION_PENDING;
  const handleUpdateMenu = (0, _element.useCallback)((menuId, options = {
    focusNavigationBlock: false
  }) => {
    const {
      focusNavigationBlock
    } = options;
    setRef(menuId);
    if (focusNavigationBlock) {
      selectBlock(clientId);
    }
  }, [selectBlock, clientId, setRef]);
  const isEntityAvailable = !isNavigationMenuMissing && isNavigationMenuResolved;

  // If the block has inner blocks, but no menu id, then these blocks are either:
  // - inserted via a pattern.
  // - inserted directly via Code View (or otherwise).
  // - from an older version of navigation block added before the block used a wp_navigation entity.
  // Consider this state as 'unsaved' and offer an uncontrolled version of inner blocks,
  // that automatically saves the menu as an entity when changes are made to the inner blocks.
  const hasUnsavedBlocks = hasUncontrolledInnerBlocks && !isEntityAvailable;
  const {
    getNavigationFallbackId
  } = (0, _lockUnlock.unlock)((0, _data.useSelect)(_coreData.store));
  const navigationFallbackId = !(ref || hasUnsavedBlocks) ? getNavigationFallbackId() : null;
  (0, _element.useEffect)(() => {
    // If:
    // - there is an existing menu, OR
    // - there are existing (uncontrolled) inner blocks
    // ...then don't request a fallback menu.
    if (ref || hasUnsavedBlocks || !navigationFallbackId) {
      return;
    }

    /**
     *  This fallback displays (both in editor and on front)
     *  The fallback should not request a save (entity dirty state)
     *  nor to be undoable, hence why it is marked as non persistent
     */

    __unstableMarkNextChangeAsNotPersistent();
    setRef(navigationFallbackId);
  }, [ref, setRef, hasUnsavedBlocks, navigationFallbackId, __unstableMarkNextChangeAsNotPersistent]);
  const navRef = (0, _element.useRef)();

  // The standard HTML5 tag for the block wrapper.
  const TagName = 'nav';

  // "placeholder" shown if:
  // - there is no ref attribute pointing to a Navigation Post.
  // - there is no classic menu conversion process in progress.
  // - there is no menu creation process in progress.
  // - there are no uncontrolled blocks.
  const isPlaceholder = !ref && !isCreatingNavigationMenu && !isConvertingClassicMenu && hasResolvedNavigationMenus && classicMenus?.length === 0 && !hasUncontrolledInnerBlocks;

  // "loading" state:
  // - there is a menu creation process in progress.
  // - there is a classic menu conversion process in progress.
  // OR:
  // - there is a ref attribute pointing to a Navigation Post
  // - the Navigation Post isn't available (hasn't resolved) yet.
  const isLoading = !hasResolvedNavigationMenus || isCreatingNavigationMenu || isConvertingClassicMenu || !!(ref && !isEntityAvailable && !isConvertingClassicMenu);
  const textDecoration = attributes.style?.typography?.textDecoration;
  const hasBlockOverlay = (0, _data.useSelect)(select => select(_blockEditor.store).__unstableHasActiveBlockOverlayActive(clientId), [clientId]);
  const isResponsive = 'never' !== overlayMenu;
  const isMobileBreakPoint = (0, _compose.useMediaQuery)(`(max-width: ${_constants.NAVIGATION_MOBILE_COLLAPSE})`);
  const isCollapsed = 'mobile' === overlayMenu && isMobileBreakPoint || 'always' === overlayMenu;
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref: navRef,
    className: (0, _classnames.default)(className, {
      'items-justified-right': justifyContent === 'right',
      'items-justified-space-between': justifyContent === 'space-between',
      'items-justified-left': justifyContent === 'left',
      'items-justified-center': justifyContent === 'center',
      'is-vertical': orientation === 'vertical',
      'no-wrap': flexWrap === 'nowrap',
      'is-responsive': isResponsive,
      'is-collapsed': isCollapsed,
      'has-text-color': !!textColor.color || !!textColor?.class,
      [(0, _blockEditor.getColorClassName)('color', textColor?.slug)]: !!textColor?.slug,
      'has-background': !!backgroundColor.color || backgroundColor.class,
      [(0, _blockEditor.getColorClassName)('background-color', backgroundColor?.slug)]: !!backgroundColor?.slug,
      [`has-text-decoration-${textDecoration}`]: textDecoration,
      'block-editor-block-content-overlay': hasBlockOverlay
    }, layoutClassNames),
    style: {
      color: !textColor?.slug && textColor?.color,
      backgroundColor: !backgroundColor?.slug && backgroundColor?.color
    }
  });

  // Turn on contrast checker for web only since it's not supported on mobile yet.
  const enableContrastChecking = _element.Platform.OS === 'web';
  const [detectedBackgroundColor, setDetectedBackgroundColor] = (0, _element.useState)();
  const [detectedColor, setDetectedColor] = (0, _element.useState)();
  const [detectedOverlayBackgroundColor, setDetectedOverlayBackgroundColor] = (0, _element.useState)();
  const [detectedOverlayColor, setDetectedOverlayColor] = (0, _element.useState)();
  const onSelectClassicMenu = async classicMenu => {
    const navMenu = await convertClassicMenu(classicMenu.id, classicMenu.name, 'draft');
    if (navMenu) {
      handleUpdateMenu(navMenu.id, {
        focusNavigationBlock: true
      });
    }
  };
  const onSelectNavigationMenu = menuId => {
    handleUpdateMenu(menuId);
  };
  (0, _element.useEffect)(() => {
    hideNavigationMenuStatusNotice();
    if (isCreatingNavigationMenu) {
      (0, _a11y.speak)((0, _i18n.__)(`Creating Navigation Menu.`));
    }
    if (createNavigationMenuIsSuccess) {
      handleUpdateMenu(createNavigationMenuPost?.id, {
        focusNavigationBlock: true
      });
      showNavigationMenuStatusNotice((0, _i18n.__)(`Navigation Menu successfully created.`));
    }
    if (createNavigationMenuIsError) {
      showNavigationMenuStatusNotice((0, _i18n.__)('Failed to create Navigation Menu.'));
    }
  }, [createNavigationMenuStatus, createNavigationMenuError, createNavigationMenuPost?.id, createNavigationMenuIsError, createNavigationMenuIsSuccess, isCreatingNavigationMenu, handleUpdateMenu, hideNavigationMenuStatusNotice, showNavigationMenuStatusNotice]);
  (0, _element.useEffect)(() => {
    hideClassicMenuConversionNotice();
    if (classicMenuConversionStatus === _useConvertClassicMenuToBlockMenu.CLASSIC_MENU_CONVERSION_PENDING) {
      (0, _a11y.speak)((0, _i18n.__)('Classic menu importing.'));
    }
    if (classicMenuConversionStatus === _useConvertClassicMenuToBlockMenu.CLASSIC_MENU_CONVERSION_SUCCESS) {
      showClassicMenuConversionNotice((0, _i18n.__)('Classic menu imported successfully.'));
    }
    if (classicMenuConversionStatus === _useConvertClassicMenuToBlockMenu.CLASSIC_MENU_CONVERSION_ERROR) {
      showClassicMenuConversionNotice((0, _i18n.__)('Classic menu import failed.'));
    }
  }, [classicMenuConversionStatus, classicMenuConversionError, hideClassicMenuConversionNotice, showClassicMenuConversionNotice]);
  (0, _element.useEffect)(() => {
    if (!enableContrastChecking) {
      return;
    }
    (0, _utils.detectColors)(navRef.current, setDetectedColor, setDetectedBackgroundColor);
    const subMenuElement = navRef.current?.querySelector('[data-type="core/navigation-submenu"] [data-type="core/navigation-link"]');
    if (!subMenuElement) {
      return;
    }

    // Only detect submenu overlay colors if they have previously been explicitly set.
    // This avoids the contrast checker from reporting on inherited submenu colors and
    // showing the contrast warning twice.
    if (overlayTextColor.color || overlayBackgroundColor.color) {
      (0, _utils.detectColors)(subMenuElement, setDetectedOverlayColor, setDetectedOverlayBackgroundColor);
    }
  }, [enableContrastChecking, overlayTextColor.color, overlayBackgroundColor.color]);
  (0, _element.useEffect)(() => {
    if (!isSelected && !isInnerBlockSelected) {
      hideNavigationMenuPermissionsNotice();
    }
    if (isSelected || isInnerBlockSelected) {
      if (ref && !navMenuResolvedButMissing && hasResolvedCanUserUpdateNavigationMenu && !canUserUpdateNavigationMenu) {
        showNavigationMenuPermissionsNotice((0, _i18n.__)('You do not have permission to edit this Menu. Any changes made will not be saved.'));
      }
      if (!ref && hasResolvedCanUserCreateNavigationMenu && !canUserCreateNavigationMenu) {
        showNavigationMenuPermissionsNotice((0, _i18n.__)('You do not have permission to create Navigation Menus.'));
      }
    }
  }, [isSelected, isInnerBlockSelected, canUserUpdateNavigationMenu, hasResolvedCanUserUpdateNavigationMenu, canUserCreateNavigationMenu, hasResolvedCanUserCreateNavigationMenu, ref, hideNavigationMenuPermissionsNotice, showNavigationMenuPermissionsNotice, navMenuResolvedButMissing]);
  const hasManagePermissions = canUserCreateNavigationMenu || canUserUpdateNavigationMenu;
  const overlayMenuPreviewClasses = (0, _classnames.default)('wp-block-navigation__overlay-menu-preview', {
    open: overlayMenuPreview
  });
  const submenuAccessibilityNotice = !showSubmenuIcon && !openSubmenusOnClick ? (0, _i18n.__)('The current menu options offer reduced accessibility for users and are not recommended. Enabling either "Open on Click" or "Show arrow" offers enhanced accessibility by allowing keyboard users to browse submenus selectively.') : '';
  const isFirstRender = (0, _element.useRef)(true); // Don't speak on first render.
  (0, _element.useEffect)(() => {
    if (!isFirstRender.current && submenuAccessibilityNotice) {
      (0, _a11y.speak)(submenuAccessibilityNotice);
    }
    isFirstRender.current = false;
  }, [submenuAccessibilityNotice]);
  const overlayMenuPreviewId = (0, _compose.useInstanceId)(_overlayMenuPreview.default, `overlay-menu-preview`);
  const colorGradientSettings = (0, _blockEditor.__experimentalUseMultipleOriginColorsAndGradients)();
  const stylingInspectorControls = (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, hasSubmenuIndicatorSetting && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Display')
  }, isResponsive && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.Button, {
    className: overlayMenuPreviewClasses,
    onClick: () => {
      setOverlayMenuPreview(!overlayMenuPreview);
    },
    "aria-label": (0, _i18n.__)('Overlay menu controls'),
    "aria-controls": overlayMenuPreviewId,
    "aria-expanded": overlayMenuPreview
  }, hasIcon && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_overlayMenuIcon.default, {
    icon: icon
  }), (0, _react.createElement)(_icons.Icon, {
    icon: _icons.close
  })), !hasIcon && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("span", null, (0, _i18n.__)('Menu')), (0, _react.createElement)("span", null, (0, _i18n.__)('Close')))), (0, _react.createElement)("div", {
    id: overlayMenuPreviewId
  }, overlayMenuPreview && (0, _react.createElement)(_overlayMenuPreview.default, {
    setAttributes: setAttributes,
    hasIcon: hasIcon,
    icon: icon,
    hidden: !overlayMenuPreview
  }))), (0, _react.createElement)("h3", null, (0, _i18n.__)('Overlay Menu')), (0, _react.createElement)(_components.__experimentalToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Configure overlay menu'),
    value: overlayMenu,
    help: (0, _i18n.__)('Collapses the navigation options in a menu icon opening an overlay.'),
    onChange: value => setAttributes({
      overlayMenu: value
    }),
    isBlock: true,
    hideLabelFromVision: true
  }, (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "never",
    label: (0, _i18n.__)('Off')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "mobile",
    label: (0, _i18n.__)('Mobile')
  }), (0, _react.createElement)(_components.__experimentalToggleGroupControlOption, {
    value: "always",
    label: (0, _i18n.__)('Always')
  })), hasSubmenus && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("h3", null, (0, _i18n.__)('Submenus')), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    checked: openSubmenusOnClick,
    onChange: value => {
      setAttributes({
        openSubmenusOnClick: value,
        ...(value && {
          showSubmenuIcon: true
        }) // Make sure arrows are shown when we toggle this on.
      });
    },
    label: (0, _i18n.__)('Open on click')
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    checked: showSubmenuIcon,
    onChange: value => {
      setAttributes({
        showSubmenuIcon: value
      });
    },
    disabled: attributes.openSubmenusOnClick,
    label: (0, _i18n.__)('Show arrow')
  }), submenuAccessibilityNotice && (0, _react.createElement)("div", null, (0, _react.createElement)(_components.Notice, {
    spokenMessage: null,
    status: "warning",
    isDismissible: false
  }, submenuAccessibilityNotice))))), colorGradientSettings.hasColorsOrGradients && (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "color"
  }, (0, _react.createElement)(_blockEditor.__experimentalColorGradientSettingsDropdown, {
    __experimentalIsRenderedInSidebar: true,
    settings: [{
      colorValue: textColor.color,
      label: (0, _i18n.__)('Text'),
      onColorChange: setTextColor,
      resetAllFilter: () => setTextColor()
    }, {
      colorValue: backgroundColor.color,
      label: (0, _i18n.__)('Background'),
      onColorChange: setBackgroundColor,
      resetAllFilter: () => setBackgroundColor()
    }, {
      colorValue: overlayTextColor.color,
      label: (0, _i18n.__)('Submenu & overlay text'),
      onColorChange: setOverlayTextColor,
      resetAllFilter: () => setOverlayTextColor()
    }, {
      colorValue: overlayBackgroundColor.color,
      label: (0, _i18n.__)('Submenu & overlay background'),
      onColorChange: setOverlayBackgroundColor,
      resetAllFilter: () => setOverlayBackgroundColor()
    }],
    panelId: clientId,
    ...colorGradientSettings,
    gradients: [],
    disableCustomGradients: true
  }), enableContrastChecking && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.ContrastChecker, {
    backgroundColor: detectedBackgroundColor,
    textColor: detectedColor
  }), (0, _react.createElement)(_blockEditor.ContrastChecker, {
    backgroundColor: detectedOverlayBackgroundColor,
    textColor: detectedOverlayColor
  }))));
  const accessibleDescriptionId = `${clientId}-desc`;
  const isManageMenusButtonDisabled = !hasManagePermissions || !hasResolvedNavigationMenus;
  if (hasUnsavedBlocks && !isCreatingNavigationMenu) {
    return (0, _react.createElement)(TagName, {
      ...blockProps,
      "aria-describedby": !isPlaceholder ? accessibleDescriptionId : undefined
    }, (0, _react.createElement)(_accessibleDescription.default, {
      id: accessibleDescriptionId
    }, (0, _i18n.__)('Unsaved Navigation Menu.')), (0, _react.createElement)(_menuInspectorControls.default, {
      clientId: clientId,
      createNavigationMenuIsSuccess: createNavigationMenuIsSuccess,
      createNavigationMenuIsError: createNavigationMenuIsError,
      currentMenuId: ref,
      isNavigationMenuMissing: isNavigationMenuMissing,
      isManageMenusButtonDisabled: isManageMenusButtonDisabled,
      onCreateNew: createUntitledEmptyNavigationMenu,
      onSelectClassicMenu: onSelectClassicMenu,
      onSelectNavigationMenu: onSelectNavigationMenu,
      isLoading: isLoading,
      blockEditingMode: blockEditingMode
    }), blockEditingMode === 'default' && stylingInspectorControls, (0, _react.createElement)(_responsiveWrapper.default, {
      id: clientId,
      onToggle: setResponsiveMenuVisibility,
      isOpen: isResponsiveMenuOpen,
      hasIcon: hasIcon,
      icon: icon,
      isResponsive: isResponsive,
      isHiddenByDefault: 'always' === overlayMenu,
      overlayBackgroundColor: overlayBackgroundColor,
      overlayTextColor: overlayTextColor
    }, (0, _react.createElement)(_unsavedInnerBlocks.default, {
      createNavigationMenu: createNavigationMenu,
      blocks: uncontrolledInnerBlocks,
      hasSelection: isSelected || isInnerBlockSelected
    })));
  }

  // Show a warning if the selected menu is no longer available.
  // TODO - the user should be able to select a new one?
  if (ref && isNavigationMenuMissing) {
    return (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)(_menuInspectorControls.default, {
      clientId: clientId,
      createNavigationMenuIsSuccess: createNavigationMenuIsSuccess,
      createNavigationMenuIsError: createNavigationMenuIsError,
      currentMenuId: ref,
      isNavigationMenuMissing: isNavigationMenuMissing,
      isManageMenusButtonDisabled: isManageMenusButtonDisabled,
      onCreateNew: createUntitledEmptyNavigationMenu,
      onSelectClassicMenu: onSelectClassicMenu,
      onSelectNavigationMenu: onSelectNavigationMenu,
      isLoading: isLoading,
      blockEditingMode: blockEditingMode
    }), (0, _react.createElement)(_deletedNavigationWarning.default, {
      onCreateNew: createUntitledEmptyNavigationMenu
    }));
  }
  if (isEntityAvailable && hasAlreadyRendered) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Block cannot be rendered inside itself.')));
  }
  const PlaceholderComponent = CustomPlaceholder ? CustomPlaceholder : _placeholder.default;

  /**
   * Historically the navigation block has supported custom placeholders.
   * Even though the current UX tries as hard as possible not to
   * end up in a placeholder state, the block continues to support
   * this extensibility point, via a CustomPlaceholder.
   * When CustomPlaceholder is present it becomes the default fallback
   * for an empty navigation block, instead of the default fallbacks.
   *
   */

  if (isPlaceholder && CustomPlaceholder) {
    return (0, _react.createElement)(TagName, {
      ...blockProps
    }, (0, _react.createElement)(PlaceholderComponent, {
      isSelected: isSelected,
      currentMenuId: ref,
      clientId: clientId,
      canUserCreateNavigationMenu: canUserCreateNavigationMenu,
      isResolvingCanUserCreateNavigationMenu: isResolvingCanUserCreateNavigationMenu,
      onSelectNavigationMenu: onSelectNavigationMenu,
      onSelectClassicMenu: onSelectClassicMenu,
      onCreateEmpty: createUntitledEmptyNavigationMenu
    }));
  }
  return (0, _react.createElement)(_coreData.EntityProvider, {
    kind: "postType",
    type: "wp_navigation",
    id: ref
  }, (0, _react.createElement)(_blockEditor.RecursionProvider, {
    uniqueId: recursionId
  }, (0, _react.createElement)(_menuInspectorControls.default, {
    clientId: clientId,
    createNavigationMenuIsSuccess: createNavigationMenuIsSuccess,
    createNavigationMenuIsError: createNavigationMenuIsError,
    currentMenuId: ref,
    isNavigationMenuMissing: isNavigationMenuMissing,
    isManageMenusButtonDisabled: isManageMenusButtonDisabled,
    onCreateNew: createUntitledEmptyNavigationMenu,
    onSelectClassicMenu: onSelectClassicMenu,
    onSelectNavigationMenu: onSelectNavigationMenu,
    isLoading: isLoading,
    blockEditingMode: blockEditingMode
  }), blockEditingMode === 'default' && stylingInspectorControls, blockEditingMode === 'default' && isEntityAvailable && (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, hasResolvedCanUserUpdateNavigationMenu && canUserUpdateNavigationMenu && (0, _react.createElement)(_navigationMenuNameControl.default, null), hasResolvedCanUserDeleteNavigationMenu && canUserDeleteNavigationMenu && (0, _react.createElement)(_navigationMenuDeleteControl.default, {
    onDelete: (deletedMenuTitle = '') => {
      replaceInnerBlocks(clientId, []);
      showNavigationMenuStatusNotice((0, _i18n.sprintf)(
      // translators: %s: the name of a menu (e.g. Header navigation).
      (0, _i18n.__)('Navigation menu %s successfully deleted.'), deletedMenuTitle));
    }
  }), (0, _react.createElement)(_manageMenusButton.default, {
    disabled: isManageMenusButtonDisabled,
    className: "wp-block-navigation-manage-menus-button"
  })), isLoading && (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)("div", {
    className: "wp-block-navigation__loading-indicator-container"
  }, (0, _react.createElement)(_components.Spinner, {
    className: "wp-block-navigation__loading-indicator"
  }))), !isLoading && (0, _react.createElement)(TagName, {
    ...blockProps,
    "aria-describedby": !isPlaceholder ? accessibleDescriptionId : undefined
  }, (0, _react.createElement)(_accessibleMenuDescription.default, {
    id: accessibleDescriptionId
  }), (0, _react.createElement)(_responsiveWrapper.default, {
    id: clientId,
    onToggle: setResponsiveMenuVisibility,
    hasIcon: hasIcon,
    icon: icon,
    isOpen: isResponsiveMenuOpen,
    isResponsive: isResponsive,
    isHiddenByDefault: 'always' === overlayMenu,
    overlayBackgroundColor: overlayBackgroundColor,
    overlayTextColor: overlayTextColor
  }, isEntityAvailable && (0, _react.createElement)(_innerBlocks.default, {
    clientId: clientId,
    hasCustomPlaceholder: !!CustomPlaceholder,
    templateLock: templateLock,
    orientation: orientation
  })))));
}
var _default = exports.default = (0, _blockEditor.withColors)({
  textColor: 'color'
}, {
  backgroundColor: 'color'
}, {
  overlayBackgroundColor: 'color'
}, {
  overlayTextColor: 'color'
})(Navigation);
//# sourceMappingURL=index.js.map