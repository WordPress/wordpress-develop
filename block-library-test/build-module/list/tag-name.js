import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { forwardRef } from '@wordpress/element';
function TagName(props, ref) {
  const {
    ordered,
    ...extraProps
  } = props;
  const Tag = ordered ? 'ol' : 'ul';
  return createElement(Tag, {
    ref: ref,
    ...extraProps
  });
}
export default forwardRef(TagName);
//# sourceMappingURL=tag-name.js.map