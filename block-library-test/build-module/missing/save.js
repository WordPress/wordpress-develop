import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RawHTML } from '@wordpress/element';
export default function save({
  attributes
}) {
  // Preserve the missing block's content.
  return createElement(RawHTML, null, attributes.originalContent);
}
//# sourceMappingURL=save.js.map