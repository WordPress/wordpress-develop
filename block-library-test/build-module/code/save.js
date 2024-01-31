import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { escape } from './utils';
export default function save({
  attributes
}) {
  return createElement("pre", {
    ...useBlockProps.save()
  }, createElement(RichText.Content, {
    tagName: "code"
    // To do: `escape` encodes characters in shortcodes and URLs to
    // prevent embedding in PHP. Ideally checks for the code block,
    // or pre/code tags, should be made on the PHP side?
    ,
    value: escape(attributes.content.toString())
  }));
}
//# sourceMappingURL=save.js.map