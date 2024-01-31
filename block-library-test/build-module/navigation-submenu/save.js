import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';
export default function save() {
  return createElement(InnerBlocks.Content, null);
}
//# sourceMappingURL=save.js.map