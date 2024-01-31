import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { isRTL } from '@wordpress/i18n';
export default function save({
  attributes
}) {
  const {
    align,
    content,
    dropCap,
    direction
  } = attributes;
  const className = classnames({
    'has-drop-cap': align === (isRTL() ? 'left' : 'right') || align === 'center' ? false : dropCap,
    [`has-text-align-${align}`]: align
  });
  return createElement("p", {
    ...useBlockProps.save({
      className,
      dir: direction
    })
  }, createElement(RichText.Content, {
    value: content
  }));
}
//# sourceMappingURL=save.js.map