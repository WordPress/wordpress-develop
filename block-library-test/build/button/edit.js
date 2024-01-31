"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _constants = require("./constants");
var _getUpdatedLinkAttributes = require("./get-updated-link-attributes");
var _removeAnchorTag = _interopRequireDefault(require("../utils/remove-anchor-tag"));
var _lockUnlock = require("../lock-unlock");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _keycodes = require("@wordpress/keycodes");
var _icons = require("@wordpress/icons");
var _blocks = require("@wordpress/blocks");
var _compose = require("@wordpress/compose");
var _data = require("@wordpress/data");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

const LINK_SETTINGS = [..._blockEditor.__experimentalLinkControl.DEFAULT_LINK_SETTINGS, {
  id: 'nofollow',
  title: (0, _i18n.__)('Mark as nofollow')
}];
function useEnter(props) {
  const {
    replaceBlocks,
    selectionChange
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlock,
    getBlockRootClientId,
    getBlockIndex
  } = (0, _data.useSelect)(_blockEditor.store);
  const propsRef = (0, _element.useRef)(props);
  propsRef.current = props;
  return (0, _compose.useRefEffect)(element => {
    function onKeyDown(event) {
      if (event.defaultPrevented || event.keyCode !== _keycodes.ENTER) {
        return;
      }
      const {
        content,
        clientId
      } = propsRef.current;
      if (content.length) {
        return;
      }
      event.preventDefault();
      const topParentListBlock = getBlock(getBlockRootClientId(clientId));
      const blockIndex = getBlockIndex(clientId);
      const head = (0, _blocks.cloneBlock)({
        ...topParentListBlock,
        innerBlocks: topParentListBlock.innerBlocks.slice(0, blockIndex)
      });
      const middle = (0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)());
      const after = topParentListBlock.innerBlocks.slice(blockIndex + 1);
      const tail = after.length ? [(0, _blocks.cloneBlock)({
        ...topParentListBlock,
        innerBlocks: after
      })] : [];
      replaceBlocks(topParentListBlock.clientId, [head, middle, ...tail], 1);
      // We manually change the selection here because we are replacing
      // a different block than the selected one.
      selectionChange(middle.clientId);
    }
    element.addEventListener('keydown', onKeyDown);
    return () => {
      element.removeEventListener('keydown', onKeyDown);
    };
  }, []);
}
function WidthPanel({
  selectedWidth,
  setAttributes
}) {
  function handleChange(newWidth) {
    // Check if we are toggling the width off
    const width = selectedWidth === newWidth ? undefined : newWidth;

    // Update attributes.
    setAttributes({
      width
    });
  }
  return (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Width settings')
  }, (0, _react.createElement)(_components.ButtonGroup, {
    "aria-label": (0, _i18n.__)('Button width')
  }, [25, 50, 75, 100].map(widthValue => {
    return (0, _react.createElement)(_components.Button, {
      key: widthValue,
      size: "small",
      variant: widthValue === selectedWidth ? 'primary' : undefined,
      onClick: () => handleChange(widthValue)
    }, widthValue, "%");
  })));
}
function ButtonEdit(props) {
  const {
    attributes,
    setAttributes,
    className,
    isSelected,
    onReplace,
    mergeBlocks,
    clientId
  } = props;
  const {
    tagName,
    textAlign,
    linkTarget,
    placeholder,
    rel,
    style,
    text,
    url,
    width,
    metadata
  } = attributes;
  const TagName = tagName || 'a';
  function onKeyDown(event) {
    if (_keycodes.isKeyboardEvent.primary(event, 'k')) {
      startEditing(event);
    } else if (_keycodes.isKeyboardEvent.primaryShift(event, 'k')) {
      unlink();
      richTextRef.current?.focus();
    }
  }

  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = (0, _element.useState)(null);
  const borderProps = (0, _blockEditor.__experimentalUseBorderProps)(attributes);
  const colorProps = (0, _blockEditor.__experimentalUseColorProps)(attributes);
  const spacingProps = (0, _blockEditor.__experimentalGetSpacingClassesAndStyles)(attributes);
  const shadowProps = (0, _blockEditor.__experimentalGetShadowClassesAndStyles)(attributes);
  const ref = (0, _element.useRef)();
  const richTextRef = (0, _element.useRef)();
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref: (0, _compose.useMergeRefs)([setPopoverAnchor, ref]),
    onKeyDown
  });
  const blockEditingMode = (0, _blockEditor.useBlockEditingMode)();
  const [isEditingURL, setIsEditingURL] = (0, _element.useState)(false);
  const isURLSet = !!url;
  const opensInNewTab = linkTarget === _constants.NEW_TAB_TARGET;
  const nofollow = !!rel?.includes(_constants.NOFOLLOW_REL);
  const isLinkTag = 'a' === TagName;
  function startEditing(event) {
    event.preventDefault();
    setIsEditingURL(true);
  }
  function unlink() {
    setAttributes({
      url: undefined,
      linkTarget: undefined,
      rel: undefined
    });
    setIsEditingURL(false);
  }
  (0, _element.useEffect)(() => {
    if (!isSelected) {
      setIsEditingURL(false);
    }
  }, [isSelected]);

  // Memoize link value to avoid overriding the LinkControl's internal state.
  // This is a temporary fix. See https://github.com/WordPress/gutenberg/issues/51256.
  const linkValue = (0, _element.useMemo)(() => ({
    url,
    opensInNewTab,
    nofollow
  }), [url, opensInNewTab, nofollow]);
  const useEnterRef = useEnter({
    content: text,
    clientId
  });
  const mergedRef = (0, _compose.useMergeRefs)([useEnterRef, richTextRef]);
  const {
    lockUrlControls = false
  } = (0, _data.useSelect)(select => {
    if (!isSelected) {
      return {};
    }
    const {
      getBlockBindingsSource
    } = (0, _lockUnlock.unlock)(select(_blockEditor.store));
    return {
      lockUrlControls: !!metadata?.bindings?.url && getBlockBindingsSource(metadata?.bindings?.url?.source)?.lockAttributesEditing === true
    };
  }, [isSelected]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("div", {
    ...blockProps,
    className: (0, _classnames.default)(blockProps.className, {
      [`has-custom-width wp-block-button__width-${width}`]: width,
      [`has-custom-font-size`]: blockProps.style.fontSize
    })
  }, (0, _react.createElement)(_blockEditor.RichText, {
    ref: mergedRef,
    "aria-label": (0, _i18n.__)('Button text'),
    placeholder: placeholder || (0, _i18n.__)('Add text…'),
    value: text,
    onChange: value => setAttributes({
      text: (0, _removeAnchorTag.default)(value)
    }),
    withoutInteractiveFormatting: true,
    className: (0, _classnames.default)(className, 'wp-block-button__link', colorProps.className, borderProps.className, {
      [`has-text-align-${textAlign}`]: textAlign,
      // For backwards compatibility add style that isn't
      // provided via block support.
      'no-border-radius': style?.border?.radius === 0
    }, (0, _blockEditor.__experimentalGetElementClassName)('button')),
    style: {
      ...borderProps.style,
      ...colorProps.style,
      ...spacingProps.style,
      ...shadowProps.style
    },
    onSplit: value => (0, _blocks.createBlock)('core/button', {
      ...attributes,
      text: value
    }),
    onReplace: onReplace,
    onMerge: mergeBlocks,
    identifier: "text"
  })), (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, blockEditingMode === 'default' && (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  }), !isURLSet && isLinkTag && !lockUrlControls && (0, _react.createElement)(_components.ToolbarButton, {
    name: "link",
    icon: _icons.link,
    title: (0, _i18n.__)('Link'),
    shortcut: _keycodes.displayShortcut.primary('k'),
    onClick: startEditing
  }), isURLSet && isLinkTag && !lockUrlControls && (0, _react.createElement)(_components.ToolbarButton, {
    name: "link",
    icon: _icons.linkOff,
    title: (0, _i18n.__)('Unlink'),
    shortcut: _keycodes.displayShortcut.primaryShift('k'),
    onClick: unlink,
    isActive: true
  })), isLinkTag && isSelected && (isEditingURL || isURLSet) && !lockUrlControls && (0, _react.createElement)(_components.Popover, {
    placement: "bottom",
    onClose: () => {
      setIsEditingURL(false);
      richTextRef.current?.focus();
    },
    anchor: popoverAnchor,
    focusOnMount: isEditingURL ? 'firstElement' : false,
    __unstableSlotName: '__unstable-block-tools-after',
    shift: true
  }, (0, _react.createElement)(_blockEditor.__experimentalLinkControl, {
    value: linkValue,
    onChange: ({
      url: newURL,
      opensInNewTab: newOpensInNewTab,
      nofollow: newNofollow
    }) => setAttributes((0, _getUpdatedLinkAttributes.getUpdatedLinkAttributes)({
      rel,
      url: newURL,
      opensInNewTab: newOpensInNewTab,
      nofollow: newNofollow
    })),
    onRemove: () => {
      unlink();
      richTextRef.current?.focus();
    },
    forceIsEditingLink: isEditingURL,
    settings: LINK_SETTINGS
  })), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(WidthPanel, {
    selectedWidth: width,
    setAttributes: setAttributes
  })), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, isLinkTag && (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Link rel'),
    value: rel || '',
    onChange: newRel => setAttributes({
      rel: newRel
    })
  })));
}
var _default = exports.default = ButtonEdit;
//# sourceMappingURL=edit.js.map