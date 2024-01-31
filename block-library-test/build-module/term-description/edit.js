import { createElement, Fragment } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, BlockControls, AlignmentControl } from '@wordpress/block-editor';
export default function TermDescriptionEdit({
  attributes,
  setAttributes,
  mergedStyle
}) {
  const {
    textAlign
  } = attributes;
  const blockProps = useBlockProps({
    className: classnames({
      [`has-text-align-${textAlign}`]: textAlign
    }),
    style: mergedStyle
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
  }, createElement("div", {
    className: "wp-block-term-description__placeholder"
  }, createElement("span", null, __('Term Description')))));
}
//# sourceMappingURL=edit.js.map