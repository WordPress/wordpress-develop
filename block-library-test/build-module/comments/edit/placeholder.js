import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CommentsForm from '../../post-comments-form/form';
export default function PostCommentsPlaceholder({
  postType,
  postId
}) {
  let [postTitle] = useEntityProp('postType', postType, 'title', postId);
  postTitle = postTitle || __('Post Title');
  const {
    avatarURL
  } = useSelect(select => select(blockEditorStore).getSettings().__experimentalDiscussionSettings);
  return createElement("div", {
    className: "wp-block-comments__legacy-placeholder",
    inert: "true"
  }, createElement("h3", null, /* translators: %s: Post title. */
  sprintf(__('One response to %s'), postTitle)), createElement("div", {
    className: "navigation"
  }, createElement("div", {
    className: "alignleft"
  }, createElement("a", {
    href: "#top"
  }, "\xAB ", __('Older Comments'))), createElement("div", {
    className: "alignright"
  }, createElement("a", {
    href: "#top"
  }, __('Newer Comments'), " \xBB"))), createElement("ol", {
    className: "commentlist"
  }, createElement("li", {
    className: "comment even thread-even depth-1"
  }, createElement("article", {
    className: "comment-body"
  }, createElement("footer", {
    className: "comment-meta"
  }, createElement("div", {
    className: "comment-author vcard"
  }, createElement("img", {
    alt: __('Commenter Avatar'),
    src: avatarURL,
    className: "avatar avatar-32 photo",
    height: "32",
    width: "32",
    loading: "lazy"
  }), createElement("b", {
    className: "fn"
  }, createElement("a", {
    href: "#top",
    className: "url"
  }, __('A WordPress Commenter'))), ' ', createElement("span", {
    className: "says"
  }, __('says'), ":")), createElement("div", {
    className: "comment-metadata"
  }, createElement("a", {
    href: "#top"
  }, createElement("time", {
    dateTime: "2000-01-01T00:00:00+00:00"
  }, __('January 1, 2000 at 00:00 am'))), ' ', createElement("span", {
    className: "edit-link"
  }, createElement("a", {
    className: "comment-edit-link",
    href: "#top"
  }, __('Edit'))))), createElement("div", {
    className: "comment-content"
  }, createElement("p", null, __('Hi, this is a comment.'), createElement("br", null), __('To get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.'), createElement("br", null), createInterpolateElement(__('Commenter avatars come from <a>Gravatar</a>.'), {
    a:
    // eslint-disable-next-line jsx-a11y/anchor-has-content
    createElement("a", {
      href: "https://gravatar.com/"
    })
  }))), createElement("div", {
    className: "reply"
  }, createElement("a", {
    className: "comment-reply-link",
    href: "#top",
    "aria-label": __('Reply to A WordPress Commenter')
  }, __('Reply')))))), createElement("div", {
    className: "navigation"
  }, createElement("div", {
    className: "alignleft"
  }, createElement("a", {
    href: "#top"
  }, "\xAB ", __('Older Comments'))), createElement("div", {
    className: "alignright"
  }, createElement("a", {
    href: "#top"
  }, __('Newer Comments'), " \xBB"))), createElement(CommentsForm, {
    postId: postId,
    postType: postType
  }));
}
//# sourceMappingURL=placeholder.js.map