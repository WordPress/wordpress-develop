import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { ToggleControl, __experimentalToggleGroupControl as ToggleGroupControl, __experimentalToggleGroupControlOption as ToggleGroupControlOption } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import OverlayMenuIcon from './overlay-menu-icon';
export default function OverlayMenuPreview({
  setAttributes,
  hasIcon,
  icon
}) {
  return createElement(Fragment, null, createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show icon button'),
    help: __('Configure the visual appearance of the button that toggles the overlay menu.'),
    onChange: value => setAttributes({
      hasIcon: value
    }),
    checked: hasIcon
  }), createElement(ToggleGroupControl, {
    __nextHasNoMarginBottom: true,
    label: __('Icon'),
    value: icon,
    onChange: value => setAttributes({
      icon: value
    }),
    isBlock: true
  }, createElement(ToggleGroupControlOption, {
    value: "handle",
    "aria-label": __('handle'),
    label: createElement(OverlayMenuIcon, {
      icon: "handle"
    })
  }), createElement(ToggleGroupControlOption, {
    value: "menu",
    "aria-label": __('menu'),
    label: createElement(OverlayMenuIcon, {
      icon: "menu"
    })
  })));
}
//# sourceMappingURL=overlay-menu-preview.js.map