import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { TextControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
export default function NavigationMenuNameControl() {
  const [title, updateTitle] = useEntityProp('postType', 'wp_navigation', 'title');
  return createElement(TextControl, {
    __nextHasNoMarginBottom: true,
    label: __('Menu name'),
    value: title,
    onChange: updateTitle
  });
}
//# sourceMappingURL=navigation-menu-name-control.js.map