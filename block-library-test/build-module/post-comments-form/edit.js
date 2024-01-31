import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { AlignmentControl, BlockControls, useBlockProps } from '@wordpress/block-editor';
import { VisuallyHidden } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CommentsForm from './form';
export default function PostCommentsFormEdit({
  attributes,
  context,
  setAttributes
}) {
  const {
    textAlign
  } = attributes;
  const {
    postId,
    postType
  } = context;
  const instanceId = useInstanceId(PostCommentsFormEdit);
  const instanceIdDesc = sprintf('comments-form-edit-%d-desc', instanceId);
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    'aria-describedby': instanceIdDesc
  });
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
  }, createElement(CommentsForm, {
    postId: postId,
    postType: postType
  }), createElement(VisuallyHidden, {
    id: instanceIdDesc
  }, __('Comments form disabled in editor.'))));
}
//# sourceMappingURL=edit.js.map