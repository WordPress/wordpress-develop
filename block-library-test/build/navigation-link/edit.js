"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = NavigationLinkEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blocks = require("@wordpress/blocks");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _keycodes = require("@wordpress/keycodes");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _url = require("@wordpress/url");
var _element = require("@wordpress/element");
var _dom = require("@wordpress/dom");
var _htmlEntities = require("@wordpress/html-entities");
var _icons = require("@wordpress/icons");
var _coreData = require("@wordpress/core-data");
var _compose = require("@wordpress/compose");
var _linkUi = require("./link-ui");
var _updateAttributes = require("./update-attributes");
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
const useIsInvalidLink = (kind, type, id) => {
  const isPostType = kind === 'post-type' || type === 'post' || type === 'page';
  const hasId = Number.isInteger(id);
  const postStatus = (0, _data.useSelect)(select => {
    if (!isPostType) {
      return null;
    }
    const {
      getEntityRecord
    } = select(_coreData.store);
    return getEntityRecord('postType', type, id)?.status;
  }, [isPostType, type, id]);

  // Check Navigation Link validity if:
  // 1. Link is 'post-type'.
  // 2. It has an id.
  // 3. It's neither null, nor undefined, as valid items might be either of those while loading.
  // If those conditions are met, check if
  // 1. The post status is published.
  // 2. The Navigation Link item has no label.
  // If either of those is true, invalidate.
  const isInvalid = isPostType && hasId && postStatus && 'trash' === postStatus;
  const isDraft = 'draft' === postStatus;
  return [isInvalid, isDraft];
};
function getMissingText(type) {
  let missingText = '';
  switch (type) {
    case 'post':
      /* translators: label for missing post in navigation link block */
      missingText = (0, _i18n.__)('Select post');
      break;
    case 'page':
      /* translators: label for missing page in navigation link block */
      missingText = (0, _i18n.__)('Select page');
      break;
    case 'category':
      /* translators: label for missing category in navigation link block */
      missingText = (0, _i18n.__)('Select category');
      break;
    case 'tag':
      /* translators: label for missing tag in navigation link block */
      missingText = (0, _i18n.__)('Select tag');
      break;
    default:
      /* translators: label for missing values in navigation link block */
      missingText = (0, _i18n.__)('Add link');
  }
  return missingText;
}
function NavigationLinkEdit({
  attributes,
  isSelected,
  setAttributes,
  insertBlocksAfter,
  mergeBlocks,
  onReplace,
  context,
  clientId
}) {
  const {
    id,
    label,
    type,
    url,
    description,
    rel,
    title,
    kind
  } = attributes;
  const [isInvalid, isDraft] = useIsInvalidLink(kind, type, id);
  const {
    maxNestingLevel
  } = context;
  const {
    replaceBlock,
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const [isLinkOpen, setIsLinkOpen] = (0, _element.useState)(false);
  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = (0, _element.useState)(null);
  const listItemRef = (0, _element.useRef)(null);
  const isDraggingWithin = useIsDraggingWithin(listItemRef);
  const itemLabelPlaceholder = (0, _i18n.__)('Add label…');
  const ref = (0, _element.useRef)();

  // Change the label using inspector causes rich text to change focus on firefox.
  // This is a workaround to keep the focus on the label field when label filed is focused we don't render the rich text.
  const [isLabelFieldFocused, setIsLabelFieldFocused] = (0, _element.useState)(false);
  const {
    innerBlocks,
    isAtMaxNesting,
    isTopLevelLink,
    isParentOfSelectedBlock,
    hasChildren
  } = (0, _data.useSelect)(select => {
    const {
      getBlocks,
      getBlockCount,
      getBlockName,
      getBlockRootClientId,
      hasSelectedInnerBlock,
      getBlockParentsByBlockName
    } = select(_blockEditor.store);
    return {
      innerBlocks: getBlocks(clientId),
      isAtMaxNesting: getBlockParentsByBlockName(clientId, ['core/navigation-link', 'core/navigation-submenu']).length >= maxNestingLevel,
      isTopLevelLink: getBlockName(getBlockRootClientId(clientId)) === 'core/navigation',
      isParentOfSelectedBlock: hasSelectedInnerBlock(clientId, true),
      hasChildren: !!getBlockCount(clientId)
    };
  }, [clientId]);

  /**
   * Transform to submenu block.
   */
  function transformToSubmenu() {
    const newSubmenu = (0, _blocks.createBlock)('core/navigation-submenu', attributes, innerBlocks.length > 0 ? innerBlocks : [(0, _blocks.createBlock)('core/navigation-link')]);
    replaceBlock(clientId, newSubmenu);
  }
  (0, _element.useEffect)(() => {
    // Show the LinkControl on mount if the URL is empty
    // ( When adding a new menu item)
    // This can't be done in the useState call because it conflicts
    // with the autofocus behavior of the BlockListBlock component.
    if (!url) {
      setIsLinkOpen(true);
    }
  }, [url]);
  (0, _element.useEffect)(() => {
    // If block has inner blocks, transform to Submenu.
    if (hasChildren) {
      // This side-effect should not create an undo level as those should
      // only be created via user interactions.
      __unstableMarkNextChangeAsNotPersistent();
      transformToSubmenu();
    }
  }, [hasChildren]);

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

  /**
   * Removes the current link if set.
   */
  function removeLink() {
    // Reset all attributes that comprise the link.
    // It is critical that all attributes are reset
    // to their default values otherwise this may
    // in advertently trigger side effects because
    // the values will have "changed".
    setAttributes({
      url: undefined,
      label: undefined,
      id: undefined,
      kind: undefined,
      type: undefined,
      opensInNewTab: false
    });

    // Close the link editing UI.
    setIsLinkOpen(false);
  }
  const {
    textColor,
    customTextColor,
    backgroundColor,
    customBackgroundColor
  } = (0, _utils.getColors)(context, !isTopLevelLink);
  function onKeyDown(event) {
    if (_keycodes.isKeyboardEvent.primary(event, 'k') || (!url || isDraft || isInvalid) && event.keyCode === _keycodes.ENTER) {
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
      [(0, _blockEditor.getColorClassName)('background-color', backgroundColor)]: !!backgroundColor
    }),
    style: {
      color: !textColor && customTextColor,
      backgroundColor: !backgroundColor && customBackgroundColor
    },
    onKeyDown
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    ...blockProps,
    className: 'remove-outline' // Remove the outline from the inner blocks container.
  }, {
    defaultBlock: DEFAULT_BLOCK,
    directInsert: true,
    renderAppender: false
  });
  if (!url || isInvalid || isDraft) {
    blockProps.onClick = () => setIsLinkOpen(true);
  }
  const classes = (0, _classnames.default)('wp-block-navigation-item__content', {
    'wp-block-navigation-link__placeholder': !url || isInvalid || isDraft
  });
  const missingText = getMissingText(type);
  /* translators: Whether the navigation link is Invalid or a Draft. */
  const placeholderText = `(${isInvalid ? (0, _i18n.__)('Invalid') : (0, _i18n.__)('Draft')})`;
  const tooltipText = isInvalid || isDraft ? (0, _i18n.__)('This item has been deleted, or is a draft') : (0, _i18n.__)('This item is missing a link');
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    name: "link",
    icon: _icons.link,
    title: (0, _i18n.__)('Link'),
    shortcut: _keycodes.displayShortcut.primary('k'),
    onClick: () => setIsLinkOpen(true)
  }), !isAtMaxNesting && (0, _react.createElement)(_components.ToolbarButton, {
    name: "submenu",
    icon: _icons.addSubmenu,
    title: (0, _i18n.__)('Add submenu'),
    onClick: transformToSubmenu
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: label ? (0, _dom.__unstableStripHTML)(label) : '',
    onChange: labelValue => {
      setAttributes({
        label: labelValue
      });
    },
    label: (0, _i18n.__)('Label'),
    autoComplete: "off",
    onFocus: () => setIsLabelFieldFocused(true),
    onBlur: () => setIsLabelFieldFocused(false)
  }), (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    value: url ? (0, _url.safeDecodeURI)(url) : '',
    onChange: urlValue => {
      (0, _updateAttributes.updateAttributes)({
        url: urlValue
      }, setAttributes, attributes);
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
  }, (0, _react.createElement)("a", {
    className: classes
  }, !url ? (0, _react.createElement)("div", {
    className: "wp-block-navigation-link__placeholder-text"
  }, (0, _react.createElement)(_components.Tooltip, {
    text: tooltipText
  }, (0, _react.createElement)("span", null, missingText))) : (0, _react.createElement)(_react.Fragment, null, !isInvalid && !isDraft && !isLabelFieldFocused && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.RichText, {
    ref: ref,
    identifier: "label",
    className: "wp-block-navigation-item__label",
    value: label,
    onChange: labelValue => setAttributes({
      label: labelValue
    }),
    onMerge: mergeBlocks,
    onReplace: onReplace,
    __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)('core/navigation-link')),
    "aria-label": (0, _i18n.__)('Navigation link text'),
    placeholder: itemLabelPlaceholder,
    withoutInteractiveFormatting: true,
    allowedFormats: ['core/bold', 'core/italic', 'core/image', 'core/strikethrough'],
    onClick: () => {
      if (!url) {
        setIsLinkOpen(true);
      }
    }
  }), description && (0, _react.createElement)("span", {
    className: "wp-block-navigation-item__description"
  }, description)), (isInvalid || isDraft || isLabelFieldFocused) && (0, _react.createElement)("div", {
    className: "wp-block-navigation-link__placeholder-text wp-block-navigation-link__label"
  }, (0, _react.createElement)(_components.Tooltip, {
    text: tooltipText
  }, (0, _react.createElement)("span", {
    "aria-label": (0, _i18n.__)('Navigation link text')
  },
  // Some attributes are stored in an escaped form. It's a legacy issue.
  // Ideally they would be stored in a raw, unescaped form.
  // Unescape is used here to "recover" the escaped characters
  // so they display without encoding.
  // See `updateAttributes` for more details.
  `${(0, _htmlEntities.decodeEntities)(label)} ${isInvalid || isDraft ? placeholderText : ''}`.trim())))), isLinkOpen && (0, _react.createElement)(_linkUi.LinkUI, {
    clientId: clientId,
    link: attributes,
    onClose: () => setIsLinkOpen(false),
    anchor: popoverAnchor,
    onRemove: removeLink,
    onChange: updatedValue => {
      (0, _updateAttributes.updateAttributes)(updatedValue, setAttributes, attributes);
    }
  })), (0, _react.createElement)("div", {
    ...innerBlocksProps
  })));
}
//# sourceMappingURL=edit.js.map