"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PageListEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blocks = require("@wordpress/blocks");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _useConvertToNavigationLinks = require("./use-convert-to-navigation-links");
var _convertToLinksModal = require("./convert-to-links-modal");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

// We only show the edit option when page count is <= MAX_PAGE_COUNT
// Performance of Navigation Links is not good past this value.
const MAX_PAGE_COUNT = 100;
const NOOP = () => {};
function BlockContent({
  blockProps,
  innerBlocksProps,
  hasResolvedPages,
  blockList,
  pages,
  parentPageID
}) {
  if (!hasResolvedPages) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)("div", {
      className: "wp-block-page-list__loading-indicator-container"
    }, (0, _react.createElement)(_components.Spinner, {
      className: "wp-block-page-list__loading-indicator"
    })));
  }
  if (pages === null) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Notice, {
      status: 'warning',
      isDismissible: false
    }, (0, _i18n.__)('Page List: Cannot retrieve Pages.')));
  }
  if (pages.length === 0) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Notice, {
      status: 'info',
      isDismissible: false
    }, (0, _i18n.__)('Page List: Cannot retrieve Pages.')));
  }
  if (blockList.length === 0) {
    const parentPageDetails = pages.find(page => page.id === parentPageID);
    if (parentPageDetails?.title?.rendered) {
      return (0, _react.createElement)("div", {
        ...blockProps
      }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.sprintf)(
      // translators: %s: Page title.
      (0, _i18n.__)('Page List: "%s" page has no children.'), parentPageDetails.title.rendered)));
    }
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Notice, {
      status: 'warning',
      isDismissible: false
    }, (0, _i18n.__)('Page List: Cannot retrieve Pages.')));
  }
  if (pages.length > 0) {
    return (0, _react.createElement)("ul", {
      ...innerBlocksProps
    });
  }
}
function PageListEdit({
  context,
  clientId,
  attributes,
  setAttributes
}) {
  const {
    parentPageID
  } = attributes;
  const [isOpen, setOpen] = (0, _element.useState)(false);
  const openModal = (0, _element.useCallback)(() => setOpen(true), []);
  const closeModal = () => setOpen(false);
  const {
    records: pages,
    hasResolved: hasResolvedPages
  } = (0, _coreData.useEntityRecords)('postType', 'page', {
    per_page: MAX_PAGE_COUNT,
    _fields: ['id', 'link', 'menu_order', 'parent', 'title', 'type'],
    // TODO: When https://core.trac.wordpress.org/ticket/39037 REST API support for multiple orderby
    // values is resolved, update 'orderby' to [ 'menu_order', 'post_title' ] to provide a consistent
    // sort.
    orderby: 'menu_order',
    order: 'asc'
  });
  const allowConvertToLinks = 'showSubmenuIcon' in context && pages?.length > 0 && pages?.length <= MAX_PAGE_COUNT;
  const pagesByParentId = (0, _element.useMemo)(() => {
    if (pages === null) {
      return new Map();
    }

    // TODO: Once the REST API supports passing multiple values to
    // 'orderby', this can be removed.
    // https://core.trac.wordpress.org/ticket/39037
    const sortedPages = pages.sort((a, b) => {
      if (a.menu_order === b.menu_order) {
        return a.title.rendered.localeCompare(b.title.rendered);
      }
      return a.menu_order - b.menu_order;
    });
    return sortedPages.reduce((accumulator, page) => {
      const {
        parent
      } = page;
      if (accumulator.has(parent)) {
        accumulator.get(parent).push(page);
      } else {
        accumulator.set(parent, [page]);
      }
      return accumulator;
    }, new Map());
  }, [pages]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)('wp-block-page-list', {
      'has-text-color': !!context.textColor,
      [(0, _blockEditor.getColorClassName)('color', context.textColor)]: !!context.textColor,
      'has-background': !!context.backgroundColor,
      [(0, _blockEditor.getColorClassName)('background-color', context.backgroundColor)]: !!context.backgroundColor
    }),
    style: {
      ...context.style?.color
    }
  });
  const pagesTree = (0, _element.useMemo)(function makePagesTree(parentId = 0, level = 0) {
    const childPages = pagesByParentId.get(parentId);
    if (!childPages?.length) {
      return [];
    }
    return childPages.reduce((tree, page) => {
      const hasChildren = pagesByParentId.has(page.id);
      const item = {
        value: page.id,
        label: '— '.repeat(level) + page.title.rendered,
        rawName: page.title.rendered
      };
      tree.push(item);
      if (hasChildren) {
        tree.push(...makePagesTree(page.id, level + 1));
      }
      return tree;
    }, []);
  }, [pagesByParentId]);
  const blockList = (0, _element.useMemo)(function getBlockList(parentId = parentPageID) {
    const childPages = pagesByParentId.get(parentId);
    if (!childPages?.length) {
      return [];
    }
    return childPages.reduce((template, page) => {
      const hasChildren = pagesByParentId.has(page.id);
      const pageProps = {
        id: page.id,
        label:
        // translators: displayed when a page has an empty title.
        page.title?.rendered?.trim() !== '' ? page.title?.rendered : (0, _i18n.__)('(no title)'),
        title: page.title?.rendered,
        link: page.url,
        hasChildren
      };
      let item = null;
      const children = getBlockList(page.id);
      item = (0, _blocks.createBlock)('core/page-list-item', pageProps, children);
      template.push(item);
      return template;
    }, []);
  }, [pagesByParentId, parentPageID]);
  const {
    isNested,
    hasSelectedChild,
    parentClientId,
    hasDraggedChild,
    isChildOfNavigation
  } = (0, _data.useSelect)(select => {
    const {
      getBlockParentsByBlockName,
      hasSelectedInnerBlock,
      hasDraggedInnerBlock
    } = select(_blockEditor.store);
    const blockParents = getBlockParentsByBlockName(clientId, 'core/navigation-submenu', true);
    const navigationBlockParents = getBlockParentsByBlockName(clientId, 'core/navigation', true);
    return {
      isNested: blockParents.length > 0,
      isChildOfNavigation: navigationBlockParents.length > 0,
      hasSelectedChild: hasSelectedInnerBlock(clientId, true),
      hasDraggedChild: hasDraggedInnerBlock(clientId, true),
      parentClientId: navigationBlockParents[0]
    };
  }, [clientId]);
  const convertToNavigationLinks = (0, _useConvertToNavigationLinks.useConvertToNavigationLinks)({
    clientId,
    pages,
    parentClientId,
    parentPageID
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    renderAppender: false,
    __unstableDisableDropZone: true,
    templateLock: isChildOfNavigation ? false : 'all',
    onInput: NOOP,
    onChange: NOOP,
    value: blockList
  });
  const {
    selectBlock
  } = (0, _data.useDispatch)(_blockEditor.store);
  (0, _element.useEffect)(() => {
    if (hasSelectedChild || hasDraggedChild) {
      openModal();
      selectBlock(parentClientId);
    }
  }, [hasSelectedChild, hasDraggedChild, parentClientId, selectBlock, openModal]);
  (0, _element.useEffect)(() => {
    setAttributes({
      isNested
    });
  }, [isNested, setAttributes]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.InspectorControls, null, pagesTree.length > 0 && (0, _react.createElement)(_components.PanelBody, null, (0, _react.createElement)(_components.ComboboxControl, {
    __next40pxDefaultSize: true,
    className: "editor-page-attributes__parent",
    label: (0, _i18n.__)('Parent'),
    value: parentPageID,
    options: pagesTree,
    onChange: value => setAttributes({
      parentPageID: value !== null && value !== void 0 ? value : 0
    }),
    help: (0, _i18n.__)('Choose a page to show only its subpages.')
  })), allowConvertToLinks && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Edit this menu')
  }, (0, _react.createElement)("p", null, _convertToLinksModal.convertDescription), (0, _react.createElement)(_components.Button, {
    variant: "primary",
    disabled: !hasResolvedPages,
    onClick: convertToNavigationLinks
  }, (0, _i18n.__)('Edit')))), allowConvertToLinks && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "other"
  }, (0, _react.createElement)(_components.ToolbarButton, {
    title: (0, _i18n.__)('Edit'),
    onClick: openModal
  }, (0, _i18n.__)('Edit'))), isOpen && (0, _react.createElement)(_convertToLinksModal.ConvertToLinksModal, {
    onClick: convertToNavigationLinks,
    onClose: closeModal,
    disabled: !hasResolvedPages
  })), (0, _react.createElement)(BlockContent, {
    blockProps: blockProps,
    innerBlocksProps: innerBlocksProps,
    hasResolvedPages: hasResolvedPages,
    blockList: blockList,
    pages: pages,
    parentPageID: parentPageID
  }));
}
//# sourceMappingURL=edit.js.map