import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { Warning, store as blockEditorStore, __experimentalGetElementClassName } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
const CommentsFormPlaceholder = () => {
  const instanceId = useInstanceId(CommentsFormPlaceholder);
  return createElement("div", {
    className: "comment-respond"
  }, createElement("h3", {
    className: "comment-reply-title"
  }, __('Leave a Reply')), createElement("form", {
    noValidate: true,
    className: "comment-form",
    onSubmit: event => event.preventDefault()
  }, createElement("p", null, createElement("label", {
    htmlFor: `comment-${instanceId}`
  }, __('Comment')), createElement("textarea", {
    id: `comment-${instanceId}`,
    name: "comment",
    cols: "45",
    rows: "8",
    readOnly: true
  })), createElement("p", {
    className: "form-submit wp-block-button"
  }, createElement("input", {
    name: "submit",
    type: "submit",
    className: classnames('wp-block-button__link', __experimentalGetElementClassName('button')),
    label: __('Post Comment'),
    value: __('Post Comment'),
    "aria-disabled": "true"
  }))));
};
const CommentsForm = ({
  postId,
  postType
}) => {
  const [commentStatus, setCommentStatus] = useEntityProp('postType', postType, 'comment_status', postId);
  const isSiteEditor = postType === undefined || postId === undefined;
  const {
    defaultCommentStatus
  } = useSelect(select => select(blockEditorStore).getSettings().__experimentalDiscussionSettings);
  const postTypeSupportsComments = useSelect(select => postType ? !!select(coreStore).getPostType(postType)?.supports.comments : false);
  if (!isSiteEditor && 'open' !== commentStatus) {
    if ('closed' === commentStatus) {
      const actions = [createElement(Button, {
        key: "enableComments",
        onClick: () => setCommentStatus('open'),
        variant: "primary"
      }, _x('Enable comments', 'action that affects the current post'))];
      return createElement(Warning, {
        actions: actions
      }, __('Post Comments Form block: Comments are not enabled for this item.'));
    } else if (!postTypeSupportsComments) {
      return createElement(Warning, null, sprintf( /* translators: 1: Post type (i.e. "post", "page") */
      __('Post Comments Form block: Comments are not enabled for this post type (%s).'), postType));
    } else if ('open' !== defaultCommentStatus) {
      return createElement(Warning, null, __('Post Comments Form block: Comments are not enabled.'));
    }
  }
  return createElement(CommentsFormPlaceholder, null);
};
export default CommentsForm;
//# sourceMappingURL=form.js.map