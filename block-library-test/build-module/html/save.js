import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RawHTML } from '@wordpress/element';
export default function save({
  attributes
}) {
  return createElement(RawHTML, null, attributes.content);
}
//# sourceMappingURL=save.js.map