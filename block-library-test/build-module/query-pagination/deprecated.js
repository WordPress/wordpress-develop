import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
const deprecated = [
// Version with wrapper `div` element.
{
  save() {
    return createElement("div", {
      ...useBlockProps.save()
    }, createElement(InnerBlocks.Content, null));
  }
}];
export default deprecated;
//# sourceMappingURL=deprecated.js.map