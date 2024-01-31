"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _icons2 = require("./icons");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const QUERY_DEFAULT_ATTRIBUTES = {
  query: {
    perPage: 3,
    pages: 0,
    offset: 0,
    postType: 'post',
    order: 'desc',
    orderBy: 'date',
    author: '',
    search: '',
    exclude: [],
    sticky: '',
    inherit: false
  }
};
const variations = [{
  name: 'posts-list',
  title: (0, _i18n.__)('Posts List'),
  description: (0, _i18n.__)('Display a list of your most recent posts, excluding sticky posts.'),
  icon: _icons.postList,
  attributes: {
    namespace: 'core/posts-list',
    query: {
      perPage: 4,
      pages: 1,
      offset: 0,
      postType: 'post',
      order: 'desc',
      orderBy: 'date',
      author: '',
      search: '',
      sticky: 'exclude',
      inherit: false
    }
  },
  scope: ['inserter'],
  isActive: ({
    namespace,
    query
  }) => {
    return namespace === 'core/posts-list' && query.postType === 'post';
  }
}, {
  name: 'title-date',
  title: (0, _i18n.__)('Title & Date'),
  icon: _icons2.titleDate,
  attributes: {
    ...QUERY_DEFAULT_ATTRIBUTES
  },
  innerBlocks: [['core/post-template', {}, [['core/post-title'], ['core/post-date']]], ['core/query-pagination'], ['core/query-no-results']],
  scope: ['block']
}, {
  name: 'title-excerpt',
  title: (0, _i18n.__)('Title & Excerpt'),
  icon: _icons2.titleExcerpt,
  attributes: {
    ...QUERY_DEFAULT_ATTRIBUTES
  },
  innerBlocks: [['core/post-template', {}, [['core/post-title'], ['core/post-excerpt']]], ['core/query-pagination'], ['core/query-no-results']],
  scope: ['block']
}, {
  name: 'title-date-excerpt',
  title: (0, _i18n.__)('Title, Date, & Excerpt'),
  icon: _icons2.titleDateExcerpt,
  attributes: {
    ...QUERY_DEFAULT_ATTRIBUTES
  },
  innerBlocks: [['core/post-template', {}, [['core/post-title'], ['core/post-date'], ['core/post-excerpt']]], ['core/query-pagination'], ['core/query-no-results']],
  scope: ['block']
}, {
  name: 'image-date-title',
  title: (0, _i18n.__)('Image, Date, & Title'),
  icon: _icons2.imageDateTitle,
  attributes: {
    ...QUERY_DEFAULT_ATTRIBUTES
  },
  innerBlocks: [['core/post-template', {}, [['core/post-featured-image'], ['core/post-date'], ['core/post-title']]], ['core/query-pagination'], ['core/query-no-results']],
  scope: ['block']
}];
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map