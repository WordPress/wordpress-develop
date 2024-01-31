import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
export default function NextPageEdit() {
  return createElement("div", {
    ...useBlockProps()
  }, createElement("span", null, __('Page break')));
}
//# sourceMappingURL=edit.js.map