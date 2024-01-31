import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { SVG, Rect } from '@wordpress/primitives';
import { Icon, menu } from '@wordpress/icons';
export default function OverlayMenuIcon({
  icon
}) {
  if (icon === 'menu') {
    return createElement(Icon, {
      icon: menu
    });
  }
  return createElement(SVG, {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    width: "24",
    height: "24",
    "aria-hidden": "true",
    focusable: "false"
  }, createElement(Rect, {
    x: "4",
    y: "7.5",
    width: "16",
    height: "1.5"
  }), createElement(Rect, {
    x: "4",
    y: "15",
    width: "16",
    height: "1.5"
  }));
}
//# sourceMappingURL=overlay-menu-icon.js.map