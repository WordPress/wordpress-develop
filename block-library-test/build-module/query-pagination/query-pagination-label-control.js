import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
export function QueryPaginationLabelControl({
  value,
  onChange
}) {
  return createElement(ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: __('Show label text'),
    help: __('Toggle off to hide the label text, e.g. "Next Page".'),
    onChange: onChange,
    checked: value === true
  });
}
//# sourceMappingURL=query-pagination-label-control.js.map