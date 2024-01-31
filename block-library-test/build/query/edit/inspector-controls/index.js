"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryInspectorControls;
var _react = require("react");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
var _orderControl = _interopRequireDefault(require("./order-control"));
var _authorControl = _interopRequireDefault(require("./author-control"));
var _parentControl = _interopRequireDefault(require("./parent-control"));
var _taxonomyControls = require("./taxonomy-controls");
var _stickyControl = _interopRequireDefault(require("./sticky-control"));
var _enhancedPaginationControl = _interopRequireDefault(require("./enhanced-pagination-control"));
var _createNewPostLink = _interopRequireDefault(require("./create-new-post-link"));
var _lockUnlock = require("../../../lock-unlock");
var _utils = require("../../utils");
var _constants = require("../../../utils/constants");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  BlockInfo
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
function QueryInspectorControls(props) {
  const {
    attributes,
    setQuery,
    setDisplayLayout,
    setAttributes,
    clientId
  } = props;
  const {
    query,
    displayLayout,
    enhancedPagination
  } = attributes;
  const {
    order,
    orderBy,
    author: authorIds,
    postType,
    sticky,
    inherit,
    taxQuery,
    parents
  } = query;
  const allowedControls = (0, _utils.useAllowedControls)(attributes);
  const [showSticky, setShowSticky] = (0, _element.useState)(postType === 'post');
  const {
    postTypesTaxonomiesMap,
    postTypesSelectOptions
  } = (0, _utils.usePostTypes)();
  const taxonomies = (0, _utils.useTaxonomies)(postType);
  const isPostTypeHierarchical = (0, _utils.useIsPostTypeHierarchical)(postType);
  (0, _element.useEffect)(() => {
    setShowSticky(postType === 'post');
  }, [postType]);
  const onPostTypeChange = newValue => {
    const updateQuery = {
      postType: newValue
    };
    // We need to dynamically update the `taxQuery` property,
    // by removing any not supported taxonomy from the query.
    const supportedTaxonomies = postTypesTaxonomiesMap[newValue];
    const updatedTaxQuery = Object.entries(taxQuery || {}).reduce((accumulator, [taxonomySlug, terms]) => {
      if (supportedTaxonomies.includes(taxonomySlug)) {
        accumulator[taxonomySlug] = terms;
      }
      return accumulator;
    }, {});
    updateQuery.taxQuery = !!Object.keys(updatedTaxQuery).length ? updatedTaxQuery : undefined;
    if (newValue !== 'post') {
      updateQuery.sticky = '';
    }
    // We need to reset `parents` because they are tied to each post type.
    updateQuery.parents = [];
    setQuery(updateQuery);
  };
  const [querySearch, setQuerySearch] = (0, _element.useState)(query.search);
  const onChangeDebounced = (0, _element.useCallback)((0, _compose.debounce)(() => {
    if (query.search !== querySearch) {
      setQuery({
        search: querySearch
      });
    }
  }, 250), [querySearch, query.search]);
  (0, _element.useEffect)(() => {
    onChangeDebounced();
    return onChangeDebounced.cancel;
  }, [querySearch, onChangeDebounced]);
  const showInheritControl = (0, _utils.isControlAllowed)(allowedControls, 'inherit');
  const showPostTypeControl = !inherit && (0, _utils.isControlAllowed)(allowedControls, 'postType');
  const showColumnsControl = false;
  const showOrderControl = !inherit && (0, _utils.isControlAllowed)(allowedControls, 'order');
  const showStickyControl = !inherit && showSticky && (0, _utils.isControlAllowed)(allowedControls, 'sticky');
  const showSettingsPanel = showInheritControl || showPostTypeControl || showColumnsControl || showOrderControl || showStickyControl;
  const showTaxControl = !!taxonomies?.length && (0, _utils.isControlAllowed)(allowedControls, 'taxQuery');
  const showAuthorControl = (0, _utils.isControlAllowed)(allowedControls, 'author');
  const showSearchControl = (0, _utils.isControlAllowed)(allowedControls, 'search');
  const showParentControl = (0, _utils.isControlAllowed)(allowedControls, 'parents') && isPostTypeHierarchical;
  const showFiltersPanel = showTaxControl || showAuthorControl || showSearchControl || showParentControl;
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(BlockInfo, null, (0, _react.createElement)(_createNewPostLink.default, {
    ...props
  })), showSettingsPanel && (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, showInheritControl && (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Inherit query from template'),
    help: (0, _i18n.__)('Toggle to use the global query context that is set with the current template, such as an archive or search. Disable to customize the settings independently.'),
    checked: !!inherit,
    onChange: value => setQuery({
      inherit: !!value
    })
  }), showPostTypeControl && (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    options: postTypesSelectOptions,
    value: postType,
    label: (0, _i18n.__)('Post type'),
    onChange: onPostTypeChange,
    help: (0, _i18n.__)('WordPress contains different types of content and they are divided into collections called “Post types”. By default there are a few different ones such as blog posts and pages, but plugins could add more.')
  }), showColumnsControl && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Columns'),
    value: displayLayout.columns,
    onChange: value => setDisplayLayout({
      columns: value
    }),
    min: 2,
    max: Math.max(6, displayLayout.columns)
  }), displayLayout.columns > 6 && (0, _react.createElement)(_components.Notice, {
    status: "warning",
    isDismissible: false
  }, (0, _i18n.__)('This column count exceeds the recommended amount and may cause visual breakage.'))), showOrderControl && (0, _react.createElement)(_orderControl.default, {
    order,
    orderBy,
    onChange: setQuery
  }), showStickyControl && (0, _react.createElement)(_stickyControl.default, {
    value: sticky,
    onChange: value => setQuery({
      sticky: value
    })
  }), (0, _react.createElement)(_enhancedPaginationControl.default, {
    enhancedPagination: enhancedPagination,
    setAttributes: setAttributes,
    clientId: clientId
  })), !inherit && showFiltersPanel && (0, _react.createElement)(_components.__experimentalToolsPanel, {
    className: "block-library-query-toolspanel__filters",
    label: (0, _i18n.__)('Filters'),
    resetAll: () => {
      setQuery({
        author: '',
        parents: [],
        search: '',
        taxQuery: null
      });
      setQuerySearch('');
    },
    dropdownMenuProps: _constants.TOOLSPANEL_DROPDOWNMENU_PROPS
  }, showTaxControl && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    label: (0, _i18n.__)('Taxonomies'),
    hasValue: () => Object.values(taxQuery || {}).some(terms => !!terms.length),
    onDeselect: () => setQuery({
      taxQuery: null
    })
  }, (0, _react.createElement)(_taxonomyControls.TaxonomyControls, {
    onChange: setQuery,
    query: query
  })), showAuthorControl && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!authorIds,
    label: (0, _i18n.__)('Authors'),
    onDeselect: () => setQuery({
      author: ''
    })
  }, (0, _react.createElement)(_authorControl.default, {
    value: authorIds,
    onChange: setQuery
  })), showSearchControl && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!querySearch,
    label: (0, _i18n.__)('Keyword'),
    onDeselect: () => setQuerySearch('')
  }, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Keyword'),
    value: querySearch,
    onChange: setQuerySearch
  })), showParentControl && (0, _react.createElement)(_components.__experimentalToolsPanelItem, {
    hasValue: () => !!parents?.length,
    label: (0, _i18n.__)('Parents'),
    onDeselect: () => setQuery({
      parents: []
    })
  }, (0, _react.createElement)(_parentControl.default, {
    parents: parents,
    postType: postType,
    onChange: setQuery
  }))));
}
//# sourceMappingURL=index.js.map