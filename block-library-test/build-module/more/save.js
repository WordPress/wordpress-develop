import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RawHTML } from '@wordpress/element';
export default function save({
  attributes: {
    customText,
    noTeaser
  }
}) {
  const moreTag = customText ? `<!--more ${customText}-->` : '<!--more-->';
  const noTeaserTag = noTeaser ? '<!--noteaser-->' : '';
  return createElement(RawHTML, null, [moreTag, noTeaserTag].filter(Boolean).join('\n'));
}
//# sourceMappingURL=save.js.map