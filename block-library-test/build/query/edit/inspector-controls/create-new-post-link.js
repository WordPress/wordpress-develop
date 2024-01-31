"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _url = require("@wordpress/url");
/**
 * WordPress dependencies
 */

const CreateNewPostLink = ({
  attributes: {
    query: {
      postType
    } = {}
  } = {}
}) => {
  if (!postType) return null;
  const newPostUrl = (0, _url.addQueryArgs)('post-new.php', {
    post_type: postType
  });
  return (0, _react.createElement)("div", {
    className: "wp-block-query__create-new-link"
  }, (0, _element.createInterpolateElement)((0, _i18n.__)('<a>Add new post</a>'),
  // eslint-disable-next-line jsx-a11y/anchor-has-content
  {
    a: (0, _react.createElement)("a", {
      href: newPostUrl
    })
  }));
};
var _default = exports.default = CreateNewPostLink;
//# sourceMappingURL=create-new-post-link.js.map