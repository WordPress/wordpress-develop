"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostCommentsPlaceholder;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _form = _interopRequireDefault(require("../../post-comments-form/form"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function PostCommentsPlaceholder({
  postType,
  postId
}) {
  let [postTitle] = (0, _coreData.useEntityProp)('postType', postType, 'title', postId);
  postTitle = postTitle || (0, _i18n.__)('Post Title');
  const {
    avatarURL
  } = (0, _data.useSelect)(select => select(_blockEditor.store).getSettings().__experimentalDiscussionSettings);
  return (0, _react.createElement)("div", {
    className: "wp-block-comments__legacy-placeholder",
    inert: "true"
  }, (0, _react.createElement)("h3", null, /* translators: %s: Post title. */
  (0, _i18n.sprintf)((0, _i18n.__)('One response to %s'), postTitle)), (0, _react.createElement)("div", {
    className: "navigation"
  }, (0, _react.createElement)("div", {
    className: "alignleft"
  }, (0, _react.createElement)("a", {
    href: "#top"
  }, "\xAB ", (0, _i18n.__)('Older Comments'))), (0, _react.createElement)("div", {
    className: "alignright"
  }, (0, _react.createElement)("a", {
    href: "#top"
  }, (0, _i18n.__)('Newer Comments'), " \xBB"))), (0, _react.createElement)("ol", {
    className: "commentlist"
  }, (0, _react.createElement)("li", {
    className: "comment even thread-even depth-1"
  }, (0, _react.createElement)("article", {
    className: "comment-body"
  }, (0, _react.createElement)("footer", {
    className: "comment-meta"
  }, (0, _react.createElement)("div", {
    className: "comment-author vcard"
  }, (0, _react.createElement)("img", {
    alt: (0, _i18n.__)('Commenter Avatar'),
    src: avatarURL,
    className: "avatar avatar-32 photo",
    height: "32",
    width: "32",
    loading: "lazy"
  }), (0, _react.createElement)("b", {
    className: "fn"
  }, (0, _react.createElement)("a", {
    href: "#top",
    className: "url"
  }, (0, _i18n.__)('A WordPress Commenter'))), ' ', (0, _react.createElement)("span", {
    className: "says"
  }, (0, _i18n.__)('says'), ":")), (0, _react.createElement)("div", {
    className: "comment-metadata"
  }, (0, _react.createElement)("a", {
    href: "#top"
  }, (0, _react.createElement)("time", {
    dateTime: "2000-01-01T00:00:00+00:00"
  }, (0, _i18n.__)('January 1, 2000 at 00:00 am'))), ' ', (0, _react.createElement)("span", {
    className: "edit-link"
  }, (0, _react.createElement)("a", {
    className: "comment-edit-link",
    href: "#top"
  }, (0, _i18n.__)('Edit'))))), (0, _react.createElement)("div", {
    className: "comment-content"
  }, (0, _react.createElement)("p", null, (0, _i18n.__)('Hi, this is a comment.'), (0, _react.createElement)("br", null), (0, _i18n.__)('To get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.'), (0, _react.createElement)("br", null), (0, _element.createInterpolateElement)((0, _i18n.__)('Commenter avatars come from <a>Gravatar</a>.'), {
    a:
    // eslint-disable-next-line jsx-a11y/anchor-has-content
    (0, _react.createElement)("a", {
      href: "https://gravatar.com/"
    })
  }))), (0, _react.createElement)("div", {
    className: "reply"
  }, (0, _react.createElement)("a", {
    className: "comment-reply-link",
    href: "#top",
    "aria-label": (0, _i18n.__)('Reply to A WordPress Commenter')
  }, (0, _i18n.__)('Reply')))))), (0, _react.createElement)("div", {
    className: "navigation"
  }, (0, _react.createElement)("div", {
    className: "alignleft"
  }, (0, _react.createElement)("a", {
    href: "#top"
  }, "\xAB ", (0, _i18n.__)('Older Comments'))), (0, _react.createElement)("div", {
    className: "alignright"
  }, (0, _react.createElement)("a", {
    href: "#top"
  }, (0, _i18n.__)('Newer Comments'), " \xBB"))), (0, _react.createElement)(_form.default, {
    postId: postId,
    postType: postType
  }));
}
//# sourceMappingURL=placeholder.js.map