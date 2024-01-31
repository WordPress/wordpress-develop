"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _primitives = require("@wordpress/primitives");
var _placeholder = _interopRequireWildcard(require("./placeholder"));
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/**
 * Render inspector controls for the Group block.
 *
 * @param {Object}   props                 Component props.
 * @param {string}   props.tagName         The HTML tag name.
 * @param {Function} props.onSelectTagName onChange function for the SelectControl.
 *
 * @return {JSX.Element}                The control group.
 */
function GroupEditControls({
  tagName,
  onSelectTagName
}) {
  const htmlElementMessages = {
    header: (0, _i18n.__)('The <header> element should represent introductory content, typically a group of introductory or navigational aids.'),
    main: (0, _i18n.__)('The <main> element should be used for the primary content of your document only. '),
    section: (0, _i18n.__)("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
    article: (0, _i18n.__)('The <article> element should represent a self-contained, syndicatable portion of the document.'),
    aside: (0, _i18n.__)("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content."),
    footer: (0, _i18n.__)('The <footer> element should represent a footer for its nearest sectioning element (e.g.: <section>, <article>, <main> etc.).')
  };
  return (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('HTML element'),
    options: [{
      label: (0, _i18n.__)('Default (<div>)'),
      value: 'div'
    }, {
      label: '<header>',
      value: 'header'
    }, {
      label: '<main>',
      value: 'main'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<article>',
      value: 'article'
    }, {
      label: '<aside>',
      value: 'aside'
    }, {
      label: '<footer>',
      value: 'footer'
    }],
    value: tagName,
    onChange: onSelectTagName,
    help: htmlElementMessages[tagName]
  }));
}
function GroupEdit({
  attributes,
  name,
  setAttributes,
  clientId
}) {
  const {
    hasInnerBlocks,
    themeSupportsLayout
  } = (0, _data.useSelect)(select => {
    const {
      getBlock,
      getSettings
    } = select(_blockEditor.store);
    const block = getBlock(clientId);
    return {
      hasInnerBlocks: !!(block && block.innerBlocks.length),
      themeSupportsLayout: getSettings()?.supportsLayout
    };
  }, [clientId]);
  const {
    tagName: TagName = 'div',
    templateLock,
    allowedBlocks,
    layout = {}
  } = attributes;

  // Layout settings.
  const {
    type = 'default'
  } = layout;
  const layoutSupportEnabled = themeSupportsLayout || type === 'flex' || type === 'grid';

  // Hooks.
  const ref = (0, _element.useRef)();
  const blockProps = (0, _blockEditor.useBlockProps)({
    ref
  });
  const [showPlaceholder, setShowPlaceholder] = (0, _placeholder.useShouldShowPlaceHolder)({
    attributes,
    usedLayoutType: type,
    hasInnerBlocks
  });

  // Default to the regular appender being rendered.
  let renderAppender;
  if (showPlaceholder) {
    // In the placeholder state, ensure the appender is not rendered.
    // This is needed because `...innerBlocksProps` is used in the placeholder
    // state so that blocks can dragged onto the placeholder area
    // from both the list view and in the editor canvas.
    renderAppender = false;
  } else if (!hasInnerBlocks) {
    // When there is no placeholder, but the block is also empty,
    // use the larger button appender.
    renderAppender = _blockEditor.InnerBlocks.ButtonBlockAppender;
  }
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(layoutSupportEnabled ? blockProps : {
    className: 'wp-block-group__inner-container'
  }, {
    dropZoneElement: ref.current,
    templateLock,
    allowedBlocks,
    renderAppender
  });
  const {
    selectBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  const selectVariation = nextVariation => {
    setAttributes(nextVariation.attributes);
    selectBlock(clientId, -1);
    setShowPlaceholder(false);
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(GroupEditControls, {
    tagName: TagName,
    onSelectTagName: value => setAttributes({
      tagName: value
    })
  }), showPlaceholder && (0, _react.createElement)(_primitives.View, null, innerBlocksProps.children, (0, _react.createElement)(_placeholder.default, {
    name: name,
    onSelect: selectVariation
  })), layoutSupportEnabled && !showPlaceholder && (0, _react.createElement)(TagName, {
    ...innerBlocksProps
  }), !layoutSupportEnabled && !showPlaceholder && (0, _react.createElement)(TagName, {
    ...blockProps
  }, (0, _react.createElement)("div", {
    ...innerBlocksProps
  })));
}
var _default = exports.default = GroupEdit;
//# sourceMappingURL=edit.js.map