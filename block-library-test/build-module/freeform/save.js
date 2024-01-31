import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RawHTML } from '@wordpress/element';
export default function save({
  attributes
}) {
  const {
    content
  } = attributes;
  return createElement(RawHTML, null, content);
}
//# sourceMappingURL=save.js.map