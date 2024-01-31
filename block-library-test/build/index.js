"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.registerCoreBlocks = exports.__experimentalRegisterExperimentalCoreBlocks = exports.__experimentalGetCoreBlocks = void 0;
var _blocks = require("@wordpress/blocks");
var archives = _interopRequireWildcard(require("./archives"));
var avatar = _interopRequireWildcard(require("./avatar"));
var audio = _interopRequireWildcard(require("./audio"));
var button = _interopRequireWildcard(require("./button"));
var buttons = _interopRequireWildcard(require("./buttons"));
var calendar = _interopRequireWildcard(require("./calendar"));
var categories = _interopRequireWildcard(require("./categories"));
var classic = _interopRequireWildcard(require("./freeform"));
var code = _interopRequireWildcard(require("./code"));
var column = _interopRequireWildcard(require("./column"));
var columns = _interopRequireWildcard(require("./columns"));
var comments = _interopRequireWildcard(require("./comments"));
var commentAuthorAvatar = _interopRequireWildcard(require("./comment-author-avatar"));
var commentAuthorName = _interopRequireWildcard(require("./comment-author-name"));
var commentContent = _interopRequireWildcard(require("./comment-content"));
var commentDate = _interopRequireWildcard(require("./comment-date"));
var commentEditLink = _interopRequireWildcard(require("./comment-edit-link"));
var commentReplyLink = _interopRequireWildcard(require("./comment-reply-link"));
var commentTemplate = _interopRequireWildcard(require("./comment-template"));
var commentsPaginationPrevious = _interopRequireWildcard(require("./comments-pagination-previous"));
var commentsPagination = _interopRequireWildcard(require("./comments-pagination"));
var commentsPaginationNext = _interopRequireWildcard(require("./comments-pagination-next"));
var commentsPaginationNumbers = _interopRequireWildcard(require("./comments-pagination-numbers"));
var commentsTitle = _interopRequireWildcard(require("./comments-title"));
var cover = _interopRequireWildcard(require("./cover"));
var details = _interopRequireWildcard(require("./details"));
var embed = _interopRequireWildcard(require("./embed"));
var file = _interopRequireWildcard(require("./file"));
var form = _interopRequireWildcard(require("./form"));
var formInput = _interopRequireWildcard(require("./form-input"));
var formSubmitButton = _interopRequireWildcard(require("./form-submit-button"));
var formSubmissionNotification = _interopRequireWildcard(require("./form-submission-notification"));
var gallery = _interopRequireWildcard(require("./gallery"));
var group = _interopRequireWildcard(require("./group"));
var heading = _interopRequireWildcard(require("./heading"));
var homeLink = _interopRequireWildcard(require("./home-link"));
var html = _interopRequireWildcard(require("./html"));
var image = _interopRequireWildcard(require("./image"));
var latestComments = _interopRequireWildcard(require("./latest-comments"));
var latestPosts = _interopRequireWildcard(require("./latest-posts"));
var list = _interopRequireWildcard(require("./list"));
var listItem = _interopRequireWildcard(require("./list-item"));
var logInOut = _interopRequireWildcard(require("./loginout"));
var mediaText = _interopRequireWildcard(require("./media-text"));
var missing = _interopRequireWildcard(require("./missing"));
var more = _interopRequireWildcard(require("./more"));
var navigation = _interopRequireWildcard(require("./navigation"));
var navigationLink = _interopRequireWildcard(require("./navigation-link"));
var navigationSubmenu = _interopRequireWildcard(require("./navigation-submenu"));
var nextpage = _interopRequireWildcard(require("./nextpage"));
var pattern = _interopRequireWildcard(require("./pattern"));
var pageList = _interopRequireWildcard(require("./page-list"));
var pageListItem = _interopRequireWildcard(require("./page-list-item"));
var paragraph = _interopRequireWildcard(require("./paragraph"));
var postAuthor = _interopRequireWildcard(require("./post-author"));
var postAuthorName = _interopRequireWildcard(require("./post-author-name"));
var postAuthorBiography = _interopRequireWildcard(require("./post-author-biography"));
var postComment = _interopRequireWildcard(require("./post-comment"));
var postCommentsCount = _interopRequireWildcard(require("./post-comments-count"));
var postCommentsForm = _interopRequireWildcard(require("./post-comments-form"));
var postCommentsLink = _interopRequireWildcard(require("./post-comments-link"));
var postContent = _interopRequireWildcard(require("./post-content"));
var postDate = _interopRequireWildcard(require("./post-date"));
var postExcerpt = _interopRequireWildcard(require("./post-excerpt"));
var postFeaturedImage = _interopRequireWildcard(require("./post-featured-image"));
var postNavigationLink = _interopRequireWildcard(require("./post-navigation-link"));
var postTemplate = _interopRequireWildcard(require("./post-template"));
var postTerms = _interopRequireWildcard(require("./post-terms"));
var postTimeToRead = _interopRequireWildcard(require("./post-time-to-read"));
var postTitle = _interopRequireWildcard(require("./post-title"));
var preformatted = _interopRequireWildcard(require("./preformatted"));
var pullquote = _interopRequireWildcard(require("./pullquote"));
var query = _interopRequireWildcard(require("./query"));
var queryNoResults = _interopRequireWildcard(require("./query-no-results"));
var queryPagination = _interopRequireWildcard(require("./query-pagination"));
var queryPaginationNext = _interopRequireWildcard(require("./query-pagination-next"));
var queryPaginationNumbers = _interopRequireWildcard(require("./query-pagination-numbers"));
var queryPaginationPrevious = _interopRequireWildcard(require("./query-pagination-previous"));
var queryTitle = _interopRequireWildcard(require("./query-title"));
var quote = _interopRequireWildcard(require("./quote"));
var reusableBlock = _interopRequireWildcard(require("./block"));
var readMore = _interopRequireWildcard(require("./read-more"));
var rss = _interopRequireWildcard(require("./rss"));
var search = _interopRequireWildcard(require("./search"));
var separator = _interopRequireWildcard(require("./separator"));
var shortcode = _interopRequireWildcard(require("./shortcode"));
var siteLogo = _interopRequireWildcard(require("./site-logo"));
var siteTagline = _interopRequireWildcard(require("./site-tagline"));
var siteTitle = _interopRequireWildcard(require("./site-title"));
var socialLink = _interopRequireWildcard(require("./social-link"));
var socialLinks = _interopRequireWildcard(require("./social-links"));
var spacer = _interopRequireWildcard(require("./spacer"));
var table = _interopRequireWildcard(require("./table"));
var tableOfContents = _interopRequireWildcard(require("./table-of-contents"));
var tagCloud = _interopRequireWildcard(require("./tag-cloud"));
var templatePart = _interopRequireWildcard(require("./template-part"));
var termDescription = _interopRequireWildcard(require("./term-description"));
var textColumns = _interopRequireWildcard(require("./text-columns"));
var verse = _interopRequireWildcard(require("./verse"));
var video = _interopRequireWildcard(require("./video"));
var footnotes = _interopRequireWildcard(require("./footnotes"));
var _isBlockMetadataExperimental = _interopRequireDefault(require("./utils/is-block-metadata-experimental"));
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */
// When IS_GUTENBERG_PLUGIN is set to false, imports of experimental blocks
// are transformed by packages/block-library/src/index.js as follows:
//    import * as experimentalBlock from './experimental-block'
// becomes
//    const experimentalBlock = null;
// This enables webpack to eliminate the experimental blocks code from the
// production build to make the final bundle smaller.
//
// See https://github.com/WordPress/gutenberg/pull/40655 for more context.

/**
 * Function to get all the block-library blocks in an array
 */
const getAllBlocks = () => {
  const blocks = [
  // Common blocks are grouped at the top to prioritize their display
  // in various contexts — like the inserter and auto-complete components.
  paragraph, image, heading, gallery, list, listItem, quote,
  // Register all remaining core blocks.
  archives, audio, button, buttons, calendar, categories, code, column, columns, commentAuthorAvatar, cover, details, embed, file, group, html, latestComments, latestPosts, mediaText, missing, more, nextpage, pageList, pageListItem, pattern, preformatted, pullquote, reusableBlock, rss, search, separator, shortcode, socialLink, socialLinks, spacer, table, tagCloud, textColumns, verse, video, footnotes,
  // theme blocks
  navigation, navigationLink, navigationSubmenu, siteLogo, siteTitle, siteTagline, query, templatePart, avatar, postTitle, postExcerpt, postFeaturedImage, postContent, postAuthor, postAuthorName, postComment, postCommentsCount, postCommentsLink, postDate, postTerms, postNavigationLink, postTemplate, postTimeToRead, queryPagination, queryPaginationNext, queryPaginationNumbers, queryPaginationPrevious, queryNoResults, readMore, comments, commentAuthorName, commentContent, commentDate, commentEditLink, commentReplyLink, commentTemplate, commentsTitle, commentsPagination, commentsPaginationNext, commentsPaginationNumbers, commentsPaginationPrevious, postCommentsForm, tableOfContents, homeLink, logInOut, termDescription, queryTitle, postAuthorBiography];
  if (window?.__experimentalEnableFormBlocks) {
    blocks.push(form);
    blocks.push(formInput);
    blocks.push(formSubmitButton);
    blocks.push(formSubmissionNotification);
  }

  // When in a WordPress context, conditionally
  // add the classic block and TinyMCE editor
  // under any of the following conditions:
  //   - the current post contains a classic block
  //   - the experiment to disable TinyMCE isn't active.
  //   - a query argument specifies that TinyMCE should be loaded
  if (window?.wp?.oldEditor && (window?.wp?.needsClassicBlock || !window?.__experimentalDisableTinymce || !!new URLSearchParams(window?.location?.search).get('requiresTinymce'))) {
    blocks.push(classic);
  }
  return blocks.filter(Boolean);
};

/**
 * Function to get all the core blocks in an array.
 *
 * @example
 * ```js
 * import { __experimentalGetCoreBlocks } from '@wordpress/block-library';
 *
 * const coreBlocks = __experimentalGetCoreBlocks();
 * ```
 */
const __experimentalGetCoreBlocks = () => getAllBlocks().filter(({
  metadata
}) => !(0, _isBlockMetadataExperimental.default)(metadata));

/**
 * Function to register core blocks provided by the block editor.
 *
 * @param {Array} blocks An optional array of the core blocks being registered.
 *
 * @example
 * ```js
 * import { registerCoreBlocks } from '@wordpress/block-library';
 *
 * registerCoreBlocks();
 * ```
 */
exports.__experimentalGetCoreBlocks = __experimentalGetCoreBlocks;
const registerCoreBlocks = (blocks = __experimentalGetCoreBlocks()) => {
  blocks.forEach(({
    init
  }) => init());
  (0, _blocks.setDefaultBlockName)(paragraph.name);
  if (window.wp && window.wp.oldEditor && blocks.some(({
    name
  }) => name === classic.name)) {
    (0, _blocks.setFreeformContentHandlerName)(classic.name);
  }
  (0, _blocks.setUnregisteredTypeHandlerName)(missing.name);
  (0, _blocks.setGroupingBlockName)(group.name);
};

/**
 * Function to register experimental core blocks depending on editor settings.
 *
 * @param {boolean} enableFSEBlocks Whether to enable the full site editing blocks.
 * @example
 * ```js
 * import { __experimentalRegisterExperimentalCoreBlocks } from '@wordpress/block-library';
 *
 * __experimentalRegisterExperimentalCoreBlocks( settings );
 * ```
 */
exports.registerCoreBlocks = registerCoreBlocks;
const __experimentalRegisterExperimentalCoreBlocks = exports.__experimentalRegisterExperimentalCoreBlocks = process.env.IS_GUTENBERG_PLUGIN ? ({
  enableFSEBlocks
} = {}) => {
  const enabledExperiments = [enableFSEBlocks ? 'fse' : null];
  getAllBlocks().filter(({
    metadata
  }) => (0, _isBlockMetadataExperimental.default)(metadata)).filter(({
    metadata: {
      __experimental
    }
  }) => __experimental === true || enabledExperiments.includes(__experimental)).forEach(({
    init
  }) => init());
} : undefined;
//# sourceMappingURL=index.js.map