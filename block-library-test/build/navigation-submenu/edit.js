"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationSubmenuEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _keycodes = require("@wordpress/keycodes");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _url = require("@wordpress/url");
var _element = require("@wordpress/element");
var _dom = require("@wordpress/dom");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _a11y = require("@wordpress/a11y");
var _blocks = require("@wordpress/blocks");
var _compose = require("@wordpress/compose");
var _icons2 = require("./icons");
var _linkUi = require("../navigation-link/link-ui");
var _updateAttributes = require("../navigation-link/update-attributes");
var _utils = require("../navigation/edit/utils");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const ALLOWED_BLOCKS = ['core/navigation-link', 'core/navigation-submenu', 'core/page-list'];
const DEFAULT_BLOCK = {
  name: 'core/navigation-link'
};

/**
 * A React hook to determine if it's dragging within the target element.
 *
 * @typedef {import('@wordpress/element').RefObject} RefObject
 *
 * @param {RefObject<HTMLElement>} elementRef The target elementRef object.
 *
 * @return {boolean} Is dragging within the target element.
 */
const useIsDraggingWithin = elementRef => {
  const [isDraggingWithin, setIsDraggingWithin] = (0, _element.useState)(false);
  (0, _element.useEffect)(() => {
    const {
      ownerDocument
    } = elementRef.current;
    function handleDragStart(event) {
      // Check the first time when the dragging starts.
      handleDragEnter(event);
    }

    // Set to false whenever the user cancel the drag event by either releasing the mouse or press Escape.
    function handleDragEnd() {
      setIsDraggingWithin(false);
    }
    function handleDragEnter(event) {
      // Check if the current target is inside the item element.
      if (elementRef.current.contains(event.target)) {
        setIsDraggingWithin(true);
      } else {
        setIsDraggingWithin(false);
      }
    }

    // Bind these events to the document to catch all drag events.
    // Ideally, we can also use `event.relatedTarget`, but sadly that
    // doesn't work in Safari.
    ownerDocument.addEventListener('dragstart', handleDragStart);
    ownerDocument.addEventListener('dragend', handleDragEnd);
    ownerDocument.addEventListener('dragenter', handleDragEnter);
    return () => {
      ownerDocument.removeEventListener('dragstart', handleDragStart);
      ownerDocument.removeEventListener('dragend', handleDragEnd);
      ownerDocument.removeEventListener('dragenter', handleDragEnter);
    };
  }, []);
  return isDraggingWithin;
};

/**
 * @typedef {'post-type'|'custom'|'taxonomy'|'post-type-archive'} WPNavigationLinkKind
 */

/**
 * Navigation Link Block Attributes
 *
 * @typedef {Object} WPNavigationLinkBlockAttributes
 *
 * @property {string}               [label]         Link text.
 * @property {WPNavigationLinkKind} [kind]          Kind is used to differentiate between term and post ids to check post draft status.
 * @property {string}               [type]          The type such as post, page, tag, category and other custom types.
 * @property {string}               [rel]           The relationship of the linked URL.
 * @property {number}               [id]            A post or term id.
 * @property {boolean}              [opensInNewTab] Sets link target to _blank when true.
 * @property {string}               [url]           Link href.
 * @property {string}               [title]         Link title attribute.
 */

function NavigationSubmenuEdit({
  attributes,
  isSelected,
  setAttributes,
  mergeBlocks,
  onReplace,
  context,
  clientId
}) {
  const {
    label,
    type,
    url,
    description,
    rel,
    title
  } = attributes;
  const {
    showSubmenuIcon,
    maxNestingLevel,
    openSubmenusOnClick
  } = context;
  const {
    __unstableMarkNextChangeAsNotPersistent,
    replaceBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const [isLinkOpen, setIsLinkOpen] = (0, _element.useState)(false);
  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = (0, _element.useState)(null);
  const listItemRef = (0, _element.useRef)(null);
  const isDraggingWithin = useIsDraggingWithin(listItemRef);
  const itemLabelPlaceholder = (0, _i18n.__)('Add text…');
  const ref = (0, _element.useRef)();
  const pagesPermissions = (0, _coreData.useResourcePermissions)('pages');
  const postsPermissions = (0, _coreData.useResourcePermissions)('posts');
  const {
    parentCount,
    isParentOfSelectedBlock,
    isImmediateParentOfSelectedBlock,
    hasChildren,
    selectedBlockHasChildren,
    onlyDescendantIsEmptyLink
  } = (0, _data.useSelect)(select => {
    const {
      hasSelectedInnerBlock,
      getSelectedBlockClientId,
      getBlockParentsByBlockName,
      getBlock,
      getBlockCount,
      getBlockOrder
    } = select(_blockEditor.store);
    let _onlyDescendantIsEmptyLink;
    const selectedBlockId = getSelectedBlockClientId();
    const selectedBlockChildren = getBlockOrder(selectedBlockId);

    // Check for a single descendant in the submenu. If that block
    // is a link block in a "placeholder" state with no label then
    // we can consider as an "empty" link.
    if (selectedBlockChildren?.length === 1) {
      const singleBlock = getBlock(selectedBlockChildren[0]);
      _onlyDescendantIsEmptyLink = singleBlock?.name === 'core/navigation-link' && !singleBlock?.attributes?.label;
    }
    return {
      parentCount: getBlockParentsByBlockName(clientId, 'core/navigation-submenu').length,
      isParentOfSelectedBlock: hasSelectedInnerBlock(clientId, true),
      isImmediateParentOfSelectedBlock: hasSelectedInnerBlock(clientId, false),
      hasChildren: !!getBlockCount(clientId),
      selectedBlockHasChildren: !!selectedBlockChildren?.length,
      onlyDescendantIsEmptyLink: _onlyDescendantIsEmptyLink
    };
  }, [clientId]);
  const prevHasChildren = (0, _compose.usePrevious)(hasChildren);

  // Show the LinkControl on mount if the URL is empty
  // ( When adding a new menu item)
  // This can't be done in the useState call because it conflicts
  // with the autofocus behavior of the BlockListBlock component.
  (0, _element.useEffect)(() => {
    if (!openSubmenusOnClick && !url) {
      setIsLinkOpen(true);
    }
  }, []);

  /**
   * The hook shouldn't be necessary but due to a focus loss happening
   * when selecting a suggestion in the link popover, we force close on block unselection.
   */
  (0, _element.useEffect)(() => {
    if (!isSelected) {
      setIsLinkOpen(false);
    }
  }, [isSelected]);

  // If the LinkControl popover is open and the URL has changed, close the LinkControl and focus the label text.
  (0, _element.useEffect)(() => {
    if (isLinkOpen && url) {
      // Does this look like a URL and have something TLD-ish?
      if ((0, _url.isURL)((0, _url.prependHTTP)(label)) && /^.+\.[a-z]+/.test(label)) {
        // Focus and select the label text.
        selectLabelText();
      } else {
        // Focus it (but do not select).
        (0, _dom.placeCaretAtHorizontalEdge)(ref.current, true);
      }
    }
  }, [url]);

  /**
   * Focus the Link label text and select it.
   */
  function selectLabelText() {
    ref.current.focus();
    const {
      ownerDocument
    } = ref.current;
    const {
      defaultView
    } = ownerDocument;
    const selection = defaultView.getSelection();
    const range = ownerDocument.createRange();
    // Get the range of the current ref contents so we can add this range to the selection.
    range.selectNodeContents(ref.current);
    selection.removeAllRanges();
    selection.addRange(range);
  }
  let userCanCreate = false;
  if (!type || type === 'page') {
    userCanCreate = pagesPermissions.canCreate;
  } else if (type === 'post') {
    userCanCreate = postsPermissions.canCreate;
  }
  const {
    textColor,
    customTextColor,
    backgroundColor,
    customBackgroundColor
  } = (0, _utils.getColors)(context, parentCount > 0);
  function onKeyDown(event) {
    if (_keycodes.isKeyboardEvent.primary(event, 'k')) {
      setIsLinkOpen(true);
    }
  }
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref: (0, _compose.useMergeRefs)([setPopoverAnchor, listItemRef]),
    className: (0, _classnames.default)('wp-block-navigation-item', {
      'is-editing': isSelected || isParentOfSelectedBlock,
      'is-dragging-within': isDraggingWithin,
      'has-link': !!url,
      'has-child': hasChildren,
      'has-text-color': !!textColor || !!customTextColor,
      [(0, _blockEditor.getColorClassName)('color', textColor)]: !!textColor,
      'has-background': !!backgroundColor || customBackgroundColor,
      [(0, _blockEditor.getColorClassName)('background-color', backgroundColor)]: !!backgroundColor,
      'open-on-click': openSubmenusOnClick
    }),
    style: {
      color: !textColor && customTextColor,
      backgroundColor: !backgroundColor && customBackgroundColor
    },
    onKeyDown
  });

  // Always use overlay colors for submenus.
  const innerBlocksColors = (0, _utils.getColors)(context, true);
  const allowedBlocks = parentCount >= maxNestingLevel ? ALLOWED_BLOCKS.filter(blockName => blockName !== 'core/navigation-submenu') : ALLOWED_BLOCKS;
  const navigationChildBlockProps = (0, _utils.getNavigationChildBlockProps)(innerBlocksColors);
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(navigationChildBlockProps, {
    allowedBlocks,
    defaultBlock: DEFAULT_BLOCK,
    directInsert: true,
    // Ensure block toolbar is not too far removed from item
    // being edited.
    // see: https://github.com/WordPress/gutenberg/pull/34615.
    __experimentalCaptureToolbars: true,
    renderAppender: isSelected || isImmediateParentOfSelectedBlock && !selectedBlockHasChildren ||
    // Show the appender while dragging to allow inserting element between item and the appender.
    hasChildren ? _blockEditor.InnerBlocks.ButtonBlockAppender : false
  });
  const ParentElement = openSubmenusOnClick ? 'button' : 'a';
  function transformToLink() {
    const newLinkBlock = (0, _blocks.createBlock)('core/navigation-link', attributes);
    replaceBlock(clientId, newLinkBlock);
  }
  (0, _element.useEffect)(() => {
    // If block becomes empty, transform to Navigation Link.
    if (!hasChildren && prevHasChildren) {
      // This side-effect should not create an undo level as those should
      // only be created via user interactions.
      __unstableMarkNextChangeAsNotPersistent();
      transformToLink();
    }
  }, [hasChildren, prevHasChildren]);
  const canConvertToLink = !selectedBlockHasChildren || onlyDescendantIsEmptyLink;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, !openSubmenusOnClick && (0, _react.createElement)(_components.ToolbarButton, {
    name: "link",
    icon: _icons.link,
    title: (0, _i18n.__)('Link'),
    shortcut: _keycodes.displayShortcut.primary('k'),
    onClick: () => setIsLinkOpen(true)
  }), (0, _react.createElement)(_components.ToolbarButton, {
    name: "revert",
    icon: _icons.removeSubmenu,
    title: (0, _i18n.__)('Convert to Link'),
    onClick: transformToLink,
    className: "wp-block-navigation__submenu__revert",
    isDisabled: !canConvertToLink
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: label || '',
    onChange: labelValue => {
      setAttributes({
        label: labelValue
      });
    },
    label: (0, _i18n.__)('Label'),
    autoComplete: "off"
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: url || '',
    onChange: urlValue => {
      setAttributes({
        url: urlValue
      });
    },
    label: (0, _i18n.__)('URL'),
    autoComplete: "off"
  }), (0, _react.createElement)(_components.TextareaControl, {
    __nextHasNoMarginBottom: true,
    value: description || '',
    onChange: descriptionValue => {
      setAttributes({
        description: descriptionValue
      });
    },
    label: (0, _i18n.__)('Description'),
    help: (0, _i18n.__)('The description will be displayed in the menu if the current theme supports it.')
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: title || '',
    onChange: titleValue => {
      setAttributes({
        title: titleValue
      });
    },
    label: (0, _i18n.__)('Title attribute'),
    autoComplete: "off",
    help: (0, _i18n.__)('Additional information to help clarify the purpose of the link.')
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: rel || '',
    onChange: relValue => {
      setAttributes({
        rel: relValue
      });
    },
    label: (0, _i18n.__)('Rel attribute'),
    autoComplete: "off",
    help: (0, _i18n.__)('The relationship of the linked URL as space-separated link types.')
  }))), (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(ParentElement, {
    className: "wp-block-navigation-item__content"
  }, (0, _react.createElement)(_blockEditor.RichText, {
    ref: ref,
    identifier: "label",
    className: "wp-block-navigation-item__label",
    value: label,
    onChange: labelValue => setAttributes({
      label: labelValue
    }),
    onMerge: mergeBlocks,
    onReplace: onReplace,
    "aria-label": (0, _i18n.__)('Navigation link text'),
    placeholder: itemLabelPlaceholder,
    withoutInteractiveFormatting: true,
    allowedFormats: ['core/bold', 'core/italic', 'core/image', 'core/strikethrough'],
    onClick: () => {
      if (!openSubmenusOnClick && !url) {
        setIsLinkOpen(true);
      }
    }
  }), !openSubmenusOnClick && isLinkOpen && (0, _react.createElement)(_linkUi.LinkUI, {
    clientId: clientId,
    link: attributes,
    onClose: () => setIsLinkOpen(false),
    anchor: popoverAnchor,
    hasCreateSuggestion: userCanCreate,
    onRemove: () => {
      setAttributes({
        url: ''
      });
      (0, _a11y.speak)((0, _i18n.__)('Link removed.'), 'assertive');
    },
    onChange: updatedValue => {
      (0, _updateAttributes.updateAttributes)(updatedValue, setAttributes, attributes);
    }
  })), (showSubmenuIcon || openSubmenusOnClick) && (0, _react.createElement)("span", {
    className: "wp-block-navigation__submenu-icon"
  }, (0, _react.createElement)(_icons2.ItemSubmenuIcon, null)), (0, _react.createElement)("div", {
    ...innerBlocksProps
  })));
}
//# sourceMappingURL=edit.js.map