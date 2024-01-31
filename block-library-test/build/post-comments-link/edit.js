"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _apiFetch = _interopRequireDefault(require("@wordpress/api-fetch"));
var _url = require("@wordpress/url");
var _i18n = require("@wordpress/i18n");
var _coreData = require("@wordpress/core-data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostCommentsLinkEdit({
  context,
  attributes,
  setAttributes
}) {
  const {
    textAlign
  } = attributes;
  const {
    postType,
    postId
  } = context;
  const [commentsCount, setCommentsCount] = (0, _element.useState)();
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  (0, _element.useEffect)(() => {
    if (!postId) {
      return;
    }
    const currentPostId = postId;
    (0, _apiFetch.default)({
      path: (0, _url.addQueryArgs)('/wp/v2/comments', {
        post: postId
      }),
      parse: false
    }).then(res => {
      // Stale requests will have the `currentPostId` of an older closure.
      if (currentPostId === postId) {
        setCommentsCount(res.headers.get('X-WP-Total'));
      }
    });
  }, [postId]);
  const post = (0, _data.useSelect)(select => select(_coreData.store).getEditedEntityRecord('postType', postType, postId), [postType, postId]);
  if (!post) {
    return null;
  }
  const {
    link
  } = post;
  let commentsText;
  if (commentsCount !== undefined) {
    const commentsNumber = parseInt(commentsCount);
    if (commentsNumber === 0) {
      commentsText = (0, _i18n.__)('No comments');
    } else {
      commentsText = (0, _i18n.sprintf)( /* translators: %s: Number of comments */
      (0, _i18n._n)('%s comment', '%s comments', commentsNumber), commentsNumber.toLocaleString());
    }
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), (0, _react.createElement)("div", {
    ...blockProps
  }, link && commentsText !== undefined ? (0, _react.createElement)("a", {
    href: link + '#comments',
    onClick: event => event.preventDefault()
  }, commentsText) : (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Post Comments Link block: post not found.'))));
}
var _default = exports.default = PostCommentsLinkEdit;
//# sourceMappingURL=edit.js.map