"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostCommentsCountEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _apiFetch = _interopRequireDefault(require("@wordpress/api-fetch"));
var _url = require("@wordpress/url");
var _i18n = require("@wordpress/i18n");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostCommentsCountEdit({
  attributes,
  context,
  setAttributes
}) {
  const {
    textAlign
  } = attributes;
  const {
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
  const hasPostAndComments = postId && commentsCount !== undefined;
  const blockStyles = {
    ...blockProps.style,
    textDecoration: hasPostAndComments ? blockProps.style?.textDecoration : undefined
  };
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
    ...blockProps,
    style: blockStyles
  }, hasPostAndComments ? commentsCount : (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Post Comments Count block: post not found.'))));
}
//# sourceMappingURL=edit.js.map