import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { Placeholder, TextControl, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { blockDefault } from '@wordpress/icons';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
const TEMPLATE = [['core/avatar'], ['core/comment-author-name'], ['core/comment-date'], ['core/comment-content'], ['core/comment-reply-link'], ['core/comment-edit-link']];
export default function Edit({
  attributes: {
    commentId
  },
  setAttributes
}) {
  const [commentIdInput, setCommentIdInput] = useState(commentId);
  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    template: TEMPLATE
  });
  if (!commentId) {
    return createElement("div", {
      ...blockProps
    }, createElement(Placeholder, {
      icon: blockDefault,
      label: _x('Post Comment', 'block title'),
      instructions: __('To show a comment, input the comment ID.')
    }, createElement(TextControl, {
      __nextHasNoMarginBottom: true,
      value: commentId,
      onChange: val => setCommentIdInput(parseInt(val))
    }), createElement(Button, {
      variant: "primary",
      onClick: () => {
        setAttributes({
          commentId: commentIdInput
        });
      }
    }, __('Save'))));
  }
  return createElement("div", {
    ...innerBlocksProps
  });
}
//# sourceMappingURL=edit.js.map