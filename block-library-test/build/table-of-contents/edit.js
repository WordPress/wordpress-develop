"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = TableOfContentsEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _blocks = require("@wordpress/blocks");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _compose = require("@wordpress/compose");
var _notices = require("@wordpress/notices");
var _icons = require("@wordpress/icons");
var _list = _interopRequireDefault(require("./list"));
var _utils = require("./utils");
var _hooks = require("./hooks");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/** @typedef {import('./utils').HeadingData} HeadingData */

/**
 * Table of Contents block edit component.
 *
 * @param {Object}                       props                                   The props.
 * @param {Object}                       props.attributes                        The block attributes.
 * @param {HeadingData[]}                props.attributes.headings               A list of data for each heading in the post.
 * @param {boolean}                      props.attributes.onlyIncludeCurrentPage Whether to only include headings from the current page (if the post is paginated).
 * @param {string}                       props.clientId
 * @param {(attributes: Object) => void} props.setAttributes
 *
 * @return {Component} The component.
 */
function TableOfContentsEdit({
  attributes: {
    headings = [],
    onlyIncludeCurrentPage
  },
  clientId,
  setAttributes
}) {
  (0, _hooks.useObserveHeadings)(clientId);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const instanceId = (0, _compose.useInstanceId)(TableOfContentsEdit, 'table-of-contents');

  // If a user clicks to a link prevent redirection and show a warning.
  const {
    createWarningNotice,
    removeNotice
  } = (0, _data.useDispatch)(_notices.store);
  let noticeId;
  const showRedirectionPreventedNotice = event => {
    event.preventDefault();
    // Remove previous warning if any, to show one at a time per block.
    removeNotice(noticeId);
    noticeId = `block-library/core/table-of-contents/redirection-prevented/${instanceId}`;
    createWarningNotice((0, _i18n.__)('Links are disabled in the editor.'), {
      id: noticeId,
      type: 'snackbar'
    });
  };
  const canInsertList = (0, _data.useSelect)(select => {
    const {
      getBlockRootClientId,
      canInsertBlockType
    } = select(_blockEditor.store);
    const rootClientId = getBlockRootClientId(clientId);
    return canInsertBlockType('core/list', rootClientId);
  }, [clientId]);
  const {
    replaceBlocks
  } = (0, _data.useDispatch)(_blockEditor.store);
  const headingTree = (0, _utils.linearToNestedHeadingList)(headings);
  const toolbarControls = canInsertList && (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: () => replaceBlocks(clientId, (0, _blocks.createBlock)('core/list', {
      ordered: true,
      values: (0, _element.renderToString)((0, _react.createElement)(_list.default, {
        nestedHeadingList: headingTree
      }))
    }))
  }, (0, _i18n.__)('Convert to static list'))));
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Only include current page'),
    checked: onlyIncludeCurrentPage,
    onChange: value => setAttributes({
      onlyIncludeCurrentPage: value
    }),
    help: onlyIncludeCurrentPage ? (0, _i18n.__)('Only including headings from the current page (if the post is paginated).') : (0, _i18n.__)('Toggle to only include headings from the current page (if the post is paginated).')
  })));

  // If there are no headings or the only heading is empty.
  // Note that the toolbar controls are intentionally omitted since the
  // "Convert to static list" option is useless to the placeholder state.
  if (headings.length === 0) {
    return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: _icons.tableOfContents
      }),
      label: (0, _i18n.__)('Table of Contents'),
      instructions: (0, _i18n.__)('Start adding Heading blocks to create a table of contents. Headings with HTML anchors will be linked here.')
    })), inspectorControls);
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)("nav", {
    ...blockProps
  }, (0, _react.createElement)("ol", null, (0, _react.createElement)(_list.default, {
    nestedHeadingList: headingTree,
    disableLinkActivation: true,
    onClick: showRedirectionPreventedNotice
  }))), toolbarControls, inspectorControls);
}
//# sourceMappingURL=edit.js.map