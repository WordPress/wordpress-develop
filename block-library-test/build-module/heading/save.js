import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
export default function save({
  attributes
}) {
  const {
    textAlign,
    content,
    level
  } = attributes;
  const TagName = 'h' + level;
  const className = classnames({
    [`has-text-align-${textAlign}`]: textAlign
  });
  return createElement(TagName, {
    ...useBlockProps.save({
      className
    })
  }, createElement(RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map