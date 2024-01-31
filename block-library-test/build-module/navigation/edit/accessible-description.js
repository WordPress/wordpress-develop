import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { VisuallyHidden } from '@wordpress/components';
export default function AccessibleDescription({
  id,
  children
}) {
  return createElement(VisuallyHidden, null, createElement("div", {
    id: id,
    className: "wp-block-navigation__description"
  }, children));
}
//# sourceMappingURL=accessible-description.js.map