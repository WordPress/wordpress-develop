import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { AlignmentControl, BlockControls, Warning, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Placeholder from './placeholder';
export default function CommentsLegacy({
  attributes,
  setAttributes,
  context: {
    postType,
    postId
  }
}) {
  const {
    textAlign
  } = attributes;
  const actions = [createElement(Button, {
    key: "convert",
    onClick: () => void setAttributes({
      legacy: false
    }),
    variant: "primary"
  }, __('Switch to editable mode'))];
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
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
  }, createElement(Warning, {
    actions: actions
  }, __('Comments block: You’re currently using the legacy version of the block. ' + 'The following is just a placeholder - the final styling will likely look different. ' + 'For a better representation and more customization options, ' + 'switch the block to its editable mode.')), createElement(Placeholder, {
    postId: postId,
    postType: postType
  })));
}
//# sourceMappingURL=comments-legacy.js.map