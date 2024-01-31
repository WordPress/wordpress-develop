"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

const CommentsFormPlaceholder = () => {
  const instanceId = (0, _compose.useInstanceId)(CommentsFormPlaceholder);
  return (0, _react.createElement)("div", {
    className: "comment-respond"
  }, (0, _react.createElement)("h3", {
    className: "comment-reply-title"
  }, (0, _i18n.__)('Leave a Reply')), (0, _react.createElement)("form", {
    noValidate: true,
    className: "comment-form",
    onSubmit: event => event.preventDefault()
  }, (0, _react.createElement)("p", null, (0, _react.createElement)("label", {
    htmlFor: `comment-${instanceId}`
  }, (0, _i18n.__)('Comment')), (0, _react.createElement)("textarea", {
    id: `comment-${instanceId}`,
    name: "comment",
    cols: "45",
    rows: "8",
    readOnly: true
  })), (0, _react.createElement)("p", {
    className: "form-submit wp-block-button"
  }, (0, _react.createElement)("input", {
    name: "submit",
    type: "submit",
    className: (0, _classnames.default)('wp-block-button__link', (0, _blockEditor.__experimentalGetElementClassName)('button')),
    label: (0, _i18n.__)('Post Comment'),
    value: (0, _i18n.__)('Post Comment'),
    "aria-disabled": "true"
  }))));
};
const CommentsForm = ({
  postId,
  postType
}) => {
  const [commentStatus, setCommentStatus] = (0, _coreData.useEntityProp)('postType', postType, 'comment_status', postId);
  const isSiteEditor = postType === undefined || postId === undefined;
  const {
    defaultCommentStatus
  } = (0, _data.useSelect)(select => select(_blockEditor.store).getSettings().__experimentalDiscussionSettings);
  const postTypeSupportsComments = (0, _data.useSelect)(select => postType ? !!select(_coreData.store).getPostType(postType)?.supports.comments : false);
  if (!isSiteEditor && 'open' !== commentStatus) {
    if ('closed' === commentStatus) {
      const actions = [(0, _react.createElement)(_components.Button, {
        key: "enableComments",
        onClick: () => setCommentStatus('open'),
        variant: "primary"
      }, (0, _i18n._x)('Enable comments', 'action that affects the current post'))];
      return (0, _react.createElement)(_blockEditor.Warning, {
        actions: actions
      }, (0, _i18n.__)('Post Comments Form block: Comments are not enabled for this item.'));
    } else if (!postTypeSupportsComments) {
      return (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.sprintf)( /* translators: 1: Post type (i.e. "post", "page") */
      (0, _i18n.__)('Post Comments Form block: Comments are not enabled for this post type (%s).'), postType));
    } else if ('open' !== defaultCommentStatus) {
      return (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Post Comments Form block: Comments are not enabled.'));
    }
  }
  return (0, _react.createElement)(CommentsFormPlaceholder, null);
};
var _default = exports.default = CommentsForm;
//# sourceMappingURL=form.js.map