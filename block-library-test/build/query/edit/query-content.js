"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryContent;
var _react = require("react");
var _data = require("@wordpress/data");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
var _queryToolbar = _interopRequireDefault(require("./query-toolbar"));
var _inspectorControls = _interopRequireDefault(require("./inspector-controls"));
var _enhancedPaginationModal = _interopRequireDefault(require("./enhanced-pagination-modal"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const DEFAULTS_POSTS_PER_PAGE = 3;
const TEMPLATE = [['core/post-template']];
function QueryContent({
  attributes,
  setAttributes,
  openPatternSelectionModal,
  name,
  clientId
}) {
  const {
    queryId,
    query,
    displayLayout,
    tagName: TagName = 'div',
    query: {
      inherit
    } = {}
  } = attributes;
  const {
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const instanceId = (0, _compose.useInstanceId)(QueryContent);
  const blockProps = (0, _blockEditor.useBlockProps)();
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    template: TEMPLATE
  });
  const {
    postsPerPage
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      getEntityRecord,
      canUser
    } = select(_coreData.store);
    const settingPerPage = canUser('read', 'settings') ? +getEntityRecord('root', 'site')?.posts_per_page : +getSettings().postsPerPage;
    return {
      postsPerPage: settingPerPage || DEFAULTS_POSTS_PER_PAGE
    };
  }, []);
  // There are some effects running where some initialization logic is
  // happening and setting some values to some attributes (ex. queryId).
  // These updates can cause an `undo trap` where undoing will result in
  // resetting again, so we need to mark these changes as not persistent
  // with `__unstableMarkNextChangeAsNotPersistent`.

  // Changes in query property (which is an object) need to be in the same callback,
  // because updates are batched after the render and changes in different query properties
  // would cause to override previous wanted changes.
  (0, _element.useEffect)(() => {
    const newQuery = {};
    // When we inherit from global query always need to set the `perPage`
    // based on the reading settings.
    if (inherit && query.perPage !== postsPerPage) {
      newQuery.perPage = postsPerPage;
    } else if (!query.perPage && postsPerPage) {
      newQuery.perPage = postsPerPage;
    }
    if (!!Object.keys(newQuery).length) {
      __unstableMarkNextChangeAsNotPersistent();
      updateQuery(newQuery);
    }
  }, [query.perPage, postsPerPage, inherit]);
  // We need this for multi-query block pagination.
  // Query parameters for each block are scoped to their ID.
  (0, _element.useEffect)(() => {
    if (!Number.isFinite(queryId)) {
      __unstableMarkNextChangeAsNotPersistent();
      setAttributes({
        queryId: instanceId
      });
    }
  }, [queryId, instanceId]);
  const updateQuery = newQuery => setAttributes({
    query: {
      ...query,
      ...newQuery
    }
  });
  const updateDisplayLayout = newDisplayLayout => setAttributes({
    displayLayout: {
      ...displayLayout,
      ...newDisplayLayout
    }
  });
  const htmlElementMessages = {
    main: (0, _i18n.__)('The <main> element should be used for the primary content of your document only. '),
    section: (0, _i18n.__)("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
    aside: (0, _i18n.__)("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content.")
  };
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_enhancedPaginationModal.default, {
    attributes: attributes,
    setAttributes: setAttributes,
    clientId: clientId
  }), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_inspectorControls.default, {
    attributes: attributes,
    setQuery: updateQuery,
    setDisplayLayout: updateDisplayLayout,
    setAttributes: setAttributes,
    clientId: clientId
  })), (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_queryToolbar.default, {
    name: name,
    clientId: clientId,
    attributes: attributes,
    setQuery: updateQuery,
    openPatternSelectionModal: openPatternSelectionModal
  })), (0, _react.createElement)(_blockEditor.InspectorControls, {
    group: "advanced"
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('HTML element'),
    options: [{
      label: (0, _i18n.__)('Default (<div>)'),
      value: 'div'
    }, {
      label: '<main>',
      value: 'main'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<aside>',
      value: 'aside'
    }],
    value: TagName,
    onChange: value => setAttributes({
      tagName: value
    }),
    help: htmlElementMessages[TagName]
  })), (0, _react.createElement)(TagName, {
    ...innerBlocksProps
  }));
}
//# sourceMappingURL=query-content.js.map