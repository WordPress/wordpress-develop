import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { AlignmentControl, BlockControls, Warning, useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __, sprintf, _n } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';
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
  const [commentsCount, setCommentsCount] = useState();
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  useEffect(() => {
    if (!postId) {
      return;
    }
    const currentPostId = postId;
    apiFetch({
      path: addQueryArgs('/wp/v2/comments', {
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
  const post = useSelect(select => select(coreStore).getEditedEntityRecord('postType', postType, postId), [postType, postId]);
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
      commentsText = __('No comments');
    } else {
      commentsText = sprintf( /* translators: %s: Number of comments */
      _n('%s comment', '%s comments', commentsNumber), commentsNumber.toLocaleString());
    }
  }
  return createElement(Fragment, null, createElement(BlockControls, {
    group: "block"
  }, createElement(AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  })), createElement("div", {
    ...blockProps
  }, link && commentsText !== undefined ? createElement("a", {
    href: link + '#comments',
    onClick: event => event.preventDefault()
  }, commentsText) : createElement(Warning, null, __('Post Comments Link block: post not found.'))));
}
export default PostCommentsLinkEdit;
//# sourceMappingURL=edit.js.map