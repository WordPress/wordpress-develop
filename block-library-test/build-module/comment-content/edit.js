import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';
import { RawHTML } from '@wordpress/element';
import { Disabled } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { AlignmentControl, BlockControls, useBlockProps } from '@wordpress/block-editor';

/**
 * Renders the `core/comment-content` block on the editor.
 *
 * @param {Object} props                      React props.
 * @param {Object} props.setAttributes        Callback for updating block attributes.
 * @param {Object} props.attributes           Block attributes.
 * @param {string} props.attributes.textAlign The `textAlign` attribute.
 * @param {Object} props.context              Inherited context.
 * @param {string} props.context.commentId    The comment ID.
 *
 * @return {JSX.Element} React element.
 */
export default function Edit({
  setAttributes,
  attributes: {
    textAlign
  },
  context: {
    commentId
  }
}) {
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const [content] = useEntityProp('root', 'comment', 'content', commentId);
  const blockControls = createElement(BlockControls, {
    group: "block"
  }, createElement(AlignmentControl, {
    value: textAlign,
    onChange: newAlign => setAttributes({
      textAlign: newAlign
    })
  }));
  if (!commentId || !content) {
    return createElement(Fragment, null, blockControls, createElement("div", {
      ...blockProps
    }, createElement("p", null, _x('Comment Content', 'block title'))));
  }
  return createElement(Fragment, null, blockControls, createElement("div", {
    ...blockProps
  }, createElement(Disabled, null, createElement(RawHTML, {
    key: "html"
  }, content.rendered))));
}
//# sourceMappingURL=edit.js.map