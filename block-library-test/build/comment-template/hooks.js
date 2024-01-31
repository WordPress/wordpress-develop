"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useCommentTree = exports.useCommentQueryArgs = void 0;
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _blockEditor = require("@wordpress/block-editor");
var _url = require("@wordpress/url");
var _apiFetch = _interopRequireDefault(require("@wordpress/api-fetch"));
/**
 * WordPress dependencies
 */

// This is limited by WP REST API
const MAX_COMMENTS_PER_PAGE = 100;

/**
 * Return an object with the query args needed to fetch the default page of
 * comments.
 *
 * @param {Object} props        Hook props.
 * @param {number} props.postId ID of the post that contains the comments.
 *                              discussion settings.
 *
 * @return {Object} Query args to retrieve the comments.
 */
const useCommentQueryArgs = ({
  postId
}) => {
  // Initialize the query args that are not going to change.
  const queryArgs = {
    status: 'approve',
    order: 'asc',
    context: 'embed',
    parent: 0,
    _embed: 'children'
  };

  // Get the Discussion settings that may be needed to query the comments.
  const {
    pageComments,
    commentsPerPage,
    defaultCommentsPage: defaultPage
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      __experimentalDiscussionSettings
    } = getSettings();
    return __experimentalDiscussionSettings;
  });

  // WP REST API doesn't allow fetching more than max items limit set per single page of data.
  // As for the editor performance is more important than completeness of data and fetching only the
  // max allowed for single page should be enough for the purpose of design and laying out the page.
  // Fetching over the limit would return an error here but would work with backend query.
  const perPage = pageComments ? Math.min(commentsPerPage, MAX_COMMENTS_PER_PAGE) : MAX_COMMENTS_PER_PAGE;

  // Get the number of the default page.
  const page = useDefaultPageIndex({
    defaultPage,
    postId,
    perPage,
    queryArgs
  });

  // Merge, memoize and return all query arguments, unless the default page's
  // number is not known yet.
  return (0, _element.useMemo)(() => {
    return page ? {
      ...queryArgs,
      post: postId,
      per_page: perPage,
      page
    } : null;
  }, [postId, perPage, page]);
};

/**
 * Return the index of the default page, depending on whether `defaultPage` is
 * `newest` or `oldest`. In the first case, the only way to know the page's
 * index is by using the `X-WP-TotalPages` header, which forces to make an
 * additional request.
 *
 * @param {Object} props             Hook props.
 * @param {string} props.defaultPage Page shown by default (newest/oldest).
 * @param {number} props.postId      ID of the post that contains the comments.
 * @param {number} props.perPage     The number of comments included per page.
 * @param {Object} props.queryArgs   Other query args.
 *
 * @return {number} Index of the default comments page.
 */
exports.useCommentQueryArgs = useCommentQueryArgs;
const useDefaultPageIndex = ({
  defaultPage,
  postId,
  perPage,
  queryArgs
}) => {
  // Store the default page indices.
  const [defaultPages, setDefaultPages] = (0, _element.useState)({});
  const key = `${postId}_${perPage}`;
  const page = defaultPages[key] || 0;
  (0, _element.useEffect)(() => {
    // Do nothing if the page is already known or not the newest page.
    if (page || defaultPage !== 'newest') {
      return;
    }
    // We need to fetch comments to know the index. Use HEAD and limit
    // fields just to ID, to make this call as light as possible.
    (0, _apiFetch.default)({
      path: (0, _url.addQueryArgs)('/wp/v2/comments', {
        ...queryArgs,
        post: postId,
        per_page: perPage,
        _fields: 'id'
      }),
      method: 'HEAD',
      parse: false
    }).then(res => {
      const pages = parseInt(res.headers.get('X-WP-TotalPages'));
      setDefaultPages({
        ...defaultPages,
        [key]: pages <= 1 ? 1 : pages // If there are 0 pages, it means that there are no comments, but there is no 0th page.
      });
    });
  }, [defaultPage, postId, perPage, setDefaultPages]);

  // The oldest one is always the first one.
  return defaultPage === 'newest' ? page : 1;
};

/**
 * Generate a tree structure of comment IDs from a list of comment entities. The
 * children of each comment are obtained from `_embedded`.
 *
 * @typedef {{ commentId: number, children: CommentNode }} CommentNode
 *
 * @param {Object[]} topLevelComments List of comment entities.
 * @return {{ commentTree: CommentNode[]}} Tree of comment IDs.
 */
const useCommentTree = topLevelComments => {
  const commentTree = (0, _element.useMemo)(() => topLevelComments?.map(({
    id,
    _embedded
  }) => {
    const [children] = _embedded?.children || [[]];
    return {
      commentId: id,
      children: children.map(child => ({
        commentId: child.id
      }))
    };
  }), [topLevelComments]);
  return commentTree;
};
exports.useCommentTree = useCommentTree;
//# sourceMappingURL=hooks.js.map