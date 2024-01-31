import { createElement } from "react";
/**
 * WordPress dependencies
 */
import { Icon, navigation } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
const PlaceholderPreview = ({
  isVisible = true
}) => {
  return createElement("div", {
    "aria-hidden": !isVisible ? true : undefined,
    className: "wp-block-navigation-placeholder__preview"
  }, createElement("div", {
    className: "wp-block-navigation-placeholder__actions__indicator"
  }, createElement(Icon, {
    icon: navigation
  }), __('Navigation')));
};
export default PlaceholderPreview;
//# sourceMappingURL=placeholder-preview.js.map