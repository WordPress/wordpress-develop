"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostTemplateEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _icons = require("@wordpress/icons");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const TEMPLATE = [['core/post-title'], ['core/post-date'], ['core/post-excerpt']];
function PostTemplateInnerBlocks() {
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)({
    className: 'wp-block-post'
  }, {
    template: TEMPLATE,
    __unstableDisableLayoutClassNames: true
  });
  return (0, _react.createElement)("li", {
    ...innerBlocksProps
  });
}
function PostTemplateBlockPreview({
  blocks,
  blockContextId,
  isHidden,
  setActiveBlockContextId
}) {
  const blockPreviewProps = (0, _blockEditor.__experimentalUseBlockPreview)({
    blocks,
    props: {
      className: 'wp-block-post'
    }
  });
  const handleOnClick = () => {
    setActiveBlockContextId(blockContextId);
  };
  const style = {
    display: isHidden ? 'none' : undefined
  };
  return (0, _react.createElement)("li", {
    ...blockPreviewProps,
    tabIndex: 0
    // eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
    ,
    role: "button",
    onClick: handleOnClick,
    onKeyPress: handleOnClick,
    style: style
  });
}
const MemoizedPostTemplateBlockPreview = (0, _element.memo)(PostTemplateBlockPreview);
function PostTemplateEdit({
  setAttributes,
  clientId,
  context: {
    query: {
      perPage,
      offset = 0,
      postType,
      order,
      orderBy,
      author,
      search,
      exclude,
      sticky,
      inherit,
      taxQuery,
      parents,
      pages,
      // We gather extra query args to pass to the REST API call.
      // This way extenders of Query Loop can add their own query args,
      // and have accurate previews in the editor.
      // Noting though that these args should either be supported by the
      // REST API or be handled by custom REST filters like `rest_{$this->post_type}_query`.
      ...restQueryArgs
    } = {},
    templateSlug,
    previewPostType
  },
  attributes: {
    layout
  },
  __unstableLayoutClassNames
}) {
  const {
    type: layoutType,
    columnCount = 3
  } = layout || {};
  const [activeBlockContextId, setActiveBlockContextId] = (0, _element.useState)();
  const {
    posts,
    blocks
  } = (0, _data.useSelect)(select => {
    const {
      getEntityRecords,
      getTaxonomies
    } = select(_coreData.store);
    const {
      getBlocks
    } = select(_blockEditor.store);
    const templateCategory = inherit && templateSlug?.startsWith('category-') && getEntityRecords('taxonomy', 'category', {
      context: 'view',
      per_page: 1,
      _fields: ['id'],
      slug: templateSlug.replace('category-', '')
    });
    const query = {
      offset: offset || 0,
      order,
      orderby: orderBy
    };
    // There is no need to build the taxQuery if we inherit.
    if (taxQuery && !inherit) {
      const taxonomies = getTaxonomies({
        type: postType,
        per_page: -1,
        context: 'view'
      });
      // We have to build the tax query for the REST API and use as
      // keys the taxonomies `rest_base` with the `term ids` as values.
      const builtTaxQuery = Object.entries(taxQuery).reduce((accumulator, [taxonomySlug, terms]) => {
        const taxonomy = taxonomies?.find(({
          slug
        }) => slug === taxonomySlug);
        if (taxonomy?.rest_base) {
          accumulator[taxonomy?.rest_base] = terms;
        }
        return accumulator;
      }, {});
      if (!!Object.keys(builtTaxQuery).length) {
        Object.assign(query, builtTaxQuery);
      }
    }
    if (perPage) {
      query.per_page = perPage;
    }
    if (author) {
      query.author = author;
    }
    if (search) {
      query.search = search;
    }
    if (exclude?.length) {
      query.exclude = exclude;
    }
    if (parents?.length) {
      query.parent = parents;
    }
    // If sticky is not set, it will return all posts in the results.
    // If sticky is set to `only`, it will limit the results to sticky posts only.
    // If it is anything else, it will exclude sticky posts from results. For the record the value stored is `exclude`.
    if (sticky) {
      query.sticky = sticky === 'only';
    }
    // If `inherit` is truthy, adjust conditionally the query to create a better preview.
    if (inherit) {
      // Change the post-type if needed.
      if (templateSlug?.startsWith('archive-')) {
        query.postType = templateSlug.replace('archive-', '');
        postType = query.postType;
      } else if (templateCategory) {
        query.categories = templateCategory[0]?.id;
      }
    }
    // When we preview Query Loop blocks we should prefer the current
    // block's postType, which is passed through block context.
    const usedPostType = previewPostType || postType;
    return {
      posts: getEntityRecords('postType', usedPostType, {
        ...query,
        ...restQueryArgs
      }),
      blocks: getBlocks(clientId)
    };
  }, [perPage, offset, order, orderBy, clientId, author, search, postType, exclude, sticky, inherit, templateSlug, taxQuery, parents, restQueryArgs, previewPostType]);
  const blockContexts = (0, _element.useMemo)(() => posts?.map(post => ({
    postType: post.type,
    postId: post.id
  })), [posts]);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)(__unstableLayoutClassNames, {
      [`columns-${columnCount}`]: layoutType === 'grid' && columnCount // Ensure column count is flagged via classname for backwards compatibility.
    })
  });
  if (!posts) {
    return (0, _react.createElement)("p", {
      ...blockProps
    }, (0, _react.createElement)(_components.Spinner, null));
  }
  if (!posts.length) {
    return (0, _react.createElement)("p", {
      ...blockProps
    }, " ", (0, _i18n.__)('No results found.'));
  }
  const setDisplayLayout = newDisplayLayout => setAttributes({
    layout: {
      ...layout,
      ...newDisplayLayout
    }
  });
  const displayLayoutControls = [{
    icon: _icons.list,
    title: (0, _i18n.__)('List view'),
    onClick: () => setDisplayLayout({
      type: 'default'
    }),
    isActive: layoutType === 'default' || layoutType === 'constrained'
  }, {
    icon: _icons.grid,
    title: (0, _i18n.__)('Grid view'),
    onClick: () => setDisplayLayout({
      type: 'grid',
      columnCount
    }),
    isActive: layoutType === 'grid'
  }];

  // To avoid flicker when switching active block contexts, a preview is rendered
  // for each block context, but the preview for the active block context is hidden.
  // This ensures that when it is displayed again, the cached rendering of the
  // block preview is used, instead of having to re-render the preview from scratch.
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, {
    controls: displayLayoutControls
  })), (0, _react.createElement)("ul", {
    ...blockProps
  }, blockContexts && blockContexts.map(blockContext => (0, _react.createElement)(_blockEditor.BlockContextProvider, {
    key: blockContext.postId,
    value: blockContext
  }, blockContext.postId === (activeBlockContextId || blockContexts[0]?.postId) ? (0, _react.createElement)(PostTemplateInnerBlocks, null) : null, (0, _react.createElement)(MemoizedPostTemplateBlockPreview, {
    blocks: blocks,
    blockContextId: blockContext.postId,
    setActiveBlockContextId: setActiveBlockContextId,
    isHidden: blockContext.postId === (activeBlockContextId || blockContexts[0]?.postId)
  })))));
}
//# sourceMappingURL=edit.js.map