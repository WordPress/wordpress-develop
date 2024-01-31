import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import TableOfContentsList from './list';
import { linearToNestedHeadingList } from './utils';
export default function save({
  attributes: {
    headings = []
  }
}) {
  if (headings.length === 0) {
    return null;
  }
  return createElement("nav", {
    ...useBlockProps.save()
  }, createElement("ol", null, createElement(TableOfContentsList, {
    nestedHeadingList: linearToNestedHeadingList(headings)
  })));
}
//# sourceMappingURL=save.js.map