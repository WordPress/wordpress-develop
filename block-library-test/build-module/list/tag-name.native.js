import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { forwardRef } from '@wordpress/element';
import { View } from '@wordpress/primitives';
function TagName(props, ref) {
  const {
    start,
    ...extraProps
  } = props;
  return createElement(View, {
    ref: ref,
    ...extraProps
  });
}
export default forwardRef(TagName);
//# sourceMappingURL=tag-name.native.js.map